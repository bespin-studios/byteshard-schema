<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema;

interface ForeignKeyInterface
{

    public function getAddForeignKeyStatement(): string;

    public function getDropForeignKeyStatement(): string;

    /**
     * @return array<ColumnManagementInterface>
     */
    public function getForeignKeyColumns(): array;

    public function getForeignKeyStatement(): string;

    public function getUpdateForeignKeyStatement(): string;

    public function getForeignKeyConstraintName(): string;

    public function getSourceColumn(): string;

    public function getTargetTable(): string;

    public function getTargetColumn(): string;
}
