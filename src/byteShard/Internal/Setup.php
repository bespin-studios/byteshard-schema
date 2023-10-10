<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Authentication\Enum\Target;
use byteShard\Database;
use byteShard\Database\BaseSchema;
use byteShard\Database\Schema\State;
use byteShard\Database\SchemaInterface;
use byteShard\Database\Struct\Parameters;
use byteShard\Environment;
use byteShard\Exception;
use byteShard\Internal\Database\Schema\DBManagementInterface;
use byteShard\Internal\Database\Schema\MySQL\DBManagement as MysqlDBManagement;
use byteShard\Internal\Database\Schema\PGSQL\DBManagement as PgsqlDBManagement;
use byteShard\Internal\Database\Schema\MySQL\StateManagement as MysqlStateManagement;
use byteShard\Internal\Database\Schema\PGSQL\StateManagement as PgsqlStateManagement;
use byteShard\Internal\Database\Schema\StateManagementInterface;
use byteShard\Password;

class Setup
{
    const BUTTON_CREATE_ADMIN = 'btn_create_admin';
    const BUTTON_CREATE_DB = 'btn_create_db';
    const BUTTON_ENSURE_DB_SCHEMA = 'btn_set_db_schema';
    const BUTTON_GET_DB_SCHEMA = 'btn_get_db_schema';
    const BUTTON_INSERT_ADMIN = 'btn_insert_admin';
    const BUTTON_LOGIN = 'btn_login';
    const BUTTON_LOGOUT = 'btn_logout';
    const BUTTON_RESET_ADMIN_PASS = 'btn_reset_admin';
    const BUTTON_RETURN_TO_MAIN_MENU = 'btn_main';


    private string      $error                         = '';
    private string      $server;
    private string      $db;
    private string      $user;
    private Password    $password;
    private string      $schema;
    private Environment $environment;
    private bool        $schemaIsUpToDate              = false;
    private string      $message                       = '';
    private ?string     $currentByteShardSchemaVersion = '';
    private ?string     $currentAppSchemaVersion       = '';
    private bool        $showAdminUseCase;
    /**
     * @var array<int, string>
     */
    private array                  $changes      = [];
    private ?DBManagementInterface $dbManagement = null;

    public function __construct(Environment $environment)
    {
        if (class_exists('\config')) {
            $config                   = new \config();
            $appDbParameters          = $config->getDbParameters(Database\Enum\ConnectionType::ADMIN);
            $this->server             = $appDbParameters->server;
            $this->db                 = $appDbParameters->database;
            $this->schema             = $appDbParameters->schema;
            $this->password           = new Password();
            $this->password->password = $appDbParameters->password;
            $this->user               = $appDbParameters->username;
            if (isset($_SESSION) && !isset($_SESSION['SETUP'], $_SESSION['SETUP']['Init'])) {
                session_destroy();
            }
            if (!isset($_SESSION)) {
                ini_set('session.use_only_cookies', 1);
                ini_set('session.cookie_httponly', 1);
                ini_set('session.cookie_secure', 1);

                session_cache_limiter('nocache');
                session_start();
                if (!isset($_SESSION['SETUP'], $_SESSION['SETUP']['Init'])) {
                    $_SESSION['SETUP']['Init'] = false;
                }
            }
            $this->environment      = $environment;
            $this->showAdminUseCase = ($environment->getAuthenticationTarget() === Target::AUTH_TARGET_DB || $environment->getAuthenticationTarget() === Target::AUTH_TARGET_DEFINED_ON_DB);
            $this->processPostFields();
        }
    }

    /**
     * @throws Exception
     */
    public function showForm(): void
    {
        $this->printHtmlHeader();
        switch ($this->getUseCase()) {
            case 'login':
                $this->printLoginForm();
                break;
            case 'confirmDbCreation':
                $this->printConfirmCreateDatabaseForm();
                break;
            case 'mainMenu':
                $this->printMainMenu();
                break;
            case 'createAdmin':
                $this->printCreateAdminForm();
                break;
            case 'getDbSchema':
                $this->printCurrentSchema();
                break;
            case 'logout':
            default:
                session_destroy();
                $this->printLoginForm();
                break;
        }

        $this->printHtmlFooter();
    }

