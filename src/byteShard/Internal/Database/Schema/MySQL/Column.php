<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema\MySQL;

use byteShard\Enum;
use byteShard\Internal\Database\Schema\ColumnArguments;
use byteShard\Internal\Database\Schema\ColumnParent;

class Column extends ColumnParent
{
    private string $collate = 'utf8mb4_unicode_ci';

    public function __construct(string $name, string $newName = '', Enum\DB\ColumnType $type = Enum\DB\ColumnType::INT, int|string $length = null, bool $isNullable = true, bool $primary = false, bool $identity = false, string|int|null $default = null, string $comment = '')
    {
        // Database specific transformations and default values
        switch ($type) {
            case Enum\DB\ColumnType::BOOL:
            case Enum\DB\ColumnType::BOOLEAN:
                $type = Enum\DB\ColumnType::TINYINT;
                if ($length === null) {
                    $length = 1;
                }
                break;
            case Enum\DB\ColumnType::INT:
                if ($length === null) {
                    $length = 11;
                }
                break;
            case Enum\DB\ColumnType::TINYINT:
                if ($length === null) {
                    $length = 4;
                }
                break;
            case Enum\DB\ColumnType::TINYBLOB:
            case Enum\DB\ColumnType::BLOB:
            case Enum\DB\ColumnType::MEDIUMBLOB:
            case Enum\DB\ColumnType::LONGBLOB:
                if ($length !== null) {
                    $length = null;
                }
                break;
        }
        if ($isNullable === false && $default === null && $type->isNumeric()) {
            $default = 0;
        }
        if ($isNullable === false && $default === null) {
            if ($type->isNumeric()) {
                $default = 0;
            } else {
                $default = "''";
            }
        }
        parent::__construct($name, $newName, $type, $length, $isNullable, $primary, $identity, $default, $comment);
    }

    public function getAddColumnStatement(): string
    {
        //don't add auto increment and primary keys yet
        return 'ADD COLUMN '.$this->getColumnDefinition(false);
    }

    public function getColumnDefinition(bool $identity = true): string
    {
        $statement = '`'.$this->getNewName().'` ';
        $statement .= $this->getColumnType($this->getType());
        $statement .= $this->getColumnLength($this->getType());
        $statement .= $this->getColumnCollate($this->getType());
        $statement .= $this->isNullable() === false ? ' NOT NULL' : ' NULL';
        $statement .= $identity === true && $this->isIdentity() === true ? ' AUTO_INCREMENT' : '';
        if ($this->getDefault() !== null) {
            $statement .= ' DEFAULT ';
            $statement .= Enum\DB\ColumnType::is_string($this->getType()) ? "'".$this->getDefault()."'" : $this->getDefault();
        }
        $statement .= $this->getComment() !== '' ? ' COMMENT \''.$this->getComment().'\'' : '';
        return $statement;
    }

    public function getDropColumnStatement(): string
    {
        return 'DROP COLUMN `'.$this->getName().'`';
    }

