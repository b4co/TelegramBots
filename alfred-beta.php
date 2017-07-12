<?php
# change: YOUR_API_KEY & YOUR_WEBHOOK_URL & ADD_ADMIN_USERNAMES_HERE

define('BOT_TOKEN', 'YOUR_API_KEY');
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
  $title = $message['chat']['title'];


  #remove user from afklist.p8 if hes online & talks
  $nome = $message['from']['first_name'];
  if (isset($message['from']['username'])) {
    $username = $message['from']['username'];
  } else {
    $username = $nome;
  }

  $readafklist = file("afklist.p8", FILE_IGNORE_NEW_LINES);
  $areuserafk = in_array($username, $readafklist);
  if ($areuserafk) {
    $arraykey = array_search($username, $readafklist);
    unset($readafklist[$arraykey]);
    $reorganizedafklist = implode("\n", array_values($readafklist));
    $writeafkagain = fopen("afklist.p8", 'w+');
    fwrite($writeafkagain, $reorganizedafklist);
    fclose($writeafkagain);
  }

  if (isset($message['new_chat_member'])) {
    $nuser = $message['new_chat_member']['username'];
    $nnome = $message['new_chat_member']['first_name'];
    $nid = $message['new_chat_member']['id'];
    $log = "#new_user\nGrupo: $title\n\n#user_data\nNome: $nnome\nUsername: @$nuser\nid: #id$nid";
    $welcome = "Bem-vindx ao grupo $nnome, @$nuser.\nId: #id$nid\nLeia as regras na descrição do grupo :D.";
    apiRequest("sendMessage", array('chat_id' => $chat_id, 'text' => "$welcome"));
    apiRequest("sendMessage", array('chat_id' => "-1001104564232", 'text' => "$log"));

  }


  if (isset($message['text'])) {

    $text = $message['text'];

    $userid = $message['from']['id'];

    $split = explode(" ", $text);
    $comando = $split[0];
    $reason = substr(strstr("$text"," "), 1);

    $admin_list = array("ADD_ADMIN_USERNAMES_HERE");

    switch ($comando) {
      case '/ban':
        $iname = $message['reply_to_message']['from']['first_name'];
        $id = $message['reply_to_message']['from']['id'];
        $iusername = $message['reply_to_message']['from']['username'];
        $banlog = "#ban_user\nGrupo: $title\n\n#user_data\nNome: $iname\nUsername: @$iusername\nId: #id$id";

        if (isset($id) && in_array($username, $admin_list) && in_array($iusername, $admin_list) == false) {
          apiRequest("kickChatMember", array('chat_id' => $chat_id, 'user_id' => $id));
          apiRequest("sendMessage", array('chat_id' => "-1001104564232", 'text' => "$banlog"));
          $blacklist = file("blacklist.p8", FILE_IGNORE_NEW_LINES);
          if (in_array($iusername, $blacklist)) {

          } else {
            $blacklistme = array_push($blacklist, $iusername);
            $makemetxt = implode("\n", array_values($blacklist));
            $openblk = fopen("blacklist.p8", 'w+');
            fwrite($openblk, $makemetxt);
            fclose($openblk);
          }
          apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => "$iusername banido"));
        }
        break;

      case '/unban':
        $iname = $message['reply_to_message']['from']['first_name'];
        $id = $message['reply_to_message']['from']['id'];
        $iusername = $message['reply_to_message']['from']['username'];
        $openblk = file("blacklist.p8", FILE_IGNORE_NEW_LINES);
        
        if (in_array($iusername, $openblk) && in_array($username, $admin_list) && in_array($iusername, $admin_list) == false) {
          apiRequest("unbanChatMember", array('chat_id' => $chat_id, 'user_id' => $id));
          $srcblk = array_search($iusername, $openblk);
          unset($openblk[$srcblk]);
          $makemetxt = implode("\n", array_values($openblk));
          $writeagain = fopen("blacklist.p8", 'w+');
          fwrite($writeagain, $makemetxt);
          fclose($writeagain);
          apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => "$iname desbanido"));
        }
        break;

      case '/blacklist':
        $openblk = file_get_contents("blacklist.p8");
        if (strlen($openblk) < 1) {
          $openblk = "Nenhum usuário banido!";
        }
        apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => "$openblk"));
        break;


      case '/afk':
        unset($split[0]);
        $newsplit = array_values($split);
        $razao = implode(" ", $newsplit);
        $razao = (empty($razao) || strlen($razao) > 20 ? $afk_resposta = "*Usuário $nome está afk!*" : $afk_resposta = "*Usuário $nome está afk!\nRazão: $razao*");

        $readafklist = file("afklist.p8", FILE_IGNORE_NEW_LINES);
        $areuserafk = in_array($username, $readafklist);
        if ($areuserafk == false) {
          $addusertoafk = array_push($readafklist, $username);
          $newafklist = array_values($readafklist);
          $newafklistimplode = implode("\n", $newafklist);
          $writeafklist = fopen("afklist.p8", "w+");
          fwrite($writeafklist, $newafklistimplode);
          fclose($writeafklist);
        }

        apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "parse_mode" => 'Markdown', "text" => "$afk_resposta"));
        break;

      case '/back':
        if ($areuserafk) {
          $arraykey = array_search($username, $readafklist);
          unset($readafklist[$arraykey]);
          $reorganizedafklist = implode("\n", array_values($readafklist));
          $writeafkagain = fopen("afklist.p8", 'w+');
          fwrite($writeafkagain, $reorganizedafklist);
          fclose($writeafkagain);
        }
        apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "parse_mode" => 'Markdown', "text" => "*Usuário $nome está de volta!*"));
        break;

      case '/afklist':
        $openafklist = file_get_contents("afklist.p8");
        if (strlen($openafklist) < 1) {
          $openafklist = "Nenhum usuário está afk!";
        }
        apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => "$openafklist"));
        break;


      case '/adminlist':
        $admin_list_read = array("ADD_ADMIN_USERNAMES_HERE");
        $adminlist = implode("\n", $admin_list_read);
        apiRequest("sendMessage", array('chat_id' => $chat_id, 'reply_to_message_id' => $message_id, 'text' => "$adminlist"));
        break;


      case '/link':
        $link = apiRequest("exportChatInviteLink", array('chat_id' => $chat_id));
        apiRequest("sendMessage", array('chat_id' => $chat_id, 'reply_to_message_id' => $message_id, 'text' => $link));
        break;


      case '/promote':
        $id = $message['reply_to_message']['from']['id'];
        $iusername = $message['reply_to_message']['from']['username'];
        if(in_array($username, $admin_list)){
          $promote = apiRequest("promoteChatMember", array('chat_id' => $chat_id, 'user_id' => $id, 'can_change_info' => false, 'can_delete_messages' => true, 'can_invite_users' => true, 'can_restrict_members' => true, 'can_pin_messages' => true, 'can_promote_members' => false));
          if($promote)
            apiRequest("sendMessage", array('chat_id' => $chat_id, 'reply_to_message_id' => $message_id, 'text' => "Usuário $iusername agora é admin"));
        }
        
        break;

      case '/demote':
        $id = $message['reply_to_message']['from']['id'];
        $iusername = $message['reply_to_message']['from']['username'];
        if(in_array($username, $admin_list)){
          $demote = apiRequest("promoteChatMember", array('chat_id' => $chat_id, 'user_id' => $id, 'can_change_info' => false, 'can_delete_messages' => false, 'can_invite_users' => false, 'can_restrict_members' => false, 'can_pin_messages' => false, 'can_promote_members' => false));
          if($demote)
            apiRequest("sendMessage", array('chat_id' => $chat_id, 'reply_to_message_id' => $message_id, 'text' => "Usuário $iusername agora não é admin"));
        }

        break;

      case '/settopic':
        unset($split[0]);
        $topico = array_values($split);
        $topico = implode(" ", $topico);
        if(in_array($username, $admin_list)){
          $logtopic = "#new_topic\nGrupo: $title\nTopic: $topico\n\n#by_user\nUsername: @$username\nId: #id$userid";
          $setname = apiRequest("setChatTitle", array('chat_id' => $chat_id, 'title' => "$topico"));
          if($setname) {
            apiRequest("sendMessage", array('chat_id' => $chat_id, 'reply_to_message_id' => $message_id, 'text' => "Topico setado para $topico"));
            apiRequest("sendMessage", array('chat_id' => "-1001104564232", 'text' => "$logtopic"));
          }
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

      case '/pin':
        $msgid = $message['reply_to_message']['message_id'];
        if (isset($msgid))
          apiRequest("pinChatMessage", array('chat_id' => $chat_id, 'message_id' => $msgid));
        break;

      case '/pr1v8':
        apiRequest("sendMessage", array('chat_id' => $chat_id, 'reply_to_message_id' => $message_id, 'text' => "#kopimi @PR1V8"));
        break;

      default:
        # code...
        break;
    }
  } else {
    
  }
}

define('WEBHOOK_URL', 'YOUR_WEBHOOK_URL');

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
