<?php
session_start();

require 'main.php';

const strInvalidJsonFormat = "ERROR (invalid JSON format)";
const strSmsStatus = 'SMS sended to author with status = ';
const strSmsLimit = 'SMS limit exceeded!';
const strCommentAdded = 'Comment added';
const resEmptyFields = "Empty fields";
const resLastDeleted = "Last message deleted";
const resGetSettings = "Get your settings!";
const resSetSettings = "Settings have been updated";
const resSetSettingsFail = "Error when saving settings";
const resAllDeleted = "All messages have been deleted";
const resCommentExists = "Comment exists!";

global $settings;
$validBallance = validSMSBallance();
$operResult = '';
$msgResult = '';
$msgID = '';
$textAreaValue = '';

if ( $_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['token'] != $_SESSION['lastToken'] ) {
  $_SESSION['lastToken'] = $_POST['token'];

  $name = trim($_POST['u_name']);
  $comment = trim($_POST['u_content']);
  $time = $_SERVER['REQUEST_TIME'];
  $isSMS = isset($_POST['sms']);

  // Clear the Base code detected
  if ( $name == CLEAR_BASE && $comment == CLEAR_BASE ) {
    clearFile(FILE_BASE);
    $operResult = resAllDeleted;
  } // Delete last message code detected
	elseif ( $name == DEL_LAST && $comment == DEL_LAST ) {
    deleteLastMessage(FILE_BASE);
    $operResult = resLastDeleted;
  } // Empty fields detected
	elseif ( strlen($name) == 0 || strlen($comment) == 0 ) {
    $operResult = resEmptyFields;
  } // Comment exists - skip it
	elseif ( commentExists(FILE_BASE, $name, $comment) ) {
    $operResult = resCommentExists;
  } // Get settings code detected - show json in textArea
	elseif ( $name == GET_SETTINGS && $comment == GET_SETTINGS ) {
    $textAreaValue = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $operResult = resGetSettings;
  } // Set settings code detected - validate json and write to file
	elseif ( $name == SET_SETTINGS ) {
    $jdata = json_decode($comment, true);
    if ( $jdata !== null && json_last_error() === JSON_ERROR_NONE &&
        count($jdata) === count($settings) ) {
      file_put_contents(FILE_SETTINGS, $comment);
      $operResult = resSetSettings;
    } else {
      $operResult = resSetSettingsFail;
    }
    // fields are just strings
  } else {
    $operResult = strCommentAdded;
    if ( $isSMS ) {
      // Format strings to SMS format and send to SMS-server
      $msgStatus = '';
      $operResult .= ', ';
      if ( getTodaySMSCount(FILE_BASE) < $settings[SET_SMS_PER_DAY] ) {

        $s = array('  ', "\r\n", "\r", "\n");         // search
        $r = array(' ', " ", " ", " ");               // replace to
        $name = str_replace($s, $r, $name);       // correct name
        $comment = str_replace($s, $r, $comment); // correct comment
        $comment = $name.': '.$comment;              // forming a message
        // different lengths for Cyrillic and Latin
        $len = preg_match('/[а-яё]/iu', $comment) ? SMS_MAX_LENGTH_KYR : SMS_MAX_LENGTH_LAT;
        $comment = mb_substr($comment, 0, $len);

        $jStatus = sendMessage($comment, [json_sms], $settings[SET_EMULATE_MODE]);
        if ( array_key_exists(json_success, $jStatus) && $jStatus[json_success] == 1 ) {
          $msgID = $jStatus[json_data][json_messageID];
          $msgStatus = $jStatus[json_data][json_sms][json_status];
          $operResult .= strSmsStatus;
        } else {
          $msgStatus = strInvalidJsonFormat;
        }
        $msgResult = $msgStatus;
      } else {
        $operResult .= strSmsLimit;
      }
    }//is sms

    // crop strings and save to Base file
    $name = mb_substr($name, 0, USER_NAME_MAX);
    $comment = mb_substr($comment, 0, CONTENT_MAX);
    appendDataToJsonFile(FILE_BASE, $name, $comment, $time, $isSMS);
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Comment</title>
	<link href="style.css" rel="stylesheet" type="text/css">
	<script type="text/javascript" src="js/main.js"></script>
</head>
<body onload="onBodyLoad()">
<header class="unselectable">
	<div class="image"></div>
	Leave your comment!
	<span>php & sms test</span>
</header>

<div class="main">
	<form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="post">
		<input type="hidden" name="token" value="<?= (rand(100000, 999999)) ?>"/>
		<input maxlength="<?= USER_NAME_MAX ?>"
		       placeholder="Your name, <?= USER_NAME_MAX ?> characters max"
		       name="u_name">
		<textarea maxlength="<?= CONTENT_MAX ?>"
		          placeholder="Please type here, <?= CONTENT_MAX ?> characters max"
		          name="u_content"><?=$textAreaValue?></textarea>
		<button class="buttonfx" type="submit">SUBMIT</button>
		<span class="result"><?= $operResult ?></span>
		<span class="result"
		      id="msgresult"
		      data-msgid="<?= $msgID ?>"
		      onclick="onSpanClick()">
			<?= $msgResult ?>
		</span>
		<div id="waiting" class="load"></div>
		<br>

		<label for="sms-send"
		       class="<?php echo $validBallance ? '' : 'disabled' ?>"
        <?= $settings[SET_DISABLE_SMS] ? 'hidden' : '' ?>>
			<input type="checkbox" name="sms" id="sms-send" value="true" <?= ($validBallance ? '' : 'disabled') ?>
			       onchange="onCheckBoxChange()">
			Send sms to author (3 per day)
		</label>
		<span <?= ($validBallance ? 'hidden' : '') ?>>(insufficient funds or request refused)</span>
	</form>
</div>
<?php
$jdata = getJsonData(FILE_BASE, EMPTY_DATA);
$cnt = count($jdata[ID_DATA]);
if ( $cnt > 0 ) {
  $currTime = time();
  $i = $cnt;
  while (--$i >= 0) {
    $user_time = 0;
    $user_name = '';
    $user_content = "";
    $user_sms = false;
    try {
      $pItem = &$jdata[ID_DATA][$i];
      $user_time = date('H:i:s d/m/Y', $pItem[ID_TIME]).'   ('.time_elapsed_string('@'.$pItem[ID_TIME]).')';
      $user_name = htmlspecialchars($pItem[ID_NAME]);
      $user_content = htmlspecialchars($pItem[ID_COMMENT]);
      $user_content = str_replace("\r\n", "<br>", $user_content);
      $user_sms = $pItem[ID_SMS];
    } catch (Exception $e) {
      $user_content = 'exception '.$e->getMessage();
    }
    ?>
		<div class="user <?= ($user_sms ? 'sms' : '') ?> ">
			<div class="user-header clearfix">
				<span class="name"><?= $user_name ?></span>
				<span class="sms-hint"><?= ($user_sms ? '(sms)' : '') ?></span>
				<div class="time">
          <?= $user_time ?>
				</div>
			</div>
			<div class="user-content">
        <?= $user_content ?>
			</div>
		</div>
    <?php
  }
} else { ?>  <!--empty base file-->
	<div>
		no comments was leaved :(
	</div>
  <?php
}
?>


</body>

</html>