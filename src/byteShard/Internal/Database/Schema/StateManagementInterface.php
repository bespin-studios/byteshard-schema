<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema;

interface StateManagementInterface
{
    /**
     * display the database changes that will be applied instead of applying them
     */
    public function setDryRun(bool $dryRun): void;

    public function setVersion(string $version): void;

    /**
     * @return array<string>
     * @internal
     */
    public function ensureState(bool $baseSchema = false, bool $drop = true): array;

    /**
     * @return array<string>
     */
    public function getSchema(): array;
}