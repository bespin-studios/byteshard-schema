<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Database\Schema;

class Index
{
    private string $name;

    /**
     * @var array<string>
     */
    private array $columns = [];

    private string $type   = '';
    private bool   $unique = false;

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

    /**
     * @return array<string>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        if (strtolower($type) === 'unique') {
            trigger_error('for '.__METHOD__.' it is deprecated to set unqiue index. Use setUnique(true) instead.', E_USER_DEPRECATED);
            $this->setUnique(true);
        }
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
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
}
