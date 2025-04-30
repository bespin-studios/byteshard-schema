<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */
/** @noinspection SqlResolve */
/** @noinspection SqlNoDataSourceInspection */

namespace byteShard\Internal\Database\Schema\MySQL;

use byteShard\Internal\Database\Schema\ColumnManagementInterface;
use byteShard\Internal\Database\Schema\ForeignKeyInterface;
use byteShard\Internal\Database\Schema\IndexManagementInterface;
use byteShard\Internal\Database\Schema\TableParent;

class Table extends TableParent
{

    private string $charset = 'utf8mb4';
    private string $collate = 'utf8mb4_unicode_ci';
    private string $engine  = 'InnoDB';

    public function getAddColumnStatement(ColumnManagementInterface $columnToAdd): string
    {
        return 'ALTER TABLE `'.$this->getName().'` '.$columnToAdd->getAddColumnStatement();
    }

    /**
     * @param string $primaryKeyName
     * @param array<ColumnManagementInterface> $primaryKeyColumns
     * @param ColumnManagementInterface $addIdentityColumn
     * @param ColumnManagementInterface|null $changeIdentityColumn
     * @param array<ColumnManagementInterface>|null $autoIncrementColumns
     * @param ColumnManagementInterface|null $addAutoIncrementColumn
     * @return string
     */
    public function getAddIdentityStatement(string $primaryKeyName, array $primaryKeyColumns, ColumnManagementInterface $addIdentityColumn, ?ColumnManagementInterface $changeIdentityColumn = null, ?array $autoIncrementColumns = null, ?ColumnManagementInterface $addAutoIncrementColumn = null): string
    {
        $orderedPrimaryKeyColumns[] = '`'.$addIdentityColumn->getName().'`';
        foreach ($primaryKeyColumns as $primaryKeyColumn) {
            if ($primaryKeyColumn->getName() !== $addIdentityColumn->getName()) {
                $orderedPrimaryKeyColumns[] = '`'.$primaryKeyColumn.'`';
            }
        }
        if ($changeIdentityColumn === null) {
            $dropPrimary = $primaryKeyName === 'PRIMARY' ? ' DROP '.$primaryKeyName.' KEY,' : '';
            return 'ALTER TABLE `'.$this->getName().'`'.$dropPrimary.' ADD COLUMN '.$addIdentityColumn->getColumnDefinition().', ADD PRIMARY KEY ('.implode(',', $orderedPrimaryKeyColumns).')';
        }
        return 'ALTER TABLE `'.$this->getName().'` DROP '.$primaryKeyName.' KEY, CHANGE `'.$changeIdentityColumn->getName().'` '.$changeIdentityColumn->getColumnDefinition().', ADD COLUMN '.$addIdentityColumn->getColumnDefinition().', ADD '.$primaryKeyName.' KEY ('.implode(',', $orderedPrimaryKeyColumns).')';
    }

    public function getAddIndexStatement(IndexManagementInterface $index): string
    {
        return 'ALTER TABLE `'.$this->getName().'` '.$index->getAddIndexStatement();
    }

    public function getCreateTableStatement(): string
    {
        $columns = $this->getColumns();
        if (!empty($columns)) {
            $items          = [];
            $primaryColumns = [];
            foreach ($columns as $column) {
                $items[] = $column->getColumnDefinition();
                if ($column->isPrimary() === true) {
                    $primaryColumns[] = '`'.$column->getName().'`';
                }
            }
            if (!empty($primaryColumns)) {
                $items[] = 'PRIMARY KEY ('.implode(',', $primaryColumns).')';
            }
            $indices = $this->getIndices();
            foreach ($indices as $index) {
                $indexColumns = $index->getIndexColumns();
                if (!empty($indexColumns)) {
                    $items[] = match (strtolower($index->getType())) {
                        'unique' => 'UNIQUE KEY `'.$index->getName().'` (`'.implode('`,`', $indexColumns).'`)',
                        default  => 'KEY `'.$index->getName().'` (`'.implode('`,`', $indexColumns).'`)',
                    };
                }
            }
            $command = 'CREATE TABLE `'.$this->getName().'` (';
            $command .= PHP_EOL.implode(','.PHP_EOL, $items);
            $command .= PHP_EOL.')';
            $command .= ' ENGINE='.$this->engine.' DEFAULT CHARSET='.$this->charset.' COLLATE='.$this->collate;
            $command .= $this->getComment() !== '' ? " COMMENT='".$this->getComment()."'" : '';
            return $command;
        }
        return '';
    }

