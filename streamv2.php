<?php
error_reporting(E_ERROR);

date_default_timezone_set('Europe/Madrid');

$path = dirname((__FILE__)) . DIRECTORY_SEPARATOR;
require_once($path . "conf.php");
require_once($path . "lib/$DRIVER.class.php");
require_once($path . "lib/Misc.php");
$db = new sql();

while (@ob_end_clean());

ignore_user_abort(true);
set_time_limit(1200);

$startTime=time();

$talkedSoFar=array();
$alreadysent=array();

function findDotPosition($string) {
    $dotPosition = strrpos($string, ".");
    
    if ($dotPosition !== false && strpos($string, ".", $dotPosition + 1) === false && substr($string, $dotPosition - 3, 3) !== "...") {
        return $dotPosition;
    }
    
    return false;
}



function split_sentences_stream($paragraph) {
    $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph, -1, PREG_SPLIT_NO_EMPTY);

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
	
	return $splitSentences;
}

function returnLines($lines) {
	
	global $db,$startTime,$forceMood,$staticMood,$talkedSoFar;
	foreach ($lines as $n=>$sentence) {

		// Remove actions
		$pattern = '/<[^>]+>/';
		$output = preg_replace($pattern, '', $sentence);
		
		$sentence=preg_replace('/[[:^print:]]/', '', $output);	// Remove non ASCII chracters
		
		$output=preg_replace('/\*([^*]+)\*/', '', $sentence);	// Remove text bewteen * *
		
		$sentence=preg_replace('/"/', '', $output);	// Remove "

		preg_match_all('/\((.*?)\)/', $sentence, $matches);
		
		$responseTextUnmooded = trim(preg_replace('/\((.*?)\)/', '', $sentence));
		
		if (stripos($responseTextUnmooded,"whispering:")!==false) {		// Very very nasty, but solves lots of isses. We must keep log clean.
			$responseTextUnmooded=str_ireplace("whispering:","",$responseTextUnmooded);
			$forceMood="whispering";
		}
		
		if (isset($forceMood)) {
			$mood = $forceMood;
		} else if (isset($matches[1][0]))
			$mood = $matches[1][0];
		else
			$mood = "default";
		
		if (isset($staticMood))
			$mood=$staticMood;
		else
			$staticMood=$mood;
		
		$responseText=$responseTextUnmooded;

		if (strlen($responseText)<2)		// Avoid too shor reponses
			return;
		
		
		if (strpos($responseText,"The Narrator:")!==false) {
			return;
		}
		
		if ($responseText) {
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
			if (trim($responseText))
				$talkedSoFar[]=$responseText;
		}
		
		$outBuffer=array(
						'localts' => time(),
						'sent' => 1,
						'text' => trim(preg_replace('/\s\s+/', ' ', $responseTextUnmooded)),
						'actor' => "Herika",
						'action' => "AASPGQuestDialogue2Topic1B1Topic",
						'tag'=>(isset($tag)?$tag:"")
					);
		$GLOBALS["DEBUG"]["BUFFER"][]="{$outBuffer["actor"]}|{$outBuffer["action"]}|$responseTextUnmooded\r\n";
		echo "{$outBuffer["actor"]}|{$outBuffer["action"]}|$responseTextUnmooded\r\n";
		@ob_flush();
		@flush();
		
		$db->insert(
				'log',
				array(
					'localts' => time(),
					'prompt' => nl2br(SQLite3::escapeString(print_r($GLOBALS["DEBUG_DATA"],true))),
					'response' => (SQLite3::escapeString($responseTextUnmooded)),
					'url' => nl2br(SQLite3::escapeString(print_r( base64_decode(stripslashes($_GET["DATA"])),true)." in ".(time()-$startTime)." secs " ))
					
				
				)
			);
	}
	
}

$starTime=microtime(true);

