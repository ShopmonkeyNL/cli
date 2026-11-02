<?php

namespace Shopmonkeynl\ShopmonkeyCli\Services;

class WatcherFilesService
{

    /**
     * The filewatcher.php that ships with this version of the CLI.
     */
    public function packagedFile() {

        return __DIR__ . '/../WatcherFiles/filewatcher.php';

    }

    /**
     * The copy inside the current project.
     */
    public function projectFile() {

        return getcwd() . '/.functions/filewatcher.php';

    }

    /**
     * Whether the project copy matches the packaged one.
     */
    public function isCurrent() {

        $project = $this->projectFile();

        return is_file($project) && md5_file($project) === md5_file($this->packagedFile());

    }

    /**
     * Make sure the project runs the filewatcher.php of this CLI version, so
     * fixes reach existing projects without running "init" again.
     *
     * @return string "created", "updated" or "unchanged"
     */
    public function sync() {

        $this->writeCliPointer();

        if ($this->isCurrent()) {
            return 'unchanged';
        }

        $project = $this->projectFile();
        $existed = is_file($project);

        if (!is_dir(dirname($project))) {
            mkdir(dirname($project), 0755, true);
        }

        copy($this->packagedFile(), $project);

        return $existed ? 'updated' : 'created';

    }

    /**
     * Tell the standalone upload script where this CLI and PHP live, so it can
     * run "check" before uploading. Rewritten on every sync, so it follows
     * updates of the CLI.
     */
    private function writeCliPointer() {

        $directory = dirname($this->projectFile());
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $cli = realpath(__DIR__ . '/../../bin/shopmonkey');
        $code = "<?php\n\nreturn " . var_export(['php' => PHP_BINARY, 'cli' => $cli ?: null], true) . ";\n";

        if (!is_file($directory . '/cli.php') || file_get_contents($directory . '/cli.php') !== $code) {
            file_put_contents($directory . '/cli.php', $code);
        }

    }

}
