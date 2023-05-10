<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema;

abstract class IndexParent implements IndexManagementInterface
{
    private string $indexName;

    /**
     * @var array<string>
     */
    private array  $columns = [];
    private string $type    = '';
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
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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
}
