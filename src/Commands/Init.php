<?php

namespace Shopmonkeynl\ShopmonkeyCli\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopmonkeynl\ShopmonkeyCli\Services\SettingsService;
use Shopmonkeynl\ShopmonkeyCli\Services\InputOutput;

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
    protected static $defaultDescription = 'Authenticate and install dependencies.';

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
        $settingsService->authenticate($input, $output);

        $this->installWatcherFiles();
        $this->addRecommendedExtensions();
        $this->createPrettierConfig();

        $io->right("Dependencies installed.");

        return Command::SUCCESS;
    }

    private function installWatcherFiles()
    {

        $oldDir        = __DIR__ . '/../WatcherFiles/';
        $oldWatcher    = $oldDir . 'filewatcher.php';
        $newWatcher    = getcwd() . '/.functions/filewatcher.php';
        $oldGulpFile   = $oldDir . 'gulpfile.js';
        $newGulpFile   = getcwd() . '/gulpfile.js';
        $oldGulpConfig = $oldDir . 'gulp.config.js';
        $newGulpConfig = getcwd() . '/gulp.config.js';
        $oldGeckoDriver = __DIR__ .'/../src/drivers/geckodriver';
        $newGeckoDriver = getcwd() . '/geckodriver';

        $this->installNodeDependencies();
        $this->createFilesAndFolders();

        if (!is_dir(getcwd() . '/.functions')) {
            mkdir((getcwd() . '/.functions'), 0755, true);
        }

        copy($oldWatcher, $newWatcher);
        copy($oldGulpFile, $newGulpFile);
        copy($oldGulpConfig, $newGulpConfig);
        copy($oldGeckoDriver, $newGeckoDriver);
    }

    private function addRecommendedExtensions()
    {
        $pathName         = getcwd() . '/.vscode';
        $extensions       = [
            'recommendations' => [
                'esbenp.prettier-vscode',
                'dbaeumer.vscode-eslint',
                'mblode.twig-language-2',
                'formulahendry.auto-rename-tag'
            ]
        ];
        $extensionsString = json_encode($extensions, JSON_PRETTY_PRINT);

        if (!is_dir($pathName)) {
            mkdir(($pathName), 0755, true);
        }

        // Create and write extensions json file
        if (!file_exists($pathName . '/extensions.json')) {
            touch($pathName . '/extensions.json');
        }

        $extensionsFile = fopen($pathName . '/extensions.json', 'w');
        fwrite($extensionsFile, $extensionsString);
        fclose($extensionsFile);
    }

    private function createPrettierConfig()
    {
        $pathName       = getcwd();
        $prettierConfig = [
            'trailingComma'          => 'es5',
            'tabWidth'               => 2,
            'semi'                   => true,
            'singleQuote'            => true,
            'bracketSpacing'         => true,
            'bracketSameLine'        => false,
            'requirePragma'          => true,
            'singleAttributePerLine' => true,
        ];

        if (!file_exists($pathName . '/.prettierrc.json')) {
            touch($pathName . '/.prettierrc.json');
        }

        $prettierConfigFile = fopen($pathName . '/.prettierrc.json', 'w');
        fwrite($prettierConfigFile, json_encode($prettierConfig, JSON_PRETTY_PRINT));
        fclose($prettierConfigFile);
    }

    private function installNodeDependencies()
    {
        try {
            // Install pnpm for faster package management
            passthru('npm install -g pnpm');

            // Install necessary gulp packages & sass
            passthru('pnpm install -D gulp gulp-exec gulp-watch gulp-sass gulp-concat gulp-autoprefixer@8.0.0 del@6.1.1 gulp-uglifycss gulp-rename sass && pnpm install -g gulp-cli');
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    private function createFilesAndFolders()
    {
        try {
            passthru('touch .gitignore && echo "/node_modules\nconfig.json" >> .gitignore');
            passthru('mkdir src && mkdir src/sass &&');
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

}