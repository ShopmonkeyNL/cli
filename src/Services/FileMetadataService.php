<?php

namespace Shopmonkeynl\ShopmonkeyCli\Services;
use Shopmonkeynl\ShopmonkeyCli\Services\InputOutput;

class FileMetadataService
{

    /**
     * Reserved keys inside files-metadata.json. Every other key is a theme
     * file key (e.g. "snippets/header.rain") mapped to its updated_at value.
     */
    const ORIGIN_KEY = '__origin';
    const HASHES_KEY = '__hashes';

    public $file;

    public function __construct() {
        $this->file = getcwd() . '/files-metadata.json';
    }

    public function get($key = false) {

        $current_data = $this->read();

        if (!$key) {
            return $current_data;
        } elseif (isset($current_data[$key])) {
            return $current_data[$key];
        } else {
            return false;
        }

    }

    /**
     * Store the remote updated_at of a file and, optionally, the md5 of the
     * content we wrote locally (used to detect local edits on the next pull).
     */
    public function update($key, $updated_at, $hash = null) {

        $new_data = $this->read();
        $new_data[$key] = $updated_at;

        if ($hash !== null) {
            $new_data[self::HASHES_KEY][$key] = $hash;
        }

        $this->write($new_data);

        return $new_data;

    }

    /**
     * The md5 of the content as it was last pulled, or null when unknown.
     */
    public function getHash($key) {

        $data = $this->read();

        return $data[self::HASHES_KEY][$key] ?? null;

    }

    /**
     * Drop everything we know about a file (after it was removed locally).
     */
    public function forget($key) {

        $data = $this->read();
        unset($data[$key], $data[self::HASHES_KEY][$key]);

        $this->write($data);

    }

    /**
     * The shop and theme this folder was last synced with, or null.
     */
    public function getOrigin() {

        $data = $this->read();
        $origin = $data[self::ORIGIN_KEY] ?? null;

        return is_array($origin) ? $origin : null;

    }

    public function setOrigin($shop_url, $theme_id) {

        $data = $this->read();
        $data[self::ORIGIN_KEY] = [
            'shop_url' => $shop_url,
            'theme_id' => (string) $theme_id,
        ];

        $this->write($data);

    }

    private function read() {

        $file = $this->file;

        if (!file_exists($file)) {
            file_put_contents($file, json_encode([], JSON_PRETTY_PRINT));
        }

        $data = json_decode(file_get_contents($file), true);

        return is_array($data) ? $data : [];

    }

    private function write($data) {

        file_put_contents($this->file, json_encode($data, JSON_PRETTY_PRINT));

    }

}
