<?php

namespace Shopmonkeynl\ShopmonkeyCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopmonkeynl\ShopmonkeyCli\Services\InputOutput;
use Shopmonkeynl\ShopmonkeyCli\Services\ProjectSetupService;
use Shopmonkeynl\ShopmonkeyCli\Services\SettingsService;

class Init extends Command
{
    /**
     * The name of the command (the part after "bin/demo").
     *
     * @var string
     */
    protected static $defaultName = 'init';

    /**
     * The command description shown when running "php bin/demo list".
     *
     * @var string
     */
    protected static $defaultDescription = 'Authenticate and (re)install dependencies. Done automatically on the first pull or watch.';

    /**
     * Execute the command
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int 0 if everything went fine, or an exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $io              = new InputOutput($input, $output);
        $settingsService = new SettingsService();

        if (!$settingsService->authenticate($input, $output)) {
            return Command::FAILURE;
        }

        // An explicit init resets the project files to the defaults.
        (new ProjectSetupService())->setup($io, true);

        $io->right("Dependencies installed.");

        return Command::SUCCESS;
    }

}
