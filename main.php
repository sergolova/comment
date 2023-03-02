<?php

session_start();

// JSON file names
const JSON_FOLDER = 'json/';
const FILE_BASE = JSON_FOLDER.'base.json';
const FILE_BALLANCE = JSON_FOLDER.'getBallance.json';
const FILE_GET_MESSAGE_STATUS = JSON_FOLDER.'getMessageStatus.json';
const FILE_PRICE_LIST = JSON_FOLDER.'getPriceList.json';
const FILE_SEND_MESSAGE = JSON_FOLDER.'sendMessage.json';
const FILE_EMULATE_ANSWER = JSON_FOLDER.'answer.json';

// json base fields names
const ID_DATA = 'data';
const ID_NAME = 'name';
const ID_COMMENT = 'comment';
const ID_TIME = 'time';
const ID_SMS = 'sms';
const EMPTY_DATA = [ID_DATA => []];

const USER_NAME_MAX = 32;
const CONTENT_MAX = 2048;
const CLEAR_FILE = 'clear';
const SMS_SEND_VALUE = 'send';
const SMS_PER_DAY = 3;

const API_KEY = '8DhdEdk8nEK7U6wWJrHlpOMKVfT1y9ds';
const API_URL = 'https://sms-fly.ua/api/v2/api.php';
const SMS_TTL = 5;    // sms life time, min. (1..1440)
const VIBER_TTL = 5;  // viber message life time, min. (1..1440)
const VIBER_IMAGE_URL = "https://upload.wikimedia.org/wikipedia/commons/thumb/8/85/Smiley.svg/330px-Smiley.svg.png";
const DEF_SMS_SOURCE = 'InfoCentr';
const DEF_VIBER_SOURCE = 'Promo';
const json_key = 'key';
const json_auth = 'auth';
const json_recipient = 'recipient';
const json_channels = 'channels';
const json_data = 'data';
const json_source = 'source';
const json_ttl = 'ttl';
const json_text = 'text';
const json_image = 'image';
const json_status = 'status';
const json_accepted = 'ACCEPTD';
const json_cost = 'cost';
const json_PriceList = 'pricelist';
const json_Ukraine = '255';
const json_sms = 'sms';
const json_viber = 'viber';
const json_balance = 'balance';
const json_success = 'success';
const answer_success = '1';
const answer_fail = '0';
const SMS_NUMBER = '380977216281';
//const SMS_NUMBER = '380976469523';
const SMS_MAX_LENGTH_KYR = 70;
const SMS_MAX_LENGTH_LAT = 160;

function validSMSBallance(): bool
{
  $ballance = getBallance();
  $price = getPrice();
  if ( array_key_exists(json_sms, $ballance) ) {
    $smsRest = $ballance[json_sms];
    if ( array_key_exists(json_sms, $price) ) {
      $smsMaxPrice = max($price[json_sms]);
      return $smsRest >= $smsMaxPrice;
    }
  }
  return false;
}

function getBallance(): array
{
  $jdata = getJsonData(FILE_BALLANCE);             // get json template from file
  if ( $jdata ) {
    $jdata[json_auth][json_key] = API_KEY;                // set out api key
    $strData = json_encode($jdata);
    $strResponse = sendJsonString($strData);              // send json string to server
    $jdata = json_decode($strResponse, true);
    if ( $jdata && (json_last_error() == JSON_ERROR_NONE) ) {
      if ( $jdata[json_success] == answer_success ) {
        $balance = &$jdata[json_data][json_balance];
        return [json_sms => floatval($balance[json_sms]),
            json_viber => floatval($balance[json_viber])];
      }
    }
  }

  return [];
}

function getPrice(): array
{
  $res = [];

  $jdata = getJsonData(FILE_PRICE_LIST);             // get json template from file
  if ( $jdata ) {
    $jdata[json_auth][json_key] = API_KEY;                // set out api key
    $strData = json_encode($jdata);
    $strResponse = sendJsonString($strData);              // send json string to server
    $jdata = json_decode($strResponse, true);
    if ( $jdata && (json_last_error() == JSON_ERROR_NONE) ) {
      if ( $jdata[json_success] == answer_success ) {
        $priceList = &$jdata[json_data][json_PriceList];

        if ( array_key_exists(json_sms, $priceList) ) {
          $sms = &$priceList[json_sms];
          if ( array_key_exists(json_Ukraine, $sms) && count($sms[json_Ukraine]) > 0 ) {
            $res = [json_sms => []];
            foreach ($sms[json_Ukraine] as $key => &$item) {
              $res[json_sms][$key] = floatval($item);
            }
          }
        }
        if ( array_key_exists(json_viber, $priceList) ) {
          $viber = &$priceList[json_viber];
          // not implemented
        }
      }
    }
  }

  return $res;
}

