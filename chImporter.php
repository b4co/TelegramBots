<?php 
/* 	alpha version -- channel importer bot
		todo => remove pkey from table(i used it only for testing) & better emoji remover
	set webhook on api: https://api.telegram.org/botAPI_KEY/setWebHook?url=https://someurl.com/path/file.php
 	if need help with setup or whatever: link_repo_0 */
class DB {
	private $sqlquery;
	function dbSave($msg_id, $channel_id, $channel_name, $channel_username, $hashtags, $title, $post) {
		// config to ur host, user, pass and db
		$dbase = new mysqli('host', 'username', 'password', 'database');
		// (int autoincrement), (int), (varchar), (text), (varchar), (text), (text), (text) 
		$this->sqlquery = "INSERT INTO `feed` (pkey, msg_id, channel_id, channel_name, channel_username, hashtags, title, post) VALUES (NULL,'$msg_id','$channel_id','$channel_name','$channel_username','$hashtags','$title','$post')";
		$dbase->query($this->sqlquery);
	}
}
class Bot {
	private $channel_id, $channel_name, $channel_username, $msg_id, $post, $title, $hashtags, $temp, $offset, $length;

	function processMessage($message) {
		$this->channel_id = $message['chat']['id'];
		$this->channel_name = $message['chat']['title'];
		$this->channel_username = $message['chat']['username'];
		$this->msg_id = $message['message_id'];
		$this->temp = $message['text'];

		
		if (isset($this->temp)) {
			$db = new DB;
			$this->post = $this->temp;
			// if there are #hashtags, markdown(bold) etc in message ; @ https://core.telegram.org/bots/api#messageentity
			if (array_key_exists("entities", $message)) {
				$entities = $message['entities'];
				// remove emoji(i dont want to import any emoji) and replace with 2 bytes(had a little bug doing this, so will fix) so entity offset works
				$this->temp = preg_replace('/([0-9|#][\x{20E3}])|[\x{00ae}|\x{00a9}|\x{203C}|\x{2047}|\x{2048}|\x{2049}|\x{3030}|\x{303D}|\x{2139}|\x{2122}|\x{3297}|\x{3299}][\x{FE00}-\x{FEFF}]?|[\x{2190}-\x{21FF}][\x{FE00}-\x{FEFF}]?|[\x{2300}-\x{23FF}][\x{FE00}-\x{FEFF}]?|[\x{2460}-\x{24FF}][\x{FE00}-\x{FEFF}]?|[\x{25A0}-\x{25FF}][\x{FE00}-\x{FEFF}]?|[\x{2600}-\x{27BF}][\x{FE00}-\x{FEFF}]?|[\x{2900}-\x{297F}][\x{FE00}-\x{FEFF}]?|[\x{2B00}-\x{2BF0}][\x{FE00}-\x{FEFF}]?|[\x{1F000}-\x{1F6FF}][\x{FE00}-\x{FEFF}]?/u', '¬&', $this->temp);
				// for $post, remove the chars
				$this->post = str_replace('¬&', '', $this->temp);
				$check = 0;
				$this->title = array();
				$this->hashtags = array();
				// read entities array
				foreach ($entities as $key => $entity) {
					// first bold text from message will be handled as the title
					if ($entity['type'] == "bold" && $check == 0) {
						$check = 1;
						$this->title[0] = intval($entities[$key]['offset']);
						$this->title[1] = intval($entities[$key]['length']);
					}
					// save any hashtags
					if($entity['type'] == "hashtag") {
						$this->offset = intval($entities[$key]['offset']);
						$this->length = intval($entities[$key]['length']);
						// get hashtag from ['text']
						$hash = substr($this->temp, $this->offset, $this->length);
						array_push($this->hashtags, $hash);
					}
				}
				// hashtags array to string
				$this->hashtags = implode(" ", $this->hashtags);			
			}
			// get title from ['text']
			$this->title = substr($this->temp, $this->title[0], $this->title[1]);
			// had a little bug bcause of line break, so if title happens to have a \n it will be removed
			$this->title = str_replace("\n", "", $this->title);
			// call func dbSave
			$db->dbSave($this->msg_id, $this->channel_id, $this->channel_name, $this->channel_username, $this->hashtags, $this->title, $this->post);

		}
	}
}
// get webhook json
$content = file_get_contents("php://input");
// json to array
$update = json_decode($content, true);
$bot = new Bot;

if (!$update) {
	// if any unwanted connection happens, exit
	exit;
}
if (isset($update["channel_post"])) {
	// ~ let the hacking begin ~
	$bot->processMessage($update["channel_post"]);
}
?>