    public function getSchema(): string
    {
        $properties[ColumnArguments::NAME->value] = '\''.$this->getName().'\'';
        switch ($this->getType()) {
            case Enum\DB\ColumnType::BIGINT:
                if ($this->getLength() !== 20) {
                    $properties[ColumnArguments::LENGTH->value] = $this->getLength();
                }
                $properties[ColumnArguments::TYPE->value] = 'ColumnType::BIGINT';
                break;
            case Enum\DB\ColumnType::INT:
                if ($this->getLength() !== 11) {
                    $properties[ColumnArguments::LENGTH->value] = $this->getLength();
                }
                $properties[ColumnArguments::TYPE->value] = 'ColumnType::INT';
                break;
            case Enum\DB\ColumnType::TINYINT:
                if ($this->getLength() !== 4) {
                    if ($this->getLength() === 1) {
                        $properties[ColumnArguments::TYPE->value] = 'ColumnType::BOOL';
                    } else {
                        $properties[ColumnArguments::LENGTH->value] = $this->getLength();
                        $properties[ColumnArguments::TYPE->value] = 'ColumnType::TINYINT';
                    }
                } else {
                    $properties[ColumnArguments::TYPE->value] = 'ColumnType::TINYINT';
                }
                break;
            case Enum\DB\ColumnType::BOOL:
            case Enum\DB\ColumnType::BOOLEAN:
                $properties[ColumnArguments::TYPE->value] = 'ColumnType::BOOL';
                break;
            case Enum\DB\ColumnType::VARCHAR:
                $properties[ColumnArguments::TYPE->value] = 'ColumnType::VARCHAR';
                if ($this->getLength() === 'MAX' || $this->getLength() === -1) {
                    $properties[ColumnArguments::LENGTH->value] = "'MAX'";
                } else {
                    $properties[ColumnArguments::LENGTH->value] = $this->getLength();
                }
                break;
            case Enum\DB\ColumnType::NCHAR:
                $properties[ColumnArguments::TYPE->value] = 'ColumnType::NCHAR';
                if ($this->getLength() === 'MAX') {
                    $properties[ColumnArguments::LENGTH->value] = "'MAX'";
                } else {
                    $properties[ColumnArguments::LENGTH->value] = $this->getLength();
                }
                break;
            case Enum\DB\ColumnType::DATETIME:
                $properties[ColumnArguments::TYPE->value] = 'ColumnType::DATETIME';
                if (!empty($this->getLength())) {
                    $properties[ColumnArguments::LENGTH->value] = $this->getLength();
                }
                break;
            case Enum\DB\ColumnType::DATETIME2:
                $properties[ColumnArguments::TYPE->value] = 'ColumnType::DATETIME2';
                if (!empty($this->getLength())) {
                    $properties[ColumnArguments::LENGTH->value] = $this->getLength();
                }
                break;
            case Enum\DB\ColumnType::CHAR:
                $properties[ColumnArguments::TYPE->value] = 'ColumnType::CHAR';
                if ($this->getLength() === 'MAX' || $this->getLength() === -1) {
                    $properties[ColumnArguments::LENGTH->value] = "'MAX'";
                } else {
                    $properties[ColumnArguments::LENGTH->value] = $this->getLength();
                }
                break;
            case Enum\DB\ColumnType::DATE:
                $properties[ColumnArguments::TYPE->value] = 'ColumnType::DATE';
                if (!empty($this->getLength())) {
                    $properties[ColumnArguments::LENGTH->value] = $this->getLength();
                }
                break;
            case Enum\DB\ColumnType::FLOAT:
                $properties[ColumnArguments::TYPE->value] = 'ColumnType::FLOAT';
                break;
            case Enum\DB\ColumnType::BLOB:
                $properties[ColumnArguments::TYPE->value] = 'ColumnType::BLOB';
                break;
            case Enum\DB\ColumnType::DECIMAL:
                $properties[ColumnArguments::TYPE->value]   = 'ColumnType::DECIMAL';
                $properties[ColumnArguments::LENGTH->value] = '\''.$this->getLength().'\'';
                break;
            case Enum\DB\ColumnType::TIME:
                $properties[ColumnArguments::TYPE->value] = 'ColumnType::TIME';
                break;
            default:
                print 'Unknown Column Type in '.get_class($this).': '.$this->getType()->value.' (11100001)';
                exit;
        }
        // because we don't want to break existing schema uses, we have to implement the same defaults as in the Schema\Column
        if ($this->getType()->isNumeric()) {
            if ($this->isNullable() === true) {
                $properties[ColumnArguments::NULLABLE->value] = 'true';
            }
        } elseif ($this->isNullable() === false) {
            $properties[ColumnArguments::NULLABLE->value] = 'false';
        }
        if ($this->isPrimary() === true) {
            $properties[ColumnArguments::PRIMARY->value] = 'true';
        }
        if ($this->isIdentity() === true) {
            $properties[ColumnArguments::IDENTITY->value] = 'true';
        }
        if ($this->getDefault() !== null) {
            // a default with 0 is not mandatory for numeric columns since it's set as the default for non-nullable columns anyway
            if ($this->isNullable() === false) {
                if ($this->getType()->isNumeric()) {
                    if ($this->getDefault() !== 0) {
                        $properties[ColumnArguments::DEFAULT->value] = $this->getDefault();
                    }
                } else {
                    if ($this->getDefault() !== "''") {
                        $properties[ColumnArguments::DEFAULT->value] = $this->getDefault();
                    }
                }
            }
        }
        array_walk($properties, function (&$value, $key) {
            $value = $key.': '.$value;
        });
        return 'new Column('.implode(', ', $properties).')';
    }

