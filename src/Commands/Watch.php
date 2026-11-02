<?php

namespace Shopmonkeynl\ShopmonkeyCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopmonkeynl\ShopmonkeyCli\Services\FileSizeService;
use Shopmonkeynl\ShopmonkeyCli\Services\InputOutput;
use Shopmonkeynl\ShopmonkeyCli\Services\ProjectSetupService;
use Shopmonkeynl\ShopmonkeyCli\Services\SettingsService;
use Shopmonkeynl\ShopmonkeyCli\Services\ShopGuardService;
use Shopmonkeynl\ShopmonkeyCli\Services\WatcherFilesService;

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
        $io              = new InputOutput($input, $output);
        $settingsService = new SettingsService();
        $guard           = new ShopGuardService();
        $sizeService     = new FileSizeService();

        if (!$settingsService->authenticate($input, $output)) {
            return Command::FAILURE;
        }

        $settings = $settingsService->get($input, $output);

        $guard->announce($io, $settings);
        if (!$guard->verify($io, $settings)) {
            $io->wrong('Watch cancelled, nothing was uploaded.');
            return Command::FAILURE;
        }
        $guard->remember($settings);

        // First run in this folder? Do what "init" does, so it doesn't have to be run by hand.
        $setup = new ProjectSetupService();
        if (!$setup->isSetUp()) {
            $io->info(' 🧰  First run in this folder, setting up gulp and the upload script (what "init" does)...');
            $setup->setup($io);
        }

        // Keep the project's upload script in step with this CLI version, so
        // fixes reach existing projects without running "init" again.
        $sync = (new WatcherFilesService())->sync();
        if ($sync !== 'unchanged') {
            $io->info(sprintf(' 🔄  Upload script %s (.functions/filewatcher.php).', $sync));
        }

        $sizeService->report($io, $sizeService->scan(getcwd() . '/theme'));

        $command = 'gulp watch';
        passthru("{$command} 2>&1");

        return Command::SUCCESS;
    }

}