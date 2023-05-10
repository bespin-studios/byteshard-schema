<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema\PGSQL;

use byteShard\Enum;
use byteShard\Exception;
use byteShard\Internal\Database\Schema\ColumnParent;

class Column extends ColumnParent
{
    private string $collate = 'en_US.utf8';

    public function __construct(string $name, string $newName = '', string $type = Enum\DB\ColumnType::INT, int|string $length = null, bool $isNullable = true, bool $primary = false, bool $identity = false, string|int|null $default = null, string $comment = '')
    {
        switch ($type) {
            case Enum\DB\ColumnType::SMALLINT:
            case Enum\DB\ColumnType::TINYINT:
                $type = Enum\DB\ColumnType::SMALLINT;
                break;
            case Enum\DB\ColumnType::INT:
            case Enum\DB\ColumnType::INTEGER:
                $type = Enum\DB\ColumnType::INTEGER;
                if ($length === null) {
                    $length = 4;
                }
                break;
            case Enum\DB\ColumnType::TIME:
            case Enum\DB\ColumnType::BIGINT:
                if ($length === null) {
                    $length = 8;
                }
                break;
            case Enum\DB\ColumnType::DATE:
            case Enum\DB\ColumnType::REAL:
                if ($length === null) {
                    $length = 4;
                }
                break;
            case Enum\DB\ColumnType::DATETIME:
            case Enum\DB\ColumnType::TIMESTAMP:
                $type = Enum\DB\ColumnType::TIMESTAMP;
                if ($length === null) {
                    $length = 6;
                }
                break;
            case Enum\DB\ColumnType::BLOB:
            case Enum\DB\ColumnType::BINARY:
                $type = Enum\DB\ColumnType::BYTEA;
                break;
        }
        parent::__construct(strtolower($name), strtolower($newName), $type, $length, $isNullable, $primary, $identity, $default, $comment);
    }

    public function getAddColumnStatement(): string
    {
        //don't add auto increment and primary keys yet
        return 'ADD COLUMN  '.$this->getColumnDefinition(false);
    }

    public function getColumnDefinition(bool $identity = true, bool $update = false): string
    {
        $statement            = '"'.$this->getNewName().'"'; // quote column name to create a table with reserved keywords
        $statements           = [];
        $columnType           = $this->getColumnType($this->getType());
        $columnLength         = $this->getColumnLength($this->getType());
        $columnTypeDefinition = empty($columnType) ? '' : trim($columnType);
        $columnTypeDefinition .= empty($columnLength) ? '' : ' '.trim($columnLength);
        if ($update === false) {
            $statements[] = $columnTypeDefinition;
            $statements[] = $this->isNullable() === false ? 'NOT NULL' : 'NULL';
        } else {
            $statements[] = 'TYPE '.$columnTypeDefinition;
            $statements[] = $this->isNullable() === false ? 'SET NOT NULL' : 'DROP NOT NULL';
        }
        $statements[] = $identity === true && $this->isIdentity() === true ? 'GENERATED ALWAYS AS IDENTITY' : '';

        if ($this->getDefault() !== null) {
            if ($update === true) {
                $prefix = 'SET DEFAULT ';
            } else {
                $prefix = 'DEFAULT ';
            }

            $statements[] = $prefix.(Enum\DB\ColumnType::is_string($this->getType()) ? '\''.$this->getDefault().'\' ' : $this->getDefault());
        } else {
            $statements[] = $update === true ? 'DROP DEFAULT' : '';
        }
        $statements[] = $this->getComment() !== '' ? ' COMMENT \''.$this->getComment().'\'' : '';
        $statements   = array_filter($statements);
        if ($update === true) {
            return $statement.' '.implode(', ALTER COLUMN '.$statement.' ', $statements);
        } else {
            return $statement.' '.implode(' ', $statements);
        }
    }

    public function getDropColumnStatement(): string
    {
        return 'DROP COLUMN "'.$this->getName().'"';
    }

