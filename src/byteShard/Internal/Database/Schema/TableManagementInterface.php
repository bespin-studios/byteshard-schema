<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema;

interface TableManagementInterface
{
    public function addColumn(ColumnParent ...$columns): void;

    public function getAddColumnStatement(ColumnManagementInterface $columnToAdd): string;

    /**
     * @param string $primaryKeyName
     * @param array<ColumnManagementInterface> $primaryKeyColumns
     * @param ColumnManagementInterface $addIdentityColumn
     * @param ColumnManagementInterface|null $changeIdentityColumn
     * @param array<ColumnManagementInterface>|null $autoIncrementColumns
     * @param ColumnManagementInterface|null $addAutoIncrementColumn
     * @return string
     */
    public function getAddIdentityStatement(string $primaryKeyName, array $primaryKeyColumns, ColumnManagementInterface $addIdentityColumn, ?ColumnManagementInterface $changeIdentityColumn = null, ?array $autoIncrementColumns = null, ?ColumnManagementInterface $addAutoIncrementColumn = null): string;

    public function getAddIndexStatement(IndexManagementInterface $index): string;

    /**
     * @return array<ColumnManagementInterface>
     */
    public function getColumns(): array;

    public function getComment(): string;

    public function getCreateTableStatement(): string;

    public function getDropColumnStatement(ColumnManagementInterface $column): string;

    /**
     * @param string $primaryKeyName
     * @param array<ColumnManagementInterface> $primaryKeyColumns
     * @param ColumnManagementInterface $dropIdentityColumn
     * @param array<ColumnManagementInterface>|null $autoIncrementColumns
     * @param ColumnManagementInterface|null $dropAutoIncrementColumn
     * @return string
     */
    public function getDropIdentityStatement(string $primaryKeyName, array $primaryKeyColumns, ColumnManagementInterface $dropIdentityColumn, ?array $autoIncrementColumns = null, ?ColumnManagementInterface $dropAutoIncrementColumn = null): string;

    public function getDropIndexStatement(IndexManagementInterface $index): string;

    /**
     * @return array<IndexManagementInterface>
     */
    public function getIndices(): array;

    /**
     * @param string $primaryKeyName
     * @param array<ColumnManagementInterface> $primaryKeyColumns
     * @param ColumnManagementInterface $currentIdentityColumn
     * @param ColumnManagementInterface $targetIdentityColumn
     * @return string
     */
    public function getMoveIdentityStatement(string $primaryKeyName, array $primaryKeyColumns, ColumnManagementInterface $currentIdentityColumn, ColumnManagementInterface $targetIdentityColumn): string;

    public function getName(): string;

    /**
     * @return array<ColumnManagementInterface>
     */
    public function getPrimaryKeyColumns(): array;

    /**
     * @return array<string, string>
     */
    public function getAutoIncrementColumns(): array;

    /**
     * @param string $primaryKeyName
     * @param array<ColumnManagementInterface> $primaryKeyColumns
     * @param ?ColumnManagementInterface $targetSchemaIdentityColumn
     * @return string
     */
    public function getRecreatePrimaryKeyStatement(string $primaryKeyName, array $primaryKeyColumns, ?ColumnManagementInterface $targetSchemaIdentityColumn = null): string;

    /**
     * @return array<string>
     */
    public function getUpdateColumnStatements(ColumnManagementInterface $column): array;

    public function getUpdateTableCommentStatement(): string;

    public function setComment(string $comment): static;

    public function setIndices(IndexManagementInterface ...$indices): void;

    public function setCollate(string $collate): static;

    public function getCollate(): string;

    public function setDefaultCharset(string $charset): static;

    public function getDefaultCharset(): string;

    /**
     * @return array<string, ForeignKeyInterface>
     */
    public function getForeignKeys(): array;

    public function setForeignKeys(ForeignKeyInterface ...$foreignKeys): void;

    public function getDropForeignKeyStatement(ForeignKeyInterface $foreignKey): string;

    public function getAddForeignKeyStatement(ForeignKeyInterface $foreignKey): string;
}
