<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema;

use byteShard\Enum;
use byteShard\Exception;

/**
 * Class ColumnParent
 * @package byteShard\Internal\Database\Schema
 */
abstract class ColumnParent implements ColumnManagementInterface
{
    private string          $comment;
    private string|int|null $default;
    private bool            $identity;
    private bool            $isNullable;
    private int|string|null $length = 0;
    private string          $name;
    private string          $newName;
    private bool            $primary;
    private string          $type;

    public function __construct(string $name, string $newName = '', string $type = Enum\DB\ColumnType::INT, int|string $length = null, bool $isNullable = true, bool $primary = false, bool $identity = false, string|int|null $default = null, string $comment = '')
    {
        if (Enum\DB\ColumnType::is_enum($type)) {
            $this->type = $type;
        } else {
            throw new Exception(__METHOD__.": Method only accepts enums of type Enum\\DB\\ColumnType. Input was '".gettype($type)."'");
        }
        $this->name       = $name;
        $this->newName    = $newName;
        $this->length     = $length;
        $this->isNullable = $isNullable;
        $this->primary    = $primary;
        $this->identity   = $identity;
        $this->default    = $default;
        $this->comment    = $comment;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function getDefault(): int|string|null
    {
        return $this->default;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNewName(): string
    {
        if ($this->newName === '') {
            return $this->name;
        }
        return $this->newName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLength(): int|string|null
    {
        return $this->length;
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function isIdentity(): bool
    {
        return $this->identity;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function isNotIdenticalTo(ColumnManagementInterface $column): bool
    {
        $notIdentical = 0;
        if ($column->isIdentity() !== $this->isIdentity()) {
            $notIdentical |= (1 << 0);
        }
        if ($column->isNullable() !== $this->isNullable()) {
            $notIdentical |= (1 << 1);
        }
        if ($column->getType() !== $this->getType()) {
            $notIdentical |= (1 << 2);
        }
        if ($column->getLength() !== $this->getLength()) {
            $notIdentical |= (1 << 3);
        }
        if ($column->getDefault() != $this->getDefault()) {
            $notIdentical |= (1 << 4);
        }
        if ($column->isPrimary() != $this->isPrimary()) {
            $notIdentical |= (1 << 5);
        }
        return $notIdentical > 0;
    }

    abstract public function getColumnDefinition(): string;
}