    public function getUpdateColumnStatement(): string
    {
        return 'CHANGE `'.$this->getName().'` '.$this->getColumnDefinition();
    }

    private function getColumnCollate(Enum\DB\ColumnType $type): string
    {
        switch ($type) {
            case Enum\DB\ColumnType::CHAR:
            case Enum\DB\ColumnType::TINYTEXT:
            case Enum\DB\ColumnType::TEXT:
            case Enum\DB\ColumnType::MEDIUMTEXT:
            case Enum\DB\ColumnType::LONGTEXT:
            case Enum\DB\ColumnType::ENUM:
            case Enum\DB\ColumnType::SET:
            case Enum\DB\ColumnType::VARCHAR:
            case Enum\DB\ColumnType::BSID_VARCHAR:
            case Enum\DB\ColumnType::BSID_VARCHAR_MATCH:
                return ' COLLATE '.$this->collate;
            case Enum\DB\ColumnType::TINYINT:
            case Enum\DB\ColumnType::SMALLINT:
            case Enum\DB\ColumnType::MEDIUMINT:
            case Enum\DB\ColumnType::INT:
            case Enum\DB\ColumnType::INTEGER:
            case Enum\DB\ColumnType::BSID_INT:
            case Enum\DB\ColumnType::BSID_INT_MATCH:
            case Enum\DB\ColumnType::BIGINT:
            case Enum\DB\ColumnType::DECIMAL:
            case Enum\DB\ColumnType::BIT:
            case Enum\DB\ColumnType::BOOL:
            case Enum\DB\ColumnType::BOOLEAN:
            case Enum\DB\ColumnType::BINARY:
            case Enum\DB\ColumnType::VARBINARY:
            case Enum\DB\ColumnType::YEAR:
            case Enum\DB\ColumnType::FLOAT:
            case Enum\DB\ColumnType::REAL:
            case Enum\DB\ColumnType::DOUBLE:
            case Enum\DB\ColumnType::BLOB:
            case Enum\DB\ColumnType::DATE:
            case Enum\DB\ColumnType::DATETIME:
            case Enum\DB\ColumnType::GEOMETRY:
            case Enum\DB\ColumnType::GEOMETRYCOLLECTION:
            case Enum\DB\ColumnType::LINESTRING:
            case Enum\DB\ColumnType::LONGBLOB:
            case Enum\DB\ColumnType::MEDIUMBLOB:
            case Enum\DB\ColumnType::MULTILINESTRING:
            case Enum\DB\ColumnType::MULTIPOINT:
            case Enum\DB\ColumnType::MULTIPOLYGON:
            case Enum\DB\ColumnType::POINT:
            case Enum\DB\ColumnType::POLYGON:
            case Enum\DB\ColumnType::TIME:
            case Enum\DB\ColumnType::TIMESTAMP:
            case Enum\DB\ColumnType::TINYBLOB:
                return '';
            case Enum\DB\ColumnType::BIGINT_DATE:
            case Enum\DB\ColumnType::DATETIME2:
            case Enum\DB\ColumnType::DATETIMEOFFSET:
            case Enum\DB\ColumnType::NCHAR:
            case Enum\DB\ColumnType::NVARCHAR:
            case Enum\DB\ColumnType::SMALLDATETIME:
            case Enum\DB\ColumnType::UNSIGNED_BIGINT:
            case Enum\DB\ColumnType::UNSIGNED_INT:
            case Enum\DB\ColumnType::UNSIGNED_INTEGER:
            case Enum\DB\ColumnType::UNSIGNED_MEDIUMINT:
            case Enum\DB\ColumnType::UNSIGNED_SMALLINT:
            case Enum\DB\ColumnType::UNSIGNED_TINYINT:
            default:
                print 'Unknown Column Type in '.__METHOD__.': '.$this->getType()->value.' (11100002)';
                return '';
        }
    }

