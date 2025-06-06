<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

/** @noinspection SqlResolve */
/** @noinspection SqlNoDataSourceInspection */

namespace byteShard\Internal\Database\Schema\MySQL;

use byteShard\Enum;
use byteShard\Database;
use byteShard\Environment;
use byteShard\Exception;
use byteShard\Internal\Database\BaseConnection;
use byteShard\Internal\Database\BaseRecordset;
use byteShard\Internal\Database\Schema\DBManagementInterface;
use byteShard\Internal\Database\Schema\ColumnManagementInterface;
use byteShard\Internal\Database\Schema\DBManagementParent;
use byteShard\Internal\Database\Schema\ForeignKeyInterface;
use byteShard\Internal\Database\Schema\Grants;
use byteShard\Internal\Database\Schema\TableManagementInterface;
use byteShard\Internal\Database\Schema\IndexManagementInterface;
use mysqli;
use PDO;
use stdClass;

class DBManagement extends DBManagementParent implements DBManagementInterface
{
    private BaseConnection $connection;
    private string         $database;
    private string         $dbSchemaTable   = 'bs_schema';
    private string         $dbSchemaType    = 'type';
    private string         $dbSchemaValue   = 'value';
    private string         $dbSchemaVersion = 'version';
    private string         $dbSchemaDone    = 'done';
    /**
     * @var array<string>
     */
    private array  $dryRunCommands;
    private string $tableSchema;

    public function __construct(BaseConnection $connection, string $database, ?string $schema = null)
    {
        $this->connection  = $connection;
        $this->database    = $database;
        $this->tableSchema = $schema ?? $database;
        $this->connect();
    }

    /**
     * @param string $command
     */
    public function execute(string $command): void
    {
        if ($this->isDryRun() === true) {
            $this->dryRunCommands[] = $command;
        } else {
            $this->connection->execute($command);
        }
    }

