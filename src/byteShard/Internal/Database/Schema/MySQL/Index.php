<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema\MySQL;

use byteShard\Internal\Database\Schema\IndexParent;

class Index extends IndexParent
{
    public function getAddIndexStatement(): string
    {
        if (!empty($this->getIndexColumns())) {
            if ($this->isUnique()){
                return 'ADD UNIQUE INDEX `'.$this->getName().'` (`'.implode('`,`', $this->getIndexColumns()).'`)';
            }
            return 'ADD INDEX `'.$this->getName().'` (`'.implode('`,`', $this->getIndexColumns()).'`)';
        }
        return '';
    }

    public function getDropIndexStatement(): string
    {
        return 'DROP INDEX `'.$this->getName().'`';
    }
}
