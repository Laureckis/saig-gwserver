<?php
error_reporting(E_ERROR);
require_once("lib/sql.class.php");
$db = new sql();


while (@ob_end_clean())
	;
ignore_user_abort(true);
set_time_limit(1200);

ob_start();
/* Send reponse */
// Dequeue message and send +		action	"AASPGDialogueHerika1WhatTopic"	std::string

$startTime = time();


// Fake Close conection asap

//header('Content-Encoding: none');
//header('Content-Length: ' . ob_get_length());
//header('Connection: close');

//ob_end_flush();
//ob_flush();
//flush();

// Log here (we can be slower)


function parseResponse($responseText, $forceMood = "")
{

	global $db, $startTime;


	/* Split into sentences for better timing in-game */
	$sentences = preg_split('/(?<=[.!?])\s+/', $responseText, -1, PREG_SPLIT_NO_EMPTY);

	$splitSentences = [];
	$currentSentence = '';

	foreach ($sentences as $sentence) {
		$currentSentence .= ' ' . $sentence;
		if (strlen($currentSentence) > 120) {
			$splitSentences[] = trim($currentSentence);
			$currentSentence = '';
		} elseif (strlen($currentSentence) >= 60 && strlen($currentSentence) <= 120) {
			$splitSentences[] = trim($currentSentence);
			$currentSentence = '';
		}
	}

	if (!empty($currentSentence)) {
		$splitSentences[] = trim($currentSentence);
	}



	/*****************************/


	foreach ($splitSentences as $n => $sentence) {
		preg_match_all('/\((.*?)\)/', $sentence, $matches);

		$responseTextUnmooded = preg_replace('/\((.*?)\)/', '', $sentence);

		if ($forceMood) {
			$mood = $forceMood;
		} else
			$mood = $matches[1][0];

		$responseText = $responseTextUnmooded;

		if ($n == 0) { // TTS stuff for first sentence
			if ($GLOBALS["TTSFUNCTION"] == "azure") {
				if ($GLOBALS["AZURE_API_KEY"]) {
					require_once("tts/tts-azure.php");
					tts($responseTextUnmooded, $mood, $responseText);
				}
			}

			if ($GLOBALS["TTSFUNCTION"] == "mimic3") {
				if ($GLOBALS["MIMIC3"]) {
					require_once("tts/tts-mimic3.php");
					ttsMimic($responseTextUnmooded, $mood, $responseText);
				}
			}

			if ($GLOBALS["TTSFUNCTION"] == "11labs") {
				if ($GLOBALS["ELEVENLABS_API_KEY"]) {
					require_once("tts/tts-11labs.php");
					tts($responseTextUnmooded, $mood, $responseText);
				}
			}
			
			if ($GLOBALS["TTSFUNCTION"] == "gcp") {
				if ($GLOBALS["GCP_SA_FILEPATH"]) {
					require_once("tts/tts-gcp.php");
					tts($responseTextUnmooded, $mood, $responseText);
				}
			}
		}

		if ($sentence) {
			if (!$errorFlag) {
				$db->insert(
					'responselog',
					array(
						'localts' => time(),
						'sent' => 1,
						'text' => trim(preg_replace('/\s\s+/', ' ', SQLite3::escapeString($responseTextUnmooded))),
						'actor' => "{$GLOBALS["HERIKA_NAME"]}",
						'action' => "AASPGQuestDialogue2Topic1B1Topic",
						'tag' => $tag
					)
				);

				$outBuffer[]=array(
						'localts' => time(),
						'sent' => 1,
						'text' => trim(preg_replace('/\s\s+/', ' ', $responseTextUnmooded)),
						'actor' => "{$GLOBALS["HERIKA_NAME"]}",
						'action' => "AASPGQuestDialogue2Topic1B1Topic",
						'tag'=>$tag
					);
			}
			$db->insert(
				'log',
				array(
					'localts' => time(),
					'prompt' => nl2br(SQLite3::escapeString(json_encode($GLOBALS["DEBUG_DATA"], JSON_PRETTY_PRINT))),
					'response' => (SQLite3::escapeString(print_r($rawResponse, true) . $responseTextUnmooded)),
					'url' => nl2br(SQLite3::escapeString(print_r(base64_decode(stripslashes($_GET["DATA"])), true) . " in " . (time() - $startTime) . " secs "))


				)
			);

		} else {
			$db->insert(
				'log',
				array(
					'localts' => time(),
					'prompt' => nl2br(SQLite3::escapeString(json_encode($parms, JSON_PRETTY_PRINT))),
					'response' => (SQLite3::escapeString(print_r($rawResponse, true))),
					'url' => nl2br(SQLite3::escapeString(print_r(base64_decode(stripslashes($_GET["DATA"])), true) . " in " . (time() - $startTime) . " secs with ERROR STATE"))


				)
			);

		}
	}

	$responseDataMl = $outBuffer;
	foreach ($responseDataMl as $responseData)
		echo "{$responseData["actor"]}|{$responseData["action"]}|{$responseData["text"]}\r\n";

	echo 'X-CUSTOM-CLOSE';
	ob_end_flush();
	ob_flush();
	flush(); //header('Content-Encoding: none');
	//header('Content-Length: ' . ob_get_length());
	//header('Connection: close');

	foreach ($splitSentences as $n => $sentence) {

		preg_match_all('/\((.*?)\)/', $sentence, $matches);
		$responseTextUnmooded = preg_replace('/\((.*?)\)/', '', $sentence);

		if ($forceMood) {
			$mood = $forceMood;
		} else
			$mood = $matches[1][0];

		$responseText = $responseTextUnmooded;

		if ($n == 0) //First sentence was genetared
			continue;

		if ($GLOBALS["TTSFUNCTION"] == "azure") {
			if ($GLOBALS["AZURE_API_KEY"]) {
				require_once("tts/tts-azure.php");
				tts($responseTextUnmooded, $mood, $responseText);
			}
		}

		if ($GLOBALS["TTSFUNCTION"] == "mimic3") {
			if ($GLOBALS["MIMIC3"]) {
				require_once("tts/tts-mimic3.php");
				ttsMimic($responseTextUnmooded, $mood, $responseText);
			}
		}


		if ($GLOBALS["TTSFUNCTION"] == "11labs") {
			if ($GLOBALS["ELEVENLABS_API_KEY"]) {
				require_once("tts/tts-11labs.php");
				tts($responseTextUnmooded, $mood, $responseText);
			}
		}
		
		if ($GLOBALS["TTSFUNCTION"] == "gcp") {
			if ($GLOBALS["GCP_SA_FILEPATH"]) {
				require_once("tts/tts-gcp.php");
				tts($responseTextUnmooded, $mood, $responseText);
				}
		}

	}

}