    /**
     * @param TableManagementInterface $table
     * @return array<ColumnManagementInterface>
     * @throws Exception
     */
    public function getColumns(TableManagementInterface $table): array
    {
        $columns      = [];
        $primary_keys = $this->getPrimaryKeyColumns($table);
        $tmp          = Database::getArray('SELECT * FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA`=\''.$this->getTableSchema().'\' AND `TABLE_NAME`=\''.$table->getName().'\'');
        foreach ($tmp as $val) {
            switch ($val->DATA_TYPE) {
                case 'tinyint': //boolean
                    $type = Enum\DB\ColumnType::TINYINT;
                    break;
                case 'smallint':
                    $type = Enum\DB\ColumnType::SMALLINT;
                    break;
                case 'mediumint':
                    $type = Enum\DB\ColumnType::MEDIUMINT;
                    break;
                case 'int':
                    $type = Enum\DB\ColumnType::INT;
                    break;
                case 'bigint':
                    $type = Enum\DB\ColumnType::BIGINT;
                    break;
                case 'decimal':
                    $type = Enum\DB\ColumnType::DECIMAL;
                    break;
                case 'float':
                    $type = Enum\DB\ColumnType::FLOAT;
                    break;
                case 'double': //real
                    $type = Enum\DB\ColumnType::DOUBLE;
                    break;
                case 'bit':
                    $type = Enum\DB\ColumnType::BIT;
                    break;
                case 'date':
                    $type = Enum\DB\ColumnType::DATE;
                    break;
                case 'datetime':
                    $type = Enum\DB\ColumnType::DATETIME;
                    break;
                case 'timestamp':
                    $type = Enum\DB\ColumnType::TIMESTAMP;
                    break;
                case 'time':
                    $type = Enum\DB\ColumnType::TIME;
                    break;
                case 'year':
                    $type = Enum\DB\ColumnType::YEAR;
                    break;
                case 'char':
                    $type = Enum\DB\ColumnType::CHAR;
                    break;
                case 'varchar':
                    $type = Enum\DB\ColumnType::VARCHAR;
                    break;
                case 'tinytext':
                    $type = Enum\DB\ColumnType::TINYTEXT;
                    break;
                case 'text':
                    $type = Enum\DB\ColumnType::TEXT;
                    break;
                case 'mediumtext':
                    $type = Enum\DB\ColumnType::MEDIUMTEXT;
                    break;
                case 'longtext':
                    $type = Enum\DB\ColumnType::LONGTEXT;
                    break;
                case 'binary':
                    $type = Enum\DB\ColumnType::BINARY;
                    break;
                case 'varbinary':
                    $type = Enum\DB\ColumnType::VARBINARY;
                    break;
                case 'tinyblob':
                    $type = Enum\DB\ColumnType::TINYBLOB;
                    break;
                case 'mediumblob':
                    $type = Enum\DB\ColumnType::MEDIUMBLOB;
                    break;
                case 'blob':
                    $type = Enum\DB\ColumnType::BLOB;
                    break;
                case 'longblob':
                    $type = Enum\DB\ColumnType::LONGBLOB;
                    break;
                case 'enum':
                    $type = Enum\DB\ColumnType::ENUM;
                    break;
                case 'set':
                    $type = Enum\DB\ColumnType::SET;
                    break;
                case 'geometry':
                    $type = Enum\DB\ColumnType::GEOMETRY;
                    break;
                case 'point':
                    $type = Enum\DB\ColumnType::POINT;
                    break;
                case 'linestring':
                    $type = Enum\DB\ColumnType::LINESTRING;
                    break;
                case 'polygon':
                    $type = Enum\DB\ColumnType::POLYGON;
                    break;
                case 'multipoint':
                    $type = Enum\DB\ColumnType::MULTIPOINT;
                    break;
                case 'multilinestring':
                    $type = Enum\DB\ColumnType::MULTILINESTRING;
                    break;
                case 'multipolygon':
                    $type = Enum\DB\ColumnType::MULTIPOLYGON;
                    break;
                case 'geometrycollection':
                    $type = Enum\DB\ColumnType::GEOMETRYCOLLECTION;
                    break;
                default:
                    print __METHOD__.': Unknown type in '.get_class($this).' DB column: '.$val->COLUMN_NAME."<br>\n";
                    print_r($val);
                    exit;
            }
            $length = $val->CHARACTER_MAXIMUM_LENGTH;

            if (str_contains($val->COLUMN_TYPE, '(')) {
                preg_match("/\\((.*?)\\)/", $val->COLUMN_TYPE, $match);
                if (array_key_exists(1, $match)) {
                    $length = $match[1];
                }
            }
            if (is_numeric($length)) {
                $length = (int)$length;
            } elseif ($length === '') {
                $length = null;
            }
            if (in_array($type, [
                Enum\DB\ColumnType::TINYBLOB,
                Enum\DB\ColumnType::BLOB,
                Enum\DB\ColumnType::MEDIUMBLOB,
                Enum\DB\ColumnType::LONGBLOB,
            ])) {
                $length = null;
            }
            $default = $val->COLUMN_DEFAULT !== '' ? $val->COLUMN_DEFAULT : null;
            if ($default === 'NULL') {
                $default = null;
            } elseif ($default === '""') {
                // unify empty string default
                $default = '\'\'';
            }
            if ($default !== null && $type->isNumeric()) {
                switch ($type) {
                    case Enum\DB\ColumnType::TINYINT:
                    case Enum\DB\ColumnType::SMALLINT:
                    case Enum\DB\ColumnType::MEDIUMINT:
                    case Enum\DB\ColumnType::INT:
                    case Enum\DB\ColumnType::BIGINT:
                        $default = intval($default);
                        break;
                    case Enum\DB\ColumnType::DECIMAL:
                    case Enum\DB\ColumnType::FLOAT:
                    case Enum\DB\ColumnType::DOUBLE:
                        $default = floatval($default);
                        break;
                }
            }
            $columns[$val->COLUMN_NAME] = new Column(
                $val->COLUMN_NAME,
                $val->COLUMN_NAME,
                $type,
                $length,
                $val->IS_NULLABLE === 'YES',
                array_key_exists($val->COLUMN_NAME, $primary_keys),
                stripos($val->EXTRA, 'auto_increment') !== false,
                $default
            );
        }
        return $columns;
    }

    /**
     * @return array<string, IndexManagementInterface>
     * @throws Exception
     */
    public function getIndices(TableManagementInterface $table): array
    {
        $result       = [];
        $indexRecords = Database::getArray('SHOW INDEXES FROM '.$table->getName().' WHERE NOT Key_name=\'PRIMARY\'');
        $indices      = [];
        foreach ($indexRecords as $index) {
            if (!array_key_exists($index->Key_name, $indices)) {
                $indices[$index->Key_name]         = new stdClass();
                $indices[$index->Key_name]->Unique = $index->Non_unique === '1';
            }
            $indices[$index->Key_name]->IndexName                     = $index->Key_name;
            $indices[$index->Key_name]->Columns[$index->Seq_in_index] = new Column($index->Column_name);
        }
        if (!empty($indices)) {
            foreach ($indices as $index) {
                ksort($index->Columns);
                $indexObject = new Index($index->IndexName, ...$index->Columns);
                if ($index->Unique === true) {
                    $indexObject->setUnique();
                }
                $result[$indexObject->getName()] = $indexObject;
            }
        }
        return $result;
    }