    public function getDropColumnStatement(ColumnManagementInterface $column): string
    {
        return 'ALTER TABLE `'.$this->getName().'` '.$column->getDropColumnStatement();
    }

    /**
     * @param string $primaryKeyName
     * @param array<ColumnManagementInterface> $primaryKeyColumns
     * @param ColumnManagementInterface $dropIdentityColumn
     * @param array<ColumnManagementInterface>|null $autoIncrementColumns
     * @param ColumnManagementInterface|null $dropAutoIncrementColumn
     * @return string
     */
    public function getDropIdentityStatement(string $primaryKeyName, array $primaryKeyColumns, ColumnManagementInterface $dropIdentityColumn, ?array $autoIncrementColumns = null, ?ColumnManagementInterface $dropAutoIncrementColumn = null): string
    {
        return 'ALTER TABLE `'.$this->getName().'` DROP '.$primaryKeyName.' KEY, DROP COLUMN `'.$dropIdentityColumn->getName().'`, ADD '.$primaryKeyName.' KEY (`'.implode('`,`', $primaryKeyColumns).'`)';
    }

    public function getDropIndexStatement(IndexManagementInterface $index): string
    {
        return 'ALTER TABLE `'.$this->getName().'` '.$index->getDropIndexStatement();
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
        $orderedPrimaryKeyColumns[] = '`'.$targetIdentityColumn->getName().'`';
        foreach ($primaryKeyColumns as $primaryKeyColumn) {
            if ($primaryKeyColumn->getName() !== $targetIdentityColumn->getName()) {
                $orderedPrimaryKeyColumns[] = '`'.$primaryKeyColumn.'`';
            }
        }
        return 'ALTER TABLE `'.$this->getName().'` DROP '.$primaryKeyName.' KEY, CHANGE `'.$currentIdentityColumn->getName().'` '.$currentIdentityColumn->getColumnDefinition().', CHANGE `'.$targetIdentityColumn->getName().'` '.$targetIdentityColumn->getColumnDefinition().', ADD '.$primaryKeyName.' KEY ('.implode(',', $orderedPrimaryKeyColumns).')';
    }

    /**
     * @param string $primaryKeyName
     * @param array<ColumnManagementInterface> $primaryKeyColumns
     * @param ColumnManagementInterface|null $targetSchemaIdentityColumn
     * @return string
     */
    public function getRecreatePrimaryKeyStatement(string $primaryKeyName, array $primaryKeyColumns, ?ColumnManagementInterface $targetSchemaIdentityColumn = null): string
    {
        $orderedPrimaryKeyColumns = [];
        if ($targetSchemaIdentityColumn !== null) {
            $orderedPrimaryKeyColumns[] = '`'.$targetSchemaIdentityColumn.'`';
        }
        foreach ($primaryKeyColumns as $primaryKeyColumn) {
            if ($primaryKeyColumn !== $targetSchemaIdentityColumn) {
                $orderedPrimaryKeyColumns[] = '`'.$primaryKeyColumn.'`';
            }
        }
        if ($primaryKeyName !== '') {
            if (empty($orderedPrimaryKeyColumns)) {
                return 'ALTER TABLE `'.$this->getName().'` DROP '.$primaryKeyName.' KEY';
            }
            return 'ALTER TABLE `'.$this->getName().'` DROP '.$primaryKeyName.' KEY, ADD '.$primaryKeyName.' KEY ('.implode(',', $orderedPrimaryKeyColumns).')';
        }
        return 'ALTER TABLE `'.$this->getName().'` ADD PRIMARY KEY ('.implode(',', $orderedPrimaryKeyColumns).')';
    }

    public function getUpdateTableCommentStatement(): string
    {
        return 'ALTER TABLE `'.$this->getName().'` COMMENT=\''.$this->getComment().'\'';
    }

    /**
     * @return array<string>
     */
    public function getUpdateColumnStatements(ColumnManagementInterface $column): array
    {
        return ['ALTER TABLE `'.$this->getName().'` '.$column->getUpdateColumnStatement()];
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

    public function setEngine(string $engine): static
    {
        $this->engine = $engine;
        return $this;
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

    public function getDropForeignKeyStatement(ForeignKeyInterface $foreignKey): string
    {
        return 'ALTER TABLE '.$this->getName().$foreignKey->getDropForeignKeyStatement();
    }

    public function getAddForeignKeyStatement(ForeignKeyInterface $foreignKey): string
    {
        return 'ALTER TABLE '.$this->getName().$foreignKey->getAddForeignKeyStatement();
    }
}