try {
	$finalData = base64_decode(strtr($_GET["DATA"], array(" " => "+")));
	//$finalData = base64_decode($_GET["DATA"]);
	$finalParsedData = explode("|", $finalData);
	foreach ($finalParsedData as $i => $ele)
		$finalParsedData[$i] = trim(preg_replace('/\s\s+/', ' ', preg_replace('/\'/m', "''", $ele)));


	$finalParsedData[3] = @mb_convert_encoding($finalParsedData[3], 'UTF-8', 'UTF-8');
	if ($finalParsedData[0] == "init") { // Reset reponses if init sent (Think about this)
		$db->delete("eventlog", "gamets>{$finalParsedData[2]}  ");
		$db->delete("quests", "1=1");
		$db->delete("speech", "gamets>{$finalParsedData[2]}  ");
		$db->delete("currentmission", "gamets>{$finalParsedData[2]}  ");
		$db->delete("diarylog", "gamets>{$finalParsedData[2]}  ");
		$db->delete("books", "gamets>{$finalParsedData[2]}  ");
		$db->delete("memory", "gamets>{$finalParsedData[2]}  ");

		$db->delete("diarylogv2", "true");
		$db->execQuery("insert into diarylogv2 select topic,content,tags,people,location from diarylog");
		//die(print_r($finalParsedData,true));
		$db->update("responselog", "sent=0", "sent=1 and (action='AASPGDialogueHerika2Branch1Topic')");
		$db->insert(
			'eventlog',
			array(
				'ts' => $finalParsedData[1],
				'gamets' => $finalParsedData[2],
				'type' => $finalParsedData[0],
				'data' => $finalParsedData[3],
				'sess' => 'pending',
				'localts' => time()
			)
		);
		
		// Delete TTS(STT cache
		$directory = __DIR__.DIRECTORY_SEPARATOR."soundcache"; 

		$sixHoursAgo = time() - (6 * 60 * 60);

		$handle = opendir($directory);
		if ($handle) {
			while (false !== ($file = readdir($handle))) {
				$filePath = $directory . DIRECTORY_SEPARATOR . $file;

				if (is_file($filePath)) {
					$fileMTime = filemtime($filePath);
					if ($fileMTime < $sixHoursAgo) {
						@unlink($filePath);
					}
				}
			}
			closedir($handle);
		}


	} else if ($finalParsedData[0] == "request") { // Just requested response
		// Do nothing
		$responseDataMl = $db->dequeue();
		foreach ($responseDataMl as $responseData)
			echo "{$responseData["actor"]}|{$responseData["action"]}|{$responseData["text"]}\r\n";


	// NEW METHODS FROM HERE	
	} else if ($finalParsedData[0] == "_quest") {
		error_reporting(E_ALL);

		$questParsedData = json_decode($finalParsedData[3], true);
		//print_r($questParsedData);
		if (!empty($questParsedData["currentbrief"])) {
			$db->delete('quests',"id_quest='{$questParsedData["formId"]}' ");
			$db->insert(
				'quests',
				array(
					'ts' => $finalParsedData[1],
					'gamets' => $finalParsedData[2],
					'name' => $questParsedData["name"],
					'briefing' => $questParsedData["currentbrief"],
					'data' => json_encode($questParsedData["currentbrief2"]),
					'stage' => $questParsedData["stage"],
					'giver_actor_id' => $questParsedData["data"]["questgiver"],
					'id_quest' => $questParsedData["formId"],
					'sess' => 'pending',
					'status' => $questParsedData["status"],
					'localts' => time()
				)
			);
			
		}



	} else if ($finalParsedData[0] == "_questreset") {
		error_reporting(E_ALL);
		$db->delete("quests", "1=1");

	}  else if ($finalParsedData[0] == "_speech") {
		error_reporting(E_ALL);
		$speech = json_decode($finalParsedData[3], true);
		//print_r($questParsedData);
		
			$db->insert(
				'speech',
				array(
					'ts' => $finalParsedData[1],
					'gamets' => $finalParsedData[2],
					'listener' => $speech["listener"],
					'speaker' => $speech["speaker"],
					'speech' => $speech["speech"],
					'location' => $speech["location"],
					'sess' => 'pending',
					'localts' => time()
				)
			);

	} else if ($finalParsedData[0] == "book") {
		$db->insert(
			'books',
			array(
				'ts' => $finalParsedData[1],
				'gamets' => $finalParsedData[2],
				'title' => $finalParsedData[3],
				'sess' => 'pending',
				'localts' => time()
			)
		);
		
		$db->insert(
			'eventlog',
			array(
				'ts' => $finalParsedData[1],
				'gamets' => $finalParsedData[2],
				'type' => $finalParsedData[0],
				'data' => $finalParsedData[3],
				'sess' => 'pending',
				'localts' => time()
			)
		);
		
		
	}   else { // It's an event. Store it
		$db->insert(
			'eventlog',
			array(
				'ts' => $finalParsedData[1],
				'gamets' => $finalParsedData[2],
				'type' => $finalParsedData[0],
				'data' => $finalParsedData[3],
				'sess' => 'pending',
				'localts' => time()
			)
		);
	}

} catch (Exception $e) {
	syslog(LOG_WARNING, $e->getMessage());
}


