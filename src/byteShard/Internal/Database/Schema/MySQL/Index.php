<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema\MySQL;

use byteShard\Enum\DB\IndexType;
use byteShard\Internal\Database\Schema\IndexParent;

class Index extends IndexParent
{
    public function getAddIndexStatement(): string
    {
        $indexColumns = $this->getIndexColumns();
        if (empty($indexColumns)) {
            return '';
        }

        $cols   = '`'.implode('`,`', $indexColumns).'`';
        $name   = $this->getName();
        $unique = $this->isUnique() ? 'UNIQUE ' : '';

        return match ($this->getIndexType()) {
            IndexType::FULLTEXT => 'ADD FULLTEXT INDEX `'.$name.'` ('.$cols.')',
            default => 'ADD '.$unique.'INDEX `'.$name.'` ('.$cols.')',
        };
    }

    public function getDropIndexStatement(): string
    {
        return 'DROP INDEX `'.$this->getName().'`';
    }
}
