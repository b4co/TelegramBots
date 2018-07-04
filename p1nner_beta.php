<?php
define('BOT_TOKEN', 'API_KEY');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');
define('WEBHOOK_URL', 'WEBHOOK_COMPLETE_URL');

function apiRequestWebhook($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  header("Content-Type: application/json");
  echo json_encode($parameters);
  return true;
}

function exec_curl_request($handle) {
  $response = curl_exec($handle);

  if ($response === false) {
    $errno = curl_errno($handle);
    $error = curl_error($handle);
    error_log("Curl returned error $errno: $error\n");
    curl_close($handle);
    return false;
  }

  $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
  curl_close($handle);

  if ($http_code >= 500) {
    // do not wat to DDOS server if something goes wrong
    sleep(10);
    return false;
  } else if ($http_code != 200) {
    $response = json_decode($response, true);
    error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
    if ($http_code == 401) {
      throw new Exception('Invalid access token provided');
    }
    return false;
  } else {
    $response = json_decode($response, true);
    if (isset($response['description'])) {
      error_log("Request was successfull: {$response['description']}\n");
    }
    $response = $response['result'];
  }

  return $response;
}

function apiRequest($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  foreach ($parameters as $key => &$val) {
    // encoding to JSON array parameters, for example reply_markup
    if (!is_numeric($val) && !is_string($val)) {
      $val = json_encode($val);
    }
  }
  $url = API_URL.$method.'?'.http_build_query($parameters);

  $handle = curl_init($url);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);

  return exec_curl_request($handle);
}

function processMessage($message) {

  $message_id = $message['message_id'];
  $chat_id = $message['chat']['id'];

  if (isset($message['text'])) {

    $text = $message['text'];
    $title = $message['chat']['title'];

    $split = explode(" ", $text);
    $comando = $split[0];
    $username = $message['from']['username'];

    $admin_array = apiRequest('getChatAdministrators', array('chat_id' => $chat_id));

    $admin_list = array();
    foreach ($admin_array as $key => $value) {
      array_push($admin_list, $value['user']['username']);
    }


    switch ($comando) {
      case '/pin':
        $tmd5 = file_get_contents("msg_md5.p8");
        $a_tmd5 = explode("\n", $tmd5);
        $fmd5 = file_get_contents("file_md5.p8");
        $a_fmd5 = explode("\n", $fmd5);
        
        $msgid = $message['reply_to_message']['message_id'];
        
        if(array_key_exists("document", $message['reply_to_message']) && in_array($username, $admin_list) && strlen($msgid) > 1) {
          $md5_file = md5($message['reply_to_message']['document']['file_id']);

          if(in_array($md5_file, $a_fmd5)) {
            apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "parse_mode" => 'Markdown', "text" => "*Arquivo já foi salvo*"));  
          } else {
            array_push($a_fmd5, $md5_file);
            $rwf = implode("\n", $a_fmd5);
            file_put_contents("file_md5.p8", "");
            file_put_contents("file_md5.p8", $rwf);
            apiRequest("forwardMessage", array('chat_id' => "@pr1v8_board", "from_chat_id" => $chat_id, "message_id" => $msgid));
            apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "parse_mode" => 'Markdown', "text" => "*Mensagem salva em* @pr1v8\_board"));
          
          }
        } elseif (array_key_exists("text", $message['reply_to_message']) && in_array($username, $admin_list) && strlen($msgid) > 1) {
          $md5_txt = md5($message['reply_to_message']['text']);

          if(in_array($md5_txt, $a_tmd5)) {
            apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "parse_mode" => 'Markdown', "text" => "*Mensagem já foi salva*"));  
          } else {
            array_push($a_tmd5, $md5_txt);
            $rw = implode("\n", $a_tmd5);
            file_put_contents("msg_md5.p8", "");
            file_put_contents("msg_md5.p8", $rw);
            apiRequest("forwardMessage", array('chat_id' => "@pr1v8_board", "from_chat_id" => $chat_id, "message_id" => $msgid));
            apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "parse_mode" => 'Markdown', "text" => "*Mensagem salva em* @pr1v8\_board"));
          }
          
        }
        break;
        case '/ginfo':
          $ginfo = "Title: $title\nID: $chat_id";
          apiRequest("sendMessage", array('chat_id' => $chat_id, 'reply_to_message_id' => $message_id, 'text' => "$ginfo"));
        break;
      default:
        # code...
        break;
    }
  } else {
    
  }
}



if (php_sapi_name() == 'cli') {
  // if run from console, set or delete webhook
  apiRequest('setWebhook', array('url' => isset($argv[1]) && $argv[1] == 'delete' ? '' : WEBHOOK_URL));
  exit;
}


$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
  // receive wrong update, must not happen
  exit;
}

if (isset($update["message"])) {
  processMessage($update["message"]);
}
