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
        $this->createFilesAndFolders();
        $this->installWatcherFiles($overwrite);
        $this->addRecommendedExtensions($overwrite);
        $this->createPrettierConfig($overwrite);

    }

    /**
     * The gulp to run: the one installed in this project (gulp 5 ships its own
     * gulp-cli), so no global gulp-cli is needed and its PATH and version
     * problems can't get in the way. Falls back to a global gulp for projects
     * that don't have a local one. The path is relative, so run it from the
     * project root.
     */
    public function gulpCommand() {

        return is_file(getcwd() . '/node_modules/.bin/gulp') ? './node_modules/.bin/gulp' : 'gulp';

    }

    private function installNodeDependencies($io) {

        if (!$this->commandExists('node')) {
            $io->warning('Node.js was not found, so gulp could not be installed. Install Node.js and run "shopmonkey init".');
            return;
        }

        if ($this->hasLocalGulp()) {
            return;
        }

        // Everything goes into the project itself; nothing is installed globally.
        $hasNpm = $this->commandExists('npm');

        if ($this->commandExists('pnpm') && $this->run('pnpm add -D gulp gulp-exec', $io, !$hasNpm) && $this->hasLocalGulp()) {
            return;
        }

        if ($hasNpm) {
            $this->run('npm install --save-dev gulp gulp-exec', $io);
        } elseif (!$this->commandExists('pnpm')) {
            $io->warning('Neither pnpm nor npm was found, so gulp could not be installed.');
        }

    }

    private function hasLocalGulp() {

        return is_dir(getcwd() . '/node_modules/gulp') && is_dir(getcwd() . '/node_modules/gulp-exec');

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

    private function run($command, $io, $warn = true) {

        // pnpm/npm print "Update available!" notices that only confuse people during setup.
        passthru('npm_config_update_notifier=false ' . $command, $exitCode);

        if ($exitCode !== 0) {
            if ($warn) {
                $io->warning(sprintf('"%s" failed (exit code %d). You can retry with "shopmonkey init".', $command, $exitCode));
            }
            return false;
        }

        return true;

    }

}
