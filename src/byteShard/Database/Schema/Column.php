<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Database\Schema;

use byteShard\Database\Enum\DefaultValue;
use byteShard\Database\Enum\RawDefault;
use byteShard\Enum;

/**
 * Class Column
 * @package byteShard\Database\Schema
 */
class Column
{
    private string                                  $comment         = '';
    private null|string|int|DefaultValue|RawDefault $default;
    private bool                                    $identity;
    private ?bool                                   $isNullable;
    private null|int|string                         $length;
    private string                                  $name;
    private string                                  $newName         = '';
    private bool                                    $primary;
    private Enum\DB\ColumnType                      $type;
    private ?string                                 $collate         = null;
    private string                                  $charset         = '';
    private string                                  $check           = '';
    private ?string                                 $onUpdate        = null;
    private ?string                                 $generatedAs     = null;
    private bool                                    $generatedStored = false;

    public function __construct(string $name, Enum\DB\ColumnType $type = Enum\DB\ColumnType::INT, int|string|null $length = null, ?bool $nullable = null, bool $primary = false, bool $identity = false, string|int|null|DefaultValue|RawDefault $default = null)
    {
        $this->type       = $type;
        $this->isNullable = $nullable;
        $this->name       = $name;
        $this->length     = $length;
        $this->primary    = $primary;
        $this->identity   = $identity;
        $this->default    = $default;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function getDefault(): int|string|null|DefaultValue|RawDefault
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

    public function getType(): Enum\DB\ColumnType
    {
        return $this->type;
    }

    public function isIdentity(): bool
    {
        return $this->identity;
    }

    public function isNullable(): ?bool
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

    public function setCollate(string $collate): static
    {
        $this->collate = $collate;
        return $this;
    }

    public function setCharacterSet(string $charset): static
    {
        $this->charset = $charset;
        return $this;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * Column level check constraint.
     * Pass the bare expression, e.g. setCheck('json_valid(`raw`)').
     * Surrounding parentheses are added automatically if missing.
     */
    public function setCheck(string $check): static
    {
        $this->check = $check;
        return $this;
    }

    public function getCheck(): string
    {
        return $this->check;
    }

    /**
     * e.g. setOnUpdate('current_timestamp()') results in
     * ... DEFAULT current_timestamp() ON UPDATE current_timestamp()
     */
    public function setOnUpdate(string $expression): static
    {
        $this->onUpdate = $expression;
        return $this;
    }

    public function getOnUpdate(): ?string
    {
        return $this->onUpdate;
    }

    /**
     * Generated (computed) column, e.g.
     * setGeneratedAs("if(`status` = 'OPEN',1,NULL)") results in
     * ... GENERATED ALWAYS AS (if(`status` = 'OPEN',1,NULL)) VIRTUAL
     * Pass $stored = true for a STORED/PERSISTENT column.
     * Generated columns cannot have NOT NULL, DEFAULT, AUTO_INCREMENT or ON UPDATE.
     */
    public function setGeneratedAs(string $expression, bool $stored = false): static
    {
        $this->generatedAs     = $expression;
        $this->generatedStored = $stored;
        return $this;
    }

    public function getGeneratedAs(): ?string
    {
        return $this->generatedAs;
    }

    public function isGeneratedStored(): bool
    {
        return $this->generatedStored;
    }
}