    /**
     * @throws Exception
     */
    private function getUseCase(): string
    {
        // check if the logout button was clicked
        if ($this->buttonWasClicked(self::BUTTON_LOGOUT)) {
            return 'logout';
        }
        // if init is incomplete, return to the login screen
        if ($_SESSION['SETUP']['Init'] === false) {
            return 'logout';
        }
        // if the db name is invalid, return to the login screen
        if (preg_match('/[^a-zA-Z0-9_\$]/', $this->db)) {
            $this->error = 'Invalid characters in Database name';
            return 'logout';
        }

        $this->initDBManagement();
        if ($this->dbManagement === null) {
            $this->error = 'Database layer could not be created';
            return 'logout';
        }
        // check if the database exists
        if ($this->dbManagement->databaseExists() === false) {
            // the database doesn't exist, check if the create db button was clicked
            if ($this->buttonWasClicked(self::BUTTON_CREATE_DB)) {
                // try to create the db
                $result = $this->dbManagement->createAndSelectDatabase();
                if ($result === false) {
                    $this->error = 'Database could not be created';
                    return 'logout';
                }
            } else {
                // database does not exist, show a dialogue to create it
                return 'confirmDbCreation';
            }
        }
        // the database must exist, try to select it
        if (!$this->dbManagement->selectDatabase()) {
            $this->error = 'Database could not be selected';
            return 'logout';
        }

        $useCase = 'mainMenu';
        // the login was successful, the database exists and was successfully selected.
        // check which button the user clicked and show the related content
        switch ($this->getClickedButton()) {
            case self::BUTTON_ENSURE_DB_SCHEMA:
                $this->ensureDbSchemaVersion($this->dbManagement);
                $this->message = 'DB Schema updated successfully';
                break;
            case self::BUTTON_RETURN_TO_MAIN_MENU:
                break;
            case self::BUTTON_GET_DB_SCHEMA:
                $useCase = 'getDbSchema';
                break;
            case self::BUTTON_CREATE_ADMIN:
                $useCase = 'createAdmin';
                break;
            case self::BUTTON_INSERT_ADMIN:
                $this->insertAdmin();
                break;
        }

        if ($this->dbManagement !== null) {
            // check if there are any scheme changes defined
            $this->changes = $this->getDBSchemaChanges($this->dbManagement);
            if (empty($this->changes)) {
                $this->schemaIsUpToDate = true;
            }
            // get the current versions stored in the table bs_schema
            $this->currentByteShardSchemaVersion = $this->dbManagement->getVersion();
            $this->currentAppSchemaVersion       = $this->dbManagement->getVersion('app_schema');
        }
        return $useCase;
    }

    private function buttonWasClicked(string $button): bool
    {
        if (array_key_exists($button, $_POST)) {
            return true;
        }
        return false;
    }

    private function getClickedButton(): string
    {
        if (array_key_exists(self::BUTTON_CREATE_ADMIN, $_POST)) {
            return self::BUTTON_CREATE_ADMIN;
        }
        if (array_key_exists(self::BUTTON_CREATE_DB, $_POST)) {
            return self::BUTTON_CREATE_DB;
        }
        if (array_key_exists(self::BUTTON_ENSURE_DB_SCHEMA, $_POST)) {
            return self::BUTTON_ENSURE_DB_SCHEMA;
        }
        if (array_key_exists(self::BUTTON_GET_DB_SCHEMA, $_POST)) {
            return self::BUTTON_GET_DB_SCHEMA;
        }
        if (array_key_exists(self::BUTTON_INSERT_ADMIN, $_POST)) {
            return self::BUTTON_INSERT_ADMIN;
        }
        if (array_key_exists(self::BUTTON_LOGIN, $_POST)) {
            return self::BUTTON_LOGIN;
        }
        if (array_key_exists(self::BUTTON_LOGOUT, $_POST)) {
            return self::BUTTON_LOGOUT;
        }
        if (array_key_exists(self::BUTTON_RESET_ADMIN_PASS, $_POST)) {
            return self::BUTTON_RESET_ADMIN_PASS;
        }
        if (array_key_exists(self::BUTTON_RETURN_TO_MAIN_MENU, $_POST)) {
            return self::BUTTON_RETURN_TO_MAIN_MENU;
        }
        return '';
    }

    private function getDBParameters(): Parameters
    {
        // parameters with empty database. We're trying to establish a connection first and then check if the database exists
        $parameters           = new Database\Struct\Parameters();
        $parameters->username = $this->user;
        $parameters->password = $this->password;
        $parameters->server   = $this->server;
        $parameters->database = '';
        return $parameters;
    }

