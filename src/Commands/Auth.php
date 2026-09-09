<?php

namespace Shopmonkeynl\ShopmonkeyCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopmonkeynl\ShopmonkeyCli\Services\SettingsService;
use Shopmonkeynl\ShopmonkeyCli\Services\MessageService;
use Shopmonkeynl\ShopmonkeyCli\Services\InputOutput;

class Auth extends Command
{
    /**
     * The name of the command (the part after "bin/demo").
     *
     * @var string
     */
    protected static $defaultName = 'auth';

    /**
     * The command description shown when running "php bin/demo list".
     *
     * @var string
     */
    protected static $defaultDescription = 'Authenticate first time.';

    /**
     * Execute the command
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int 0 if everything went fine, or an exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $settingsService = new SettingsService();

        return $settingsService->authenticate($input, $output)
            ? Command::SUCCESS
            : Command::FAILURE;
    }

}