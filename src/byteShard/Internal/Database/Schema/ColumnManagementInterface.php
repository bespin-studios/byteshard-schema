<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema;

use byteShard\Enum\DB\ColumnType;

interface ColumnManagementInterface
{
    public function __toString(): string;

    public function getAddColumnStatement(): string;

    public function getColumnDefinition(): string;

    public function getDefault(): int|string|null;

    public function getDropColumnStatement(): string;

    public function getLength(): int|string|null;

    public function getName(): string;

    public function getNewName(): string;

    public function getType(): ColumnType;

    public function getUpdateColumnStatement(): string;

    public function isIdentity(): bool;

    public function isNotIdenticalTo(ColumnManagementInterface $column): bool;

    public function isNullable(): bool;

    public function isPrimary(): bool;

    public function getSchema(): string;

    public function getCollate(): string;

    public function setCollate(string $collate): static;

    public function getUpdateColumnNullConstraint(): string;
}
