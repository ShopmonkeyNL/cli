<?php

namespace Shopmonkeynl\ShopmonkeyCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopmonkeynl\ShopmonkeyCli\Services\InputOutput;
use Shopmonkeynl\ShopmonkeyCli\Services\SettingsService;
use Shopmonkeynl\ShopmonkeyCli\Services\FileMetadataService;
use Shopmonkeynl\ShopmonkeyCli\Services\MessageService;

class Pull extends Command
{
    /**
     * The name of the command (the part after "bin/demo").
     *
     * @var string
     */
    protected static $defaultName = 'pull';

    /**
     * The command description shown when running "php bin/demo list".
     *
     * @var string
     */
    protected static $defaultDescription = 'Pull current theme';

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
        $settings        = $settingsService->get($input, $output);
        $messageService = new MessageService();

        $availableToPull = $this->availableToPull($settings);
        if (!$availableToPull) {
            $io->wrong('Seems like you have to log in again.');
            $settings = $settingsService->update($input, $output);
            $this->execute($input, $output);
            return false;
        }

        $theme_directory  = getcwd() . '/theme';
        if (!is_dir($theme_directory)) {
            mkdir($theme_directory, 0755, true);
        }

        $templates = $this->getTemplates($settings);
        foreach ($templates as $template) {
            $this->saveTemplate($template);
        }
        $assets = $this->getAssets($settings);
        foreach ($assets as $asset) {
            $this->saveAsset($asset);
        }

        $this->deleteDeletedFiles($templates, $assets);

        $this->saveThemeSettings($settings);

        $io->right("Theme pulled successfuly from '" . $settings['shop_url'] . "'.");

        // TODO: pull settings
        // TODO: pull settings data

