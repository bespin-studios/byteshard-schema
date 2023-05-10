<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Database\Schema;

class State
{

    private bool $dryRun = false;

    /**
     * @var array<string, array<int, Statement|Table>>
     */
    private array $steps = [];

    private ?string $version = null;

    public function setDryRun(bool $dryRun): static
    {
        $this->dryRun = $dryRun;
        return $this;
    }

    public function getDryRun(): bool
    {
        return $this->dryRun;
    }

    public function addTable(Table $table): Table
    {
        $this->steps[$this->version][] = $table;
        return $table;
    }

    public function addStatement(Statement $statement): static
    {
        $this->steps[$this->version][] = $statement;
        return $this;
    }

    /**
     * @return array<int, Statement|Table>
     */
    public function getSteps(string $version): array
    {
        if (array_key_exists($version, $this->steps)) {
            return $this->steps[$version];
        }
        return [];
    }

    /**
     * @internal
     */
    public function setVersion(string $version): self
    {
        if ($this->version !== null && $this->version !== $version && array_key_exists($this->version, $this->steps)) {
            unset($this->steps[$this->version]);
        }
        $this->steps   = [];
        $this->version = $version;
        return $this;
    }
}
