<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */
/** @noinspection SqlResolve */
/** @noinspection SqlNoDataSourceInspection */

namespace byteShard\Internal\Database\Schema\PGSQL;

use byteShard\Internal\Database\Schema\ForeignKeyInterface;
use byteShard\Internal\Database\Schema\IndexManagementInterface;
use byteShard\Internal\Database\Schema\TableParent;
use byteShard\Internal\Database\Schema\ColumnManagementInterface;

class Table extends TableParent
{

    private string $charset = 'UTF8';
    private string $collate = ' en_US.utf8';

    public function getAddColumnStatement(ColumnManagementInterface $columnToAdd): string
    {
        return 'ALTER TABLE '.$this->getName().' '.$columnToAdd->getAddColumnStatement();
    }

    public function getDropColumnStatement(ColumnManagementInterface $column): string
    {
        return 'ALTER TABLE '.$this->getName().' '.$column->getDropColumnStatement();
    }

    /**
     * @param $primaryKeyName
     * @param array<ColumnManagementInterface> $primaryKeyColumns
     * @param ColumnManagementInterface $addIdentityColumn
     * @param array<ColumnManagementInterface>|null $autoIncrementColumns
     * @param ColumnManagementInterface|null $addAutoIncrementColumn
     * @param ColumnManagementInterface|null $changeIdentityColumn
     * @return string
     */
    public function getAddIdentityStatement(
        $primaryKeyName,
        array $primaryKeyColumns,
        ColumnManagementInterface $addIdentityColumn,
        ?ColumnManagementInterface $changeIdentityColumn = null,
        ?array $autoIncrementColumns = null,
        ?ColumnManagementInterface $addAutoIncrementColumn = null
    ): string {
        if ($changeIdentityColumn === null) {
            return 'ALTER TABLE '.$this->getName().' ADD COLUMN '.$addIdentityColumn->getColumnDefinition();
        } elseif ($changeIdentityColumn === $addIdentityColumn) {
            return 'ALTER TABLE '.$this->getName().' ALTER COLUMN '.$changeIdentityColumn->getName().' ADD GENERATED ALWAYS AS IDENTITY';
        }
        return '';
    }

    /**
     * @param $primaryKeyName
     * @param array<ColumnManagementInterface> $primaryKeyColumns
     * @param ColumnManagementInterface $dropIdentityColumn
     * @param array<ColumnManagementInterface>|null $autoIncrementColumns
     * @param ColumnManagementInterface|null $dropAutoIncrementColumn
     * @return string
     */
    public function getDropIdentityStatement($primaryKeyName, array $primaryKeyColumns, ColumnManagementInterface $dropIdentityColumn, ?array $autoIncrementColumns = null, ?ColumnManagementInterface $dropAutoIncrementColumn = null): string
    {
        return 'ALTER TABLE '.$this->getName().' ALTER COLUMN '.$dropIdentityColumn->getName().' DROP IDENTITY';
    }

    public function getAddIndexStatement(IndexManagementInterface $index): string
    {
        if (!empty($index->getIndexColumns())) {
            return $index->getAddIndexStatement();
        } else {
            return '';
        }
    }

    public function getDropIndexStatement(IndexManagementInterface $index): string
    {
        return $index->getDropIndexStatement();
    }

    public function getCreateTableStatement(): string
    {
        $foreignKeyColumns = $this->getForeignKeys();
        $columns           = $this->getColumns();
        if (!empty($columns)) {
            $items          = [];
            $primaryColumns = [];
            foreach ($columns as $column) {
                $items[] = $column->getColumnDefinition();
                if ($column->isPrimary() === true) {
                    $primaryColumns[] = $column->getName();
                }
            }
            if (!empty($primaryColumns)) {
                $items[] = 'PRIMARY KEY ('.implode(',', $primaryColumns).')';
            }
            if (!empty($foreignKeyColumns)) {
                foreach ($foreignKeyColumns as $foreignKeyColumn) {
                    $items[] = 'CONSTRAINT fk_'.$this->getName().'_'.$foreignKeyColumn->getForeignKeyStatement();
                }
            }

            $command = 'CREATE TABLE '.$this->getName().' (';
            $command .= PHP_EOL.implode(','.PHP_EOL, $items);
            $command .= PHP_EOL.')';
            $command .= $this->getComment() !== '' ? " COMMENT='".$this->getComment()."'" : '';
            return $command;
        }
        return '';
    }

    /**
     * Function to convert table name in lowercase for postgres
     */
    public function getName(): string
    {
        return strtolower(parent::getName());
    }

    /**
     * @param string $primaryKeyName
     * @param array<ColumnManagementInterface> $primaryKeyColumns
     * @param ColumnManagementInterface $currentIdentityColumn
     * @param ColumnManagementInterface $targetIdentityColumn
     * @return string
     */
    public function getMoveIdentityStatement(string $primaryKeyName, array $primaryKeyColumns, ColumnManagementInterface $currentIdentityColumn, ColumnManagementInterface $targetIdentityColumn): string
    {
        // Not implemented for postgres since it's not needed
        return '';
    }

    /**
     * @param string $primaryKeyName
     * @param array<ColumnManagementInterface> $primaryKeyColumns
     * @param ColumnManagementInterface|null $targetSchemaIdentityColumn
     * @return string
     */
    public function getRecreatePrimaryKeyStatement(string $primaryKeyName, array $primaryKeyColumns, ?ColumnManagementInterface $targetSchemaIdentityColumn = null): string
    {
        if (empty($primaryKeyColumns) && !empty($primaryKeyName)) {
            return 'ALTER TABLE '.$this->getName().' DROP CONSTRAINT '.$primaryKeyName;
        }
        $recreatePrimaryKey = '';
        if ($primaryKeyName !== '') {
            $recreatePrimaryKey = 'ALTER TABLE '.$this->getName().' DROP CONSTRAINT '.$primaryKeyName.'; ';
        }
        $recreatePrimaryKey .= ' ALTER TABLE '.$this->getName().' ADD PRIMARY KEY ('.implode(',', $primaryKeyColumns).')';
        return $recreatePrimaryKey;
    }

    public function getDropForeignKeyStatement(ForeignKeyInterface $foreignKey): string
    {
        return 'ALTER TABLE '.$this->getName().' DROP CONSTRAINT '.$foreignKey->getForeignKeyConstraintName();
    }

    public function getAddForeignKeyStatement(ForeignKeyInterface $foreignKey): string
    {
        return 'ALTER TABLE '.$this->getName().' ADD CONSTRAINT fk_'.$this->getName().'_'.$foreignKey->getSourceColumn().' FOREIGN KEY ('.$foreignKey->getSourceColumn().') REFERENCES '.$foreignKey->getTargetTable().' ('.$foreignKey->getTargetColumn().')';
    }

    /**
     * @return array<string>
     */
    public function getUpdateColumnStatements(ColumnManagementInterface $column): array
    {
        return ['ALTER TABLE '.$this->getName().' '.$column->getUpdateColumnStatement()];
    }

    public function getUpdateTableCommentStatement(): string
    {
        return 'ALTER TABLE '.$this->getName().' COMMENT=\''.$this->getComment().'\'';
    }

    public function setCollate(string $collate): static
    {
        $this->collate = $collate;
        return $this;
    }

    public function getCollate(): string
    {
        return $this->collate;
    }

    public function setDefaultCharset(string $charset): static
    {
        $this->charset = $charset;
        return $this;
    }

    public function getDefaultCharset(): string
    {
        return $this->charset;
    }
}
