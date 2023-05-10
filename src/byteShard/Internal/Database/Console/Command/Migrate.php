<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Console\Command;

use App\Config\ByteShard;
use byteShard\Database;
use byteShard\Exception;
use byteShard\Internal\Setup;
use config;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'migrate')]
class Migrate extends Command
{
    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!(class_exists('\\config') && class_exists('App\\Config\\ByteShard'))) {
            $output->writeln('class \\config and/or App\\Config\\ByteShard does not exist');
            return Command::FAILURE;
        }
        $config               = new config();
        $setupManagement      = new Setup(ByteShard::getInstance($config));
        $parameters           = $config->getDbParameters(Database\Enum\ConnectionType::ADMIN);
        $dbName               = $parameters->database;
        $parameters->database = '';

        $dbManagement = $setupManagement->getDBManagement();
        if ($dbManagement === null) {
            $output->writeln('Initialization failed');
            return Command::FAILURE;
        }
        if (!$dbManagement->databaseExists()) {
            $output->writeln('db '.$dbName.' does not exists');
            $dbManagement->createAndSelectDatabase();
            $output->writeln('created db '.$dbName);
        } else {
            $output->writeln('db '.$dbName.' already exists');
        }
        $dbManagement->selectDatabase();
        $result = $setupManagement->ensureDbSchemaVersion($dbManagement);
        foreach ($result as $line) {
            $output->writeln($line);
        }
        $output->writeln('done!');
        return Command::SUCCESS;
    }
}
