<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema\MySQL;

use byteShard\Database;
use byteShard\Database\Schema\Statement;
use byteShard\Database\Schema\Table;
use byteShard\Exception;
use byteShard\Internal\Database\Schema\ColumnManagementInterface;
use byteShard\Internal\Database\Schema\ForeignKeyInterface;
use byteShard\Internal\Database\Schema\TableManagementInterface;

class StateManagement extends \byteShard\Internal\Database\Schema\StateManagement
{
    /**
     * @return array<string>
     * @throws Exception
     * @internal
     */
    public function ensureState(bool $baseSchema = false, bool $drop = true): array
    {
        $applyState     = false;
        $currentVersion = $this->dbManagement->getVersion($baseSchema ? $this->dbSchemaStateType : $this->dbSchemaStateAppType, $this->dbSchemaStateValue, $this->dbSchemaInitialVersion);
        if ($currentVersion === null || version_compare($this->version, $currentVersion) > 0) {
            $applyState = true;
        }
        $steps                = $this->state->getSteps($this->version);
        $this->dryRunCommands = [];
        $this->dbManagement->setDryRunCommandArrayReference($this->dryRunCommands);
        if ($applyState === true && !empty($steps)) {
            $this->dbManagement->setDryRun($this->dryRun);
            foreach ($steps as $step) {
                if ($step instanceof Table) {
                    $table = $this->convertTable($step);
                    if ($this->dbManagement->tableExists($table->getName()) === false) {
                        // table doesn't exist, create it with all columns and indices
                        $this->dbManagement->execute($table->getCreateTableStatement());
                    } else {
                        if ($table->getComment() !== $this->dbManagement->getTableComment($table->getName())) {
                            $this->dbManagement->execute($table->getUpdateTableCommentStatement());
                        }
                        $currentColumns = $this->dbManagement->getColumns($table);
                        $targetColumns  = $table->getColumns();
                        // first add columns (they need to be present in case they are part of an index)
                        $this->ensureColumns($currentColumns, $targetColumns, $this->dbManagement->getPrimaryKeyColumns($table), $table->getPrimaryKeyColumns(), $table);

                        // create or update indices if necessary
                        $this->ensureIndices($this->dbManagement->getIndices($table), $table->getIndices(), $table);

                        // create or update foreign keys if necessary
                        $this->ensureForeignKeys($this->dbManagement->getForeignKeys($table), $table->getForeignKeys(), $table);

                        // drop columns (must be done after index update)
                        if ($drop === true) {
                            $this->ensureColumnsDelete($currentColumns, $targetColumns, $table);
                        }
                    }
                } elseif ($step instanceof Statement) {
                    $statement = $step;
                    if ($this->isStatementCompleted($statement) === false) {
                        $this->dbManagement->execute($statement);
                        $this->dbManagement->setVersion('statement', $statement->getName(), $this->version);
                    }
                }
            }
            $this->dbManagement->setVersion($baseSchema ? $this->dbSchemaStateType : $this->dbSchemaStateAppType, $this->dbSchemaStateValue, $this->version);
        }
        return $this->dryRunCommands;
    }

    private function convertTable(Table $table): TableManagementInterface
    {
        $defaultCollate = $this->config->getCollate();
        $defaultCharset = $this->config->getCharset();

        $columns = [];
        foreach ($table->getColumns() as $column) {
            $columns[] = Factory::column($column, $column->getCollate() ?? $table->getCollate() ?? $defaultCollate);
        }

        $indices = [];
        foreach ($table->getIndices() as $index) {
            $indices[] = Factory::index($table->getName(), $index);
        }

        $foreignKeys = [];
        foreach ($table->getForeignKeys() as $foreignKey) {
            $foreignKeys[] = Factory::foreignKey($table->getName(), $foreignKey);
        }

        $tableObject = Factory::table($table, $defaultCollate, $defaultCharset, ...$columns);
        $tableObject->setIndices(...$indices);
        $tableObject->setForeignKeys(...$foreignKeys);
        return $tableObject;
    }

