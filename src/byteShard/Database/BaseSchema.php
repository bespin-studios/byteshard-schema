<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Database;

use byteShard\Authentication\Enum\Target;
use byteShard\Database\Schema\Column;
use byteShard\Database\Schema\State;
use byteShard\Database\Schema\Table;
use byteShard\Enum;
use byteShard\Internal\Schema\DB\UserTable;

class BaseSchema
{
    private string    $dbSchemaTable   = 'bs_schema';
    private string    $dbSchemaId      = 'id';
    private string    $dbSchemaType    = 'type';
    private string    $dbSchemaValue   = 'value';
    private string    $dbSchemaVersion = 'version';
    private string    $dbSchemaDone    = 'done';
    private UserTable $userTableSchema;
    private Target    $authenticationTarget;

    public function __construct(UserTable $userTableSchema, Target $authenticationTarget)
    {
        $this->userTableSchema      = $userTableSchema;
        $this->authenticationTarget = $authenticationTarget;
    }

    /**
     * @return Array<string>
     */
    public function getBaseSchemaVersions(): array
    {
        return ['1.0.0', '1.0.1', '1.0.2'];
    }

    /**
     * @param State $state
     * @param string $version
     */
    public function getBaseSchema(State $state, string $version): void
    {
        switch ($version) {
            case '1.0.0':
                $state->addTable(
                    new Table(
                        $this->dbSchemaTable,
                        new Column(name: $this->dbSchemaId, type: Enum\DB\ColumnType::INT, nullable:false, primary:true, identity: true),
                        new Column($this->dbSchemaType, Enum\DB\ColumnType::VARCHAR, 256),
                        new Column($this->dbSchemaValue, Enum\DB\ColumnType::VARCHAR, 256),
                        new Column($this->dbSchemaVersion, Enum\DB\ColumnType::VARCHAR, 256),
                        new Column($this->dbSchemaDone, Enum\DB\ColumnType::TINYINT, 4, true)
                    )
                );
                $state->addTable(
                    new Table(
                        'tbl_User_Settings',
                        new Column('Tab', Enum\DB\ColumnType::VARCHAR, 64, false, true, false, ''),
                        new Column('Cell', Enum\DB\ColumnType::VARCHAR, 64, false, true, false, ''),
                        new Column('Type', Enum\DB\ColumnType::VARCHAR, 64, false, true, false, ''),
                        new Column('Item', Enum\DB\ColumnType::VARCHAR, 64, false, true, false, ''),
                        new Column('Value', Enum\DB\ColumnType::VARCHAR, 64, true),
                        new Column('User_ID', Enum\DB\ColumnType::INT, null, false, true)
                    )
                );

                // User Table
                $userTable[] = new Column($this->userTableSchema->getFieldNameUserId(), $this->userTableSchema->getFieldTypeUserIdEnum(), $this->userTableSchema->getFieldTypeUserIdEnum() === Enum\DB\ColumnType::VARCHAR ? 256 : null, false, true, true);
                $userTable[] = new Column($this->userTableSchema->getFieldNameUsername(), $this->userTableSchema->getFieldTypeUsernameEnum(), $this->userTableSchema->getFieldTypeUsernameEnum() === Enum\DB\ColumnType::VARCHAR ? 256 : null, true);
                if ($this->userTableSchema->getFieldNameAccessControlTarget() !== '') {
                    $userTable[] = new Column($this->userTableSchema->getFieldNameAccessControlTarget(), Enum\DB\ColumnType::VARCHAR, 8, false);
                }
                $userTable[] = new Column($this->userTableSchema->getFieldNameGrantLogin(), Enum\DB\ColumnType::TINYINT, 1, false, false, false, 0);
                $userTable[] = new Column($this->userTableSchema->getFieldNameAuthenticationTarget(), Enum\DB\ColumnType::VARCHAR, 16, true);
                switch ($this->authenticationTarget) {
                    case Target::AUTH_TARGET_DEFINED_ON_DB:
                    case Target::AUTH_TARGET_DB:
                        $userTable[] = new Column($this->userTableSchema->getFieldNameLocalPassword(), Enum\DB\ColumnType::VARCHAR, 256, false);
                        break;
                }
                $userTable[] = new Column($this->userTableSchema->getFieldNameServiceAccount(), Enum\DB\ColumnType::TINYINT, 1, false, false, false, 0);
                $userTable[] = new Column($this->userTableSchema->getFieldNameLastTab(), Enum\DB\ColumnType::VARCHAR, 256, true);
                if ($this->userTableSchema->getFieldNameLastLogin() !== '') {
                    $userTable[] = new Column($this->userTableSchema->getFieldNameLastLogin(), Enum\DB\ColumnType::DATETIME, null);
                }
                if ($this->userTableSchema->getFieldNameLoginCount() !== '') {
                    $userTable[] = new Column($this->userTableSchema->getFieldNameLoginCount(), Enum\DB\ColumnType::INT, null);
                }
                if ($this->userTableSchema->getFieldNameLocalPasswordExpires() !== '') {
                    $userTable[] = new Column($this->userTableSchema->getFieldNameLocalPasswordExpires(), Enum\DB\ColumnType::TINYINT, 1);
                    $userTable[] = new Column($this->userTableSchema->getFieldNameLocalPasswordLastChange(), Enum\DB\ColumnType::DATE, 1);
                    $userTable[] = new Column($this->userTableSchema->getFieldNameLocalPasswordExpiresAfterDays(), Enum\DB\ColumnType::INT, null);
                }
                $state->addTable(new Table($this->userTableSchema->getTableName(), ...$userTable));
                break;
            case '1.0.1':
                $state->addTable(
                    new Table(
                        'bs_queue',
                        new Column('id', Enum\DB\ColumnType::INT, null, false, true, true),
                        new Column('class', Enum\DB\ColumnType::VARCHAR, 512),
                        new Column('data', Enum\DB\ColumnType::VARCHAR, 12288),
                        new Column('queue', Enum\DB\ColumnType::VARCHAR, 256),
                        new Column('tries', Enum\DB\ColumnType::INT),
                        new Column('createdOn', Enum\DB\ColumnType::DATETIME)
                    )
                );
                break;
            case '1.0.2':
                $state->addTable(
                    new Table(
                        'bs_queue',
                        new Column('id', Enum\DB\ColumnType::INT, null, false, true, true),
                        new Column('class', Enum\DB\ColumnType::VARCHAR, 512),
                        new Column('data', Enum\DB\ColumnType::VARCHAR, 12288),
                        new Column('queue', Enum\DB\ColumnType::VARCHAR, 256),
                        new Column('tries', Enum\DB\ColumnType::INT),
                        new Column('jobState', Enum\DB\ColumnType::VARCHAR, 16),
                        new Column('createdOn', Enum\DB\ColumnType::DATETIME)
                    )
                );
                break;
        }
    }

    /**
     * @return array<string, string>
     */
    public function getBaseSchemaParameters(): array
    {
        $parameters['db_schema_table']   = $this->dbSchemaTable;
        $parameters['db_schema_id']      = $this->dbSchemaId;
        $parameters['db_schema_type']    = $this->dbSchemaType;
        $parameters['db_schema_value']   = $this->dbSchemaValue;
        $parameters['db_schema_version'] = $this->dbSchemaVersion;
        $parameters['db_schema_done']    = $this->dbSchemaDone;
        return $parameters;
    }
}