    public function getSchema(int $length = 0): string
    {
        $optionalLength   = false;
        $optionalNullable = false;
        $length           = $length - strlen($this->getName());
        $schema           = '    new Column('."'".$this->getName()."'".str_repeat(' ', $length).',';

        switch ($this->getType()) {
            case Enum\DB\ColumnType::SMALLINT:
            case Enum\DB\ColumnType::TINYINT:
                $schema .= 'Enum\DB\ColumnType::SMALLINT';
                break;
            case Enum\DB\ColumnType::BOOLEAN:
            case Enum\DB\ColumnType::BIT:
                $schema .= 'Enum\DB\ColumnType::BOOLEAN';
                break;
            case Enum\DB\ColumnType::INTEGER:
            case Enum\DB\ColumnType::INT:
                $schema .= 'Enum\DB\ColumnType::INTEGER';
                break;
            case Enum\DB\ColumnType::BIGINT:
                $schema .= 'Enum\DB\ColumnType::BIGINT';
                break;
            case Enum\DB\ColumnType::BLOB:
            case Enum\DB\ColumnType::BINARY:
            case Enum\DB\ColumnType::BYTEA:
                $schema .= 'Enum\DB\ColumnType::BYTEA';
                break;
            case Enum\DB\ColumnType::REAL:
            case Enum\DB\ColumnType::FLOAT:
                $schema .= 'Enum\DB\ColumnType::REAL';
                break;
            case Enum\DB\ColumnType::DECIMAL:
                $schema .= 'Enum\DB\ColumnType::DECIMAL';
                if ($this->getLength() === 'MAX' || $this->getLength() === -1) {
                    $schema .= "'MAX'";
                } else {
                    $schema .= $this->getLength();
                }
                $optionalLength = true;
                break;
            case Enum\DB\ColumnType::VARCHAR:
            case Enum\DB\ColumnType::CHARACTER_VARYING:
                $schema .= 'Enum\DB\ColumnType::VARCHAR ';
                if ($this->getLength() === 'MAX' || $this->getLength() === -1) {
                    $schema .= "'MAX'";
                } else {
                    $schema .= ($this->getLength() === null) ? ', null ' : (', '.$this->getLength());
                }
                $optionalLength = true;
                break;
            case Enum\DB\ColumnType::CHAR:
                $schema .= 'Enum\DB\ColumnType::CHAR ';
                if ($this->getLength() === 'MAX' || $this->getLength() === -1) {
                    $schema .= "'MAX'";
                } else {
                    $schema .= $this->getLength();
                }
                $optionalLength = true;
                break;
            case Enum\DB\ColumnType::TEXT:
                $schema .= 'Enum\DB\ColumnType::TEXT, ';
                if ($this->getLength() === 'MAX') {
                    $schema .= "'MAX'";
                } else {
                    $schema .= $this->getLength();
                }
                $optionalLength = true;
                break;
            case Enum\DB\ColumnType::DATETIME:
            case Enum\DB\ColumnType::TIMESTAMP:
                $schema .= 'Enum\DB\ColumnType::TIMESTAMP';
                break;
            case Enum\DB\ColumnType::DATE:
                $schema .= 'Enum\DB\ColumnType::DATE';
                break;
            case Enum\DB\ColumnType::TIME:
                $schema .= 'Enum\DB\ColumnType::TIME';
                break;
            default:
                print 'Unknown Column Type in '.get_class($this).': '.$this->getType();
                exit;
        }
        if ($this->isNullable() === false) {
            if ($optionalLength === false) {
                $optionalLength = true;
                $schema         .= ',null';
            }
            $optionalNullable = true;
            $schema           .= ',false';
        } else {
            if ($optionalLength === false) {
                $schema .= ',null';
            }
            $schema .= ',true';
        }
        if ($this->isPrimary() === true) {
            if ($optionalNullable === false) {
                if ($optionalLength === false) {
                    $optionalLength = true;
                    $schema         .= ',null';
                }
                $schema           .= ',true';
                $optionalNullable = true;
            }
            $schema .= ',true';
        } else {
            $schema .= ',false';
        }
        if ($this->isIdentity() === true) {
            if ($optionalNullable === false) {
                if ($optionalLength === false) {
                    $schema .= ',null';
                }
                $schema .= ',true';
            }
            $schema .= ',true';
        } else {
            $schema .= ',false';
        }
        if ($this->getDefault() !== null) {
            if ($this->getDefault() === '') {
                $schema .= ', "'.$this->getDefault().'"';
            } else {
                $schema .= ', '.$this->getDefault();
            }
        } else {
            $schema .= ',null';
        }
        $schema .= '),';
        return $schema;
    }

    /**
     * function to convert column names to lowercase
     */
    public function getName(): string
    {
        return strtolower(parent::getName());
    }

    public function getUpdateColumnStatement(): string
    {
        return 'ALTER COLUMN '.$this->getColumnDefinition(true, true);
    }

    public function getUpdateColumnNullConstraint(): string
    {
        if ($this->isNullable() === false) {
            return ' ALTER COLUMN '.$this->getName().' SET NOT NULL';
        } else {
            return ' ALTER COLUMN '.$this->getName().' SET NULL ';
        }
    }

