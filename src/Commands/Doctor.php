<?php

namespace Shopmonkeynl\ShopmonkeyCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopmonkeynl\ShopmonkeyCli\Application;
use Shopmonkeynl\ShopmonkeyCli\Services\FileMetadataService;
use Shopmonkeynl\ShopmonkeyCli\Services\FileSizeService;
use Shopmonkeynl\ShopmonkeyCli\Services\InputOutput;
use Shopmonkeynl\ShopmonkeyCli\Services\ProjectSetupService;
use Shopmonkeynl\ShopmonkeyCli\Services\SettingsService;
use Shopmonkeynl\ShopmonkeyCli\Services\ShopGuardService;
use Shopmonkeynl\ShopmonkeyCli\Services\WatcherFilesService;

class Doctor extends Command
{
    /**
     * The name of the command (the part after "bin/shopmonkey").
     *
     * @var string
     */
    protected static $defaultName = 'doctor';

    /**
     * The command description shown when running "bin/shopmonkey list".
     *
     * @var string
     */
    protected static $defaultDescription = 'Check your setup: versions, tools, session and theme files.';

    /**
     * Number of failed checks.
     *
     * @var int
     */
    private $failures = 0;

    /**
     * Execute the command
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int 0 if everything went fine, or an exit code.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new InputOutput($input, $output);

        $io->section('Shopmonkey CLI');
        $this->checkVersion($io);

        $io->section('Tools');
        $this->checkTools($io);

        $io->section('Project (' . getcwd() . ')');
        $this->checkProject($io);

        $io->newLine();
        if ($this->failures > 0) {
            $io->wrong(sprintf('%d problem(s) found.', $this->failures));
            return Command::FAILURE;
        }

        $io->right('Everything looks good.');

        return Command::SUCCESS;
    }

    private function checkVersion($io)
    {
        $this->ok($io, 'CLI version', Application::VERSION);

        $latest = $this->latestVersion();
        if ($latest === null) {
            $this->warn($io, 'Latest version', 'could not be checked (offline?)');
        } elseif (version_compare($latest, Application::VERSION, '>')) {
            $this->warn($io, 'Latest version', sprintf('%s is available, run "shopmonkey update"', $latest));
        } else {
            $this->ok($io, 'Latest version', 'you are up to date');
        }
    }

    private function checkTools($io)
    {
        if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
            $this->ok($io, 'PHP', PHP_VERSION);
        } else {
            $this->fail($io, 'PHP', PHP_VERSION . ' (8.0 or higher required)');
        }

        if (extension_loaded('curl')) {
            $this->ok($io, 'PHP curl extension', 'loaded');
        } else {
            $this->fail($io, 'PHP curl extension', 'missing');
        }

        $this->tool($io, 'node', 'node -v', true);
        $this->tool($io, 'pnpm', 'pnpm -v', false);
        $this->tool($io, 'gulp', 'gulp --version', true);
    }

    private function checkProject($io)
    {
        $settingsService = new SettingsService();
        $settings        = $settingsService->read();

        if ($settings === null) {
            $this->warn($io, 'config.json', 'not found, run "shopmonkey auth" in your theme folder');
            return;
        }

        $this->ok($io, 'Shop', $settings['shop_url'] ?? '?');
        $this->ok($io, 'Theme', $settings['theme_id'] ?? '?');

        if ($settingsService->check($settings)) {
            $this->ok($io, 'Session', 'valid');
        } else {
            $this->fail($io, 'Session', 'expired or invalid, run "shopmonkey auth"');
        }

        $origin = (new FileMetadataService())->getOrigin();
        if ($origin === null) {
            $this->warn($io, 'Last synced shop', 'unknown yet (set after the next pull or watch)');
        } elseif ((new ShopGuardService())->differs($origin, $settings)) {
            $this->fail($io, 'Last synced shop', sprintf('%s (theme %s) differs from config.json', $origin['shop_url'], $origin['theme_id']));
        } else {
            $this->ok($io, 'Last synced shop', 'matches config.json');
        }

        $watcherFiles = new WatcherFilesService();
        if (!is_file($watcherFiles->projectFile())) {
            $this->warn($io, 'Upload script', '.functions/filewatcher.php missing (created on the next "shopmonkey watch")');
        } elseif ($watcherFiles->isCurrent()) {
            $this->ok($io, 'Upload script', 'up to date');
        } else {
            $this->warn($io, 'Upload script', 'outdated (updated on the next "shopmonkey watch")');
        }

        if ((new ProjectSetupService())->isSetUp()) {
            $this->ok($io, 'Gulp setup', 'gulpfile.js, upload script and gulp packages found');
        } else {
            $this->warn($io, 'Gulp setup', 'not set up yet (done automatically on the next pull or watch, or run "shopmonkey init")');
        }

        $sizeService = new FileSizeService();
        $found       = $sizeService->scan(getcwd() . '/theme');
        if (!is_dir(getcwd() . '/theme')) {
            $this->warn($io, 'Template sizes', 'no theme folder yet, run "shopmonkey pull"');
        } elseif (!$found) {
            $this->ok($io, 'Template sizes', 'all templates are well below the 64 KB limit');
        } else {
            $blocking = $sizeService->report($io, $found);
            if ($blocking) {
                $this->failures++;
            }
        }
    }

    private function tool($io, $label, $command, $required)
    {
        $version = trim((string) @shell_exec($command . ' 2>/dev/null | head -n 1'));

        if ($version !== '') {
            $this->ok($io, $label, $version);
        } elseif ($required) {
            $this->fail($io, $label, 'not found');
        } else {
            $this->warn($io, $label, 'not found');
        }
    }

    /**
     * Highest stable version on Packagist, or null when it can't be reached.
     */
    private function latestVersion()
    {
        $context = stream_context_create(['http' => ['timeout' => 3, 'header' => "User-Agent: shopmonkey-cli\r\n"]]);
        $json    = @file_get_contents('https://repo.packagist.org/p2/' . Application::PACKAGE . '.json', false, $context);
        $data    = $json ? json_decode($json, true) : null;

        if (!isset($data['packages'][Application::PACKAGE])) {
            return null;
        }

        $latest = null;
        foreach ($data['packages'][Application::PACKAGE] as $package) {
            $version = ltrim($package['version'], 'v');
            if (preg_match('/^\d+\.\d+\.\d+$/', $version) && ($latest === null || version_compare($version, $latest, '>'))) {
                $latest = $version;
            }
        }

        return $latest;
    }

    private function ok($io, $label, $detail)
    {
        $io->writeln(sprintf('  <info>✔</info> %-18s %s', $label, $detail));
    }

    private function warn($io, $label, $detail)
    {
        $io->writeln(sprintf('  <comment>!</comment> %-18s %s', $label, $detail));
    }

    private function fail($io, $label, $detail)
    {
        $this->failures++;
        $io->writeln(sprintf('  <error>✖</error> %-18s %s', $label, $detail));
    }
}
