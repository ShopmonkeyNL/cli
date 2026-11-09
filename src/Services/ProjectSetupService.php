<?php

namespace Shopmonkeynl\ShopmonkeyCli\Services;

/**
 * The one-time project setup that "init" does: gulp, the upload script, the
 * gulp files, editor recommendations and the prettier config.
 *
 * "pull" and "watch" run it automatically the first time, so running "init"
 * by hand is no longer required.
 */
class ProjectSetupService
{

    /**
     * Whether this folder has what "watch" needs.
     */
    public function isSetUp() {

        return count($this->missing()) === 0;

    }

    /**
     * What "watch" needs that this folder doesn't have yet.
     *
     * @return string[]
     */
    public function missing() {

        $root    = getcwd();
        $missing = [];

        if (!is_file($root . '/gulpfile.js')) {
            $missing[] = 'gulpfile.js';
        }
        if (!is_file($root . '/.functions/filewatcher.php')) {
            $missing[] = '.functions/filewatcher.php';
        }
        if (!is_dir($root . '/node_modules/gulp')) {
            $missing[] = 'node_modules/gulp';
        }
        if (!is_dir($root . '/node_modules/gulp-exec')) {
            $missing[] = 'node_modules/gulp-exec';
        }

        return $missing;

    }

    /**
     * Run the setup.
     *
     * @param bool $overwrite Replace files that already exist (what an explicit
     *                        "init" does). When false only missing files are
     *                        created, so nothing customised gets clobbered.
     */
    public function setup($io, $overwrite = false) {

        $this->installNodeDependencies($io);
        $this->ensureGulpCli($io);
        $this->createFilesAndFolders();
        $this->installWatcherFiles($overwrite);
        $this->addRecommendedExtensions($overwrite);
        $this->createPrettierConfig($overwrite);

    }

    /**
     * Whether the global gulp-cli can't drive this project's local gulp.
     *
     * gulp-cli 2.x only knows gulp 3 and 4, so with the gulp 5 that setup
     * installs it stops with "Unsupported gulp version". "gulp --version" does
     * not run that check, and running a task would start the watcher, so the
     * versions are compared instead. Null when it is fine or can't be told.
     */
    public function gulpProblem() {

        $package = getcwd() . '/node_modules/gulp/package.json';
        if (!$this->commandExists('gulp') || !is_file($package)) {
            return null;
        }

        $output = (string) @shell_exec('gulp --version 2>&1');
        $local  = json_decode((string) @file_get_contents($package), true)['version'] ?? null;

        $unsupported = stripos($output, 'Unsupported gulp version') !== false;

        if (!$unsupported && $local !== null && preg_match('/CLI version:\s*(\d+)\./i', $output, $cli)) {
            $unsupported = (int) $cli[1] < 3 && (int) explode('.', $local)[0] >= 5;
        }

        return $unsupported
            ? 'The global gulp-cli is too old for the gulp in this project ("Unsupported gulp version").'
            : null;

    }

    /**
     * Update the global gulp-cli when it is too old for the local gulp.
     *
     * @return bool true when gulp works afterwards
     */
    public function ensureGulpCli($io) {

        if ($this->gulpProblem() === null) {
            return true;
        }

        $io->info(' 🔧  The global gulp-cli is too old for this project, updating it...');
        $this->run($this->commandExists('pnpm') ? 'pnpm add -g gulp-cli@latest' : 'npm install -g gulp-cli@latest', $io);

        if ($this->gulpProblem() === null) {
            return true;
        }

        $path = trim((string) @shell_exec('command -v gulp 2>/dev/null'));
        $io->warning(sprintf(
            "gulp still reports \"Unsupported gulp version\". An old gulp is being run from %s.\nRemove it (for example \"npm uninstall -g gulp-cli\") and run \"pnpm add -g gulp-cli@latest\", then try again.",
            $path !== '' ? $path : 'your PATH'
        ));

        return false;

    }

    private function installNodeDependencies($io) {

        if (!$this->commandExists('node')) {
            $io->warning('Node.js was not found, so gulp could not be installed. Install Node.js and run "shopmonkey init".');
            return;
        }

        // Install pnpm for faster package management
        if (!$this->commandExists('pnpm')) {
            $this->run('npm install -g pnpm', $io);
        }

        // Install the gulp packages this project needs
        if (!is_dir(getcwd() . '/node_modules/gulp') || !is_dir(getcwd() . '/node_modules/gulp-exec')) {
            $this->run('pnpm add -D gulp gulp-exec', $io);
        }

        if (!$this->commandExists('gulp')) {
            $this->run('pnpm add -g gulp-cli', $io);
        }

    }

    private function createFilesAndFolders() {

        // Keep the dependencies and credentials out of git
        $gitignore = getcwd() . '/.gitignore';
        $current   = is_file($gitignore) ? file_get_contents($gitignore) : '';
        $lines     = array_map('trim', explode("\n", $current));
        $missing   = [];

        if (!in_array('/node_modules', $lines) && !in_array('node_modules', $lines)) {
            $missing[] = '/node_modules';
        }
        if (!in_array('config.json', $lines) && !in_array('/config.json', $lines)) {
            $missing[] = 'config.json';
        }

        if ($missing) {
            $separator = ($current !== '' && substr($current, -1) !== "\n") ? "\n" : '';
            file_put_contents($gitignore, $current . $separator . implode("\n", $missing) . "\n");
        }

        if (!is_dir(getcwd() . '/src/sass')) {
            mkdir(getcwd() . '/src/sass', 0755, true);
        }

    }

    private function installWatcherFiles($overwrite) {

        $sourceDir = __DIR__ . '/../WatcherFiles/';

        // The upload script always follows the installed CLI version
        (new WatcherFilesService())->sync();

        foreach (['gulpfile.js', 'gulp.config.js'] as $file) {
            $target = getcwd() . '/' . $file;
            if ($overwrite || !is_file($target)) {
                copy($sourceDir . $file, $target);
            }
        }

    }

    private function addRecommendedExtensions($overwrite) {

        $pathName   = getcwd() . '/.vscode';
        $file       = $pathName . '/extensions.json';
        $extensions = [
            'recommendations' => [
                'esbenp.prettier-vscode',
                'dbaeumer.vscode-eslint',
                'mblode.twig-language-2',
                'formulahendry.auto-rename-tag'
            ]
        ];

        if (is_file($file) && !$overwrite) {
            return;
        }

        if (!is_dir($pathName)) {
            mkdir($pathName, 0755, true);
        }

        file_put_contents($file, json_encode($extensions, JSON_PRETTY_PRINT));

    }

    private function createPrettierConfig($overwrite) {

        $file           = getcwd() . '/.prettierrc.json';
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

        if (is_file($file) && !$overwrite) {
            return;
        }

        file_put_contents($file, json_encode($prettierConfig, JSON_PRETTY_PRINT));

    }

    private function commandExists($command) {

        return trim((string) @shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null')) !== '';

    }

    private function run($command, $io) {

        // pnpm/npm print "Update available!" notices that only confuse people during setup.
        passthru('npm_config_update_notifier=false ' . $command, $exitCode);

        if ($exitCode !== 0) {
            $io->warning(sprintf('"%s" failed (exit code %d). You can retry with "shopmonkey init".', $command, $exitCode));
            return false;
        }

        return true;

    }

}
