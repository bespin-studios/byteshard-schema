<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */
/** @noinspection SqlNoDataSourceInspection */

namespace byteShard\Internal\Database\Schema\PGSQL;

use byteShard\Internal\Database\Schema\ForeignKeyParent;

class ForeignKey extends ForeignKeyParent
{
    public function getForeignKeyStatement(): string
    {
        // return ' CONSTRAINT fk_'.$this->column.' FOREIGN KEY('.$this->column.') REFERENCES '. $this->targetTable.'('.$this->targetColumn.') ON DELETE CASCADE';
        return $this->column.' FOREIGN KEY('.$this->column.') REFERENCES '.strtolower($this->targetTable).'('.$this->targetColumn.') ON DELETE CASCADE';
    }

    public function getAddForeignKeyStatement(): string
    {
        // TODO: Implement getAddForeignKeyStatement() method.
        return 'ALTER TABLE '.strtolower($this->sourceTable).' ADD CONSTRAINT '.$this->getForeignKeyConstraintName().' FOREIGN KEY ('.$this->column.') REFERENCES '.strtolower($this->targetTable).'( '.$this->targetColumn.') '.'ON DELETE CASCADE';
    }

    public function getDropForeignKeyStatement(): string
    {
//        $statement =  $fkey->getSourceColumn();
//        $statement =  ' DROP CONSTRAINT fk_'.$fkey->getSourceColumn();
//        return $statement;
        return '';
    }

    public function getForeignKeyConstraintName(): string
    {
        return $this->foreignKeyConstraintName ?? 'constraint fk_'.strtolower($this->sourceTable).'_'.$this->column;
    }
}
   
