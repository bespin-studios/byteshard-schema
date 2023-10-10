<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema\PGSQL;

use byteShard\Internal\Database\Schema\ColumnManagementInterface;
use byteShard\Internal\Database\Schema\IndexParent;

class Index extends IndexParent
{

    private string $tableName;

    public function __construct(string $tableName, string $indexName, ColumnManagementInterface ...$columns)
    {
        $this->tableName = $tableName;
        parent::__construct($indexName, ...$columns);
    }

    public function getAddIndexStatement(): string
    {
        if (!empty($this->getIndexColumns())) {
            if ($this->isUnique()) {
                return 'CREATE UNIQUE INDEX '.$this->getName().' ON '.$this->getTableName().' ('.implode(',', $this->getIndexColumns()).')';
            } else {
                return 'CREATE INDEX '.$this->getName().' ON '.$this->getTableName().' ('.implode(',', $this->getIndexColumns()).')';
            }
        }
        return '';
    }

    public function getDropIndexStatement(): string
    {
        return 'DROP INDEX '.$this->getName();
    }

    private function getTableName(): string
    {
        return $this->tableName;
    }
}
