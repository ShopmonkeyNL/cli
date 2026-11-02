<?php

namespace Shopmonkeynl\ShopmonkeyCli\Services;

class FileSizeService
{

    /**
     * Lightspeed cuts a template off at exactly this many bytes when it is
     * pulled (64 KB). Uploading a file this big is therefore a trap: the next
     * pull returns a broken copy. Keep this in sync with filewatcher.php.
     */
    const LIMIT = 65535;

    /**
     * Start warning at this share of the limit.
     */
    const WARN_RATIO = 0.8;

    const OK        = 'ok';
    const NEAR      = 'near';
    const OVER      = 'over';
    const TRUNCATED = 'truncated';

    /**
     * Template folders the limit applies to (assets are not affected).
     */
    const TEMPLATE_DIRECTORIES = ['layouts', 'pages', 'snippets'];

    public function status($bytes) {

        if ($bytes == self::LIMIT) {
            return self::TRUNCATED;
        }

        if ($bytes > self::LIMIT) {
            return self::OVER;
        }

        if ($bytes >= self::LIMIT * self::WARN_RATIO) {
            return self::NEAR;
        }

        return self::OK;

    }

    public function format($bytes) {

        return number_format($bytes / 1024, 1) . ' KB';

    }

    /**
     * All local templates that are near, at or over the limit, biggest first.
     *
     * @return array<int, array{key: string, bytes: int, status: string}>
     */
    public function scan($themeDirectory) {

        $found = [];

        foreach (self::TEMPLATE_DIRECTORIES as $directory) {
            $path = $themeDirectory . '/' . $directory;
            if (!is_dir($path)) {
                continue;
            }

            foreach (scandir($path) as $file) {
                $full = $path . '/' . $file;
                if (!is_file($full)) {
                    continue;
                }

                $bytes  = filesize($full);
                $status = $this->status($bytes);
                if ($status !== self::OK) {
                    $found[] = ['key' => $directory . '/' . $file, 'bytes' => $bytes, 'status' => $status];
                }
            }
        }

        usort($found, function ($a, $b) {
            return $b['bytes'] <=> $a['bytes'];
        });

        return $found;

    }

    /**
     * Print a warning/error block for the given scan result.
     *
     * @return bool true when something at or over the limit was found.
     */
    public function report($io, array $found) {

        $blocking = array_filter($found, function ($file) {
            return $file['status'] === self::OVER || $file['status'] === self::TRUNCATED;
        });
        $near = array_filter($found, function ($file) {
            return $file['status'] === self::NEAR;
        });

        if ($blocking) {
            $io->error('These templates are at or over the 64 KB limit and will NOT be uploaded. Split them into snippets:');
            $io->listing(array_map([$this, 'line'], $blocking));
        }

        if ($near) {
            $io->warning('These templates are getting close to the 64 KB limit. Consider splitting them into snippets:');
            $io->listing(array_map([$this, 'line'], $near));
        }

        return count($blocking) > 0;

    }

    private function line($file) {

        $percentage = round($file['bytes'] / self::LIMIT * 100);

        return sprintf('%s: %s (%d%% of the limit)', $file['key'], $this->format($file['bytes']), $percentage);

    }

}
