<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema;

abstract class DBManagementParent implements DBManagementInterface
{
    private bool $dryRun = false;
    public function setDryRun(bool $dryRun): static
    {
        $this->dryRun = $dryRun;
        return $this;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }
}