// PARSE GET RESPONSE
$finalData = base64_decode(stripslashes($_GET["DATA"]));
//$finalData = base64_decode("ZnVuY3JldHwxOTEyMDczNjIzMzQwMDB8NDQ5MzM3MTg0fGNvbW1hbmRASW5zcGVjdFN1cnJvdW5kaW5nc0BAQWRyaWFubmUgQXZlbmljY2ksQmVsZXRob3IsV2hpdGVydW4gR3VhcmQsQnJpbGwsUGx1Z2luZWVyLEhlcmlrYSxXaGl0ZXJ1biBHdWFyZCwNCg==");

$finalParsedData = explode("|", $finalData);
foreach ($finalParsedData as $i => $ele)
		$finalParsedData[$i] = trim(preg_replace('/\s\s+/', ' ', preg_replace('/\'/m', "''", $ele)));


if ($finalParsedData[0]=="info") {	// Output queues must be indepentent by type
	$db->insert(
				'eventlog',
				array(
					'ts' => $finalParsedData[1],
					'gamets' => $finalParsedData[2],
					'type' => $finalParsedData[0],
					'data' => SQLite3::escapeString($finalParsedData[3]),
					'sess' => 'pending',
					'localts' => time()
				)
			);
	die();
}

// PREPARE CONTEXT DATA
require_once(__DIR__ . DIRECTORY_SEPARATOR . "prompts.php");

$PROMPT_HEAD=($GLOBALS["PROMPT_HEAD"])?$GLOBALS["PROMPT_HEAD"]:"Let\'s roleplay in the Universe of Skyrim. I\'m {$GLOBALS["PLAYER_NAME"]} ";


//COMMAND Follow(NPC name): To Instruct Herika to start following the specified target, dont use if wanna engage. DONT FOLLOW {$GLOBALS["PLAYER_NAME"]}

$COMMAND_PROMPT="
To ensure a smooth and immersive experience, let's establish a set of roleplay rules:
Rule 1: Context will be provided by the The Narrator. Please avoid giving any context yourself.
Rule 2: Speak and respond as your character, remaining in roleplay at all times and do not describe actions
Rule 3: Don't ask questions to other characters besides {$GLOBALS["PLAYER_NAME"]}, they cannot hear you. 
Rule 4. DO NOT impersonate 'The Narrator'
";

require_once(__DIR__.DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR."functions.php");


$PROMPTS["inputtext"]=[
			"(put voice style in parenthesys,valid voice styles:" . 
			implode(",", (@is_array($GLOBALS["AZURETTS_CONF"]["validMoods"])?$GLOBALS["AZURETTS_CONF"]["validMoods"]:array())) . 
			", you can optionally call functions, complete herika's sentence) Herika: " // Prompt is implicit

		];
$PROMPTS["inputtext_s"]=[
			"(you can optionally call functions, complete herika's sentence) Herika: " // Prompt is implicit

		];

$PROMPTS["funcret"]=$PROMPTS["inputtext"];


$request=$PROMPTS[$finalParsedData[0]][0];	//*


if (stripos($finalParsedData[3],"stop")!==false) {
	echo "Herika|command|StopAll@\r\n";
	@ob_flush();
	$alreadysent[md5("Herika|command|StopAll@\r\n")]="Herika|command|StopAll@\r\n";
}

				
$commandSent=false;

if ($finalParsedData[0]=="inputtext_s") {
		$forceMood="whispering";
} 

if ($finalParsedData[0]=="funcret") {							// Overwrite funrect with info from database when topic requested
	$returnFunction = explode("@",$finalParsedData[3]);			// Function returns here
	if ($returnFunction[1]=="GetTopicInfo") {
		
		
	}
}

/// LOG INTO DB
$db->insert(
				'eventlog',
				array(
					'ts' => $finalParsedData[1],
					'gamets' => $finalParsedData[2],
					'type' => $finalParsedData[0],
					'data' => SQLite3::escapeString($finalParsedData[3]),
					'sess' => 'pending',
					'localts' => time()
				)
			);