    private function getColumnLength(Enum\DB\ColumnType $type): string
    {
        switch ($type) {
            case Enum\DB\ColumnType::YEAR:
            case Enum\DB\ColumnType::TINYINT:
                return '('.($this->getLength() === null ? '4' : $this->getLength()).')';
            case Enum\DB\ColumnType::SMALLINT:
                return '('.($this->getLength() === null ? '6' : $this->getLength()).')';
            case Enum\DB\ColumnType::MEDIUMINT:
                return '('.($this->getLength() === null ? '9' : $this->getLength()).')';
            case Enum\DB\ColumnType::INT:
            case Enum\DB\ColumnType::INTEGER:
            case Enum\DB\ColumnType::BSID_INT:
            case Enum\DB\ColumnType::BSID_INT_MATCH:
                return '('.($this->getLength() === null ? '11' : $this->getLength()).')';
            case Enum\DB\ColumnType::BIGINT:
                return '('.($this->getLength() === null ? '20' : $this->getLength()).')';
            case Enum\DB\ColumnType::DECIMAL:
                return '('.($this->getLength() === null ? '10,0' : $this->getLength()).')';
            case Enum\DB\ColumnType::BIT:
                return '('.($this->getLength() === null ? '2' : $this->getLength()).')';
            case Enum\DB\ColumnType::BOOL:
            case Enum\DB\ColumnType::BOOLEAN:
                return '('.($this->getLength() === null ? '1' : $this->getLength()).')';
            case Enum\DB\ColumnType::CHAR:
            case Enum\DB\ColumnType::VARBINARY:
            case Enum\DB\ColumnType::BSID_VARCHAR_MATCH:
            case Enum\DB\ColumnType::BSID_VARCHAR:
            case Enum\DB\ColumnType::VARCHAR:
            case Enum\DB\ColumnType::BINARY:
                return '('.($this->getLength() === null ? '???' : $this->getLength()).')';
            case Enum\DB\ColumnType::FLOAT:
            case Enum\DB\ColumnType::REAL:
            case Enum\DB\ColumnType::DOUBLE:
            case Enum\DB\ColumnType::BLOB:
            case Enum\DB\ColumnType::DATE:
            case Enum\DB\ColumnType::DATETIME:
            case Enum\DB\ColumnType::ENUM:
            case Enum\DB\ColumnType::GEOMETRY:
            case Enum\DB\ColumnType::GEOMETRYCOLLECTION:
            case Enum\DB\ColumnType::LINESTRING:
            case Enum\DB\ColumnType::LONGBLOB:
            case Enum\DB\ColumnType::LONGTEXT:
            case Enum\DB\ColumnType::MEDIUMBLOB:
            case Enum\DB\ColumnType::MEDIUMTEXT:
            case Enum\DB\ColumnType::MULTILINESTRING:
            case Enum\DB\ColumnType::MULTIPOINT:
            case Enum\DB\ColumnType::MULTIPOLYGON:
            case Enum\DB\ColumnType::POINT:
            case Enum\DB\ColumnType::POLYGON:
            case Enum\DB\ColumnType::SET:
            case Enum\DB\ColumnType::TEXT:
            case Enum\DB\ColumnType::TIME:
            case Enum\DB\ColumnType::TIMESTAMP:
            case Enum\DB\ColumnType::TINYBLOB:
            case Enum\DB\ColumnType::TINYTEXT:
                return '';
            case Enum\DB\ColumnType::BIGINT_DATE:
            case Enum\DB\ColumnType::DATETIME2:
            case Enum\DB\ColumnType::DATETIMEOFFSET:
            case Enum\DB\ColumnType::NCHAR:
            case Enum\DB\ColumnType::NVARCHAR:
            case Enum\DB\ColumnType::SMALLDATETIME:
            case Enum\DB\ColumnType::UNSIGNED_BIGINT:
            case Enum\DB\ColumnType::UNSIGNED_INT:
            case Enum\DB\ColumnType::UNSIGNED_INTEGER:
            case Enum\DB\ColumnType::UNSIGNED_MEDIUMINT:
            case Enum\DB\ColumnType::UNSIGNED_SMALLINT:
            case Enum\DB\ColumnType::UNSIGNED_TINYINT:
            default:
                print 'Unknown Column Type in '.__METHOD__.': '.$this->getType()->value.' (11100003)';
                return '';
        }
    }

