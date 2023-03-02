<?php
require 'main.php';

$validBallance = validSMSBallance();
$result = 'null1';

if ( $_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['token'] != $_SESSION['lastToken'] ) {
  $_SESSION['lastToken'] = $_POST['token'];

  //$jdata = getJsonData(FILE_BASE, EMPTY_DATA);
  $price = getPrice();
  $bal = getBallance();
  $res = validSMSBallance();

  $name = correct($_POST['u_name']);
  $comment = correct($_POST['u_content']);
  $time = $_SERVER['REQUEST_TIME'];
	$sms = isset($_POST['sms']);
  $result = 'null2';
  if ( $name == CLEAR_FILE && $comment == CLEAR_FILE ) {
    clearFile(FILE_BASE);
    $result = 'No comments yet!';
  } elseif ( strlen($name) == 0 || strlen($comment) == 0 ) {
    $result = 'Empty fields';
  } elseif ( commentExists(FILE_BASE, $name, $comment) ) {
    $result = 'Comment exists!';
  } else {
    $result = 'Comment added';
    if ( $sms ) {
      $strStatus='';
      $sms = false;
      $result .= ', ';
      if ( getTodaySMSCount(FILE_BASE) < SMS_PER_DAY ) {
        $status = sendMessage($name, $comment, [json_sms],true);
        if (array_key_exists(json_success, $status)) {
          $strStatus = $status[json_data][json_sms][json_status];
          $result .= 'SMS sended to author with status = '.$strStatus;
        }

        $sms = $strStatus == json_accepted;
      } else {
        $result .= 'SMS limit exceeded!';
      }
    }
    appendDataToJsonFile(FILE_BASE, $name, $comment, $time, $sms);
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Comment</title>
	<link href="style.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="header">
	<div class="image"></div>
	Leave your comment!
	<span>php & sms test</span>

</div>
<div class="main">
	<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
		<input type="hidden" name="token" value="<?php echo(rand(100000, 999999)); ?>"/>
		<input maxlength="32" placeholder="Your name, 32 characters max" name="u_name">
		<textarea maxlength="2048" placeholder="Please type here, 2048 characters max" name="u_content"></textarea>
		<input type="submit"><span class="result"><?php echo $result ?></span><br>


		<label for="sms-send" class="<?php echo $validBallance ? '' : 'disabled' ?>">
			<input type="checkbox" name="sms" id="sms-send" value="send" <?php echo $validBallance ? '' : 'disabled' ?>>
			Send sms to autor (3 per day)
		</label>
		<span <?php echo $validBallance ? 'hidden' : '' ?>>(insufficient funds or request refused)</span>
	</form>
</div>
<?php
$jdata = getJsonData(FILE_BASE, EMPTY_DATA);
$cnt = count($jdata[ID_DATA]);
if ( $cnt > 0 ) {
  $currTime = time();
  $i = $cnt;
	while (--$i >= 0)
	{
    $user_name = '';
    $user_time = 0;
    $user_content = "";
    $user_sms = false;
    try {
      $pItem = &$jdata[ID_DATA][$i];
      $user_name = mb_substr($pItem[ID_NAME], 0, USER_NAME_MAX);
      $user_time = date('H:i:s d/m/Y', $pItem[ID_TIME]).'   ('.time_elapsed_string('@'.$pItem[ID_TIME]).')';
      $user_content = getContent($pItem[ID_COMMENT]);
      $user_sms = $pItem[ID_SMS];
    } catch (Exception $e) {
      echo 'exception '.$e->getMessage();
    }
    ?>
		<div class="user <?php
    echo($user_sms ? 'sms' : ''); ?>">
			<div class="user-header clearfix">
					<span class="name"><?php
            echo $user_name; ?></span>
				<div class="time">
          <?php
          echo $user_time;
          ?>
				</div>
			</div>
			<div class="user-content">
        <?php
        echo $user_content;
        ?>
			</div>
		</div>
    <?php
  }
} else { ?>  <!--empty base file-->
	<div>
		no comments was leaved!
	</div>
  <?php
}
?>
</body>
</html>