$preprompt=preg_replace("/^[^:]*:/", "", $finalParsedData[3]);
$lastNDataForContext=25;
$contextData = $db->lastDataFor("",$lastNDataForContext*-1);	// Context (last dialogues, events,...)
$contextData2 = $db->lastInfoFor("",-2);						// Infot about location and npcs in first position


$contextDataFull=array_merge($contextData2,$contextData);

$head = array();
$foot = array();

$head[] = array('role' => 'user', 'content' => '('.$PROMPT_HEAD.$GLOBALS["HERIKA_PERS"].$COMMAND_PROMPT);


//$foot[] = array('role' => 'user', 'content' => $GLOBALS["PLAYER_NAME"].':' . $preprompt);

$url = 'https://api.openai.com/v1/chat/completions';

if ($finalParsedData[0]=="funcret") {
	$prompt[] = array('role' => 'assistant', 'content' => $request);
	
	$returnFunction = explode("@",$finalParsedData[3]);				// Function returns here

	
	if (isset($returnFunction[2])) {
		if ($returnFunction[1]=="GetTopicInfo") {
			$argName="topic";
			// Lets overwrite this
			// Get info about $returnFunction[2]}
			$returnFunction[3]="";
			
			
		} else {
			$argName="target";
			
		}
		$functionCalled[]=array('role' => 'assistant', 'content'=>null,'function_call'=>array("name"=>$returnFunction[1],"arguments"=>"{\"$argName\":\"{$returnFunction[2]}}"));
		
	}
	
	else
		$functionCalled[]=array('role' => 'assistant', 'content'=>null,'function_call'=>["name"=>$returnFunction[1],"arguments"=>"\"{}\""]);
	
	$returnFunctionArray[]=array('role' => 'function', 'name'=>$returnFunction[1],'content' => $returnFunction[3]);

	$returnFunctionArray[]=	 array('role' => 'assistant', 'content' => $request);


		
	$parms = array_merge($head, ($contextDataFull), $functionCalled,$returnFunctionArray);

	$data = array(
		'model' => 'gpt-3.5-turbo-0613',
		'messages' => 
			$parms
		,
		'stream' => true,
		'max_tokens'=>((isset($GLOBALS["OPENAI_MAX_TOKENS"])?$GLOBALS["OPENAI_MAX_TOKENS"]:48)+0),
		'temperature'=>1,
		'presence_penalty'=>1
		//'function_call'=>'none'
		
	);
	
} else {
	//$parms = array_merge($head, ($contextDataFull), $foot, $prompt);
	$prompt[] = array('role' => 'assistant', 'content' => $request);
	$parms = array_merge($head, ($contextDataFull), $prompt);
		$data = array(
		'model' => 'gpt-3.5-turbo-0613',
		'messages' => 
			$parms
		,
		'stream' => true,
		'max_tokens'=>((isset($GLOBALS["OPENAI_MAX_TOKENS"])?$GLOBALS["OPENAI_MAX_TOKENS"]:48)+0),
		'temperature'=>1,
		'presence_penalty'=>1,
		'functions'=>$GLOBALS["FUNCTIONS"],
		'function_call'=>'auto'
	);
}

//print_r($data);
//die();
$GLOBALS["DEBUG_DATA"][]=$data;

$headers = array(
    'Content-Type: application/json',
    "Authorization: Bearer {$GLOBALS["OPENAI_API_KEY"]}"
);

$options = array(
    'http' => array(
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => json_encode($data)
    )
);



$context = stream_context_create($options);

///////DEBUG CODE
$fileLog = fopen("log.txt", 'a');
fwrite($fileLog, ">>".$finalParsedData[3] . PHP_EOL); // Write the line to the file with a line break // DEBUG CODE

fwrite($fileLog, ">>".print_r($data["messages"],true) . PHP_EOL); // Write the line to the file with a line break // DEBUG CODE


