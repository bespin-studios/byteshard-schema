<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Database\Schema;

use byteShard\Enum\DB\IndexType;

class Index
{
    private string $name;
    /** @var array<string> */
    private array     $columns   = [];
    private IndexType $indexType = IndexType::BTREE;
    private bool      $unique    = false;

    public function __construct(string $name, Column ...$columns)
    {
        $this->name = $name;
        foreach ($columns as $index => $column) {
            $this->columns[$index] = $column->getNewName();
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return array<string> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function setType(string $type): static
    {
        trigger_error('for '.__METHOD__.' it is deprecated to set index type. Use setIndexType(IndexType::<Type>) instead.', E_USER_DEPRECATED);
        $indexType = IndexType::tryFrom(strtolower($type));
        if ($indexType !== null) {
            $this->setIndexType($indexType);
        }
        if (strtolower($type) === 'unique') {
            trigger_error('for '.__METHOD__.' it is deprecated to set unique index. Use setUnique(true) instead.', E_USER_DEPRECATED);
            $this->setUnique();
        }
        return $this;
    }

    public function getType(): string
    {
        trigger_error('for '.__METHOD__.' it is deprecated to get index type. Use getIndexType() instead.', E_USER_DEPRECATED);
        return $this->indexType->value;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    public function setUnique(bool $unique = true): Index
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
