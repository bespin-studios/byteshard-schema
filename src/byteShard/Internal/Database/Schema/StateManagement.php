<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema;

use byteShard\Database;
use byteShard\Database\Schema\State;
use byteShard\Database\Schema\Statement;
use byteShard\Exception;
use byteShard\Internal\Config;

abstract class StateManagement implements StateManagementInterface
{
    protected DBManagementInterface $dbManagement;
    protected string                $dbSchemaStateType      = 'bs_schema';
    protected string                $dbSchemaStateAppType   = 'app_schema';
    protected string                $dbSchemaStateValue     = 'version_identifier';
    protected string                $dbSchemaInitialVersion = 'v0.0.0';
    private string                  $dbCompletedField       = 'done';
    protected bool                  $dryRun                 = false;

    /**
     * @var array<string>
     */
    protected array  $dryRunCommands = [];
    protected State  $state;
    protected string $version;
    protected Config $config;

    public function __construct(DBManagementInterface $dbManagement, State $state)
    {
        $this->dbManagement = $dbManagement;
        $this->state        = $state;
        if (class_exists('\config')) {
            $config = new \config();
            if ($config instanceof Config) {
                $this->config = $config;
            }
        }
    }

    /**
     * display the database changes that will be applied instead of applying them
     */
    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    /**
     * @param array<IndexManagementInterface> $currentSchemaIndices
     * @param array<IndexManagementInterface> $targetSchemaIndices
     * @param TableManagementInterface $table
     */
    protected function ensureIndices(array $currentSchemaIndices, array $targetSchemaIndices, TableManagementInterface $table): void
    {
        foreach ($currentSchemaIndices as $indexName => $currentSchemaIndex) {
            if (array_key_exists($indexName, $targetSchemaIndices) === false) {
                $this->dbManagement->execute($table->getDropIndexStatement($currentSchemaIndex));
            } elseif ($currentSchemaIndex->getIndexColumns() !== $targetSchemaIndices[$indexName]->getIndexColumns()) {
                $this->dbManagement->execute($table->getDropIndexStatement($currentSchemaIndex));
                $this->dbManagement->execute($table->getAddIndexStatement($targetSchemaIndices[$indexName]));
            }
        }
        foreach ($targetSchemaIndices as $indexName => $targetSchemaIndex) {
            if (array_key_exists($indexName, $currentSchemaIndices) === false) {
                $this->dbManagement->execute($table->getAddIndexStatement($targetSchemaIndex));
            }
        }
    }

    /**
     * @param array<ColumnManagementInterface> $currentSchemaColumns
     * @param array<ColumnManagementInterface> $targetSchemaColumns
     * @param TableManagementInterface $table
     */
    protected function ensureColumnsDelete(array $currentSchemaColumns, array $targetSchemaColumns, TableManagementInterface $table): void
    {
        foreach ($currentSchemaColumns as $currentSchemaColumnName => $currentSchemaColumn) {
            if (array_key_exists($currentSchemaColumnName, $targetSchemaColumns) === false) {
                $this->dbManagement->execute($table->getDropColumnStatement($currentSchemaColumn));
            }
        }
    }

    protected function addComment(string $comment): void
    {
        if ($this->dryRun === true) {
            $this->dryRunCommands[] = '/*'.$comment.'*/';
        }
    }

    /**
     * @throws Exception
     */
    protected function isStatementCompleted(Statement $statement): bool
    {
        if ($this->dbManagement->tableExists($this->dbSchemaStateType)) {
            $tmp = Database::getSingle('SELECT '.$this->dbCompletedField.' FROM '.$this->dbSchemaStateType." WHERE value='".$statement->getName()."' AND version='".$this->version."' AND type='statement'");
            if ($tmp === null) {
                return false;
            }
            return 1 === (int)$tmp->{$this->dbCompletedField};
        } else {
            return false;
        }
    }
}