    /**
     * @param TableManagementInterface $table
     * @return array<int|string,Grants>
     * @throws Exception
     */
    public function getGrants(TableManagementInterface $table): array
    {
        $tableGrants  = Database::getArray('SELECT GRANTEE, PRIVILEGE_TYPE FROM INFORMATION_SCHEMA.TABLE_PRIVILEGES WHERE TABLE_SCHEMA=\''.$this->getTableSchema().'\' AND TABLE_NAME=\''.$table->getName().'\' ORDER BY TABLE_NAME');
        $columnGrants = Database::getArray('SELECT GRANTEE, COLUMN_NAME, PRIVILEGE_TYPE FROM information_schema.COLUMN_PRIVILEGES WHERE TABLE_SCHEMA=\''.$this->getTableSchema().'\' AND TABLE_NAME=\''.$table->getName().'\' ORDER BY PRIVILEGE_TYPE;');
        $grants       = [];
        foreach ($tableGrants as $tableGrant) {
            if (!array_key_exists($tableGrant->GRANTEE, $grants)) {
                $grants[$tableGrant->GRANTEE] = new Grants();
                $grants[$tableGrant->GRANTEE]->setGrantee($tableGrant->GRANTEE);
            }
            $grants[$tableGrant->GRANTEE]->addPrivilege($tableGrant->PRIVILEGE_TYPE);
        }
        foreach ($columnGrants as $columnGrant) {
            if (!array_key_exists($columnGrant->GRANTEE, $grants)) {
                $grants[$columnGrant->GRANTEE] = new Grants();
                $grants[$columnGrant->GRANTEE]->setGrantee($columnGrant->GRANTEE);
            }
            $grants[$columnGrant->GRANTEE]->addColumns($columnGrant->PRIVILEGE_TYPE, $columnGrant->COLUMN_NAME);
        }
        return $grants;
    }

    /**
     * @return array<string, string>
     * @throws Exception
     */
    public function getPrimaryKeyColumns(TableManagementInterface $table): array
    {
        $columns       = [];
        $newConnection = $this->connection->getConnection(true);
        if ($newConnection instanceof BaseConnection) {
            $tmp = Database::getArray('SHOW INDEX FROM `'.$table->getName().'` WHERE `Key_name`=\'PRIMARY\'', [], $newConnection);
            foreach ($tmp as $val) {
                if (is_string($val->Column_name)) {
                    $columns[$val->Column_name] = $val->Column_name;
                }
            }
            $newConnection->disconnect();
        }
        return $columns;
    }

    /**
     * @throws Exception
     */
    public function getPrimaryKeyName(TableManagementInterface $table): string
    {
        $newConnection = $this->connection->getConnection(true);
        if ($newConnection instanceof BaseConnection) {
            $tmp = Database::getArray('SHOW INDEX FROM `'.$table->getName().'` WHERE `Key_name`=\'PRIMARY\'', [], $newConnection);
            $newConnection->disconnect();
        }
        if (empty($tmp)) {
            return '';
        }
        // MySQL Primary Key Name is always PRIMARY
        return 'PRIMARY';
    }

    /**
     * @throws Exception
     */
    public function getTableComment(string $table): string
    {
        $newConnection = $this->connection->getConnection(true);
        if ($newConnection instanceof BaseConnection) {
            $record = Database::getSingle('SELECT `TABLE_COMMENT` FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA`=\''.$this->getTableSchema().'\' AND `TABLE_NAME`=\''.$table.'\'', [], $newConnection);
            $newConnection->disconnect();
            if ($record !== null && isset($record->TABLE_COMMENT)) {
                return $record->TABLE_COMMENT;
            }
        }
        return '';
    }

    /**
     * @return array<TableManagementInterface>
     * @throws Exception
     */
    public function getTables(bool $sorted = false): array
    {
        $tables        = [];
        $newConnection = $this->connection->getConnection(true);
        if ($newConnection instanceof BaseConnection) {
            $records = Database::getArray('SELECT `TABLE_NAME` FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA`=\''.$this->getTableSchema().'\'', [], $newConnection);
            $newConnection->disconnect();
            foreach ($records as $record) {
                $tables[] = new Table($record->TABLE_NAME);
            }
        }
        foreach ($tables as $table) {
            $indices = $this->getIndices($table);
            $table->setIndices(...$indices);
        }
        if ($sorted === true) {
            usort($tables, function (TableManagementInterface $a, TableManagementInterface $b): int {
                return strcmp($a->getName(), $b->getName());
            });
        }
        return $tables;
    }

