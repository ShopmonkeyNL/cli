<?php

namespace Shopmonkeynl\ShopmonkeyCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Shopmonkeynl\ShopmonkeyCli\Services\InputOutput;
use Shopmonkeynl\ShopmonkeyCli\Services\SettingsService;
use Shopmonkeynl\ShopmonkeyCli\Services\FileMetadataService;
use Shopmonkeynl\ShopmonkeyCli\Services\FileSizeService;
use Shopmonkeynl\ShopmonkeyCli\Services\MessageService;
use Shopmonkeynl\ShopmonkeyCli\Services\ProjectSetupService;
use Shopmonkeynl\ShopmonkeyCli\Services\ShopGuardService;

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
     * Templates that Lightspeed returned cut off at its size limit.
     *
     * @var array
     */
    private $truncated = [];

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Overwrite or remove local changes without asking.'
        );
    }

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
            $io->wrong('Pull cancelled, nothing was changed.');
            return Command::FAILURE;
        }

        // First run in this folder? Do what "init" does, so it doesn't have to be run by hand.
        $setup = new ProjectSetupService();
        if (!$setup->isSetUp()) {
            $io->info(' 🧰  First run in this folder, setting up gulp and the upload script (what "init" does)...');
            $setup->setup($io);
        }

        $theme_directory  = getcwd() . '/theme';
        if (!is_dir($theme_directory)) {
            mkdir($theme_directory, 0755, true);
        }

        $templates = $this->getTemplates($settings);
        $assets    = $this->getAssets($settings);

        // Never continue with an incomplete listing: everything that is "missing"
        // from it would be removed locally.
        if (empty($templates) || $assets === null) {
            $io->wrong('Could not fetch the theme from the shop, nothing was changed.');
            return Command::FAILURE;
        }

        $conflicts = $this->findLocalChanges($templates, $assets);
        if ($conflicts && !$input->getOption('force')) {
            $io->wrong(sprintf('%d local file(s) would be overwritten or removed:', count($conflicts)));
            $io->listing(array_map(function ($conflict) {
                return $conflict['key'] . ' (' . $conflict['reason'] . ')';
            }, array_slice($conflicts, 0, 15)));
            if (count($conflicts) > 15) {
                $io->text(sprintf('... and %d more.', count($conflicts) - 15));
            }

            if (!$io->confirm('Continue and lose these local changes?', false)) {
                $io->info('Pull cancelled, nothing was changed. Use --force to skip this check.');
                return Command::FAILURE;
            }
        }

        foreach ($templates as $template) {
            $this->saveTemplate($template);
        }
        foreach ($assets as $asset) {
            $this->saveAsset($asset);
        }

        $this->deleteDeletedFiles($templates, $assets);

        $this->saveThemeSettings($settings);

        $guard->remember($settings);

        $sizeService->report($io, $sizeService->scan($theme_directory));

        if ($this->truncated) {
            $io->error('Lightspeed cut these templates off at 64 KB, so they were NOT pulled (your local copy was left alone):');
            $io->listing($this->truncated);
            return Command::FAILURE;
        }

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

    /**
     * Do an authenticated GET against the shop admin and decode the JSON.
     */
    private function request($settings, $path)
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL            => $settings['shop_url'] . $path,
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

        $response = json_decode((string) $response, true);

        return is_array($response) ? $response : null;
    }

    private function getTemplates($settings)
    {
        $response = $this->request($settings, 'admin/themes/' . $settings['theme_id'] . '/templates.json');

        return $response['theme_templates'] ?? null;
    }

    private function saveThemeSettings($settings)
    {

        $response = $this->request($settings, 'admin/theme/manage/settings.json');

        file_put_contents($this->getThemePath('settings.json'), json_encode($response['theme_settings'] ?? null, JSON_PRETTY_PRINT));

        $response = $this->request($settings, 'admin/theme/preview/settings.json');

        file_put_contents($this->getThemePath('settings_data.json'), json_encode($response['theme_settings'] ?? null, JSON_PRETTY_PRINT));

    }

    private function getAssets($settings)
    {
        $response = $this->request($settings, 'admin/themes/' . $settings['theme_id'] . '/assets.json');

        return $response['theme_assets'] ?? null;
    }

    private function saveTemplate($template)
    {
        $messageService = new MessageService();
        $fileMetadataService = new FileMetadataService();
        $sizeService = new FileSizeService();

        // Lightspeed hands out templates above its limit cut off at exactly the
        // limit. Writing that to disk would make the watcher upload a broken file.
        if ($sizeService->status(strlen($template['content'])) === FileSizeService::TRUNCATED) {
            $this->truncated[] = $template['key'];
            $messageService->create('Truncated', ($template['key'] . ' was cut off by Lightspeed at 64 KB and was not pulled.'), 'red');
            return false;
        }

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
        $fileMetadataService->update($template['key'], $template['updated_at'], md5($template['content']));
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
        $content = @file_get_contents($asset['src']);
        if ($content === false) {
            $messageService->create('Failed', ($asset['key'] . ' could not be downloaded.'), 'red');
            return false;
        }
        $fileMetadataService->update($asset['key'], $asset['updated_at'], md5($content));
        file_put_contents($fullPath, $content);
        $messageService->create('Loaded', ($asset['key']. ' was loaded.'), 'green');
    }

    /**
     * Local files that this pull would overwrite or remove, even though they
     * differ from what we last pulled (i.e. work that only exists locally).
     */
    private function findLocalChanges($templates, $assets)
    {

        $metadata  = new FileMetadataService();
        $conflicts = [];

        // Files that the shop changed since the last pull and that we edited too.
        foreach ($templates as $template) {
            $path = $this->getThemePath($template['key']);
            $hash = $metadata->getHash($template['key']);

            if (!is_file($path) || $hash === null || $metadata->get($template['key']) == $template['updated_at']) {
                continue;
            }

            $local = md5_file($path);
            if ($local !== $hash && $local !== md5($template['content'])) {
                $conflicts[] = ['key' => $template['key'], 'reason' => 'edited locally and changed on the shop'];
            }
        }

        foreach ($assets as $asset) {
            $path = $this->getThemePath($asset['key']);
            $hash = $metadata->getHash($asset['key']);

            if (!is_file($path) || $hash === null || $metadata->get($asset['key']) == $asset['updated_at']) {
                continue;
            }

            if (md5_file($path) !== $hash) {
                $remote = @file_get_contents($asset['src']);
                if ($remote !== false && md5_file($path) !== md5($remote)) {
                    $conflicts[] = ['key' => $asset['key'], 'reason' => 'edited locally and changed on the shop'];
                }
            }
        }

        // Files that only exist locally and would be removed. When we pulled the
        // file and it is untouched, removing it loses nothing.
        foreach ($this->findLocalOnlyFiles($templates, $assets) as $key => $path) {
            $hash = $metadata->getHash($key);

            if ($hash === null || md5_file($path) !== $hash) {
                $conflicts[] = ['key' => $key, 'reason' => 'only exists locally, not on the shop'];
            }
        }

        return $conflicts;
    }

    /**
     * Local theme files (key => absolute path) that are not on the shop.
     */
    private function findLocalOnlyFiles($templates, $assets)
    {

        $production_paths = [];
        foreach ([$templates, $assets] as $items) {
            foreach ($items as $item) {
                $production_paths[] = $this->getThemePath($item['key']);
            }
        }

        $local_only = [];
        foreach(['layouts', 'pages', 'snippets', 'assets'] as $directory) {
            $path = $this->getThemePath($directory);
            if (!is_dir($path)) {
                continue;
            }

            foreach (array_diff(scandir($path), array('..', '.')) as $file) {
                $full = $path . '/' . $file;
                if (is_file($full) && !in_array($full, $production_paths, true)) {
                    $local_only[$directory . '/' . $file] = $full;
                }
            }
        }

        return $local_only;
    }

    private function deleteDeletedFiles($templates, $assets) {

        $messageService = new MessageService();
        $metadata = new FileMetadataService();

        foreach ($this->findLocalOnlyFiles($templates, $assets) as $key => $file) {
            unlink($file);
            $metadata->forget($key);
            $messageService->create('Deleted', ($file. ' was deleted.'), 'red');
        }

    }

}
