<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Database\Schema;

/**
 * Class Table
 * @package byteShard\Database\Schema
 */
class Table
{

    /**
     * @var array<Column>
     */
    private array  $columns = [];
    private string $comment = '';

    /**
     * @var array<Index>
     */
    private array   $indices = [];
    private string  $name;
    private ?string $collate = null;
    private ?string $charset = null;
    /**
     * @var array<string, ForeignKey>
     */
    private array $foreignKeys = [];

    public function __construct(string $tableName, Column ...$columns)
    {
        $this->name = $tableName;
        foreach ($columns as $column) {
            $this->columns[$column->getName()] = $column;
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function setComment(string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setIndices(Index ...$indices): void
    {
        foreach ($indices as $index) {
            $this->indices[$index->getName()] = $index;
        }
    }

    /**
     * @return array<Index>
     */
    public function getIndices(): array
    {
        return $this->indices;
    }

    public function setForeignKeys(ForeignKey ...$foreignKeys): void
    {
        foreach ($foreignKeys as $value) {
            $this->foreignKeys[$value->getSourceColumn()] = $value;
        }
    }

    /**
     * @return array<string, ForeignKey>
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
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

    public function getCharset(): ?string
    {
        return $this->charset;
    }

    public function setCharset(string $charset): static
    {
        $this->charset = $charset;
        return $this;
    }
}
