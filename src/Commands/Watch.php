<?php

namespace Shopmonkeynl\ShopmonkeyCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopmonkeynl\ShopmonkeyCli\Services\SettingsService;

class Watch extends Command
{
    /**
     * The name of the command (the part after "bin/demo").
     *
     * @var string
     */
    protected static $defaultName = 'watch';

    /**
     * The command description shown when running "php bin/demo list".
     *
     * @var string
     */
    protected static $defaultDescription = 'Watch files and push them to shop.';

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

        if (!$settingsService->authenticate($input, $output)) {
            return Command::FAILURE;
        }

        $command = 'gulp watch';
        passthru("{$command} 2>&1");

        return Command::SUCCESS;
    }

}