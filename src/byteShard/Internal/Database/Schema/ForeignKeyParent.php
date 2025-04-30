<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema;

use byteShard\Database\Schema\Column;

abstract class ForeignKeyParent implements ForeignKeyInterface
{
    protected string $targetTable;
    protected string $targetColumn;
    protected string $column;
    protected string $sourceTable;
    protected ?string $foreignKeyConstraintName;

    public function __construct(Column|string $column, string $sourceTable, string $targetTable, string $targetColumn, ?string $foreignKeyConstraintName = null)
    {
        $this->column                   = ($column instanceof Column) ? $column->getName() : $column;
        $this->sourceTable              = $sourceTable;
        $this->targetTable              = $targetTable;
        $this->targetColumn             = $targetColumn;
        $this->foreignKeyConstraintName = $foreignKeyConstraintName;
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

    public function getUpdateForeignKeyStatement(): string
    {
        // TODO: Implement getUpdateForeignKeyStatement() method.
        return '';
    }

    public function getForeignKeyColumns(): array
    {
        // TODO: Implement getForeignKeyColumns() method.
        return [];
    }
}