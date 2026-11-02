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

}
