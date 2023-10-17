<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */
/** @noinspection SqlNoDataSourceInspection */

namespace byteShard\Internal\Database\Schema\MySQL;

use byteShard\Internal\Database\Schema\ForeignKeyParent;

class ForeignKey extends ForeignKeyParent
{
    public function getAddForeignKeyStatement(): string
    {
        return ' ADD CONSTRAINT '.$this->getForeignKeyConstraintName().' FOREIGN KEY ('.$this->getSourceColumn().') REFERENCES '.$this->getTargetTable().' ('.$this->getTargetColumn().')';
    }

    public function getDropForeignKeyStatement(): string
    {
        return ' DROP FOREIGN KEY '.$this->getForeignKeyConstraintName();
    }

    public function getForeignKeyStatement(): string
    {
        // TODO: Implement getForeignKeyStatement() method.
        return '';
    }

    public function getForeignKeyConstraintName(): string
    {
        return $this->foreignKeyConstraintName ?? 'fk_'.strtolower($this->getTargetTable()).'_'.$this->getTargetColumn();
    }
}
