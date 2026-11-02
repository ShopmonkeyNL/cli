<?php

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
      if ($settings['trigger'] == 'change') {
         $page = $this->updateFile($settings);
         $action = 'updated';
      }
      if ($settings['trigger'] == 'add') {
         $page = $this->createFile($settings);
         $action = 'created';
      }
      if ($settings['trigger'] == 'unlink') {
         $page = $this->deleteFile($settings);
         $action = 'deleted';
      }

      if (isset($page['error'])) {
         echo "Error: An error occurred.\n";
      } else {
         $type = $this->getFileType($settings);
         $key = $page['theme_' . $type]['key'];
         echo "Success: The file '" . $key . "' was " . $action . " successfuly.\n";
      }
   }

   private function updateFile($settings)
   {

      $type = $this->getFileType($settings);
      $file = basename($settings['path']);
      $relativeFolderArray = explode('/', dirname($settings['path']));
      $relativeFolder = $relativeFolderArray[count($relativeFolderArray) - 1];

      // JSON-inhoud voorbereiden
      $json_content = json_encode(["theme_$type" => ["content" => $settings['content']]]);

      // URL voorbereiden
      $url = $settings['shop_url'] . "admin/themes/" . $settings['theme_id'] . "/" . $type . "s?key=" . $relativeFolder . '/' . $file . "";

      // Headers instellen
      $headers = [
         'Accept: application/json, text/plain, */*',
         'Content-Type: application/json;charset=UTF-8',
         'Sec-Fetch-Dest: empty',
         'Sec-Fetch-Mode: cors',
         'Sec-Fetch-Site: same-origin',
         "x-csrf-token: " . $settings['csrf'],
         "Cookie: shared_session_id=" . $settings['backend_session_id'] . "; backend_session_id=" . $settings['backend_session_id'] . "; request_method=PATCH",
      ];

      // Data voorbereiden
      $settings = $json_content;

      // Een CURL-verzoek maken en uitvoeren
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $settings);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      $response = curl_exec($ch);

      $response = json_decode($response, true);

      return $response;
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

      // Headers instellen
      $headers = [
         'Accept: application/json, text/plain, */*',
         'Content-Type: application/json;charset=UTF-8',
         'Sec-Fetch-Dest: empty',
         'Sec-Fetch-Mode: cors',
         'Sec-Fetch-Site: same-origin',
         "x-csrf-token: " . $settings['csrf'],
         "Cookie: shared_session_id=" . $settings['backend_session_id'] . "; backend_session_id=" . $settings['backend_session_id'] . "; request_method=PATCH",
      ];

      // Data voorbereiden
      $settings = $json_content;

      // Een CURL-verzoek maken en uitvoeren
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $settings);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      $response = curl_exec($ch);

      $response = json_decode($response, true);

      return $response;
   }

   private function deleteFile($settings)
   {

      $type = $this->getFileType($settings);
      $file = basename($settings['path']);
      $relativeFolderArray = explode('/', dirname($settings['path']));
      $relativeFolder = $relativeFolderArray[count($relativeFolderArray) - 1];

      // URL voorbereiden
      $url = $settings['shop_url'] . "admin/themes/" . $settings['theme_id'] . "/" . $type . "s?key=" . $relativeFolder . '/' . $file . "";

      // Headers instellen
      $headers = [
         'Accept: application/json, text/plain, */*',
         'Content-Type: application/json;charset=UTF-8',
         'Sec-Fetch-Dest: empty',
         'Sec-Fetch-Mode: cors',
         'Sec-Fetch-Site: same-origin',
         "x-csrf-token: " . $settings['csrf'],
         "Cookie: shared_session_id=" . $settings['backend_session_id'] . "; backend_session_id=" . $settings['backend_session_id'] . "; request_method=GET",
      ];

      // Een CURL-verzoek maken en uitvoeren
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      $response = curl_exec($ch);

      $response = json_decode($response, true);

      return $response;
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
