<?php
/* Processes a GET request
 * msgid=FAPI00040A3AFA000002
 * */

require 'main.php';

const msgid = 'msgid';

if ( $_SERVER['REQUEST_METHOD'] == 'GET' ) {
  $smsID = $_GET[msgid];
  if ( strlen($smsID) > 0) {
    echo getMessageStatus($smsID);
  }
}