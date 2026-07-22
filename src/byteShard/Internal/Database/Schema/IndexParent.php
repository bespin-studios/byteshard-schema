<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema;

use byteShard\Enum\DB\IndexType;

abstract class IndexParent implements IndexManagementInterface
{
    private string $indexName;

    /**
     * @var array<string>
     */
    private array     $columns   = [];
    private IndexType $indexType = IndexType::BTREE;
    private bool   $unique  = false;

    public function __construct(string $indexName, ColumnManagementInterface ...$columns)
    {
        $this->indexName = $indexName;
        foreach ($columns as $colIndex => $column) {
            $this->columns[$colIndex] = $column->getNewName();
        }
    }

    /**
     * @return array<string>
     */
    public function getIndexColumns(): array
    {
        return $this->columns;
    }

    public function getName(): string
    {
        return $this->indexName;
    }

    public function getType(): string
    {
        return $this->indexType->value;
    }

    public function setType(string $type): static
    {
        $indexType = IndexType::tryFrom(strtolower($type));
        if ($indexType === null) {
            throw new \InvalidArgumentException('Invalid index type: '.$type);
        }
        $this->indexType = $indexType;
        return $this;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    public function setUnique(bool $unique = true): static
    {
        $this->unique = $unique;
        return $this;
    }

    public function setIndexType(IndexType $indexType): static
    {
        $this->indexType = $indexType;
        return $this;
    }

    public function getIndexType(): IndexType
    {
        return $this->indexType;
    }
}