    /**
     * @param array<ColumnManagementInterface> $currentSchemaColumns
     * @param array<ColumnManagementInterface> $targetSchemaColumns
     * @param array<string, string>|array<ColumnManagementInterface> $currentSchemaPrimaryKeyColumns
     * @param array<ColumnManagementInterface> $targetSchemaPrimaryKeyColumns
     * @param TableManagementInterface $table
     */
    private function ensureColumns(array $currentSchemaColumns, array $targetSchemaColumns, array $currentSchemaPrimaryKeyColumns, array $targetSchemaPrimaryKeyColumns, TableManagementInterface $table): void
    {
        // check identity column
        $currentIdentityColumn = null;
        $targetIdentityColumn  = null;
        foreach ($currentSchemaColumns as $column) {
            if ($column->isIdentity() === true) {
                $currentIdentityColumn = $column;
            }
        }

        // first add all columns which don't exist in the current schema and which are not identity
        $identityColumnChange = false;
        foreach ($targetSchemaColumns as $columnName => $targetColumn) {
            if ($targetColumn->isIdentity() === false) {
                if (array_key_exists($columnName, $currentSchemaColumns) === false) {
                    // column doesn't exist in current schema and is not identity -> add it to the table
                    $this->dbManagement->execute($table->getAddColumnStatement($targetColumn));
                }
            } else {
                $targetIdentityColumn = $targetColumn;
                if ($currentIdentityColumn === null || $targetColumn->getName() !== $currentIdentityColumn->getName()) {
                    // either the current schema doesn't have an identity column or the new identity column doesn't match the current one
                    $identityColumnChange = true;
                }
            }
        }

        // compare each current schema column definition with the target schema definition
        foreach ($currentSchemaColumns as $columnName => $currentColumn) {
            if (array_key_exists($columnName, $targetSchemaColumns) === true) {
                $targetColumn = $targetSchemaColumns[$columnName];
                if ($currentColumn->isNotIdenticalTo($targetColumn)) {
                    if (
                        (($targetColumn->isIdentity() === true && $currentColumn->isIdentity() === true) ||
                            ($targetColumn->isIdentity() === false && $currentColumn->isIdentity() === false)) &&
                        (($targetColumn->isPrimary() === true && $currentColumn->isPrimary() === true) ||
                            ($targetColumn->isPrimary() === false && $currentColumn->isPrimary() === false))
                    ) {
                        $statements = $table->getUpdateColumnStatements($targetColumn);
                        foreach ($statements as $statement) {
                            $this->dbManagement->execute($statement);
                        }
                    }
                }
            }
        }

        // special handling in case the identity column changes
        if ($identityColumnChange === true) {
            if ($currentIdentityColumn === null && $targetIdentityColumn !== null) {
                // table in current schema doesn't have identity -> add it
                $this->addComment('Add new Identity Column ('.$targetIdentityColumn->getName().') and Primary Key');
                $this->dbManagement->execute($table->getAddIdentityStatement($this->dbManagement->getPrimaryKeyName($table), $targetSchemaPrimaryKeyColumns, $targetIdentityColumn));
            } elseif ($currentIdentityColumn !== null && $targetIdentityColumn === null) {
                // table in target schema doesn't have an identity column but the current schema has one -> remove it
                $this->addComment('Remove Identity from Column '.$currentIdentityColumn->getName());
                $this->dbManagement->execute($table->getDropIdentityStatement($this->dbManagement->getPrimaryKeyName($table), $targetSchemaPrimaryKeyColumns, $currentIdentityColumn));
            } elseif ($targetIdentityColumn !== null && array_key_exists($targetIdentityColumn->getName(), $currentSchemaColumns) && $currentIdentityColumn !== null) {
                // new identity column already exists, modify it
                $this->addComment('Move Identity from '.$currentIdentityColumn->getName().' to '.$targetIdentityColumn->getName());
                $this->dbManagement->execute($table->getMoveIdentityStatement($this->dbManagement->getPrimaryKeyName($table), $targetSchemaPrimaryKeyColumns, $targetSchemaColumns[$currentIdentityColumn->getName()], $targetIdentityColumn));
            } elseif ($targetIdentityColumn !== null && $currentIdentityColumn !== null) {
                // new identity column doesn't exist yet, create it
                $this->addComment('Change current Identity Column ('.$currentIdentityColumn->getName().'), add new Identity Column ('.$targetIdentityColumn->getName().') and update Primary Key');
                $this->dbManagement->execute($table->getAddIdentityStatement($this->dbManagement->getPrimaryKeyName($table), $targetSchemaPrimaryKeyColumns, $targetIdentityColumn, $targetSchemaColumns[$currentIdentityColumn->getName()]));
            }
        } else {
            // check primary key
            $this->ensurePrimaryKeys($currentSchemaPrimaryKeyColumns, $targetSchemaPrimaryKeyColumns, $table, $targetIdentityColumn);
        }
    }

    /**
     * @param array<string,string>|array<ColumnManagementInterface> $currentSchemaPrimaryKeyColumns
     * @param array<ColumnManagementInterface> $targetSchemaPrimaryKeyColumns
     * @param TableManagementInterface $table
     * @param ColumnManagementInterface|null $targetSchemaIdentityColumn
     */
    private function ensurePrimaryKeys(array $currentSchemaPrimaryKeyColumns, array $targetSchemaPrimaryKeyColumns, TableManagementInterface $table, ColumnManagementInterface $targetSchemaIdentityColumn = null): void
    {
        $primaryKeyMatches = true;
        // check if a primary key column doesn't exist in either the target or the current schema
        foreach ($targetSchemaPrimaryKeyColumns as $primaryKeyColumn) {
            if (array_key_exists($primaryKeyColumn->getName(), $currentSchemaPrimaryKeyColumns) === false) {
                $primaryKeyMatches = false;
            }
        }
        foreach ($currentSchemaPrimaryKeyColumns as $primaryKeyColumn) {
            $primaryKeyColumnName = $primaryKeyColumn instanceof ColumnManagementInterface ? $primaryKeyColumn->getName() : $primaryKeyColumn;
            if (array_key_exists($primaryKeyColumnName, $targetSchemaPrimaryKeyColumns) === false) {
                $primaryKeyMatches = false;
            }
        }
        // if the primary key columns don't match, drop the index and recreate it with the same name
        if ($primaryKeyMatches === false) {
            $this->dbManagement->execute($table->getRecreatePrimaryKeyStatement($this->dbManagement->getPrimaryKeyName($table), $targetSchemaPrimaryKeyColumns, $targetSchemaIdentityColumn));
        }
    }

