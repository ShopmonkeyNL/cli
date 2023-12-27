<?php

namespace Shopmonkeynl\ShopmonkeyCli\Services;

use Error;
use Spekulatius\PHPScraper\PHPScraper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopmonkeynl\ShopmonkeyCli\Services\InputOutput;

use Symfony\Component\Panther\Client;

class SettingsService
{

    public $file;

    public function __construct()
    {
        $this->file = getcwd() . '/config.json';
    }

    public function get($input, $output)
    {

        $file = $this->file;
        if (file_exists($file)) {
            $settings = file_get_contents($file);
            $settings = json_decode($settings, true);
            return $settings;
        } else {
            return $this->create($input, $output);
        }
    }

    public function update($input, $output)
    {

        $io = new InputOutput($input, $output);
        $file = $this->file;
        $current_settings = file_get_contents($file);
        $current_settings = json_decode($current_settings, true);

        $csrf = $io->question('Enter current CSRF token');
        $backend_session_id = $io->question('Enter current backend session ID');

        $new_settings = $current_settings;
        $new_settings['csrf'] = $csrf;
        $new_settings['backend_session_id'] = $backend_session_id;

        file_put_contents($file, json_encode($new_settings, JSON_PRETTY_PRINT));

        return $new_settings;
    }

    public function create($input, $output)
    {

        $io = new InputOutput($input, $output);
        $file = $this->file;
        $shop_url = $io->question('Enter shop URL');

        $parsed_url = parse_url($shop_url);
        $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . '/';

        $settings = $this->logIn($io, $base_url);

        file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT));

        return $settings;
    }

    public function authenticate($input, $output)
    {
        if (!$this->isPackageInstalled('dbrekelmans/bdi')) {
            echo 'package not installed';
            exec('composer require --dev dbrekelmans/bdi && vendor/bin/bdi detect drivers');
        }

        $settings = $this->get($input, $output);
        $io = new InputOutput($input, $output);

        $curl = curl_init();

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => $settings['shop_url'] . 'admin/themes/' . $settings['theme_id'] . '/templates.json',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Accept: application/json, text/plain, */*',
                    'Content-Type: application/json;charset=UTF-8',
                    'Sec-Fetch-Dest: empty',
                    'Sec-Fetch-Mode: cors',
                    'Sec-Fetch-Site: same-origin',
                    'x-csrf-token: ' . $settings['csrf'],
                    'Cookie: shared_session_id=' . $settings['backend_session_id'] . '; backend_session_id=' . $settings['backend_session_id'] . '; request_method=GET'
                ),
            )
        );

        $response = curl_exec($curl);

        curl_close($curl);
        // $response = json_decode($response, true);

        if (!$response) {
            return false;
        } else {
            $response = json_decode($response, true);
            if (isset($response['error'])) {

                $io->info("Please log in below");
                // $io->info(json_encode($settings, JSON_PRETTY_PRINT));
                $settings = $this->create($input, $output);
                $this->authenticate($input, $output);
            } else {
                $io->right("Authentication successful for '" . $settings['shop_url'] . "'.");
                return true;
            }
        }
    }

    private function logIn(InputOutput $io, string $base_url, bool $require_new_password = false)
    {
        $login_url = $base_url . 'admin/auth/login';
        $io->info('Logging in on: ' . $login_url);

        $client = Client::createChromeClient();
        $client->request('GET', $login_url);
        $crawler = $client->waitFor('#form_auth_login');
        $form = $crawler->filter('#form_auth_login')->form();

        $file = $this->file;
        if (file_exists($file)) {
            $currentSettings = file_get_contents($file);
            $currentSettings = json_decode($currentSettings, true);
        }

        if (isset($currentSettings) && $currentSettings['user']) {
            $user = $currentSettings['user'];
        } else {
            $user = $io->ask('Log in email address');
        }

        exec('security find-generic-password -a "' . $user . '" -s "shopmonkeycli" -w', $output, $returnCode);
        if ($returnCode === 0 && !$require_new_password) {
            $password = trim($output[0]);
        } else {
            $password = $io->askHidden('password');
            passthru('security add-generic-password -a "' . $user . '" -s "shopmonkeycli" -w "' . $password . '" -U');
        }

        $form->setValues([
            'login[email]' => $user,
            'login[password]' => $password
        ]);

        $client->submit($form);
        $client->waitFor('.Sidebar');

        // $io->info($client->getCurrentURL() === $login_url);
        if ($client->getCurrentURL() === $login_url) {
            $io->error('Could not log in');
            $client->quit();
            $this->logIn($io, $base_url, true);
            return false;
        }

        $crawler = $client->waitFor('[name="csrf-token"]');
        $csrf = $crawler->filter('[name="csrf-token"]')->first()->attr('content');
        $io->success('CSRF Token: ' . $csrf);

        $themes_url = $base_url . 'admin/themes.json';
        $client->request('GET', $themes_url);
        $crawler = $client->waitFor('pre');
        $text = $crawler->filter('pre')->getText();
        $themes_json = json_decode($text);
        $theme_id = $themes_json->themes[0]->id;

        $io->success('Theme ID: ' . $theme_id);

        $cookieJar = $client->getCookieJar();
        $cookie = $cookieJar->get('backend_session_id');
        $backend_session_id = $cookie->getValue();
        $io->success('Backend session ID: ' . $backend_session_id);

        return [
            'theme_id' => $theme_id,
            'csrf' => $csrf,
            'backend_session_id' => $backend_session_id,
            'shop_url' => $base_url,
            'user' => $user
        ];
    }

    /**
     * Checks if a composer package installed
     * 
     * @param string $packageName - The name of the package eg. 'vendor/package'
     * 
     * @return bool
     */
    public function isPackageInstalled($packageName): bool
    {
        // Run the `composer show` command to get a list of installed packages as an array
        exec('composer show -N', $output, $exitCode);

        if ($exitCode === 0) {
            // Return the opposite because if it is in array it is installed.
            return !in_array($packageName, $output);
        }

        return false;
    }
}
