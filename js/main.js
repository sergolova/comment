'use strict'

const msgCodesRu = {
  'ACCEPTD': "сообщение принято системой",
  'PENDING': "сообщение в очереди на отправку",
  'INPROGRESS': "сообщение в обработке",
  'SENT': "сообщение отправлено",
  'DELIVRD': "сообщение доставлено",
  'VIEWED': "сообщение просмотрено",
  'EXPIRED': "истек срок доставки сообщения",
  'UNDELIV': "сообщение не доставлено",
  'STOPED': "сообщение остановлено системой",
  'ERROR': "ошибка отправки сообщения",
  'INSUFFICIENTFUNDS': "недостаточно средств для отправки данного сообщения",
  'MODERATION': "сообщение на модерации",
  'RESERVED': "сообщение зарезервировано системой",
  'REFUND': "сообщение подготовлено к возврату средств",
  'UNKNOWN': "невозможно получить информацию о сообщении"
};

const msgCodesEn = {
  'ACCEPTD': 'message accepted by system',
  'PENDING': 'message in queue to be sent',
  'INPROGRESS': 'message in processing',
  'SENT': 'message sent',
  'DELIVRD': 'message delivered',
  'VIEWED': "message viewed",
  'EXPIRED': 'message delivery time has expired',
  'UNDELIV': 'message undelivered',
  'STOPED': "message has been stopped by the system",
  'ERROR': 'message sending error',
  'INSUFFICIENTFUNDS': "insufficient funds to send this message",
  'MODERATION': "message is in moderation",
  'RESERVED': "the message is reserved by the system",
  'REFUND': 'message prepared for refund',
  'UNKNOWN': "unable to retrieve information about the message"
};

const msgErrorsRu = {
  'INVREQUEST': 'запрос пустой или имеет не верный формат',
  'INVACTION': 'действие не задано или не поддерживается',
  'INVRECIPIENT': 'не верный получатель',
  'INVTEXT': 'текст сообщения отсутствует или не соответствует требованиям',
  'INVBUTTON': 'не верный формат кнопки',
  'INVIMAGEURL': 'не верный формат url',
  'INVROUTE': 'сообщение не может быть отправлено',
  'INVSOURCE': 'не указан или не верный отправитель',
  'INVCHANNELS': 'не заданы корректные каналы отправки',
  'INVSMSMESSAGE': 'сообщение для канала sms не задано',
  'INVVIBERMESSAGE': 'сообщение для канала viber не задано',
  'INVMSGID': 'идентификатор не задан или не найден'
};

const msgErrorsEn = {
  'INVREQUEST': 'query is empty or has an invalid format',
  'INVACTION': 'action not specified or not supported',
  'INVRECIPIENT': 'wrong recipient',
  'INVTEXT': 'message text is missing or does not match requirements',
  'INVBUTTON': 'wrong button format',
  'INVIMAGEURL': 'wrong url format',
  'INVROUTE': 'message cannot be sent',
  'INVSOURCE': 'sender not specified or not correct',
  'INVCHANNELS': 'correct sending channels are not specified',
  'INVSMSMESSAGE': 'message for sms channel not specified',
  'INVVIBERMESSAGE': 'the message for the viber channel is not set',
  'INVMSGID': 'ID not set or not found'
};

// в каких случаях запрос повторяется
const msgAgainList = ['ACCEPTD', 'PENDING', 'INPROGRESS', 'SENT', 'DELIVRD', 'MODERATION', 'UNDELIV'];

const msgCodes = msgCodesEn;
const msgErrors = msgErrorsEn;
const CHECK_INTERVAL1 = 1000;
const CHECK_INTERVAL2 = 4000;
const CHECK_COUNT = 5;
const GET_FILE = "get.php";

var rqTimer; // global

function onSpanClick() {
  requestSmsStatus(1, 10,0);
}

function onBodyLoad() {
  let inp = document.querySelector('input[name="u_name"]');
  inp?.focus(); // set focus to first input
  requestSmsStatus();
}

function requestSmsStatus(numTimes=CHECK_COUNT, firstDelay=CHECK_INTERVAL1, delay=CHECK_INTERVAL2) {
  clearTimeout(rqTimer);
  let smsIDElem = document.getElementById('msgresult');
  let msgID = smsIDElem.getAttribute('data-msgid');
  if ( msgID.length > 0 ) {
    startMessageListerner(msgID, smsIDElem, numTimes, firstDelay, delay);
  }
}

/**
 * @param {string} msgID - messageID
 * @param {HTMLElement} elem - html element to display the message status
 * @param {number} numTimes - number of requests to the server
 * @param {number} firstDelay - delay before the first request
 * @param {number} delay - delay between subsequent requests
 */
function startMessageListerner(msgID, elem, numTimes, firstDelay, delay) {
  let waiting = document.getElementById('waiting');  // animation elem
  
  let onTimer = () => {
    if ( ++numCheck > numTimes ) {
      return;
    }
    
    let x = new XMLHttpRequest();
    x.open("GET", GET_FILE+"?msgid=" + msgID, true);
    
    x.onload = function () {
      let jResp = JSON.parse(x.responseText);
      console.log(`responce ${numCheck}/${numTimes} from ${GET_FILE}: ${jResp}`);
      let status, descr;
      if (jResp?.success === 1) {
         status = jResp?.data?.sms?.status;
         descr = msgCodes[status];
      } else {
        status = jResp?.error?.code;
        descr = msgErrors[status];
      }
      elem.innerHTML = `${status} (${descr})`;
      
      if ( msgAgainList.includes(status) ) {
        rqTimer = setTimeout(onTimer, delay);   // repeat request
      } else {
        console.log("done");
      }
      waiting.style.display = "none";
    };
    
    x.onerror = function () { // происходит, только когда запрос совсем не получилось выполнить
      waiting.classList.remove("load");
      console.log("request error!");
    };
  
    waiting.style.display = "inline-block";
    x.send(null);
  }
  
  let numCheck = 0;
  rqTimer = setTimeout(onTimer, firstDelay);
}

function onCheckBoxChange() {
  let elem = document.getElementById("sms-send");
  let btn = document.querySelector('button[type="submit"]');
  if ( elem.checked ) {
    btn.classList.add('button-sms');
  } else {
    btn.classList.remove('button-sms');
  }
}