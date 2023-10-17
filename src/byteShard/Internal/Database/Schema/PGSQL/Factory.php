<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema\PGSQL;

use byteShard\Internal\Database\Schema\ColumnManagementInterface;
use byteShard\Internal\Database\Schema\ForeignKeyInterface;
use byteShard\Internal\Database\Schema\IndexManagementInterface;
use byteShard\Internal\Database\Schema\TableManagementInterface;

class Factory
{
    public static function column(\byteShard\Database\Schema\Column $column, string $collate): ColumnManagementInterface
    {
        $isNullable = $column->isNullable();
        if ($isNullable === null) {
            if ($column->getType()->isNumeric()) {
                $isNullable = false;
            } else {
                $isNullable = true;
            }
        }
        $pgsqlColumn = new Column(strtolower($column->getName()), $column->getNewName(), $column->getType(), $column->getLength(), $isNullable, $column->isPrimary(), $column->isIdentity(), $column->getDefault(), $column->getComment());
        $pgsqlColumn->setCollate($collate);
        return $pgsqlColumn;
    }

    public static function foreignKey(string $tableName, \byteShard\Database\Schema\ForeignKey $foreignKey): ForeignKeyInterface
    {
        return new ForeignKey($foreignKey->getSourceColumn(), $tableName, $foreignKey->getTargetTable(), $foreignKey->getTargetColumn(), $foreignKey->getName());
    }

    public static function index(string $tableName, \byteShard\Database\Schema\Index $index): IndexManagementInterface
    {
        $columns = [];
        foreach ($index->getColumns() as $column) {
            $columns[] = new Column($column);
        }
        $pgsqlIndex = new Index($tableName, $index->getName(), ...$columns);
        $pgsqlIndex->setType($index->getType());
        $pgsqlIndex->setUnique($index->isUnique());
        return $pgsqlIndex;
    }

    public static function table(\byteShard\Database\Schema\Table $table, string $defaultCollate, string $defaultCharset, ColumnManagementInterface ...$columns): TableManagementInterface
    {
        $pgsqlTable = new Table(strtolower($table->getName()), ...$columns);
        $pgsqlTable->setComment($table->getComment());
        $pgsqlTable->setCollate($table->getCollate() ?? $defaultCollate);
        $pgsqlTable->setDefaultCharset($table->getCharset() ?? $defaultCharset);
        return $pgsqlTable;
    }
}