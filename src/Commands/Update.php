<?php

namespace Shopmonkeynl\ShopmonkeyCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopmonkeynl\ShopmonkeyCli\Application;
use Shopmonkeynl\ShopmonkeyCli\Services\InputOutput;

class Update extends Command
{
    /**
     * The name of the command (the part after "bin/shopmonkey").
     *
     * @var string
     */
    protected static $defaultName = 'update';

    /**
     * The command description shown when running "bin/shopmonkey list".
     *
     * @var string
     */
    protected static $defaultDescription = 'Update the Shopmonkey CLI to the latest version.';

    /**
     * Execute the command
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int 0 if everything went fine, or an exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new InputOutput($input, $output);
        $package = Application::PACKAGE;

        $io->info(sprintf(' ⬆️  Updating %s (currently v%s)', $package, Application::VERSION));

        passthru(sprintf('composer global update %s 2>&1', escapeshellarg($package)), $exitCode);

        if ($exitCode !== 0) {
            $io->wrong(sprintf(
                'Update failed. Try running "composer global update %s" yourself.',
                $package
            ));

            return Command::FAILURE;
        }

        $io->right('Shopmonkey CLI is up to date. Run "shopmonkey --version" to check.');

        return Command::SUCCESS;
    }
}
