<?php 
define('BOT_TOKEN', '');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

class Connection {
	private $cRun, $http_code;
	public $json;

	function mCurl($curl) {	
		$this->cRun = curl_exec($curl);

		if ($this->cRun === false) {
			$errno = curl_errno($curl);
			$error = curl_error($curl);
 			error_log("Curl returned error $errno: $error\n");
			curl_close($curl);
			return false;
		}

		$this->http_code = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
		curl_close($this->cRun);

		if ($this->http_code >= 500) {
			sleep(10);
			return false;
		} else if ($this->http_code != 200) {
			$this->json = json_decode($cRun, true);
			error_log("Request has failed with error {$json['error_code']}: {$json['description']}\n");
			if ($this->http_code == 401) {
				throw new Exception('Invalid access token provided');
			}
			return false;
		} else {
			$this->json = json_decode($cRun, true);
			if (isset($json['description'])) {
				error_log("Request was successfull: {$json['description']}\n");
			}
			$this->json = $json['result'];
  		}

		return $this->json;
	}
}
class API {
	private $url;
	public $curl;
	
	function apiRequest($method, $parameters) {

		foreach ($parameters as $key => &$value) {
			if (!is_numeric($value) && !is_string($value)) {
				$value = json_encode($value);
			}
		}

		$this->url = API_URL.$method.'?'.http_build_query($parameters);

		$this->curl = curl_init($this->url);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($this->curl, CURLOPT_TIMEOUT, 60);

		$conn = new Connection;
		return $conn->mCurl($this->curl);
	}
}

class Bot {
	private $comando, $chat_id, $msg_id, $api, $resposta, $content, $update, $run;

	function processMessage($mensagem) {
		$this->chat_id = $mensagem['chat']['id'];
		$this->msg_id = $mensagem['message_id'];
		
		if (isset($mensagem['text'])) {
			$comando = explode(" ", $mensagem['text'])[0];
			$api = new API;

			switch ($comando) {
				case '/hello':
					$this->resposta = "Hello Friend.";
					$api->apiRequest("sendMessage", 
						array('chat_id' => $this->chat_id, 
							'reply_to_message_id' => $this->msg_id, 
							'text' => $this->resposta
						));
					
					break;
				case '/code':
					$this->resposta = "#kopimi\n_pau no cu deles_ `&` *código na minha tela*";
					$api->apiRequest("sendMessage", 
						array('chat_id' => $this->chat_id, 
							'reply_to_message_id' => $this->msg_id, 
							'parse_mode' => "Markdown", 
							'text' => $this->resposta
						));
					break;
				
				default:
					# code...
					break;
			}
		}
	}

}

$content = file_get_contents("php://input");
$update = json_decode($content, true);
$bot = new Bot;
$api = new API;

if (php_sapi_name() == 'cli') {
  // if run from console, set or delete webhook
  $api->apiRequest('setWebhook', array('url' => isset($argv[1]) && $argv[1] == 'delete' ? '' : WEBHOOK_URL));
  exit;
}

if (!$update) {
	exit;
}

if (isset($update["message"])) {
	$bot->processMessage($update["message"]);
}
?>
