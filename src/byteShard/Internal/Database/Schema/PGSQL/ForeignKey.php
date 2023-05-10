<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema\PGSQL;

use byteShard\Internal\Database\Schema\ForeignKeyParent;

class ForeignKey extends ForeignKeyParent
{

    protected function getConstraintName(): string
    {
        return 'constraint fk_'.$this->sourceTable.'_'.$this->column;
    }
}
   
