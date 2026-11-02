<?php

// Lightspeed kapt templates af op precies dit aantal bytes (64 KB) zodra ze
// worden opgehaald. Een groter bestand uploaden is dus een valkuil: de volgende
// pull geeft een kapot bestand terug. Houd in sync met FileSizeService::LIMIT.
const SM_TEMPLATE_LIMIT = 65535;
const SM_WARN_RATIO = 0.8;

$settings = file_get_contents(__DIR__ . '/../config.json');
$settings = json_decode($settings, true);
$settings['path'] = $argv[1];
$settings['trigger'] = $argv[2];
if (file_exists($settings['path'])) {
   $settings['content'] = file_get_contents($settings['path']);
} else {
   $settings['content'] = '';
}
$themeFiles = new ThemeFiles();
$themeFiles->handle($settings);

class ThemeFiles
{

   public function handle($settings)
   {
      $action = '';
      $type = $this->getFileType($settings);
      $name = $this->getKey($settings);
      $result = null;

      // Templates boven de limiet worden niet geupload (SHOPMONKEY_ALLOW_LARGE=1 negeert dit).
      if ($type == 'template' && in_array($settings['trigger'], ['change', 'add']) && !getenv('SHOPMONKEY_ALLOW_LARGE')) {
         $bytes = strlen($settings['content']);
         if ($bytes == SM_TEMPLATE_LIMIT) {
            echo "Error: '" . $name . "' was NOT uploaded: it is exactly 64 KB, which looks like a file that Lightspeed cut off during a pull. Restore it from git and split it into snippets.\n";
            return;
         }
         if ($bytes > SM_TEMPLATE_LIMIT) {
            echo "Error: '" . $name . "' was NOT uploaded: it is " . $this->kb($bytes) . " and Lightspeed cuts templates off at 64 KB when pulling. Split it into snippets (or set SHOPMONKEY_ALLOW_LARGE=1 to upload it anyway).\n";
            return;
         }
      }

      // Eerst controleren op syntaxfouten e.d. (SHOPMONKEY_SKIP_CHECK=1 slaat dit over).
      if ($type == 'template' && in_array($settings['trigger'], ['change', 'add']) && !getenv('SHOPMONKEY_SKIP_CHECK')) {
         $problems = $this->runCheck($settings['path']);
         if ($problems !== null) {
            echo "Error: '" . $name . "' was NOT uploaded because the check failed (SHOPMONKEY_SKIP_CHECK=1 uploads it anyway):\n" . $problems . "\n";
            return;
         }
      }

      if ($settings['trigger'] == 'change') {
         $result = $this->updateFile($settings);
         $action = 'updated';
      }
      if ($settings['trigger'] == 'add') {
         $result = $this->createFile($settings);
         $action = 'created';
      }
      if ($settings['trigger'] == 'unlink') {
         $result = $this->deleteFile($settings);
         $action = 'deleted';
      }

      if ($result === null) {
         return;
      }

      $error = $this->getError($result, $settings['trigger'] == 'unlink');
      if ($error !== null) {
         echo "Error: '" . $name . "' was not " . $action . ": " . $error . "\n";
      } else {
         $key = $result['body']['theme_' . $type]['key'] ?? $name;
         echo "Success: The file '" . $key . "' was " . $action . " successfuly.\n";

         if ($type == 'template' && $settings['trigger'] != 'unlink') {
            $bytes = strlen($settings['content']);
            if ($bytes >= SM_TEMPLATE_LIMIT * SM_WARN_RATIO) {
               echo "Warning: '" . $name . "' is " . $this->kb($bytes) . ", " . round($bytes / SM_TEMPLATE_LIMIT * 100) . "% of the 64 KB template limit. Consider splitting it into snippets.\n";
            }
         }
      }
   }

   /**
    * Draait "shopmonkey check" op het bestand. Geeft null terug als het in orde is
    * (of als de CLI niet gevonden kan worden), anders de gevonden fouten.
    */
   private function runCheck($path)
   {
      $pointer = __DIR__ . '/cli.php';
      if (!is_file($pointer)) {
         return null;
      }

      $cli = include $pointer;
      if (empty($cli['php']) || empty($cli['cli']) || !is_file($cli['cli'])) {
         return null;
      }

      $command = escapeshellarg($cli['php']) . ' ' . escapeshellarg($cli['cli']) . ' check --short --no-ansi --no-interaction ' . escapeshellarg($path) . ' 2>&1';
      exec($command, $lines, $exitCode);

      $output = trim(implode("\n", $lines));
      if ($exitCode === 0) {
         if ($output !== '') {
            echo "Warning: " . trim(preg_replace('/\s+/', ' ', $output)) . "\n";
         }
         return null;
      }

      return $output !== '' ? $output : 'check failed';
   }

