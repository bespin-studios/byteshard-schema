<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Database\Schema;

class ForeignKey
{
    private string $targetTable;
    private string $targetColumn;
    private string $column;

    public function __construct(Column|string $column, string $targetTable, string $targetColumn)
    {
        $this->column       = ($column instanceof Column) ? $column->getName() : $column;
        $this->targetColumn = $targetColumn;
        $this->targetTable  = $targetTable;
    }

    public function getForeignKeys(): object
    {
        return (object)[
            'column'       => $this->column,
            'targetTable'  => $this->targetTable,
            'targetColumn' => $this->targetColumn
        ];
    }

    public function getSourceColumn(): string
    {
        return $this->column;
    }

    public function getTargetColumn(): string
    {
        return $this->targetColumn;
    }

    public function getTargetTable(): string
    {
        return $this->targetTable;
    }

    public function getForeignKeyStatement(): string
    {
        return $this->getSourceColumn().' FOREIGN KEY('.$this->getSourceColumn().') REFERENCES '.$this->getTargetTable().'('.$this->getTargetColumn().') ON DELETE CASCADE';
    }
}