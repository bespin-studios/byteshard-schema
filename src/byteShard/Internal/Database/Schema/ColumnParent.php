<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema;

use byteShard\Database\Enum\DefaultValue;
use byteShard\Database\Enum\RawDefault;
use byteShard\Enum;

/**
 * Class ColumnParent
 * @package byteShard\Internal\Database\Schema
 */
abstract class ColumnParent implements ColumnManagementInterface
{
    private string                                     $comment;
    private string|int|null|DefaultValue|RawDefault    $default;
    private bool                                       $identity;
    private bool                                       $isNullable;
    private int|string|null                            $length;
    private string                                     $name;
    private string                                     $newName;
    private bool                                       $primary;
    private Enum\DB\ColumnType                         $type;

    public function __construct(string $name, string $newName = '', Enum\DB\ColumnType $type = Enum\DB\ColumnType::INT, int|string|null $length = null, bool $isNullable = true, bool $primary = false, bool $identity = false, string|int|null|DefaultValue|RawDefault $default = null, string $comment = '')
    {
        $this->type       = $type;
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

    public function getDefault(): int|string|null|DefaultValue|RawDefault
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

    public function getType(): Enum\DB\ColumnType
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
        if ($this->normalizeDefault($column->getDefault()) !== $this->normalizeDefault($this->getDefault())) {
            $notIdentical |= (1 << 4);
        }
        if ($column->isPrimary() !== $this->isPrimary()) {
            $notIdentical |= (1 << 5);
        }
        return $notIdentical > 0;
    }

    /**
     * map defaults to a scalar so they can be compared with !==
     * (two RawDefault instances with the same expression must be considered equal)
     */
    protected function normalizeDefault(int|string|null|DefaultValue|RawDefault $default): int|string|null
    {
        if ($default instanceof DefaultValue) {
            return 'fn:'.$default->value;
        }
        if ($default instanceof RawDefault) {
            return 'raw:'.strtolower(str_replace([' ', '`'], '', $default->getExpression()));
        }
        return $default;
    }

    abstract public function getColumnDefinition(): string;
}