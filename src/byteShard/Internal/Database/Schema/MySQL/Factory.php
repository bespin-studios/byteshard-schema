<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema\MySQL;

use byteShard\Enum;
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
            } elseif ($column->getType() === Enum\DB\ColumnType::BOOL || $column->getType() === Enum\DB\ColumnType::BOOLEAN) {
                $isNullable = false;
            } else {
                $isNullable = true;
            }
        }
        $mysqlColumn = new Column($column->getName(), $column->getNewName(), $column->getType(), $column->getLength(), $isNullable, $column->isPrimary(), $column->isIdentity(), $column->getDefault(), $column->getComment());
        $mysqlColumn->setCollate($collate);
        return $mysqlColumn;
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
        $mysqlIndex = new Index($index->getName(), ...$columns);
        $mysqlIndex->setType($index->getType());
        return $mysqlIndex;
    }

    public static function table(\byteShard\Database\Schema\Table $table, string $defaultCollate, string $defaultCharset, ColumnManagementInterface ...$columns): TableManagementInterface
    {
        $mysqlTable = new Table($table->getName(), ...$columns);
        $mysqlTable->setComment($table->getComment());
        $mysqlTable->setCollate($table->getCollate() ?? $defaultCollate);
        $mysqlTable->setDefaultCharset($table->getCharset() ?? $defaultCharset);
        return $mysqlTable;
    }
}
