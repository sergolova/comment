<?php

// JSON file names
const JSON_FOLDER = 'json/';
const FILE_BASE = JSON_FOLDER.'base.json';
const FILE_SETTINGS = JSON_FOLDER.'settings.json';
const FILE_BALLANCE = JSON_FOLDER.'getBallance.json';
const FILE_GET_MESSAGE_STATUS = JSON_FOLDER.'getMessageStatus.json';
const FILE_PRICE_LIST = JSON_FOLDER.'getPriceList.json';
const FILE_SEND_MESSAGE = JSON_FOLDER.'sendMessage.json';
const FILE_EMULATE_ANSWER = JSON_FOLDER.'answer.json';

// json settings fields names
const SET_AUTHOR_PHONE = "author_phone";
const	SET_SMS_SOURCE = "sms_source";
const	SET_VIBER_SOURCE = "viber_source";
const	SET_API_KEY = "api_key";
const	SET_API_URL = "api_url";
const	SET_EMULATE_MODE = "emulate_mode";
const	SET_SMS_TTL = "sms_ttl";    // sms life time, min. (1..1440)
const	SET_VIBER_TTL = "viber_ttl";  // viber life time, min. (1..1440)
const	SET_DISABLE_SMS = "disable_sms";
const	SET_SMS_PER_DAY = "sms_per_day";

// json base fields names
const ID_DATA = 'data';
const ID_NAME = 'name';
const ID_COMMENT = 'comment';
const ID_TIME = 'time';
const ID_SMS = 'sms';
const EMPTY_DATA = [ID_DATA => []];

// special admin codes for manage Base and Settings
const CLEAR_BASE = 'clear';
const DEL_LAST = 'del';
const GET_SETTINGS = 'get';
const SET_SETTINGS = 'setsettings';

// api json fields names
const json_key = 'key';
const json_auth = 'auth';
const json_data = 'data';
const json_messageID = 'messageID';
const json_recipient = 'recipient';
const json_channels = 'channels';
const json_source = 'source';
const json_ttl = 'ttl';
const json_text = 'text';
const json_image = 'image';
const json_status = 'status';
const json_cost = 'cost';
const json_PriceList = 'pricelist';
const json_Ukraine = '255';
const json_sms = 'sms';
const json_viber = 'viber';
const json_balance = 'balance';
const json_success = 'success';
const answer_success = '1';

const SMS_MAX_LENGTH_KYR = 70;
const SMS_MAX_LENGTH_LAT = 160;
const USER_NAME_MAX = 32;
const CONTENT_MAX = 2048;

// load settings
$settings = getJsonData(FILE_SETTINGS,[]);

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
  global $settings;
  $jdata = getJsonData(FILE_BALLANCE);             // get json template from file
  if ( $jdata ) {
    $jdata[json_auth][json_key] = $settings[SET_API_KEY];                // set out api key
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
  global $settings;
  $res = [];

  $jdata = getJsonData(FILE_PRICE_LIST);             // get json template from file
  if ( $jdata ) {
    $jdata[json_auth][json_key] = $settings[SET_API_KEY];                // set out api key
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

function sendMessage($text, $channels, $emulate = false): array
{
  global $settings;
  $jsend = getJsonData(FILE_SEND_MESSAGE);     // get json tem$jdata = {array[3]} plate from file
  if ( $jsend ) {
    $jsend[json_auth][json_key] = $settings[SET_API_KEY];            // set out api key

    $jdata = &$jsend[json_data];
    $jdata[json_recipient] = $settings[SET_AUTHOR_PHONE];
    $jdata[json_channels] = $channels;  // sms or viber

    if ( in_array(json_sms, $channels) ) {
      $sms = &$jdata[json_sms];
      $sms[json_source] = $settings[SET_SMS_SOURCE];
      $sms[json_text] = $text;
      $sms[json_ttl] = $settings[SET_SMS_TTL];
    } else {
      unset($jdata[json_sms]);
    }

    if ( in_array(json_viber, $channels) ) {
      $viber = &$jdata[json_viber];
      $viber[json_source] = $settings[SET_VIBER_SOURCE];
      $viber[json_text] = $text;
      $viber[json_ttl] = $settings[SET_VIBER_TTL];
      $viber[json_image] = "";//VIBER_IMAGE_URL;
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

function getMessageStatus($id): string
{
  global $settings;
  $jdata = getJsonData(FILE_GET_MESSAGE_STATUS);             // get json template from file
  if ( $jdata ) {
    $jdata[json_auth][json_key] = $settings[SET_API_KEY];                // set out api key
    $jdata[json_data][json_messageID] = $id;
    $strData = json_encode($jdata);
    return sendJsonString($strData);              // send json string to server
  }
  return 'false';
}

function sendJsonString($str): string
{
  global $settings;
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $settings[SET_API_URL]);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: text/xml", "Accept: text/xml"]);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $str);
  $response = curl_exec($ch);
  curl_close($ch);
  return $response;
}

function commentExists($fname, $u_name, $u_comment): bool
{
  $json = file_get_contents($fname);              // get string
  $jdata = json_decode($json, true);    // get associative array
  $err = json_last_error();

  if ( $err === JSON_ERROR_NONE && array_key_exists(ID_DATA, $jdata) ) {
    $u_name = mb_strtoupper($u_name);
    $u_comment = mb_strtoupper($u_comment);
    foreach ($jdata[ID_DATA] as $item) {
      if ( mb_strtoupper($item[ID_NAME]) === $u_name && mb_strtoupper($item[ID_COMMENT]) === $u_comment ) {
        return true;
      }
    }
  }
  return false;
}

function deleteLastMessage($fname): bool
{
  $json = file_get_contents($fname);              // get string
  $jdata = json_decode($json, true);    // get associative array
  $err = json_last_error();

  if ( $err === JSON_ERROR_NONE && array_key_exists(ID_DATA, $jdata) ) {
    array_pop($jdata[ID_DATA]);
    $str = json_encode($jdata);
    return file_put_contents($fname, $str) > 0; // write to file
  }
  return false;
}

function getJsonData($fname, $default = null): array
{
  clearstatcache();
  if ( file_exists($fname) and filesize($fname) > 0 ) {
    $json = file_get_contents($fname);              // get string
    $jdata = json_decode($json, true);    // get associative array
    $err = json_last_error();

    if ( $err === JSON_ERROR_NONE && $jdata !== null ) { // empty file or invalid json
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
  $jdata = getJsonData($fname, EMPTY_DATA);

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