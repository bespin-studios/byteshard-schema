<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema;

use byteShard\Internal\Database\Schema\PGSQL\ForeignKey;

/**
 * Class TableParent
 * @package byteShard\Internal\Database\Schema;
 */
abstract class TableParent implements TableManagementInterface
{

    /**
     * @var ColumnManagementInterface[]
     */
    private array  $columns = [];
    private string $comment = '';

    /**
     * @var IndexManagementInterface[]
     */
    private array  $indices = [];
    private string $name;

    /**
     * @var array<string, ForeignKeyInterface>
     */
    private array $foreignKeys = [];

    public function __construct(string $tableName, ColumnManagementInterface ...$columns)
    {
        $this->name = $tableName;
        foreach ($columns as $column) {
            $this->columns[$column->getName()] = $column;
        }
    }

    public function __toString()
    {
        return $this->name;
    }

    public function addColumn(ColumnParent ...$columns): void
    {
        foreach ($columns as $column) {
            $this->columns[$column->getName()] = $column;
        }
    }

    /**
     * @return array<ColumnManagementInterface>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<IndexManagementInterface>
     */
    public function getIndices(): array
    {
        return $this->indices;
    }

    /**
     * @return array<ColumnManagementInterface>
     */
    public function getPrimaryKeyColumns(): array
    {
        $primaryKeyColumns = [];
        foreach ($this->columns as $column) {
            if ($column->isPrimary() === true) {
                $primaryKeyColumns[$column->getName()] = $column;
            }
        }
        return $primaryKeyColumns;
    }

    /**
     * @return array<string, ForeignKeyInterface>
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    public function setForeignKeys(ForeignKeyInterface ...$foreignKeys): void
    {
        foreach ($foreignKeys as $foreignKey) {
            $this->foreignKeys[$foreignKey->getSourceColumn()] = $foreignKey;
        }
    }

    /**
     * function to get array of AutoIncrement/Identity columns
     * @return array<string, string>
     */
    public function getAutoIncrementColumns(): array
    {
        $identityKeyColumns = [];
        foreach ($this->columns as $column) {
            if ($column->isIdentity() === true) {
                $identityKeyColumns[$column->getName()] = $column->getName();
            }
        }
        return $identityKeyColumns;
    }

    public function setComment(string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function setIndices(IndexManagementInterface ...$indices): void
    {
        foreach ($indices as $index) {
            $this->indices[$index->getName()] = $index;
        }
    }
}
