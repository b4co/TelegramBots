<?php


define('BOT_TOKEN', 'YOUR_APIKEY_HERE');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

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
  // process incoming message
  $message_id = $message['message_id'];
  $chat_id = $message['chat']['id'];
  if (isset($message['text'])) {
    // incoming text message
    $text = $message['text'];
    //$date = $message['date'];
    $title = $message['chat']['title'];
    $userid = $message['from']['id'];

    $split = explode(" ", $text);
    $comando = $split[0];
    $reason = substr(strstr("$text"," "), 1);
    $nome = $message['from']['first_name'];
    if (isset($message['from']['username'])) {
      $username = $message['from']['username'];
    } else {
      $username = $nome;
    }

    switch ($comando) {
      case '/afk':
        unset($split[0]);
        $newsplit = array_values($split);
        $razao = implode(" ", $newsplit);
        $razao = (empty($razao) || strlen($razao) > 20 ? $afk_resposta = "*Usuário $nome está afk!*" : $afk_resposta = "*Usuário $nome está afk!\nRazão: $razao*");

        $readafklist = file("afklist.txt", FILE_IGNORE_NEW_LINES);
        $areuserafk = in_array($username, $readafklist);
        if ($areuserafk == false) {
          $addusertoafk = array_push($readafklist, $username);
          $newafklist = array_values($readafklist);
          $newafklistimplode = implode("\n", $newafklist);
          $writeafklist = fopen("afklist.txt", "w+");
          fwrite($writeafklist, $newafklistimplode);
          fclose($writeafklist);
        }

        apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "parse_mode" => 'Markdown', "text" => "$afk_resposta"));  
        break;
      case '/afklist':
        $openafklist = file_get_contents("afklist.txt");
        if (strlen($openafklist) < 1) {
          $openafklist = "Nenhum usuário está afk!";
        }
        apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => "$openafklist"));
        break;
      case '/back':
        $readafklist = file("afklist.txt", FILE_IGNORE_NEW_LINES);
        $areuserafk = in_array($username, $readafklist);

        if ($areuserafk) {
          $arraykey = array_search($username, $readafklist);
          unset($readafklist[$arraykey]);
          $reorganizedafklist = implode("\n", array_values($readafklist));
          $writeafkagain = fopen("afklist.txt", 'w+');
          fwrite($writeafkagain, $reorganizedafklist);
          fclose($writeafkagain);
        }
        apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "parse_mode" => 'Markdown', "text" => "*Usuário $nome está de volta!*"));
        break;
      case '/pr1v8':
        apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "parse_mode" => 'Markdown', "text" => "#kopimi @PR1V8"));
        break;
      case '/limpar':
        $adme = array("pr1muS");
        if (in_array($username, $adme)) {
          $limparafk = fopen("afklist.txt", 'w+');
          fclose($limparafk);
          $limparblk = fopen("blacklist.txt", 'w+');
          fclose($limparblk);
          apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "parse_mode" => 'Markdown', "text" => "_Listas limpas!_"));
        }
        break;
      case '/ajuda':
        $listacomandos = "- /afk [razao]\n- /back\n- /afklist\n- /pr1v8\n- /ban\n- /blacklist\n- /unban";
        apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "parse_mode" => 'Markdown', "text" => "$listacomandos"));
        break;
      case '/rank':
        $valor = $split[1];
        $username = $message['reply_to_message']['from']['first_name'];
        $id = $message['reply_to_message']['from']['id'];
        if (isset($valor, $username, $id)) {
          apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "parse_mode" => 'Markdown', "text" => "User:$username\nID:$id\nValor:$valor\n"));  
        } else {
          apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "parse_mode" => 'Markdown', "text" => "Falta de valores pra comando /rank"));
        }
        break;
      case '/info':
        $iname = $message['reply_to_message']['from']['first_name'];
        $id = $message['reply_to_message']['from']['id'];
        $id = "#id$id";
        $iusername = $message['reply_to_message']['from']['username'];

        if (isset($iusername)) {
          $iusername = "@$iusername";
          $infos = "Username: $iusername\nNome: $iname\nID: $id";
        } else {
          $infos = "Nome: $iname\nID: $id";
        }
        apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => "$infos"));

        break;
      case '/grupo':
        apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => "Grupo: $title\nId: $chat_id"));
        break;
      case '/ban':
        $iname = $message['reply_to_message']['from']['first_name'];
        $id = $message['reply_to_message']['from']['id'];
        $iusername = $message['reply_to_message']['from']['username'];
        $admin = array("pr1muS", "vMoriarty", "CowFboy", "Xcaminhante");
        if (isset($id) && in_array($username, $admin)) {
          apiRequest("kickChatMember", array('chat_id' => $chat_id, 'user_id' => $id));
          $blacklist = file("blacklist.txt", FILE_IGNORE_NEW_LINES);
          if (in_array($iusername, $blacklist)) {

          } else {
            $blacklistme = array_push($blacklist, $iusername);
            $makemetxt = implode("\n", array_values($blacklist));
            $openblk = fopen("blacklist.txt", 'w+');
            fwrite($openblk, $makemetxt);
            fclose($openblk);
          }
          apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => "$iname banido"));
        }
        
        break;
      case '/unban':
        $iname = $message['reply_to_message']['from']['first_name'];
        $id = $message['reply_to_message']['from']['id'];
        $iusername = $message['reply_to_message']['from']['username'];
        $openblk = file("blacklist.txt", FILE_IGNORE_NEW_LINES);
        if (in_array($iusername, $openblk)) {
          apiRequest("unbanChatMember", array('chat_id' => $chat_id, 'user_id' => $id));
          $srcblk = array_search($iusername, $openblk);
          unset($openblk[$srcblk]);
          $makemetxt = implode("\n", array_values($openblk));
          $writeagain = fopen("blacklist.txt", 'w+');
          fwrite($writeagain, $makemetxt);
          fclose($writeagain);
          apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => "$iname desbanido"));
        }

        break;
      case '/blacklist':
        $openblk = file_get_contents("blacklist.txt");
        if (strlen($openblk) < 1) {
          $openblk = "Nenhum usuário banido!";
        }
        apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => "$openblk"));
        break;
      default:
        # code...
        break;
    }
  } else {
    
  }
}

define('WEBHOOK_URL', 'WEBHOOK_URL_HERE');

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