// Queue for more responses. Be carefull here. This will efective send data to AI Chat.
// AASPGQuestDialogue2Topic1B1Topic will enqueue on ASAP TopicInfo
// AASPGDialogueHerika3Branch1Topic will enqueue on What do you know about this place? TopicInfo
// AASPGQuestDialogue2Topic1B1Topic will enqueue on What we should do now? TopicInfo


if ($finalParsedData[0] == "combatend") {
	require_once("chat/generic.php");
	$GLOBALS["DEBUG_MODE"] = false;
	require_once(__DIR__ . DIRECTORY_SEPARATOR . "prompts.php");

	if (isset($PROMPTS[$finalParsedData[0]]["extra"]["mood"]))
		$GLOBALS["FORCE_MOOD"] = $PROMPTS[$finalParsedData[0]]["extra"]["mood"];
	if (isset($PROMPTS[$finalParsedData[0]]["extra"]["force_tokens_max"]))
		$GLOBALS["OPENAI_MAX_TOKENS"] = $PROMPTS[$finalParsedData[0]]["extra"]["force_tokens_max"];
	if (isset($PROMPTS[$finalParsedData[0]]["extra"]["transformer"]))
		$GLOBALS["TRANSFORMER_FUNCTION"] = $PROMPTS[$finalParsedData[0]]["extra"]["transformer"];
	if (isset($PROMPTS[$finalParsedData[0]]["extra"]["dontuse"]))
		if (($PROMPTS[$finalParsedData[0]]["extra"]["dontuse"]))
			return "";

	$responseText = requestGeneric(
		($GLOBALS["FORCE_MOOD"]?"({$GLOBALS["FORCE_MOOD"]})":"").$PROMPTS["combatend"][rand(0,sizeof($PROMPTS["combatend"])-2)]);
	parseResponse($responseText);

} else if ($finalParsedData[0] == "location") { // Locations might be cached	// Disabled
	//require_once("chat/generic.php");
	//$GLOBALS["DEBUG_MODE"] = false;
	//$alreadyGenerated = $db->fetchAll(("select * from responselog where  sent=1 and tag='{$finalParsedData[3]}'"));
	//if (sizeof($alreadyGenerated) > 0) {
	//	$db->update("responselog", "sent=0", "sent=1 and tag='{$finalParsedData[3]}'");
	//	die();
	//}
	//requestGeneric("(Chat as Herika)","What do you think about last events?","AASPGDialogueHerika1WhatTopic");
	//requestGeneric("(Chat as Herika)","What should we do?","AASPGDialogueHerika2Branch1Topic");
	//require_once(__DIR__ . DIRECTORY_SEPARATOR . "prompts.php");
	//requestGeneric($PROMPTS["location"][0], $PROMPTS["location"][1], "AASPGDialogueHerika3Branch1Topic", 2, $finalParsedData[3]);

} else if ($finalParsedData[0] == "book") { // Books should be cached
	require_once("chat/generic.php");
	$GLOBALS["DEBUG_MODE"] = false;
	if (stripos($finalParsedData[3], 'note') !== false) // Avoid notes
		return;
	require_once(__DIR__ . DIRECTORY_SEPARATOR . "prompts.php");
	//$responseText = requestGeneric($PROMPTS["book"][0], $PROMPTS["book"][1], 'AASPGQuestDialogue2Topic1B1Topic', 1);
	//parseResponse($responseText);


} else if ($finalParsedData[0] == "quest") {
	/* Disabled
	   require_once("chat/generic.php");

	   preg_match('/"(.*?)"/', $finalParsedData[3], $matches);

	   $questName = $matches[1];

	   $GLOBALS["DEBUG_MODE"] = false;
	   require_once(__DIR__ . DIRECTORY_SEPARATOR . "prompts.php");
	   requestGeneric($PROMPTS["quest"][0], $PROMPTS["quest"][1], 'AASPGDialogueHerika2Branch1Topic', 5);
	   */

} else if ($finalParsedData[0] == "bleedout") {
	require_once("chat/generic.php");
	$GLOBALS["DEBUG_MODE"] = false;
	require_once(__DIR__ . DIRECTORY_SEPARATOR . "prompts.php");

	$responseText = requestGeneric($PROMPTS["bleedout"][0], $PROMPTS["bleedout"][1], 'AASPGQuestDialogue2Topic1B1Topic', 10);

	parseResponse($responseText);

} else if ($finalParsedData[0] == "bored") {
	require_once("chat/generic.php");
	$GLOBALS["DEBUG_MODE"] = false;
	require_once(__DIR__ . DIRECTORY_SEPARATOR . "prompts.php");
	$responseText = requestGeneric($PROMPTS["bored"][rand(1, sizeof($PROMPTS["bored"])-1)], $PROMPTS["bored"][0], 'AASPGQuestDialogue2Topic1B1Topic', 10);
	parseResponse($responseText);



} else if ($finalParsedData[0] == "goodmorning") {
	require_once("chat/generic.php");
	$GLOBALS["DEBUG_MODE"] = false;
	require_once(__DIR__ . DIRECTORY_SEPARATOR . "prompts.php");
	$responseText = requestGeneric($PROMPTS["goodmorning"][0], $PROMPTS["goodmorning"][1], 'AASPGQuestDialogue2Topic1B1Topic', 5);
	parseResponse($responseText);

} else if ($finalParsedData[0] == "inputtext") { // Highest priority, must return qeuee data
	require_once("chat/generic.php");
	$GLOBALS["DEBUG_MODE"] = false;

	$newString = preg_replace("/^[^:]*:/", "", $finalParsedData[3]); // Work here
	require_once(__DIR__ . DIRECTORY_SEPARATOR . "prompts.php");

	$responseText = requestGeneric($PROMPTS["inputtext"][0], $newString, 'AASPGQuestDialogue2Topic1B1Topic', 10);

	parseResponse($responseText);


} else if ($finalParsedData[0] == "inputtext_s") { // Highest priority, must return qeuee data
	require_once("chat/generic.php");
	$GLOBALS["DEBUG_MODE"] = false;

	$newString = preg_replace("/^[^:]*:/", "", $finalParsedData[3]); // Work here
	require_once(__DIR__ . DIRECTORY_SEPARATOR . "prompts.php");
	$responseText = requestGeneric($PROMPTS["inputtext_s"][0], $newString, 'AASPGQuestDialogue2Topic1B1Topic', 10);
	parseResponse($responseText, "whispering");


} else if ($finalParsedData[0] == "lockpicked") {
	require_once("chat/generic.php");

	$GLOBALS["DEBUG_MODE"] = false;
	require_once(__DIR__ . DIRECTORY_SEPARATOR . "prompts.php");
	$responseText = requestGeneric($PROMPTS["lockpicked"][0], $PROMPTS["lockpicked"][1], 'AASPGQuestDialogue2Topic1B1Topic', 5);
	parseResponse($responseText, "whispering");

	
} else if ($finalParsedData[0] == "force_current_task") {
	$db->insert(
			'currentmission',
			array(
				'ts' => $finalParsedData[1],
				'gamets' => $finalParsedData[2],
				'description' => SQLite3::escapeString($finalParsedData[3]),
				'sess' => 'pending',
				'localts' => time()
			)
	);
} 

?>
