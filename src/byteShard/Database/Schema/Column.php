<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Database\Schema;

use byteShard\Enum;
use byteShard\Exception;

/**
 * Class Column
 * @package byteShard\Database\Schema
 */
class Column
{
    private string          $comment = '';
    private null|string|int $default;
    private bool            $identity;
    private bool            $isNullable;
    private null|int|string $length;
    private string          $name;
    private string          $newName = '';
    private bool            $primary;
    private string          $type;
    private ?string         $collate = null;

    /**
     * @throws Exception
     */
    public function __construct(string $name, string $type = Enum\DB\ColumnType::INT, int|string $length = null, bool $is_nullable = null, bool $primary = false, bool $identity = false, string|int $default = null)
    {
        if (Enum\DB\ColumnType::is_enum($type)) {
            $this->type = $type;
        } else {
            throw new Exception(__METHOD__.": Method only accepts enums of type Enum\\DB\\ColumnType. Input was '".gettype($type)."'");
        }
        if ($is_nullable === null) {
            if (Enum\DB\ColumnType::is_numeric($type)) {
                $this->isNullable = false;
            } else {
                $this->isNullable = true;
            }
        } else {
            $this->isNullable = $is_nullable;
        }
        $this->name     = $name;
        $this->length   = $length;
        $this->primary  = $primary;
        $this->identity = $identity;
        $this->default  = $default;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function getDefault(): int|string|null
    {
        return $this->default;
    }

    public function getLength(): null|int|string
    {
        return $this->length;
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

    public function isIdentity(): bool
    {
        return $this->identity;
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function setComment(string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function setNewName(string $name): static
    {
        $this->newName = $name;
        return $this;
    }

    public function getCollate(): ?string
    {
        return $this->collate;
    }

    public function setCollate(string $collate): self
    {
        $this->collate = $collate;
        return $this;
    }
}