    private function processPostFields(): void
    {
        if ($_SESSION['SETUP']['Init'] === false && (isset($_POST['user'], $_POST['pass'], $_POST['server'], $_POST['db']) && !empty($_POST['user']) && !empty($_POST['pass']) && !empty($_POST['server']) && !empty($_POST['db']))) {
            $_SESSION['SETUP']['DB']['User']   = $_POST['user'];
            $_SESSION['SETUP']['DB']['Pass']   = $_POST['pass'];
            $_SESSION['SETUP']['DB']['Server'] = $_POST['server'];
            $_SESSION['SETUP']['DB']['DB']     = $_POST['db'];
            $_SESSION['SETUP']['DB']['Schema'] = $_POST['schema'] ?? '';
            $_SESSION['SETUP']['Init']         = true;
        }
        if ($_SESSION['SETUP']['Init'] === true) {
            $this->db                 = $_SESSION['SETUP']['DB']['DB'];
            $this->schema             = $_SESSION['SETUP']['DB']['Schema'];
            $this->server             = $_SESSION['SETUP']['DB']['Server'];
            $this->user               = $_SESSION['SETUP']['DB']['User'];
            $this->password->password = $_SESSION['SETUP']['DB']['Pass'];
        }
    }

    /**
     * @return array<int, string>
     * @throws Exception
     */
    public function getDBSchemaChanges(DBManagementInterface $dbManagement): array
    {
        return $this->ensureDbSchemaVersion($dbManagement, true);
    }

