<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */
/** @noinspection SqlResolve */
/** @noinspection SqlNoDataSourceInspection */

namespace byteShard\Internal\Database\Schema\PGSQL;

use byteShard\Database;
use byteShard\Enum;
use byteShard\Exception;
use byteShard\Internal\Database\BaseConnection;
use byteShard\Internal\Database\Schema\ColumnManagementInterface;
use byteShard\Internal\Database\Schema\DBManagementInterface;
use byteShard\Internal\Database\Schema\DBManagementParent;
use byteShard\Internal\Database\Schema\ForeignKeyInterface;
use byteShard\Internal\Database\Schema\Grants;
use byteShard\Internal\Database\Schema\IndexManagementInterface;
use byteShard\Internal\Database\Schema\TableManagementInterface;
use PDO;
use PDOException;

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

    public function __construct(BaseConnection $connection, string $database, ?string $schema)
    {
        $this->connection  = $connection;
        $this->database    = $database;
        $this->tableSchema = $schema ?? $database;
        $this->connect();
    }

    public function execute(string $command): void
    {
        if ($this->isDryRun() === true) {
            $this->dryRunCommands[] = $command;
        } else {
            $this->connection->execute($command);
        }
    }

    /**
     * @return array<ColumnManagementInterface>
     * @throws Exception
     */
    public function getColumns(TableManagementInterface $table): array
    {
        $columns = [];
        $query   = 'SELECT ic.column_name, ic.data_type,ic.character_maximum_length, ic.is_nullable, ic.column_default, ic.is_identity, ik.constraint_name
                        FROM INFORMATION_SCHEMA.COLUMNS as ic LEFT JOIN (select table_name, column_name, constraint_name FROM  information_schema.key_column_usage WHERE table_name =:table and TABLE_SCHEMA =:tableSchema) as ik on ic.column_name = ik.column_name
                        WHERE  ic.table_name=:table AND ic.TABLE_SCHEMA =:tableSchema';

        $tmp = Database::getArray($query, ['table' => $table, 'tableSchema' => $this->tableSchema]);
        foreach ($tmp as $val) {
            switch ($val->data_type) {
                case 'integer':
                case 'int':
                    $type = Enum\DB\ColumnType::INTEGER;
                    break;
                case 'smallint':
                    $type = Enum\DB\ColumnType::SMALLINT;
                    break;
                case 'boolean':
                    $type = Enum\DB\ColumnType::BOOLEAN;
                    break;
                case 'bigint':
                    $type = Enum\DB\ColumnType::BIGINT;
                    break;
                case 'numeric':
                case 'decimal':
                    $type = Enum\DB\ColumnType::DECIMAL;
                    break;
                case 'real':
                    $type = Enum\DB\ColumnType::REAL;
                    break;
                case 'double precision':
                    $type = Enum\DB\ColumnType::DOUBLE;
                    break;
                case 'timestamp without time zone':
                case 'time without time zone':
                    $type = Enum\DB\ColumnType::TIMESTAMP;
                    break;
                case 'time':
                    $type = Enum\DB\ColumnType::TIME;
                    break;
                case 'date':
                    $type = Enum\DB\ColumnType::DATE;
                    break;
                case 'varchar':
                case 'character varying':
                    $type = Enum\DB\ColumnType::VARCHAR;
                    break;
                case 'character' :
                case 'char':
                    $type = Enum\DB\ColumnType::CHAR;
                    break;
                case 'text':
                    $type = Enum\DB\ColumnType::TEXT;
                    break;
                case 'bytea':
                    $type = Enum\DB\ColumnType::BYTEA;
                    break;
                case 'blob':
                    $type = Enum\DB\ColumnType::BLOB;
                    break;
                case 'point':
                    $type = Enum\DB\ColumnType::POINT;
                    break;
                case 'line':
                    $type = Enum\DB\ColumnType::LINESTRING;
                    break;
                case 'polygon':
                    $type = Enum\DB\ColumnType::POLYGON;
                    break;
                case 'circle':
                    $type = Enum\DB\ColumnType::CIRCLE;
                    break;
                case 'box':
                    $type = Enum\DB\ColumnType::BOX;
                    break;
                case 'paths':
                    $type = Enum\DB\ColumnType::PATHS;
                    break;
                case 'money':
                    $type = Enum\DB\ColumnType::MONEY;
                    break;
                case 'cidr':
                    $type = Enum\DB\ColumnType::CIDR;
                    break;
                case 'inet':
                    $type = Enum\DB\ColumnType::INET;
                    break;
                case 'macaddr':
                    $type = Enum\DB\ColumnType::MACADDR;
                    break;
                case 'tsvector':
                    $type = Enum\DB\ColumnType::TSVECTOR;
                    break;
                case 'tsquery':
                    $type = Enum\DB\ColumnType::TSQUERY;
                    break;
                default:
                    print __METHOD__.': Unknown type in '.get_class($this).' DB column: '.$val->column_name."<br>\n";
                    print_r($val);
                    exit;
            }

            $length = is_numeric($val->character_maximum_length) ? (int)$val->character_maximum_length : null;

            if ($val->column_default === null) {
                $default = null;
            } else {
                $defaultValue = strstr($val->column_default, '::', true);
                if ($defaultValue !== false) {
                    if (!str_contains($defaultValue, 'nextval')) {
                        if ($defaultValue === "''") {
                            $default = '';
                        } else {
                            $default = $defaultValue;
                        }
                    } else {
                        $default = null;
                    }
                } else {
                    $default = $val->column_default;
                }
            }


            $columns[$val->column_name] = new Column(
                $val->column_name,
                $val->column_name,
                $type,
                $length,
                $val->is_nullable === 'YES',
                isset($val->constraint_name) && str_contains($val->constraint_name, 'pkey'),
                isset($val->column_default) && str_contains($val->column_default, 'nextval') || $val->is_identity !== 'NO',
                $default
            );
        }
        return $columns;
    }

    /**
     * @return array<IndexManagementInterface>
     * @throws Exception
     */
    public function getIndices(TableManagementInterface $table): array
    {
        $indices    = [];
        $indicesTmp = [];

        $query = 'SELECT t.relname as table_name, a.attname as column_name,  ix.relname as index_name, am.amname as index_type, a.attnum as seq_in_index,
indisunique as is_unique, indisprimary as is_primary FROM pg_index i JOIN pg_class t ON t.oid = i.indrelid
JOIN  pg_attribute a on  a.attrelid = t.oid JOIN pg_class ix ON ix.oid = i.indexrelid JOIN pg_am am on am.oid = t.relam JOIN pg_namespace AS namespace ON ix.relnamespace = namespace.OID
WHERE a.attnum = ANY(i.indkey) and  t.relname =:table and i.indisprimary != true and namespace.nspname =:schema;';

        $tmp = Database::getArray($query, ['table' => $table, 'schema' => $this->tableSchema]);
        foreach ($tmp as $val) {
            if (is_string($val->index_name)) {
                $indicesTmp[$val->index_name]['Columns'][$val->seq_in_index] = new Column($val->column_name);
                $indicesTmp[$val->index_name]['Index_type']                  = $val->index_type;
                $indicesTmp[$val->index_name]['unique']                      = $val->is_unique;
            }
        }
        foreach ($indicesTmp as $indexName => $index) {
            $columns = $index['Columns'];
            ksort($columns);
            $indices[$indexName] = new Index($table->getName(), $indexName, ...$columns);
            if (is_string($index['Index_type'])) {
                $indices[$indexName]->setType($index['Index_type']);
                $indices[$indexName]->setUnique($index['unique']);
            }
        }
        return $indices;
    }

    /**
     * @return array<string, ForeignKeyInterface>
     * @throws Exception
     */
    public function getForeignKeys(TableManagementInterface $table): array
    {
        $foreignKeys = [];

        $query  = 'SELECT tc.table_name,  kcu.column_name, ccu.constraint_name,
                    ccu.table_name AS foreign_table_name,
                    ccu.column_name AS foreign_column_name
                    FROM information_schema.table_constraints AS tc
                    JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name
                    JOIN information_schema.constraint_column_usage AS ccu ON ccu.constraint_name = tc.constraint_name
                    WHERE constraint_type =:constraint_type  and tc.table_name =:table_name and kcu.table_schema=:table_schema ';
        $fields = ['constraint_type' => 'FOREIGN KEY', 'table_name' => $table->getName(), 'table_schema' => $this->tableSchema];

        $tmp = Database::getArray($query, $fields);
        if (!empty($tmp)) {
            foreach ($tmp as $val) {
                if (is_string($val->column_name)) {
                    $foreignKey                     = new ForeignKey($val->column_name, $val->table_name, $val->foreign_table_name, $val->foreign_column_name, $val->constraint_name);
                    $foreignKeys[$val->column_name] = $foreignKey;
                }
            }
        }
        return $foreignKeys;
    }

    /**
     * @return array<string, string>
     * @throws Exception
     */
    public function getPrimaryKeyColumns(TableManagementInterface $table): array
    {
        $columns = [];
        $query   = ' SELECT column_name, constraint_name FROM information_schema.key_column_usage WHERE TABLE_SCHEMA =:tableSchema AND table_name =:table';
        $tmp     = Database::getArray($query, ['tableSchema' => $this->tableSchema, 'table' => $table]);
        foreach ($tmp as $val) {
            if (str_contains($val->constraint_name, 'pkey'))
                if (is_string($val->column_name)) {
                    $columns[$val->column_name] = $val->column_name;
                }
        }
        return $columns;
    }


    /**
     * @return array<string, string>
     * @throws Exception
     */
    public function getAutoIncrementColumns(TableManagementInterface $table): array
    {
        $columns = [];
        $query   = 'SELECT table_name, column_name FROM information_schema.columns WHERE is_identity  =:yes AND TABLE_SCHEMA =:tableSchema AND  table_name = :table_name';
        $tmp     = Database::getArray($query, ['yes' => 'YES', 'tableSchema' => $this->tableSchema, 'table_name' => $table]);
        foreach ($tmp as $val) {
            if (is_string($val->column_name)) {
                $columns[$val->column_name] = $val->column_name;
            }
        }
        return $columns;
    }

    /**
     * @throws Exception
     */
    public function getTableComment(string $table): string
    {
        $query = 'SELECT c.relname As tname, CASE WHEN c.relkind = \'r\' THEN \'view\' ELSE \'table\' END As type,
       pg_get_userbyid(c.relowner) AS towner, t.spcname AS tspace,  n.nspname AS sname,  d.description as table_comment
       FROM pg_class As c   LEFT JOIN pg_namespace n ON n.oid = c.relnamespace LEFT JOIN pg_tablespace t ON t.oid = c.reltablespace  LEFT JOIN pg_description As d ON (d.objoid = c.oid AND d.objsubid = 0)
       WHERE c.relkind IN(\'r\' , \'v\') AND d.description >  \'\' and c.relname =:tablename AND n.nspname =:tableSchema ';

        $record = Database::getSingle($query, ['tablename' => $table, 'tableSchema' => $this->tableSchema]);

        if ($record !== null && isset($record->table_comment)) {
            return $record->table_comment;
        }
        return '';
    }

    /**
     * @return array<TableManagementInterface>
     * @throws Exception
     */
    public function getTables(bool $sorted = false): array
    {
        $query   = 'SELECT table_name FROM information_schema.tables WHERE table_schema = :table_schema ';
        $records = Database::getArray($query, ['table_schema' => $this->tableSchema]);
        $tables  = [];
        foreach ($records as $record) {
            $tables[] = new Table($record->table_name);
        }
        return $tables;
    }

    /**
     * @throws Exception
     */
    public function getVersion(string $type = 'bs_schema', string $value = 'version_identifier', string|null $initialVersion = 'v0.0.0'): null|string
    {
        if ($this->tableExists($this->dbSchemaTable) === false) {
            return null;
        }
        $query   = 'SELECT '.$this->dbSchemaVersion.' FROM '.$this->dbSchemaTable.' WHERE '.$this->dbSchemaType.' =:type AND '.$this->dbSchemaValue.' =:value';
        $version = Database::getSingle($query, ['type' => $type, 'value' => $value]);
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
        $dbExist = Database::getSingle('SELECT datname FROM  pg_database WHERE datname= :datname', ['datname' => $this->database], $this->connection);
        if ($dbExist !== null) {
            $schemaExist = Database::getSingle('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE CATALOG_NAME =:databaseName AND SCHEMA_NAME=:schemaName', ['databaseName' => $this->database, 'schemaName' => $this->tableSchema], $this->connection);
            if ($schemaExist !== null)
                return true;
        }
        return false;
    }

    public function selectDatabase(): bool
    {
        try {
            $this->connection->setDB($this->database);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @throws Exception
     */
    public function createAndSelectDatabase(): bool
    {
        $dbExist     = Database::getSingle('SELECT datname FROM  pg_database WHERE datname = :datname', ['datname' => $this->database]);
        $schemaExist = Database::getSingle('SELECT schema_name FROM INFORMATION_SCHEMA.SCHEMATA WHERE CATALOG_NAME =:databaseName AND SCHEMA_NAME =:schemaName', ['databaseName' => $this->database, 'schemaName' => $this->tableSchema], $this->connection);
        if ($dbExist !== null) {
            if ($schemaExist === null) {
                try {
                    $tempConnection = $this->connection->getConnection();
                    if ($tempConnection instanceof PDO) {
                        $stmt = $tempConnection->prepare('CREATE SCHEMA '.$this->tableSchema);
                        $stmt->execute();
                    }
                } catch (PDOException $e) {
                    echo 'Error in Database Schema creation'.$e->getMessage();
                }
            }
        }
        $this->connection->setDB($this->database);
        $this->connection->execute('ALTER DATABASE '.$this->database.' SET SEARCH_PATH TO '.$this->tableSchema);
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
        $fields = [$this->dbSchemaType => $type, $this->dbSchemaValue => $value, $this->dbSchemaVersion => $version, $this->dbSchemaDone => true];
        if (($this->getVersion($type, $value, null) === null)) {
            if ($this->isDryRun() === false) {
                $query = 'INSERT INTO '.$this->dbSchemaTable.'('.$this->dbSchemaType.', '.$this->dbSchemaValue.', '.$this->dbSchemaVersion.', '.$this->dbSchemaDone.') VALUES(:type, :value, :version, :done)';
                Database::insert($query, $fields);
            }
        } else {
            if ($this->isDryRun() === false) {
                $query = 'UPDATE '.$this->dbSchemaTable.' SET '.$this->dbSchemaVersion.'=:version, '.$this->dbSchemaDone.'=:done  WHERE '.$this->dbSchemaType.'=:type  and '.$this->dbSchemaValue.'=:value ';
                Database::update($query, $fields);
            }
        }
        return $this;
    }

    /**
     * @throws Exception
     */
    public function tableExists(string $table): bool
    {
        $query  = 'select table_name from information_schema.tables where TABLE_CATALOG =:tableCatalog and  TABLE_SCHEMA =:tableSchema and table_name =:table_name';
        $record = Database::getSingle($query, ['tableCatalog' => $this->database, 'tableSchema' => $this->tableSchema, 'table_name' => $table]);
        if ($record !== null) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @throws Exception
     */
    public function getPrimaryKeyName(TableManagementInterface $table): string
    {
        $query = 'SELECT constraint_name
                  FROM information_schema.table_constraints
                  WHERE table_name = :tablename
                    AND constraint_type = :key
                    AND constraint_schema = :schema';
        $tmp   = Database::getArray($query, [$table->getName(), 'PRIMARY KEY', $this->tableSchema]);
        if (empty($tmp)) {
            return '';
        }
        if (count($tmp) > 1) {
            throw new Exception('More than one primary key');
        }
        return $tmp[0]->constraint_name;
    }

    ///////////////***** PRIVATE FUNCTIONS ****///////////////////

    private function connect(): void
    {
        try {
            $this->connection->connect();
        } catch (\Exception $e) {
            print_r($e);
            echo 'failed to connect';
        }
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
        $columnUsername = strtolower($columnUsername);
        $adminUser      = Database::getSingle('SELECT '.$columnUserID.' FROM '.$tableName.' WHERE "'.$columnUsername.'"=:'.$columnUsername, [$columnUsername => $username]);
        $params         = array_change_key_case($params);
        if ($adminUser === null) {
            Database::insert('INSERT INTO '.$tableName.' ("'.implode('", "', array_keys($params)).'") VALUES (:'.implode(', :', array_keys($params)).')', $params);
        } else {
            unset($params[$columnUsername]);
            $result                  = array_map(function (string $key) {
                return $key.'"=:'.$key;
            }, array_keys($params));
            $query                   = 'UPDATE '.$tableName.' SET "'.implode(', "', $result).' WHERE "'.$columnUsername.'"='.':'.$columnUsername;
            $params[$columnUsername] = $username;
            Database::update($query, $params);
        }
        return true;
    }

    /**
     * @return array<int|string,Grants>
     */
    public function getGrants(TableManagementInterface $table): array
    {
        return [];
    }
}