    private function getColumnType(Enum\DB\ColumnType $type): string
    {
        switch ($type) {
            case Enum\DB\ColumnType::BOOLEAN:
            case Enum\DB\ColumnType::BOOL:
            case Enum\DB\ColumnType::TINYINT:
                return 'tinyint';
            case Enum\DB\ColumnType::SMALLINT:
                return 'smallint';
            case Enum\DB\ColumnType::MEDIUMINT:
                return 'mediumint';
            case Enum\DB\ColumnType::INT:
            case Enum\DB\ColumnType::INTEGER:
            case Enum\DB\ColumnType::BSID_INT:
            case Enum\DB\ColumnType::BSID_INT_MATCH:
                return 'int';
            case Enum\DB\ColumnType::BIGINT:
                return 'bigint';
            case Enum\DB\ColumnType::DECIMAL:
                return 'decimal';
            case Enum\DB\ColumnType::FLOAT:
                return 'float';
            case Enum\DB\ColumnType::REAL:
            case Enum\DB\ColumnType::DOUBLE:
                return 'double';
            case Enum\DB\ColumnType::BIT:
                return 'bit';
            case Enum\DB\ColumnType::BINARY:
                return 'binary';
            case Enum\DB\ColumnType::BLOB:
                return 'blob';
            case Enum\DB\ColumnType::CHAR:
                return 'char';
            case Enum\DB\ColumnType::DATE:
                return 'date';
            case Enum\DB\ColumnType::DATETIME:
                return 'datetime';
            case Enum\DB\ColumnType::ENUM:
                return 'enum';
            case Enum\DB\ColumnType::GEOMETRY:
                return 'geometry';
            case Enum\DB\ColumnType::GEOMETRYCOLLECTION:
                return 'geometrycollection';
            case Enum\DB\ColumnType::LINESTRING:
                return 'linestring';
            case Enum\DB\ColumnType::LONGBLOB:
                return 'longblob';
            case Enum\DB\ColumnType::LONGTEXT:
                return 'longtext';
            case Enum\DB\ColumnType::MEDIUMBLOB:
                return 'mediumblob';
            case Enum\DB\ColumnType::MEDIUMTEXT:
                return 'mediumtext';
            case Enum\DB\ColumnType::MULTILINESTRING:
                return 'multilinestring';
            case Enum\DB\ColumnType::MULTIPOINT:
                return 'multipoint';
            case Enum\DB\ColumnType::MULTIPOLYGON:
                return 'multipolygon';
            case Enum\DB\ColumnType::POINT:
                return 'point';
            case Enum\DB\ColumnType::POLYGON:
                return 'polygon';
            case Enum\DB\ColumnType::SET:
                return 'set';
            case Enum\DB\ColumnType::TEXT:
                return 'text';
            case Enum\DB\ColumnType::TIME:
                return 'time';
            case Enum\DB\ColumnType::TIMESTAMP:
                return 'timestamp';
            case Enum\DB\ColumnType::TINYBLOB:
                return 'tinyblob';
            case Enum\DB\ColumnType::TINYTEXT:
                return 'tinytext';
            case Enum\DB\ColumnType::VARBINARY:
                return 'varbinary';
            case Enum\DB\ColumnType::YEAR:
                return 'year';
            case Enum\DB\ColumnType::VARCHAR:
            case Enum\DB\ColumnType::BSID_VARCHAR:
            case Enum\DB\ColumnType::BSID_VARCHAR_MATCH:
                return 'varchar';
            case Enum\DB\ColumnType::BIGINT_DATE:
            case Enum\DB\ColumnType::DATETIME2:
            case Enum\DB\ColumnType::DATETIMEOFFSET:
            case Enum\DB\ColumnType::NCHAR:
            case Enum\DB\ColumnType::NVARCHAR:
            case Enum\DB\ColumnType::SMALLDATETIME:
            case Enum\DB\ColumnType::UNSIGNED_BIGINT:
            case Enum\DB\ColumnType::UNSIGNED_INT:
            case Enum\DB\ColumnType::UNSIGNED_INTEGER:
            case Enum\DB\ColumnType::UNSIGNED_MEDIUMINT:
            case Enum\DB\ColumnType::UNSIGNED_SMALLINT:
            case Enum\DB\ColumnType::UNSIGNED_TINYINT:
            default:
                print 'Unknown Column Type in '.__METHOD__.': '.$this->getType()->value.' (11100004)';
                return '';
        }
    }

    public function getCollate(): string
    {
        return $this->collate;
    }

    public function setCollate(string $collate): static
    {
        $this->collate = $collate;
        return $this;
    }

    public function getUpdateColumnNullConstraint(): string
    {
        if ($this->isNullable() === false) {
            return ' ALTER COLUMN '.$this->getName().' SET NOT NULL';
        } else {
            return ' ALTER COLUMN '.$this->getName().' SET NULL ';
        }
    }

}