    /**
     * @return array<int, string>
     */
    public function ensureDbSchemaVersion(DBManagementInterface $dbManagement, bool $dryRun = false): array
    {
        $result          = [];
        $state           = new State();
        $stateManagement = $this->getStateManagement($state);
        if ($stateManagement !== null) {
            $stateManagement->setDryRun($dryRun);

            $baseSchema = new BaseSchema($this->environment->getUserTableSchema(), $this->environment->getAuthenticationTarget());
            $dbManagement->setSchemaParameters($baseSchema->getBaseSchemaParameters());
            $baseSchemaVersions = $baseSchema->getBaseSchemaVersions();
            $mergeArray         = [];
            foreach ($baseSchemaVersions as $baseSchemaVersion) {
                $state->setVersion($baseSchemaVersion);
                $baseSchema->getBaseSchema($state, $baseSchemaVersion);
                $stateManagement->setVersion($baseSchemaVersion);
                $mergeArray[$baseSchemaVersion] = $stateManagement->ensureState(true, false);
            }

            if (!empty($mergeArray)) {
                foreach ($mergeArray as $version => $results) {
                    if (!empty($results)) {
                        $result[] = '/* Changes for byteShard Schema Version: '.$version.' */';
                        foreach ($results as $tmp) {
                            $result[] = $tmp.';';
                        }
                    }
                }
            }

            if ($this->environment instanceof SchemaInterface) {
                $mergeArray     = [];
                $schemaVersions = $this->environment->getSchemaVersions();

                foreach ($schemaVersions as $schemaVersion) {
                    $state->setVersion($schemaVersion);
                    $this->environment->getSchema($state, $schemaVersion);
                    $stateManagement->setVersion($schemaVersion);
                    $mergeArray[$schemaVersion] = $stateManagement->ensureState();
                }

                if (!empty($mergeArray)) {
                    foreach ($mergeArray as $version => $results) {
                        if (!empty($results)) {
                            if (!empty($result)) {
                                $result[] = '/* -------------------------------------------------------------------------------- */';
                            }
                            $result[] = '/* Changes for Application Schema Version: '.$version.' */';
                            foreach ($results as $tmp) {
                                $result[] = $tmp.';';
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    private function insertAdmin(): void
    {
        if (isset($_POST['adminUser'], $_POST['adminPass']) && !empty($_POST['adminUser']) && !empty($_POST['adminPass'])) {
            $username           = $_POST['adminUser'];
            $password           = new Password();
            $password->password = $_POST['adminPass'];
            $userSchema         = $this->environment->getUserTableSchema();
            $this->addOrUpdateUser($userSchema, $username, $password);
        }
    }

    /**
     * @throws Exception
     */
    public function insertAdminConsole(string $username, Password $password): void
    {
        if (!isset($this->dbManagement)) {
            $this->initDBManagement();
            $this->dbManagement?->selectDatabase();
        }
        $userSchema = $this->environment->getUserTableSchema();
        $this->addOrUpdateUser($userSchema, $username, $password);
    }

    private function printHtmlHeader(): void
    {
        $html = '<html lang="en">';
        $html .= '<head><meta content="text/html; charset=utf-8" http-equiv="Content-Type"><title>byteShard</title>';
        if (defined('BS_WEB_ROOT_DIR')) {
            $html .= '<link href="'.BS_WEB_ROOT_DIR.'/bs/css/setup.css" type="text/css" rel="stylesheet">';
        } else {
            $html .= '<link href="bs/css/setup.css" type="text/css" rel="stylesheet">';
        }
        $html .= '</head>';
        $html .= '<body>';
        $html .= '<div id="ContentFrame">';
        $html .= '<div class="MessageFrame">';
        $html .= '<div class="left">';
        $html .= '<div id="Gears"></div>';
        $html .= '</div><div class="right"><div class="inner_right">';
        $html .= '<div><h1>byteShard setup</h1></div>';
        $html .= ($this->message !== '') ? '<div id="StepMessage"><p>'.$this->message.'</p></div>' : '';
        $html .= '<div id="SetupForm">';
        print  $html;
    }

    private function printHtmlFooter(): void
    {
        $html = '</div>';
        $html .= ($this->error !== '') ? '<div id="Error">'.$this->error.'</div>' : '';
        $html .= '</div></div></div>';
        $html .= '</div>';
        $html .= '</body>';
        $html .= '</html>';
        print $html;
    }

    private function printLoginForm(): void
    {
        global $dbDriver;
        $html = '<form action="setup.php" method="post" name="setup">';
        $html .= $this->input('server', 'DB Server', '', $this->server);
        $html .= $this->input('user', 'DB User');
        $html .= $this->input('pass', 'DB Password', '', '', 'password');
        $html .= $this->input('db', 'Database', '', $this->db);
        // added field for postgres schema
        if ($dbDriver === Environment::DRIVER_PGSQL_PDO) {
            $html .= $this->input('schema', 'Database Schema', '', $this->schema);
        }
        /*$html .= '<p><label class="bs_input_underline" for="server">DB Server<input class="right" type="text" id="server" name="server" '.(($this->server !== '') ? 'value="'.$this->server.'"' : '').'></label></p>';
        $html .= '<p><label for="user">DB User<input class="right" type="text" name="user" id="user"></label></p>';
        $html .= '<p><label for="pass">DB Password<input class="right" type="password" name="pass" id="pass"></label></p>';
        $html .= '<p><label for="db">Database<input class="right" type="text" name="db" id="db" '.(($this->db !== '') ? 'value="'.$this->db.'"' : '').'></label></p>';*/
        $html .= '<input type="submit" class="bsButton" value="Login" name="'.self::BUTTON_LOGIN.'">';
        $html .= '</form>';
        print $html;
    }

    private function input(string $id, string $label, string $help = '', string $value = '', string $type = 'text'): string
    {
        $value = $value === '' ? '' : ' value="'.$value.'"';
        $html  = '<div class="bs_input">';
        $html  .= '<label for="'.$id.'">';
        $html  .= '<input required name="'.$id.'" type="'.$type.'" '.$value.'>';
        $html  .= '<span class="bs_input_label">'.$label.'</span>';
        if ($help !== '') {
            $html .= '<span class="bs_input_helper">'.$help.'</span>';
        }
        $html .= '</label></div>';
        return $html;
    }

    private function printConfirmCreateDatabaseForm(): void
    {
        global $dbDriver;
        if ($dbDriver === Environment::DRIVER_PGSQL_PDO) {
            $html = '<p>Database Schema '.$this->schema.' does not exist in '.$this->db.'<br>Create a new Database Schema now?</p>';
        } else {
            $html = '<p>Database '.$this->db.' does not exist.<br>Create a new Database now?</p>';
        }
        $html .= '<form action="setup.php" method="post" name="setup">';
        $html .= '<input class="bsButton" type="submit" value="Logout" name="'.self::BUTTON_LOGOUT.'">';
        if ($dbDriver === Environment::DRIVER_PGSQL_PDO) {
            $html .= '<input class="bsButton" type="submit" value="Create Schema" name="'.self::BUTTON_CREATE_DB.'">';
        } else {
            $html .= '<input class="bsButton" type="submit" value="Create Database" name="'.self::BUTTON_CREATE_DB.'">';
        }
        $html .= '</form>';
        print $html;
    }

    private function printCreateAdminForm(): void
    {
        $html = '<form action="setup.php" method="post" name="setup">';
        $html .= '<p><label for="adminUser">Admin User<input class="right" type="text" name="adminUser" id="adminUser" value="admin"></label></p>';
        $html .= '<p><label for="adminPass">Password<input class="right" type="password" name="adminPass" id="adminPass"></label></p>';
        $html .= '<input class="bsButton" type="submit" value="Return to Main Menu" name="'.self::BUTTON_RETURN_TO_MAIN_MENU.'">';
        $html .= '<input class="bsButton" type="submit" value="Create Admin User" name="'.self::BUTTON_INSERT_ADMIN.'">';
        $html .= '</form>';
        print $html;
    }

    private function printMainMenu(): void
    {
        $html     = '<p>Current byteShard Schema Version: '.$this->currentByteShardSchemaVersion.'</p>';
        $html     .= '<p>Current Application Schema Version: '.$this->currentAppSchemaVersion.'</p>';
        $html     .= '<form action="setup.php" method="post" name="setup">';
        $html     .= '<input class="bsButton" type="submit" value="Get Current Database Schema" name="'.self::BUTTON_GET_DB_SCHEMA.'">';
        $disabled = '';
        if ($this->schemaIsUpToDate === false) {
            $html     .= '<input class="bsButton" type="submit" value="Apply Database Version changes" name="'.self::BUTTON_ENSURE_DB_SCHEMA.'">';
            $disabled = 'disabled';
        }
        if ($this->showAdminUseCase === true) {
            $html .= '<input class="bsButton" type="submit" value="Create Admin User" name="'.self::BUTTON_CREATE_ADMIN.'" '.$disabled.'>';
        }
        $html .= '<input class="bsButton" type="submit" value="Logout" name="'.self::BUTTON_LOGOUT.'">';
        $html .= '</form>';
        if ($this->schemaIsUpToDate === false) {
            $html .= '<pre id="SQLChanges">';
            foreach ($this->changes as $change) {
                $html .= '<p>'.$change.'</p>';
            }
            $html .= '</pre>';
        }
        print $html;
    }

    private function printCurrentSchema(): void
    {
        $stateManagement = $this->getStateManagement();
        if ($stateManagement !== null) {
            $schema = $stateManagement->getSchema();
            $html   = '<form action="setup.php" method="post" name="setup">';
            $html   .= '<input class="bsButton" type="submit" value="Return to Main Menu" name="'.self::BUTTON_RETURN_TO_MAIN_MENU.'">';
            $html   .= '</form>';
            $html   .= '<pre id="SQLChanges">';
            $html   .= implode("\n", $schema);
            /*foreach ($schema as $item) {
                $html .= $item;
            }*/
            $html .= '</pre>';
            print $html;
        }
    }

    /**
     * @throws Exception
     */
    protected function addOrUpdateUser(Schema\DB\UserTable $userSchema, string $username, Password $password): void
    {
        $columnUserID         = $userSchema->getFieldNameUserId();
        $columnUsername       = $userSchema->getFieldNameUsername();
        $columnAuthTarget     = $userSchema->getFieldNameAuthenticationTarget();
        $columnServiceAccount = $userSchema->getFieldNameServiceAccount();
        $columnGrantLogin     = $userSchema->getFieldNameGrantLogin();
        $columnPassword       = $userSchema->getFieldNameLocalPassword();

        $params                        = [];
        $params[$columnUsername]       = $username;
        $params[$columnAuthTarget]     = Target::AUTH_TARGET_DB->value;
        $params[$columnServiceAccount] = 1;
        $params[$columnGrantLogin]     = 1;
        $params[$columnPassword]       = $password->hash();

        $tableName = $userSchema->getTableName();
        $this->dbManagement?->createOrUpdateAdminUser($columnUserID, $tableName, $columnUsername, $username, $params);
    }

    /**
     * @throws Exception
     */
    protected function initDBManagement(): void
    {
        $cn = Database::getConnection(Database\Enum\ConnectionType::ADMIN);

        global $dbDriver;
        if ($dbDriver !== Environment::DRIVER_PGSQL_PDO) {
            $cn->setParameters($this->getDBParameters());
        }

        $this->dbManagement = match ($dbDriver) {
            Environment::DRIVER_PGSQL_PDO => new PgsqlDBManagement($cn, $this->db, $this->schema),
            default                       => new MysqlDBManagement($cn, $this->db),
        };
    }

    /**
     * @throws Exception
     */
    public function getDBManagement(): ?DBManagementInterface
    {
        if (!isset($this->dbManagement)) {
            $this->initDBManagement();
        }
        return $this->dbManagement;
    }

    protected function getStateManagement(State $state = new State()): ?StateManagementInterface
    {
        if ($this->dbManagement !== null) {
            global $dbDriver;
            return match ($dbDriver) {
                Environment::DRIVER_PGSQL_PDO => new PgsqlStateManagement($this->dbManagement, $state),
                default                       => new MysqlStateManagement($this->dbManagement, $state)
            };
        }
        return null;
    }
}