    private function getColumnLength(string $type): ?string
    {
        switch ($type) {
            case Enum\DB\ColumnType::TIME:
            case Enum\DB\ColumnType::DATE:
            case Enum\DB\ColumnType::BYTEA:
            case Enum\DB\ColumnType::BINARY:
            case Enum\DB\ColumnType::BLOB:
            case Enum\DB\ColumnType::REAL:
            case Enum\DB\ColumnType::TINYINT:
            case Enum\DB\ColumnType::BOOLEAN:
            case Enum\DB\ColumnType::BOOL:
            case Enum\DB\ColumnType::BIT:
            case Enum\DB\ColumnType::SMALLINT:
            case Enum\DB\ColumnType::INT:
            case Enum\DB\ColumnType::INTEGER:
            case Enum\DB\ColumnType::BIGINT:
            case Enum\DB\ColumnType::FLOAT:
            case Enum\DB\ColumnType::DOUBLE:
                break;
            case Enum\DB\ColumnType::TEXT:
            case Enum\DB\ColumnType::VARCHAR:
            case Enum\DB\ColumnType::CHAR:
            case Enum\DB\ColumnType::DECIMAL:
                return $this->getLength() === null ? '' : '('.$this->getLength().')';
            case Enum\DB\ColumnType::DATETIME:
            case Enum\DB\ColumnType::TSQUERY:
            case Enum\DB\ColumnType::TIMESTAMP:
            case Enum\DB\ColumnType::MONEY:
            case Enum\DB\ColumnType::POINT:
            case Enum\DB\ColumnType::BOX:
            case Enum\DB\ColumnType::CIRCLE:
            case Enum\DB\ColumnType::PATHS:
            case Enum\DB\ColumnType::LINESTRING:
            case Enum\DB\ColumnType::POLYGON:
            case Enum\DB\ColumnType::MACADDR:
            case Enum\DB\ColumnType::INET:
            case Enum\DB\ColumnType::CIDR:
            case Enum\DB\ColumnType::UUID:
            case Enum\DB\ColumnType::TSVECTOR:
                return '';
            default:
                print 'Unknown Column Type in '.__METHOD__.': '.$this->getType();
                return '';
        }
        return null;
    }

    private function getColumnType(string $type): string
    {
        switch ($type) {
            case Enum\DB\ColumnType::BOOLEAN:
            case Enum\DB\ColumnType::BOOL:
            case Enum\DB\ColumnType::BIT:
                return 'boolean';
            case Enum\DB\ColumnType::TINYINT:
            case Enum\DB\ColumnType::SMALLINT:
                return 'smallint';
            case Enum\DB\ColumnType::INTEGER:
            case Enum\DB\ColumnType::INT:
                return 'integer';
            case Enum\DB\ColumnType::BIGINT:
                return 'bigint';
            case Enum\DB\ColumnType::FLOAT:
                return 'real';
            case Enum\DB\ColumnType::DECIMAL:
                return 'numeric';
            case Enum\DB\ColumnType::DOUBLE:
                return 'double precision';
            case Enum\DB\ColumnType::BLOB:
            case Enum\DB\ColumnType::BINARY:
            case Enum\DB\ColumnType::BYTEA:
                return 'bytea';
            case Enum\DB\ColumnType::CHAR:
                return 'character';
            case Enum\DB\ColumnType::VARCHAR:
                return 'varchar';
            case Enum\DB\ColumnType::TEXT:
                return 'text';
            case Enum\DB\ColumnType::DATE:
                return 'date';
            case Enum\DB\ColumnType::TIME:
                return 'time';
            case Enum\DB\ColumnType::DATETIME:
            case Enum\DB\ColumnType::TIMESTAMP:
                return 'timestamp';
            case Enum\DB\ColumnType::POINT:
                return 'point';
            case Enum\DB\ColumnType::LINESTRING:
                return 'lines';
            case Enum\DB\ColumnType::BOX:
                return 'box';
            case Enum\DB\ColumnType::CIRCLE:
                return 'circle';
            case Enum\DB\ColumnType::PATHS:
                return 'paths';
            case Enum\DB\ColumnType::POLYGON:
                return 'polygon';
            case Enum\DB\ColumnType::MONEY:
                return 'money';
            case Enum\DB\ColumnType::CIDR:
                return 'cidr';
            case Enum\DB\ColumnType::MACADDR:
                return 'macaddr';
            case Enum\DB\ColumnType::INET:
                return 'inet';
            case Enum\DB\ColumnType::UUID:
                return 'uuid';
            case Enum\DB\ColumnType::TSQUERY:
                return 'tsquery';
            case Enum\DB\ColumnType::TSVECTOR:
                return 'tsvector';
            default:
                print 'Unknown Column Type in '.__METHOD__.': '.$this->getType();
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
}