$fileLogSent = fopen("logSent.txt", 'a');				// Will LOG OpenAI calls
$stamp="@STAMP ################# ".date('h:i:s',time());
fwrite($fileLogSent, $stamp . PHP_EOL); // Write the line to the file with a line break // DEBUG CODE

/////
error_reporting(E_ALL);
$handle = fopen($url, 'r', false, $context);



if ($handle === false) {
	
	$db->insert(
				'log',
				array(
					'localts' => time(),
					'prompt' => nl2br(SQLite3::escapeString(print_r($GLOBALS["DEBUG_DATA"],true))),
					'response' => (SQLite3::escapeString(print_r(error_get_last(),true))),
					'url' => nl2br(SQLite3::escapeString(print_r( base64_decode(stripslashes($_GET["DATA"])),true)." in ".(time()-$startTime)." secs " ))
					
				
				)
			);
	echo "Seems my mind has blown, too many thoutghs\r\n";
	@ob_end_flush();
	
	print_r(error_get_last(),true);
	
} else {
    // Read and process the response line by line
    $buffer="";
    $totalBuffer="";
	$functionIsUsed=false;
    while (!feof($handle)) {
        $line = fgets($handle);
	    //echo $line;
	    //continue;
		fwrite($fileLogSent, $line . PHP_EOL); // Write the line to the file with a line break // DEBUG CODE
		
        $data=json_decode(substr($line,6),true);
        if (isset($data["choices"][0]["delta"]["content"])) {
            if (strlen(trim($data["choices"][0]["delta"]["content"]))>0)
                $buffer.=$data["choices"][0]["delta"]["content"];
			$totalBuffer.=$data["choices"][0]["delta"]["content"];
		
			
		} 
		
		// Catch function calling  here
		
		if (isset($data["choices"][0]["delta"]["function_call"])) {
			
			if (isset($data["choices"][0]["delta"]["function_call"]["name"])) {
				$functionName =$data["choices"][0]["delta"]["function_call"]["name"] ;
			}

			if (isset($data["choices"][0]["delta"]["function_call"]["arguments"])) {
				
					$parameterBuff .= $data["choices"][0]["delta"]["function_call"]["arguments"] ;
				
			}
		}
		
		
		if (isset($data["choices"][0]["finish_reason"])&&$data["choices"][0]["finish_reason"]=="function_call") {
			$parameterArr=json_decode($parameterBuff,true);
			$parameter=current($parameterArr);	// Only support for one parameter
			
			if (!isset($alreadysent[md5("Herika|command|$functionName@$parameter\r\n")])) {
					echo "Herika|command|$functionName@$parameter\r\n";
						$db->insert(
							'eventlog',
							array(
								'ts' => $finalParsedData[1],
								'gamets' => $finalParsedData[2],
								'type' => "funccall",
								'data' => "Herika: {"."$functionName($parameter)"."}",
								'sess' => 'pending',
								'localts' => time()
							)
						);
						
				}
					
				$alreadysent[md5("Herika|command|$functionName@$parameter\r\n")]="Herika|command|$functionName@$parameter\r\n";
				@ob_flush();
		}
		
       $buffer=strtr($buffer,array("\""=>""));
	   
		if (strlen($buffer)<50)	// Avoid too short buffers
			continue;
		
		
		$position = findDotPosition($buffer);
		
        if ($position !== false) {
            $extractedData = substr($buffer, 0, $position + 1);
            $remainingData = substr($buffer, $position + 1);
            $sentences=split_sentences_stream(cleanReponse($extractedData));
			$GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in openai stream";
            returnLines($sentences);
            //echo "$extractedData  # ".(microtime(true)-$starTime)."\t".strlen($finalData)."\t".PHP_EOL;  // Output
            $extractedData="";
            $buffer=$remainingData;
            
        }
    }
    if (trim($buffer)) {
		
		 $sentences=split_sentences_stream(cleanReponse(trim($buffer)));
		 $GLOBALS["DEBUG_DATA"][]=(microtime(true) - $starTime)." secs in openai stream";
         returnLines($sentences);
		
	}
    fclose($handle);
	fwrite($fileLog, $totalBuffer . " lines talked. ".sizeof($talkedSoFar)." commands ".sizeof($alreadysent)."\r\nParsed Commands:".print_r($alreadysent,true).PHP_EOL); // Write the line to the file with a line break // DEBUG CODE
}

