<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema\PGSQL;

use byteShard\Database\Schema\Statement;
use byteShard\Database\Schema\Table;
use byteShard\Exception;
use byteShard\Internal\Database\Schema\ColumnManagementInterface;
use byteShard\Internal\Database\Schema\ForeignKeyInterface;
use byteShard\Internal\Database\Schema\TableManagementInterface;
use byteShard\Permission\ObjectArray;

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
                        // table doesn't exist, create it with all columns
                        $this->dbManagement->execute($table->getCreateTableStatement());
                        $this->ensureIndices($this->dbManagement->getIndices($table), $table->getIndices(), $table);
                    } else {
                        if ($table->getComment() !== $this->dbManagement->getTableComment($table->getName())) {
                            $this->dbManagement->execute($table->getUpdateTableCommentStatement());
                        }
                        $currentColumns = $this->dbManagement->getColumns($table);
                        $targetColumns  = $table->getColumns();
                        // first add columns (they need to be present in case they are part of an index)
                        if ($this->dbManagement instanceof DBManagement) {
                            $this->ensureColumns($currentColumns, $targetColumns, $this->dbManagement->getPrimaryKeyColumns($table), $table->getPrimaryKeyColumns(), $table, $table->getAutoIncrementColumns());
                        }

                        //create or add foreign keys
                        $this->ensureForeignKey($this->dbManagement->getForeignKeyColumns($table), $table->getForeignKeyColumns(), $table);
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
        $indices     = [];
        $foreignKeys = $table->getForeignKeys();

        foreach ($table->getIndices() as $index) {
            $indexObject = $this->dbManagement->getIndexObject($table->getName(), $index->getName(), ...$index->getColumns());
            $indexObject->setType($index->getType());
            $indexObject->setUnique($index->isUnique());
            $indices[] = $indexObject;
        }

        $tableObject = $this->dbManagement->getTableObject($table->getName(), ...$columns);
        $tableObject->setComment($table->getComment());
        $tableObject->setIndices(...$indices);
        $tableObject->setCollate($table->getCollate() ?? $defaultCollate);
        $tableObject->setDefaultCharset($table->getCharset() ?? $defaultCharset);
        $tableObject->setForeignKeys(...$foreignKeys);
        return $tableObject;
    }

    /**
     * @param array<ColumnManagementInterface> $currentSchemaColumns
     * @param array<ColumnManagementInterface> $targetSchemaColumns
     * @param array<string, string> $currentSchemaPrimaryKeyColumns
     * @param array<string, ColumnManagementInterface> $targetSchemaPrimaryKeyColumns
     * @param TableManagementInterface $table
     * @param array<string, string> $currentSchemaAutoIncrementColumns
     */
    private function ensureColumns(array $currentSchemaColumns, array $targetSchemaColumns, array $currentSchemaPrimaryKeyColumns, array $targetSchemaPrimaryKeyColumns, TableManagementInterface $table, array $currentSchemaAutoIncrementColumns = []): void
    {
        $currentPrimaryColumns = [];
        $targetPrimaryColumns  = [];
        foreach ($currentSchemaColumns as $column) {
            if ($column->isPrimary() === true) {
                $currentPrimaryColumns[$column->getName()] = $column;
            }
        }
        $currentAutoIncrementColumns = [];
        $targetAutoIncrementColumns  = [];
        foreach ($currentSchemaColumns as $column) {
            if ($column->isIdentity() === true) {
                $currentAutoIncrementColumns[$column->getName()] = $column;
            }
        }

        // first add all columns which don't exist in the current schema and which are not identity/primary, autoincrement
        $primaryColumnChange       = false;
        $autoIncrementColumnChange = false;
        foreach ($targetSchemaColumns as $columnName => $targetColumn) {
            // Add column if it doesn't exist
            if ($targetColumn->isIdentity() === false) {
                if (array_key_exists($columnName, $currentSchemaColumns) === false) {
                    $this->dbManagement->execute($table->getAddColumnStatement($targetColumn));
                }
            }
            // check if target column is primary and if it will change from current
            if ($targetColumn->isPrimary() === true) {
                $targetPrimaryColumns[$targetColumn->getName()] = $targetColumn;
                if (empty($currentPrimaryColumns)) {
                    $primaryColumnChange = true;
                }
                foreach ($currentPrimaryColumns as $currentPrimaryColumn) {
                    if ($targetColumn->getName() !== $currentPrimaryColumn->getName()) {
                        $primaryColumnChange = true;
                        break;
                    }
                }
            }
            // check if target column is identity and if it will change from current
            if ($targetColumn->isIdentity() === true) {
                $targetAutoIncrementColumns[$targetColumn->getName()] = $targetColumn;
                if (empty($currentAutoIncrementColumns)) {
                    $autoIncrementColumnChange = true;
                }
                foreach ($currentAutoIncrementColumns as $currentAutoIncrementColumn) {
                    if ($targetColumn->getName() !== $currentAutoIncrementColumn->getName()) {
                        $autoIncrementColumnChange = true;
                        break;
                    }
                }
            }
        }

        // check if there are e.g. more primary keys in the current scheme than in the target one which is not covered by the last check
        if (count($targetPrimaryColumns) !== count($currentPrimaryColumns)) {
            $primaryColumnChange = true;
        }

        // compare each current schema column definition with the target schema definition
        foreach ($currentSchemaColumns as $columnName => $currentColumn) {
            if (array_key_exists($columnName, $targetSchemaColumns) === true) {
                $targetColumn = $targetSchemaColumns[$columnName];
                if ($currentColumn->isNotIdenticalTo($targetColumn)) {
                    // if there is no change in either identity nor primary update it, otherwise it will update later
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

        // special handling in case the identity column-primary key changes
        if ($autoIncrementColumnChange === true) {
            $this->ensureIdentityColumn($currentAutoIncrementColumns, $table, $targetSchemaPrimaryKeyColumns, $targetAutoIncrementColumns, $currentSchemaColumns, $targetSchemaColumns);
        }
        if ($primaryColumnChange === true) {
            $this->ensurePrimaryKeys($currentSchemaPrimaryKeyColumns, $targetSchemaPrimaryKeyColumns, $table, $currentSchemaAutoIncrementColumns);
        }
    }

    /**
     * @param array<string, string> $currentSchemaPrimaryKeyColumns
     * @param array<string, ColumnManagementInterface> $targetSchemaPrimaryKeyColumns
     * @param TableManagementInterface $table
     * @param array<string, string> $currentSchemaAutoIncrementColumns
     */
    private function ensurePrimaryKeys(array $currentSchemaPrimaryKeyColumns, array $targetSchemaPrimaryKeyColumns, TableManagementInterface $table, array $currentSchemaAutoIncrementColumns): void
    {
        $primaryKeyMatches = true;
        // check if a primary key column doesn't exist in either the target or the current schema
        foreach ($targetSchemaPrimaryKeyColumns as $primaryKeyColumn) {
            if (array_key_exists($primaryKeyColumn->getName(), $currentSchemaPrimaryKeyColumns) === false) {
                $primaryKeyMatches = false;
                break;
            }
        }
        if ($primaryKeyMatches === true) {
            $targetSchemaPrimaryKeyColumnNames = [];
            foreach ($targetSchemaPrimaryKeyColumns as $targetSchemaPrimaryKeyColumn) {
                $targetSchemaPrimaryKeyColumnNames [] = $targetSchemaPrimaryKeyColumn->getName();
            }
            foreach ($currentSchemaPrimaryKeyColumns as $primaryKeyColumn) {
                if (in_array($primaryKeyColumn, $targetSchemaPrimaryKeyColumnNames) === false) {
                    $primaryKeyMatches = false;
                    break;
                }
            }
        }
        if ($primaryKeyMatches === true) {
            foreach ($currentSchemaAutoIncrementColumns as $autoIncrementColumn) {
                if (array_key_exists($autoIncrementColumn, $currentSchemaAutoIncrementColumns) === false) {
                    $primaryKeyMatches = false;
                    break;
                }
            }
        }
        // if the primary key columns don't match, drop the index and recreate it with the same name
        if ($primaryKeyMatches === false) {
            $this->dbManagement->execute($table->getRecreatePrimaryKeyStatement($this->dbManagement->getPrimaryKeyName($table), $targetSchemaPrimaryKeyColumns));
        }
    }

    /**
     * @return array<string>
     */
    public function getSchema(): array
    {
        $tables      = $this->dbManagement->getTables();
        $tableSchema = [];
        foreach ($tables as $table) {
            $schema   = [];
            $schema[] = '$'.str_replace('_', '', lcfirst(ucwords($table->getName(), '_'))).' = new Table('."'".$table->getName()."',";
            $columns  = $this->dbManagement->getColumns($table);
            if (!empty($columns)) {
                $col    = '';
                $length = 0;
                foreach ($columns as $column) {
                    $length = max($length, strlen($column));
                }
                // code updated to remove last komma from the printSchema statement
                $totalColumns = count($columns);
                foreach ($columns as $column) {
                    /**@var ColumnManagementInterface $column */
                    if ($totalColumns === 1) {
                        $res = substr($column->getSchema($length), 0, -1).");\n";
                    } else {
                        $res = $column->getSchema($length);
                    }
                    $schema[]     = $res;
                    $totalColumns -= 1;
                }
                $foreignKeyColumns = $this->dbManagement->getForeignKeyColumns($table);
                if (!empty($foreignKeyColumns)) {
                    foreach ($foreignKeyColumns as $foreignKey) {
                        if ($foreignKey->getSourceColumn() && $foreignKey->getTargetTable() && $foreignKey->getTargetColumn()) {
                            $schema[] = $foreignKey->getAddForeignKeyStatement();
                        }
                    }
                    $schema[] = "\n";
                }
                $indexColumns = $this->dbManagement->getIndices($table);
                if (!empty($indexColumns)) {
                    foreach ($indexColumns as $indexColumn) {
                        $schema[] = $indexColumn->getAddIndexStatement();
                    }
                    $schema[] = "\n";
                }
                $tableSchema[] = implode("\n", $schema);
            }
        }
        return $tableSchema;
    }

    /**
     * Add or Drop foreign key according to current schema status
     * @param array<string, ForeignKeyInterface> $currentSchemaForeignKeys
     * @param array<string, ForeignKeyInterface> $targetSchemaForeignKeys
     * @param TableManagementInterface $table
     * @return void
     */
    private function ensureForeignKey(array $currentSchemaForeignKeys, array $targetSchemaForeignKeys, TableManagementInterface $table): void
    {
        if (($currentSchemaForeignKeys === $targetSchemaForeignKeys) === false) {
            $currentCount = count($currentSchemaForeignKeys);
            $targetCount  = count($targetSchemaForeignKeys);
            if ($currentCount > $targetCount) {
                $this->addComment('Drop foreign key from table :'.$table->getName());
                foreach ($currentSchemaForeignKeys as $foreignKeyName => $currentSchemaForeignKey) {
                    if (array_key_exists($foreignKeyName, $targetSchemaForeignKeys) === false) {
                        // Drop Foreign key
                        $this->dbManagement->execute($table->getDropForeignKeyStatement($currentSchemaForeignKey));
                    }
                }
            } else {
                foreach ($targetSchemaForeignKeys as $foreignKeyName => $targetSchemaForeignKey) {
                    if (array_key_exists($foreignKeyName, $currentSchemaForeignKeys) === false) {
                        // add foreign key
                        $this->addComment('Add new Foreign key to the table :'.$table->getName());
                        $this->dbManagement->execute($table->getAddForeignKeyStatement($targetSchemaForeignKey));
                    }
                }
            }
        }
    }

    /**
     * @param array<string, ColumnManagementInterface> $currentAutoIncrementColumns
     * @param TableManagementInterface $table
     * @param array<ColumnManagementInterface> $targetSchemaPrimaryKeyColumns
     * @param array<ColumnManagementInterface> $targetAutoIncrementColumns
     * @param array<ColumnManagementInterface> $currentSchemaColumns
     * @param array<ColumnManagementInterface> $targetSchemaColumns
     * @return void
     */
    private function ensureIdentityColumn(array $currentAutoIncrementColumns, TableManagementInterface $table, array $targetSchemaPrimaryKeyColumns, array $targetAutoIncrementColumns, array $currentSchemaColumns, array $targetSchemaColumns): void
    {
        foreach ($targetAutoIncrementColumns as $targetName => $targetAutoIncrementColumn) {
            if (!array_key_exists($targetName, $currentAutoIncrementColumns) && array_key_exists($targetName, $currentSchemaColumns)) {
                // column already exists but is not auto increment, change it
                $this->addComment('Add Auto Increment to existing Column ('.$targetAutoIncrementColumn->getName().')');
                $this->dbManagement->execute($table->getAddIdentityStatement('', $targetSchemaPrimaryKeyColumns, $targetAutoIncrementColumn, $targetAutoIncrementColumn));
            } elseif (!array_key_exists($targetName, $currentAutoIncrementColumns) && !array_key_exists($targetName, $currentSchemaColumns)) {
                // column doesn't exist already, add it as auto increment
                $this->addComment('Add new Identity Column ('.$targetAutoIncrementColumn->getName().')');
                $this->dbManagement->execute($table->getAddIdentityStatement('', $targetSchemaPrimaryKeyColumns, $targetAutoIncrementColumn));
            }
        }

        foreach ($currentAutoIncrementColumns as $currentName => $currentAutoIncrementColumn) {
            if (!array_key_exists($currentName, $targetAutoIncrementColumns) && array_key_exists($currentName, $targetSchemaColumns)) {
                // auto increment needs to be removed
                $this->addComment('Drop Identity column and re-add it ('.$currentAutoIncrementColumn->getName().')');
                $this->dbManagement->execute($table->getDropIdentityStatement('', $targetSchemaPrimaryKeyColumns, $targetSchemaColumns[$currentName]));
            }
        }
    }

}