    /**
     * @throws Exception
     */
    public function getVersion(string $type = 'bs_schema', string $value = 'version_identifier', ?string $initialVersion = 'v0.0.0'): ?string
    {
        if ($this->tableExists($this->dbSchemaTable) === false) {
            return null;
        }
        $version = $this->getSingle('SELECT '.$this->dbSchemaVersion.' FROM '.$this->dbSchemaTable.' WHERE '.$this->dbSchemaType."='".$type."' AND ".$this->dbSchemaValue."='".$value."'");
        if ($version !== null && $version->{$this->dbSchemaVersion} !== null) {
            return $version->{$this->dbSchemaVersion};
        }
        return $initialVersion;
    }

    /**
     * @throws Exception
     */
    public function databaseExists(): bool
    {
        $dbExist = Database::getSingle('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME=\''.$this->database.'\'', [], $this->connection);
        if ($dbExist === null) {
            return false;
        }
        return true;
    }

    public function selectDatabase(): bool
    {
        try {
            $this->connection->setDB($this->database);
            $newConnection = $this->connection->getConnection(true);
            if ($newConnection instanceof BaseConnection) {
                $this->connection = $newConnection;
            }
            return $newConnection instanceof BaseConnection;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @throws Exception
     */
    public function createAndSelectDatabase(): bool
    {
        $dbExist = Database::getSingle('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME=\''.$this->database.'\'', [], $this->connection);
        if ($dbExist === null) {
            try {
                $this->connection->execute('CREATE DATABASE '.$this->database);
            } catch (\Exception) {
                return false;
            }
        }
        $this->connection->setDB($this->database);
        return true;
    }

    /**
     * @param array<string> $dryRunCommands
     */
    public function setDryRunCommandArrayReference(array &$dryRunCommands): static
    {
        $this->dryRunCommands = &$dryRunCommands;
        return $this;
    }

    /**
     * @param array<string, string> $parameters
     */
    public function setSchemaParameters(array $parameters): static
    {
        $this->dbSchemaTable   = $parameters['db_schema_table'];
        $this->dbSchemaType    = $parameters['db_schema_type'];
        $this->dbSchemaValue   = $parameters['db_schema_value'];
        $this->dbSchemaVersion = $parameters['db_schema_version'];
        $this->dbSchemaDone    = $parameters['db_schema_done'];
        return $this;
    }

    /**
     * @throws Exception
     */
    public function setVersion(string $type = 'bs_schema', string $value = 'version_identifier', string $version = 'v0.0.0'): static
    {
        if ($this->isDryRun() === false) {
            $connection = $this->connection->getConnection();
            $update     = false;
            $params     = [$this->dbSchemaVersion => $version, $this->dbSchemaDone => true, $this->dbSchemaType => $type, $this->dbSchemaValue => $value];
            if ($this->getVersion($type, $value, null) === null) {
                $query = 'INSERT INTO `'.$this->dbSchemaTable.'` (`'.implode('`, `', array_keys($params)).'`) VALUES (?, ? ,?, ?)';
            } else {
                $query  = 'UPDATE `'.$this->dbSchemaTable.'` SET `'.$this->dbSchemaVersion.'`=?, `'.$this->dbSchemaDone.'`=? WHERE `'.$this->dbSchemaType.'`=? AND `'.$this->dbSchemaValue.'`=?';
                $update = true;
            }
            if ($connection instanceof PDO) {
                // transform index to named parameters
                foreach ($params as $key => $param) {
                    $position = strpos($query, '?');
                    if ($position !== false) {
                        $query = substr_replace($query, ':'.$key, $position, 1);
                    }
                }
                if ($update === true) {
                    Database::update($query, $params);
                } else {
                    Database::insert($query, $params);
                }
            } elseif ($connection instanceof mysqli) {
                $statement = $connection->prepare($query);
                if ($statement !== false) {
                    list($types, $params) = $this->getBindArguments($params);
                    $statement->bind_param($types, ...$params);
                    $statement->execute();
                }
            }
        }
        return $this;
    }

    /**
     * @throws Exception
     */
    public function tableExists(string $table): bool
    {
        $record        = null;
        $newConnection = $this->connection->getConnection(true);
        if ($newConnection instanceof BaseConnection) {
            $record = Database::getSingle('SELECT `TABLE_NAME` FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA`=\''.$this->getTableSchema().'\' AND `TABLE_NAME`=\''.$table.'\'', [], $newConnection);
            $newConnection->disconnect();
        }
        return $record !== null;
    }


    /**
     * @return array<string, ForeignKeyInterface>
     * @throws Exception
     */
    public function getForeignKeys(TableManagementInterface $table): array
    {
        $keys   = Database::getArray('SELECT COLUMN_NAME, TABLE_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME=\''.$table->getName().'\' AND NOT REFERENCED_TABLE_SCHEMA IS NULL');
        $result = [];
        foreach ($keys as $key) {
            if (is_string($key->COLUMN_NAME)) {
                $result[$key->COLUMN_NAME] = new ForeignKey($key->COLUMN_NAME, $key->TABLE_NAME, $key->REFERENCED_TABLE_NAME, $key->REFERENCED_COLUMN_NAME, $key->CONSTRAINT_NAME);
            }
        }
        return $result;
    }

    ##########################################################################
    ### PRIVATE FUNCTIONS
    ##########################################################################

    private function connect(): void
    {
        try {
            $this->connection->connect();
        } catch (\Exception $e) {
            print_r($e);
            print 'failed to connect';
        }
    }

    /**
     * @param array<string, string|bool|int|float> $data
     * @return array{0: string, 1: array<string|int|float>}
     */
    private function getBindArguments(array $data): array
    {
        $columns = [];
        $types   = array_reduce(
            $data,
            function ($string, $arg) use (&$columns) {
                if (is_bool($arg)) {
                    $arg = (int)$arg;
                }
                $columns[] = $arg;
                if (is_float($arg)) {
                    $string .= 'd';
                } elseif (is_int($arg)) {
                    $string .= 'i';
                } else {
                    $string .= 's';
                }
                return $string;
            },
            ''
        );
        return [$types, $columns];
    }

    /**
     * @throws Exception
     */
    private function getSingle(string $statement): ?object
    {
        $record        = null;
        $newConnection = $this->connection->getConnection(true);
        if ($newConnection instanceof BaseConnection) {
            $record = Database::getSingle($statement, [], $newConnection);
            $newConnection->disconnect();
        }
        return $record;
    }

    private function getTableSchema(): string
    {
        if ($this->tableSchema === '') {
            $this->tableSchema = $this->connection->getDB();
        }
        return $this->tableSchema;
    }

    /**
     * @param string $columnUserID
     * @param string $tableName
     * @param string $columnUsername
     * @param string $username
     * @param array<string, string|int|null> $params
     * @return bool
     * @throws Exception
     */
    public function createOrUpdateAdminUser(string $columnUserID, string $tableName, string $columnUsername, string $username, array $params): bool
    {
        global $dbDriver;
        if ($dbDriver === Environment::DRIVER_MySQL_mysqli) {
            $rs = Database::getRecordset($cn = Database::getConnection(Database\Enum\ConnectionType::WRITE));
            if ($rs instanceof BaseRecordset) {
                $rs->open('SELECT '.implode(', ', array_keys($params)).' FROM '.$tableName.' WHERE '.$columnUsername.'=\''.$username.'\'');
                if ($rs->recordcount() === 0) {
                    $rs->addnew();
                    foreach ($params as $key => $value) {
                        $rs->fields[$key] = $value;
                    }
                    $rs->update();
                } elseif ($rs->recordcount() === 1) {
                    unset($params[$columnUsername]);
                    foreach ($params as $key => $value) {
                        $rs->fields[$key] = $value;
                    }
                    $rs->update();
                }
                $rs->close();
                $cn->disconnect();
                return true;
            }
        } elseif ($dbDriver === Environment::DRIVER_MYSQL_PDO) {
            $newConnection = $this->connection->getConnection(true);
            if ($newConnection instanceof BaseConnection) {
                $adminUser = Database::getSingle('SELECT '.$columnUserID.' FROM '.$tableName.' WHERE '.$columnUsername.'=:username', ['username' => $params[$columnUsername]], $newConnection);
                if ($adminUser === null) {
                    Database::insert('INSERT INTO '.$tableName.' ('.implode(', ', array_keys($params)).') VALUES (:'.implode(', :', array_keys($params)).')', $params, $newConnection);
                } else {
                    unset($params[$columnUsername]);
                    $query                   = 'UPDATE '.$tableName.' SET '.implode(', ', array_map(function ($value) {
                            return $value.' = :'.$value;
                        }, array_keys($params))).' WHERE '.$columnUsername.'='.':'.$columnUsername;
                    $params[$columnUsername] = $username;
                    Database::update($query, $params, $newConnection);
                }
                $newConnection->disconnect();
            }
            return true;
        }
        return false;
    }
}