   private function updateFile($settings)
   {

      $type = $this->getFileType($settings);
      $key = $this->getKey($settings);

      // JSON-inhoud voorbereiden
      $json_content = json_encode(["theme_$type" => ["content" => $settings['content']]]);
      if ($json_content === false) {
         return $this->failure('could not encode the file as JSON (is it valid UTF-8?)');
      }

      // URL voorbereiden
      $url = $settings['shop_url'] . "admin/themes/" . $settings['theme_id'] . "/" . $type . "s?key=" . $key . "";

      return $this->request('PATCH', $url, $this->headers($settings, 'PATCH'), $json_content);
   }

   private function createFile($settings)
   {

      $file = basename($settings['path']);
      $type = $this->getFileType($settings);
      $fileArray = explode(".", $file);
      $fileTitle = $fileArray[0];
      $fileExtensions = array_slice($fileArray, 1);
      $fileExtension = implode('.', $fileExtensions);

      $send_data = [
         "theme_$type" => [
            "title" => $fileTitle
         ]
      ];

      if ($type == 'asset') {
         $send_data["theme_$type"]['extension'] = $fileExtension;
      }

      // JSON-inhoud voorbereiden
      $json_content = json_encode($send_data);

      // URL voorbereiden
      $url = $settings['shop_url'] . "admin/themes/" . $settings['theme_id'] . "/" . $type . "s";

      return $this->request('POST', $url, $this->headers($settings, 'PATCH'), $json_content);
   }

   private function deleteFile($settings)
   {

      $type = $this->getFileType($settings);
      $key = $this->getKey($settings);

      // URL voorbereiden
      $url = $settings['shop_url'] . "admin/themes/" . $settings['theme_id'] . "/" . $type . "s?key=" . $key . "";

      return $this->request('DELETE', $url, $this->headers($settings, 'GET'));
   }

   private function headers($settings, $requestMethod)
   {
      return [
         'Accept: application/json, text/plain, */*',
         'Content-Type: application/json;charset=UTF-8',
         'Sec-Fetch-Dest: empty',
         'Sec-Fetch-Mode: cors',
         'Sec-Fetch-Site: same-origin',
         "x-csrf-token: " . $settings['csrf'],
         "Cookie: shared_session_id=" . $settings['backend_session_id'] . "; backend_session_id=" . $settings['backend_session_id'] . "; request_method=" . $requestMethod,
      ];
   }

   // Een CURL-verzoek maken en uitvoeren
   private function request($method, $url, $headers, $body = null)
   {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      if ($body !== null) {
         curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
      }
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
      curl_setopt($ch, CURLOPT_TIMEOUT, 60);

      $raw = curl_exec($ch);
      $curlError = curl_errno($ch) ? curl_error($ch) : null;
      $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

      $body = json_decode((string) $raw, true);

      return [
         'status' => $status,
         'raw' => (string) $raw,
         'body' => is_array($body) ? $body : null,
         'curl_error' => $curlError,
      ];
   }

   private function failure($message)
   {
      return ['status' => 0, 'raw' => '', 'body' => null, 'curl_error' => null, 'message' => $message];
   }

   /**
    * Geeft null terug als het verzoek geslaagd is, anders een leesbare melding.
    */
   private function getError($result, $isDelete)
   {
      if (isset($result['message'])) {
         return $result['message'];
      }

      if ($result['curl_error'] !== null) {
         return 'could not reach the shop (' . $result['curl_error'] . ').';
      }

      $sessionHint = 'your session has expired. Stop the watcher (Ctrl+C), run "shopmonkey auth" and start "shopmonkey watch" again.';

      if (in_array($result['status'], [401, 403, 419]) || ($result['status'] >= 300 && $result['status'] < 400)) {
         return $sessionHint;
      }

      $body = $result['body'];
      $message = null;
      if (is_array($body)) {
         foreach (['error', 'message', 'errors'] as $field) {
            if (!empty($body[$field])) {
               $message = is_string($body[$field]) ? $body[$field] : json_encode($body[$field]);
               break;
            }
         }
      }

      if ($message !== null) {
         if (preg_match('/csrf|session|login|log in|authenticat/i', $message)) {
            return $message . ' - ' . $sessionHint;
         }
         return $message;
      }

      if ($result['status'] >= 400) {
         return 'the shop answered with HTTP ' . $result['status'] . '.';
      }

      if ($body === null && !($isDelete && trim($result['raw']) === '')) {
         // Een 200 zonder JSON is meestal de loginpagina.
         return 'unexpected response from the shop (HTTP ' . $result['status'] . '), ' . $sessionHint;
      }

      return null;
   }

   private function kb($bytes)
   {
      return number_format($bytes / 1024, 1) . ' KB';
   }

   private function getKey($settings)
   {
      $file = basename($settings['path']);
      $relativeFolderArray = explode('/', dirname($settings['path']));
      $relativeFolder = $relativeFolderArray[count($relativeFolderArray) - 1];

      return $relativeFolder . '/' . $file;
   }

   private function getFileType($settings)
   {
      $fileName = basename($settings['path']);
      $relativeFolderArray = explode('/', dirname($settings['path']));
      $type = $relativeFolderArray[count($relativeFolderArray) - 1];

      if ($type == "assets") {
         $type = "asset";
      } else {
         $type = "template";
      }

      return $type;
   }
}
