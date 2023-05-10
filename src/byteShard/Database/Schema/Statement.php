<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Database\Schema;

class Statement
{

    private string $statement;

    public function __construct(string $statement)
    {
        $this->statement = $statement;
    }

    public function getName(): string
    {
        return md5($this->statement);
    }

    public function __toString()
    {
        return $this->statement;
    }
}
