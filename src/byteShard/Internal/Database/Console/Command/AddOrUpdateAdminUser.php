<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Console\Command;

use App\Config\ByteShard;
use byteShard\Exception;
use byteShard\Internal\Setup;
use byteShard\Password;
use config;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'user:admin')]
class AddOrUpdateAdminUser extends Command
{
    /**
     * @throws Exception
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!(class_exists('\\config') && class_exists('App\\Config\\ByteShard'))) {
            $output->writeln('class \\config and/or App\\Config\\ByteShard does not exist');
            return Command::FAILURE;
        }
        $config          = new config();
        $setupManagement = new Setup(ByteShard::getInstance($config));
        $password        = $input->getArgument('password');
        $newPassword     = false;
        $passwordObject  = new Password();
        if (!is_string($password)) {
            $passwordObject = Password::getPassword(20);
            $newPassword    = true;
        } else {
            $passwordObject->password = $password;
        }
        $userName = $input->getArgument('name');
        if (is_string($userName) && $passwordObject !== null) {
            $output->writeln('New Admin user added: name: '.$userName);
            if ($newPassword === true) {
                $output->writeln('initial password: '.$passwordObject->password);
            }
            $setupManagement->insertAdminConsole($userName, $passwordObject);
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Admin username')
             ->addArgument('password', InputArgument::OPTIONAL, 'Password');
    }
}