function sendMessage($u_name, $u_comment, $channels, $emulate = false): array
{
  $res = [];
  $s = array('  ', "\r\n", "\r", "\n");
  $r = array(' ', " ", " ", " ");
  $u_name = str_replace($s, $r, $u_name);
  $u_comment = str_replace($s, $r, $u_comment);
  $text = $u_name.': '.$u_comment;
  $len = preg_match('/[а-яё]/iu', $text) ? SMS_MAX_LENGTH_KYR : SMS_MAX_LENGTH_LAT;
  $text = mb_substr($text, 0, $len);  // crop to max length

  $jsend = getJsonData(FILE_SEND_MESSAGE);             // get json tem$jdata = {array[3]} plate from file
  if ( $jsend ) {
    $jsend[json_auth][json_key] = API_KEY;                // set out api key

    $jdata = &$jsend[json_data];
    $jdata[json_recipient] = SMS_NUMBER;                  //
    $jdata[json_channels] = $channels;

    if ( in_array(json_sms, $channels) ) {
      $sms = &$jdata[json_sms];
      $sms[json_source] = DEF_SMS_SOURCE;//$u_name;
      $sms[json_text] = $text;
      $sms[json_ttl] = VIBER_TTL;
    } else {
      unset($jdata[json_sms]);
    }

    if ( in_array(json_viber, $channels) ) {
      $viber = &$jdata[json_viber];
      $viber[json_source] = DEF_VIBER_SOURCE;
      $viber[json_text] = $text;
      $viber[json_ttl] = VIBER_TTL;
      $viber[json_image] = VIBER_IMAGE_URL;
    } else {
      unset($jdata[json_viber]);
    }

    $strSend = json_encode($jsend);
    if ( !$emulate ) {
      $strResponse = sendJsonString($strSend);              // send json string to server
      file_put_contents(FILE_EMULATE_ANSWER, $strResponse);
    } else {
      $strResponse = file_get_contents(FILE_EMULATE_ANSWER);
    }
    $jsend = json_decode($strResponse, true);
    if ( $jsend && (json_last_error() == JSON_ERROR_NONE) ) {
      return $jsend;
    }
  }
  return [];
}

function sendJsonString($str): string
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, API_URL);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: text/xml", "Accept: text/xml"]);
  curl_setopt($ch, CURLOPT_POST, 1);
//	curl_setopt($ch, CURLOPT_USERPWD, $user.':'.$password);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $str);
  $response = curl_exec($ch);
  curl_close($ch);
  return $response;
}

function correct($str, $upper = false): string
{
  return trim(htmlspecialchars($upper ? mb_strtoupper($str) : $str));
}

function commentExists($fname, $u_name, $u_comment): bool
{
  $json = file_get_contents($fname);              // get string
  $jdata = json_decode($json, true);    // get associative array
  $err = json_last_error();

  if ( $err !== JSON_ERROR_NONE ) { // empty file or invalid json
    $jdata = [ID_DATA => []]; // create empty array DATA
  }

  $u_name = mb_strtoupper($u_name);
  $u_comment = mb_strtoupper($u_comment);
  return false;
  /*
    $cnt = count($ini);
    for ($i = $cnt; $i > 0; $i--) {
      $item = $ini[USER_SECT.$i];
      if ( mb_strtoupper($item['name']) == $u_name && mb_strtoupper(getContent($item, "\r\n")) == $u_comment ) {
        return true;
      }
    }*/
}

function getJsonData($fname, $default=null): array
{
  if ( file_exists(FILE_BASE) and filesize(FILE_BASE) > 0) {
    $json = file_get_contents($fname);              // get string
    $jdata = json_decode($json, true);    // get associative array
    $err = json_last_error();

    if ( $err === JSON_ERROR_NONE && $jdata !== null) { // empty file or invalid json
      return $jdata;
    }
  }
  return $default; // create empty array DATA
}

function appendDataToJsonFile($fname, $u_name, $u_comment, $u_time, $sms): bool
{
  if ( !$u_name || !$u_comment || !$fname ) {
    return false;
  }

  $jdata = getJsonData($fname, EMPTY_DATA);

  $new = [ID_NAME => $u_name, ID_COMMENT => $u_comment, ID_TIME => $u_time, ID_SMS => $sms];
  $jdata[ID_DATA][] = $new;
  $str = json_encode($jdata);
  return file_put_contents($fname, $str) > 0; // write to file
}

function clearFile($fname): void
{
  file_put_contents($fname, "");
}

function getTodaySMSCount($fname): int
{
  $jdata = getJsonData($fname,EMPTY_DATA);

  $cnt = 0;
  foreach ($jdata[ID_DATA] as $item) {
    $stamp = $item[ID_TIME];
    $now = time();
    if ( $item[ID_SMS] && ($now - $stamp) / 60 / 60 / 24 < 1 ) {
      $cnt++;
    }
  }

  return $cnt;
}

/**
 * @throws Exception
 */
function time_elapsed_string($datetime, $full = false): string
{
  $now = new DateTime;
  $ago = new DateTime($datetime);
  $diff = $now->diff($ago);

  $diff->w = floor($diff->d / 7);
  $diff->d -= $diff->w * 7;

  $string = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second',];
  foreach ($string as $k => &$v) {
    if ( $diff->$k ) {
      $v = $diff->$k.' '.$v.($diff->$k > 1 ? 's' : '');
    } else {
      unset($string[$k]);
    }
  }

  if ( !$full )
    $string = array_slice($string, 0, 1);

  return $string ? implode(', ', $string).' ago' : 'just now';
}

function getContent($str): string
{
   return str_replace("\r\n", "<br>", $str);
}