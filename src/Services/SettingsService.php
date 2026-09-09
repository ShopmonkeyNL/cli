<?php

namespace Shopmonkeynl\ShopmonkeyCli\Services;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopmonkeynl\ShopmonkeyCli\Services\InputOutput;

class SettingsService
{
    
    public $file;

    public function __construct() {
        $this->file = getcwd() . '/config.json';
    }

    public function get($input, $output) {

        $file = $this->file;
        if (file_exists($file)) {
            $settings = file_get_contents($file); 
            $settings = json_decode($settings,true); 
            return $settings;
        } else {
            return $this->create($input, $output);
        }

    }

    public function update($input, $output) {

        $io = new InputOutput($input, $output);
        $file = $this->file;
        $current_settings = file_get_contents($file);
        $current_settings = json_decode($current_settings,true);

        $clipboard = $this->credentialsFromClipboard($io);
        if ($clipboard !== null) {
            $new_settings = array_merge($current_settings, $clipboard);
            file_put_contents($file, json_encode($new_settings, JSON_PRETTY_PRINT));
            return $new_settings;
        }

        $csrf = $io->question('Enter current CSRF token');
        $backend_session_id = $io->question('Enter current backend session ID');

        $new_settings = $current_settings;
        $new_settings['csrf'] = $csrf;
        $new_settings['backend_session_id'] = $backend_session_id;

        file_put_contents($file, json_encode($new_settings, JSON_PRETTY_PRINT));

        return $new_settings;

    }

    public function create($input, $output) {

        $io = new InputOutput($input, $output);
        $file = $this->file;

        $settings = $this->credentialsFromClipboard($io);

        if ($settings === null) {
            $pasted = (string) $io->question('Paste your credentials JSON (or leave empty to fill in each field)');
            if (trim($pasted) !== '') {
                $settings = $this->parseCredentials($pasted);
                if ($settings === null) {
                    $io->wrong('Could not read the pasted JSON, please fill in the fields below.');
                }
            }
        }

        if ($settings === null) {
            $shop_url = $io->question('Enter shop URL');
            $theme_id = $io->question('Enter theme ID');
            $csrf = $io->question('Enter current CSRF token');
            $backend_session_id = $io->question('Enter current backend session ID');

            $parsed_url = parse_url($shop_url);
            $base_url = (!empty($parsed_url['scheme']) && !empty($parsed_url['host']))
                ? $parsed_url['scheme'] . '://' . $parsed_url['host'] . '/'
                : rtrim($shop_url, '/') . '/';

            $settings = [
                'theme_id' => $theme_id,
                'shop_url' => $base_url,
                'csrf' => $csrf,
                'backend_session_id' => $backend_session_id
            ];
        }

        file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT));

        return $settings;

    }

    /**
     * Try to build a full credentials set from the macOS clipboard.
     *
     * Expects a JSON object with theme_id, shop_url, csrf and backend_session_id
     * (as produced by the "copy credentials" bookmarklet). Returns null when the
     * clipboard is unusable or the user declines, so callers can fall back to
     * asking for each field.
     */
    private function credentialsFromClipboard($io)
    {
        $raw = $this->readClipboard();
        if ($raw === null) {
            return null;
        }

        $credentials = $this->parseCredentials($raw);
        if ($credentials === null) {
            return null;
        }

        $io->info(sprintf(
            ' 📋  Found credentials on your clipboard: %s (theme %s)',
            $credentials['shop_url'],
            $credentials['theme_id']
        ));

        if (!$io->confirm('Use these credentials?', true)) {
            return null;
        }

        return $credentials;
    }

    /**
     * Read the current clipboard contents (macOS only).
     */
    private function readClipboard()
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            return null;
        }

        $raw = @shell_exec('pbpaste 2>/dev/null');

        return is_string($raw) && trim($raw) !== '' ? trim($raw) : null;
    }

    /**
     * Validate and normalise a raw JSON credentials string.
     */
    private function parseCredentials($raw)
    {
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return null;
        }

        foreach (['theme_id', 'shop_url', 'csrf', 'backend_session_id'] as $key) {
            if (empty($data[$key])) {
                return null;
            }
        }

        $parsed_url = parse_url($data['shop_url']);
        if (empty($parsed_url['scheme']) || empty($parsed_url['host'])) {
            return null;
        }

        return [
            'theme_id'           => (string) $data['theme_id'],
            'shop_url'           => $parsed_url['scheme'] . '://' . $parsed_url['host'] . '/',
            'csrf'               => (string) $data['csrf'],
            'backend_session_id' => (string) $data['backend_session_id'],
        ];
    }

    /**
     * Check whether the stored session is still valid.
     *
     * Hits an authenticated endpoint and returns true only when the shop
     * answers without an "error" payload. A missing or unreachable response
     * counts as "not authenticated" so callers re-run the login flow.
     */
    public function check($settings): bool
    {
        if (empty($settings['shop_url']) || empty($settings['theme_id'])) {
            return false;
        }

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
        curl_close($curl);

        if (!$response) {
            return false;
        }

        $response = json_decode($response, true);

        return is_array($response) && !isset($response['error']);
    }

    /**
     * Make sure we have a working session, running the login flow (clipboard →
     * paste → per field) until the credentials check out. Returns true once
     * authenticated, false when it gives up.
     */
    public function authenticate($input, $output) {

        $io       = new InputOutput($input, $output);
        $settings = $this->get($input, $output);

        $attempts = 0;

        while (!$this->check($settings)) {

            if (++$attempts > 3) {
                $io->wrong('Could not authenticate after 3 attempts. Aborting.');
                return false;
            }

            $io->wrong('Your session is expired or the credentials are invalid. Please log in again.');

            $settings = ($attempts === 1 && file_exists($this->file))
                ? $this->update($input, $output)
                : $this->create($input, $output);
        }

        $io->right("Authentication successful for '" . $settings['shop_url'] . "'.");

        return true;
    }

}