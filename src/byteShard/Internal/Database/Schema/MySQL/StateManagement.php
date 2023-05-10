<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema\MySQL;

use byteShard\Database\Schema\Statement;
use byteShard\Database\Schema\Table;
use byteShard\Exception;
use byteShard\Internal\Database\Schema\ColumnManagementInterface;
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
        $columns        = [];
        $defaultCollate = $this->config->getCollate();
        $defaultCharset = $this->config->getCharset();
        foreach ($table->getColumns() as $column) {
            $columnObj = $this->dbManagement->getColumnObject($column->getName(), $column->getNewName(), $column->getType(), $column->getLength(), $column->isNullable(), $column->isPrimary(), $column->isIdentity(), $column->getDefault(), $column->getComment());
            $columnObj->setCollate($column->getCollate() ?? $table->getCollate() ?? $defaultCollate);
            $columns[] = $columnObj;
        }
        $indices = [];
        foreach ($table->getIndices() as $index) {
            $indexObject = $this->dbManagement->getIndexObject($table->getName(), $index->getName(), ...$index->getColumns());
            $indexObject->setType($index->getType());
            $indices[] = $indexObject;
        }
        $tableObject = $this->dbManagement->getTableObject($table->getName(), ...$columns);
        $tableObject->setComment($table->getComment());
        $tableObject->setIndices(...$indices);
        $tableObject->setCollate($table->getCollate() ?? $defaultCollate);
        $tableObject->setDefaultCharset($table->getCharset() ?? $defaultCharset);
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
        $tables = $this->dbManagement->getTables();
        $schema = [];
        foreach ($tables as $table) {
            $schema[] = '$'.str_replace('_', '', lcfirst(ucwords($table->getName(), '_'))).' = new Table('."'".$table->getName()."',";
            $columns  = $this->dbManagement->getColumns($table);
            if (!empty($columns)) {
                $length = 0;
                foreach ($columns as $column) {
                    $length = max($length, strlen($column));
                }
                foreach ($columns as $column) {
                    $schema[] = $column->getSchema($length);
                }
                //$col = substr($col, 0, -2);
                $col      = ');';
                $schema[] = $col;
            }
        }

        return $schema;
    }
}