if (sizeof($talkedSoFar)==0) {
	if (sizeof($alreadysent)>0) {	// AI only issued commands, plugin will request a response in 10 seconds.
		//echo "Herika|command|Continue@\r\n";
		
		/*$randomFillGap = [
			"Hmm... let me think",
			"Well, let me ponder on that for a second",
			"Hmm, I need a moment to consider all the possibilities",
			"Let's see... give me a few seconds to mull it over",
			"Hmm, I'm processing the information, just give me a brief moment",
			"Hmm, let me wrap my head around that before I respond",
			"Give me a second to find the right words, please",
			"Hmm... I'm contemplating the best course of action",
			"Hold on, I'm formulating my response in my mind",
			"Let me take a moment to reflect on what you just said"
		];
		$randomSentence = $randomFillGap[array_rand($randomFillGap)];

		returnLines(array($randomSentence));
		$db->insert(
				'log',
				array(
					'localts' => time(),
					'prompt' => nl2br(SQLite3::escapeString(print_r($GLOBALS["DEBUG_DATA"],true))),
					'response' => SQLite3::escapeString(print_r($alreadysent,true)),
					'url' => nl2br(SQLite3::escapeString(print_r( base64_decode(stripslashes($_GET["DATA"])),true)." in ".(time()-$startTime)." secs " ))
					
				
				)
			);	
		
		*/
	} else {			// Fail request? or maybe an invalid command was issued
		
		$randomFillGapWhenError = [
			"Apologies, my mind wandered off. Could you please repeat that?",
			"My apologies, I got caught up in my thoughts. Could you say that again?",
			"Oh, sorry about that. I was lost in thought. Could you repeat what you just said?",
			"Oops, my mind drifted away. Can you please say it one more time?",
			"Pardon me, I was lost in contemplation. Could you kindly repeat your statement?",
			"I'm sorry, I got a bit distracted. Could you go over that once more?",
			"My apologies for spacing out. Can you repeat what you said, please?",
			"I got a little lost in my own world. Could you say that again, please?",
			"Sorry, I was deep in thought. Could you give me a quick recap?",
			"Apologies for being inattentive. Can you please repeat what you just mentioned?"
		];

		// Accessing a random sentence from the array
		$randomSentence = $randomFillGapWhenError[array_rand($randomFillGapWhenError)];
		
		//returnLines(array($randomSentence));
		$db->insert(
				'log',
				array(
					'localts' => time(),
					'prompt' => nl2br(SQLite3::escapeString(print_r($GLOBALS["DEBUG_DATA"],true))),
					'response' => SQLite3::escapeString(print_r($alreadysent,true)),
					'url' => nl2br(SQLite3::escapeString(print_r( base64_decode(stripslashes($_GET["DATA"])),true)." in ".(time()-$startTime)." secs " ))
					
				
				)
			);	
		
	}

	
	
}
echo 'X-CUSTOM-CLOSE';
$stamp="@STAMP END ################# ".date('h:i:s',time());
fwrite($fileLogSent, print_r($GLOBALS["DEBUG"]["BUFFER"],true) . PHP_EOL); // Write the line to the file with a line break // DEBUG CODE
fwrite($fileLogSent, $stamp . PHP_EOL); // Write the line to the file with a line break // DEBUG CODE

//echo "\r\n<$totalBuffer>";
?>