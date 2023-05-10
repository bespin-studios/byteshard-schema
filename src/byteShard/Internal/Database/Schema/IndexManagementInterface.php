<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema;

interface IndexManagementInterface
{
    public function getAddIndexStatement(): string;

    public function getDropIndexStatement(): string;

    /**
     * @return array<string>
     */
    public function getIndexColumns(): array;

    public function getName(): string;

    public function getType(): string;

    public function setType(string $type): static;

    public function setUnique(bool $unique = true): static;

    public function isUnique(): bool;
}
