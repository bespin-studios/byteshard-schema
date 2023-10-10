<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema;

class Grants
{
    private string $grantee;
    /**
     * @var array<string,array<string>>
     */
    private array $privileges = [];

    public function setGrantee(string $grantee): self
    {
        $this->grantee = $grantee;
        return $this;
    }

    public function addPrivilege(string $privilege): self
    {
        $this->privileges[$privilege] = [];
        return $this;
    }

    public function addColumns(string $privilege, string $column): self
    {
        $this->privileges[$privilege][] = $column;
        return $this;
    }

    public function getGrantee(): string
    {
        return $this->grantee;
    }

    /**
     * @return array<string,array<string>>
     */
    public function getPrivileges(): array
    {
        ksort($this->privileges);
        foreach ($this->privileges as &$privilege) {
            sort($privilege);
        }
        return $this->privileges;
    }
}