    public function getSchema(): array
    {
        $tables = $this->dbManagement->getTables(true);
        $schema = [];
        foreach ($tables as $table) {
            $tableName = $this->getVariableNameForSchema($table->getName());
            array_push($schema, ...$this->getTableSchema($table, $tableName));
            array_push($schema, ...$this->getTableIndices($table, $tableName));
            array_push($schema, ...$this->getTableForeignKeys($table, $tableName, ...$this->dbManagement->getForeignKeys($table)));
        }
        foreach ($tables as $table) {
            $tableName = $this->getVariableNameForSchema($table->getName());
            $schema[]  = '$state->addTable($'.$tableName.');';
        }
        foreach ($tables as $table) {
            array_push($schema, ...$this->getTableGrants($table));
        }
        return $schema;
    }

    /**
     * @return array<string>
     */
    private function getTableSchema(TableManagementInterface $table, string $tableName): array
    {
        $schema[] = '$'.$tableName.' = new Table('."'".$table->getName()."',";
        $columns  = $this->dbManagement->getColumns($table);
        if (!empty($columns)) {
            $columnDefinitions = [];
            foreach ($columns as $column) {
                $columnName                     = $this->getVariableNameForSchema($column->getName());
                $columnDefinitions[$columnName] = $column->getSchema();
            }
            $maxLengthColumnName = 0;
            foreach ($columnDefinitions as $columnName => $columnDefinition) {
                $maxLengthColumnName = max($maxLengthColumnName, strlen($columnName));
            }
            foreach ($columnDefinitions as $columnName => $columnDefinition) {
                $schema[] = '   $'.$tableName.'_'.$columnName.(str_repeat(' ', $maxLengthColumnName - strlen($columnName))).' = '.$columnDefinition.',';
            }
            $schema[] = ');';
        }
        return $schema;
    }

    /**
     * @return array<string>
     */
    private function getTableIndices(TableManagementInterface $table, string $tableName): array
    {
        $schema  = [];
        $indices = $table->getIndices();
        if (!empty($indices)) {
            foreach ($indices as $index) {
                $columns = [];
                foreach ($index->getIndexColumns() as $indexColumn) {
                    $columns[] = '$'.$tableName.'_'.$this->getVariableNameForSchema($indexColumn);
                }
                $indexVariableName = '$'.$tableName.'_index_'.$this->getVariableNameForSchema($index->getName());
                $schema[]          = '$'.$tableName.'->setIndices('.$indexVariableName.' = new Index(\''.$index->getName().'\', '.implode(', ', $columns).'));';
                if ($index->isUnique()) {
                    $schema[] = $indexVariableName.'->setUnique();';
                }
            }
        }
        return $schema;
    }

    /**
     * @return array<string>
     */
    private function getTableForeignKeys(TableManagementInterface $table, string $tableName, ForeignKeyInterface ...$foreignKeys): array
    {
        $result = [];
        foreach ($foreignKeys as $foreignKey) {
            $result[] = '$'.$tableName.'->setForeignKeys(new ForeignKey($'.$tableName.'_'.$foreignKey->getSourceColumn().', \''.$foreignKey->getTargetTable().'\', \''.$foreignKey->getTargetColumn().'\', \''.$foreignKey->getForeignKeyConstraintName().'\'));';
        }
        return $result;
    }

    /** @return array<string> */
    private function getTableGrants(TableManagementInterface $table): array
    {
        $result = [];
        $grants = $this->dbManagement->getGrants($table);
        if (!empty($grants)) {
            foreach ($grants as $grant) {
                $statement  = '$state->addStatement(new Statement("GRANT ';
                $privileges = [];
                foreach ($grant->getPrivileges() as $privilege => $columns) {
                    if (empty($columns)) {
                        $privileges[] = $privilege;
                    } else {
                        $privileges[] = $privilege.' (`'.implode('`, `', $columns).'`)';
                    }
                }
                $statement .= implode(', ', $privileges);
                $statement .= ' ON '.$table->getName().' TO '.$grant->getGrantee().'"));';
                $result[]  = $statement;
            }
        }
        return $result;
    }

    private function getVariableNameForSchema(string $name): string
    {
        return str_replace('_', '', lcfirst(ucwords($name, '_')));
    }
}
