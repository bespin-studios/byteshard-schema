<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Database;

use byteShard\Database\Schema\State;

interface SchemaInterface
{
    /**
     * @return array<int, string>
     */
    public function getSchemaVersions(): array;

    public function getSchema(State $state, string $version): void;
}
