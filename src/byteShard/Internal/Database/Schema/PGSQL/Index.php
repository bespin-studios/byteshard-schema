<?php
/** @noinspection SqlResolve */

/** @noinspection SqlNoDataSourceInspection */
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema\PGSQL;

use byteShard\Enum\DB\IndexType;
use byteShard\Internal\Database\Schema\ColumnManagementInterface;
use byteShard\Internal\Database\Schema\IndexParent;
use InvalidArgumentException;

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
        $indexColumns = $this->getIndexColumns();
        if (empty($indexColumns)) {
            return '';
        }

        if (in_array($this->getIndexType(), [IndexType::FULLTEXT], true)) {
            throw new InvalidArgumentException(
                $this->getIndexType()->value.' indexes require additional setup on Postgres and aren\'t auto-generated.'
            );
        }

        $cols   = implode(',', $indexColumns);
        $name   = $this->getName();
        $unique = $this->isUnique() ? 'UNIQUE ' : '';

        $using = match ($this->getIndexType()) {
            default => 'btree',
        };

        return 'CREATE '.$unique.'INDEX '.$name.' ON '.$this->getTableName().' USING '.$using.' ('.$cols.')';
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