        return Command::SUCCESS;
    }

    private function getThemePath($path) {

        $path = preg_replace('/^\/+/', '', $path);
        $path = preg_replace('/\/+$/', '', $path);

        $base = getcwd() . '/theme/' . $path;

        return $base;

    }

    private function getTemplates($settings)
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL            => $settings['shop_url'] . 'admin/themes/' . $settings['theme_id'] . '/templates.json',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => array(
                'Accept: application/json, text/plain, */*',
                'Content-Type: application/json;charset=UTF-8',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: same-origin',
                'x-csrf-token: ' . $settings['csrf'],
                'Cookie: shared_session_id=' . $settings['backend_session_id'] . '; backend_session_id=' . $settings['backend_session_id'] . '; request_method=GET'
            ),
        ));

        $response = curl_exec($curl);

        $response = json_decode($response, true);

        return $response['theme_templates'];
    }

    private function saveThemeSettings($settings)
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL            => $settings['shop_url'] . 'admin/theme/manage/settings.json',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => array(
                'Accept: application/json, text/plain, */*',
                'Content-Type: application/json;charset=UTF-8',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: same-origin',
                'x-csrf-token: ' . $settings['csrf'],
                'Cookie: shared_session_id=' . $settings['backend_session_id'] . '; backend_session_id=' . $settings['backend_session_id'] . '; request_method=GET'
            ),
        ));

        $response = curl_exec($curl);

        $response = json_decode($response, true);

        file_put_contents($this->getThemePath('settings.json'), json_encode($response['theme_settings'], JSON_PRETTY_PRINT));

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL            => $settings['shop_url'] . 'admin/theme/preview/settings.json',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => array(
                'Accept: application/json, text/plain, */*',
                'Content-Type: application/json;charset=UTF-8',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: same-origin',
                'x-csrf-token: ' . $settings['csrf'],
                'Cookie: shared_session_id=' . $settings['backend_session_id'] . '; backend_session_id=' . $settings['backend_session_id'] . '; request_method=GET'
            ),
        ));

        $response = curl_exec($curl);

        $response = json_decode($response, true);

        file_put_contents($this->getThemePath('settings_data.json'), json_encode($response['theme_settings'], JSON_PRETTY_PRINT));

    }

    private function getAssets($settings)
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL            => $settings['shop_url'] . 'admin/themes/' . $settings['theme_id'] . '/assets.json',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => array(
                'Accept: application/json, text/plain, */*',
                'Content-Type: application/json;charset=UTF-8',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: same-origin',
                'x-csrf-token: ' . $settings['csrf'],
                'Cookie: shared_session_id=' . $settings['backend_session_id'] . '; backend_session_id=' . $settings['backend_session_id'] . '; request_method=GET'
            ),
        ));

        $response = curl_exec($curl);

        $response = json_decode($response, true);

        return $response['theme_assets'];
    }

    private function saveTemplate($template)
    {
        $messageService = new MessageService();
        $fileMetadataService = new FileMetadataService();
        $parts = pathinfo($template['key']);
        if (!is_dir($this->getThemePath($parts['dirname']))) {
            mkdir($this->getThemePath($parts['dirname']), 0755, true);
        }
        $fullPath = $this->getThemePath($parts['dirname'] . '/' . $parts['basename']);
        $updatedAt = $fileMetadataService->get($template['key']);
        if (file_exists($fullPath)) {
            if ($updatedAt == $template['updated_at']) {
                return false;
            }
        } 
        $fileMetadataService->update($template['key'], $template['updated_at']);
        file_put_contents($fullPath, $template['content']);
        $messageService->create('Loaded', ($template['key']. ' was loaded.'), 'green');
    }

    private function saveAsset($asset)
    {

        $messageService = new MessageService();
        $fileMetadataService = new FileMetadataService();
        $parts = pathinfo($asset['key']);
        if (!is_dir($this->getThemePath($parts['dirname']))) {
            mkdir($this->getThemePath($parts['dirname']), 0755, true);
        }
        $fullPath = $this->getThemePath($parts['dirname'] . '/' . $parts['basename']);
        $updatedAt = $fileMetadataService->get($asset['key']);
        if (file_exists($fullPath)) {
            if ($updatedAt == $asset['updated_at']) {
                return false;
            }
        } 
        $fileMetadataService->update($asset['key'], $asset['updated_at']);
        file_put_contents($fullPath, file_get_contents($asset['src']));
        $messageService->create('Loaded', ($asset['key']. ' was loaded.'), 'green');
    }

    private function availableToPull($settings)
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL            => $settings['shop_url'] . 'admin/themes/' . $settings['theme_id'] . '/templates.json',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => array(
                'Accept: application/json, text/plain, */*',
                'Content-Type: application/json;charset=UTF-8',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: same-origin',
                'x-csrf-token: ' . $settings['csrf'],
                'Cookie: shared_session_id=' . $settings['backend_session_id'] . '; backend_session_id=' . $settings['backend_session_id'] . '; request_method=GET'
            ),
        ));

        $response = curl_exec($curl);

        // $response = json_decode($response, true);

        if (!$response) {
            return false;
        } else {
            $response = json_decode($response, true);
            if (isset($response['error'])) {
                return false;
            } else {
                return true;
            }
        }
    }

    private function deleteDeletedFiles($templates, $assets) {

        $messageService = new MessageService();
        $base  = getcwd();
        $production_paths = [];
        foreach ([$templates, $assets] as $items) {
            foreach ($items as $item) {
                $production_paths[] = $this->getThemePath($item['key']);
            }
        }

        $local_paths = [];
        foreach(['layouts', 'pages', 'snippets', 'assets'] as $directory) {
            $path = $this->getThemePath($directory);
            $scanned_directory = array_diff(scandir($path), array('..', '.'));
            foreach ($scanned_directory as $file){
                $local_paths[] = $path . '/' . $file;
            }
        }

        $deleted_files = array_diff($local_paths, $production_paths);
        
        foreach($deleted_files as $file) {
            unlink($file);
            $messageService->create('Deleted', ($file. ' was deleted.'), 'red');
        }
        

    }

}
