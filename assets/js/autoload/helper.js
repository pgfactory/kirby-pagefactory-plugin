/*
 * helpers.js
 * Helper functions for Kirby PageFactory plugin
 */


execLateLoading();

window.onresize = function() {
  adaptToWidth();
}



function execLateLoading() {
// perform late loading:
  var elem = document.getElementsByClassName('pfy-onload-css');
  for (i = 0; i < elem.length; i++) {
    let href = elem[i].getAttribute('href');
    elem[i].setAttribute('media', 'all');
  }

  elem = document.getElementsByClassName('pfy-onload');
  for (i = 0; i < elem.length; i++) {
    let src = elem[i].getAttribute('data-src');
    elem[i].setAttribute('src', src);
    elem[i].removeAttribute('data-src');
  }
}


function adaptToWidth() {
    let windowWidth = document.documentElement.clientWidth;
  if (windowWidth < screenSizeBreakpoint) {
    document.body.classList.remove('pfy-large-screen');
    document.body.classList.add('pfy-small-screen');
  } else {
    document.body.classList.remove('pfy-small-screen');
    document.body.classList.add('pfy-large-screen');
  }
}


function mylog(str, showOnScreen) {
  console.log(str);
  if (typeof showOnScreen !== 'undefined') {
    logToScreen(str);
  }
}


function logToScreen(text) {
  var $log = document.getElementById('pfy-log');
  if (!$log) {
    var logPlaceholder = document.createElement('div');
    logPlaceholder.id = 'pfy-log-placeholder';
    document.body.appendChild(logPlaceholder);

    $log = document.createElement('div');
    $log.id = 'pfy-log';
    document.body.appendChild($log);
  }

  var newLogEntry = document.createElement('p');
  newLogEntry.innerHTML = timeStamp() + '&nbsp;&nbsp;' + text;
  $log.appendChild(newLogEntry);

  $log.scrollTop = $log.scrollHeight;
}


function timeStamp() {
  const now = new Date();
  const options = {
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    hour: 'numeric',
    minute: 'numeric',
    second: 'numeric'
  };
  return now.toLocaleString(undefined, options);
}




function scrollIntoView( selector, container ) {
    // if (typeof container !== 'undefined') {
        let elem = document.querySelector( selector );
        if (elem !== null) { // happens when on unlisted page
          elem.scrollIntoView(false);
        }
    // } else {
        // document.querySelector('html, body').animate({
        //     scrollTop: document.querySelector( selector ).offset().top
        // }, 500);
    // }
} // scrollIntoView



function translateVar(transvarDef) {
  if (typeof transvarDef === 'undefined') {
    transvarDef = '_' + transvarDef;
    if (typeof transvarDef === 'undefined') {
      return '';
    }
  }
  if (typeof transvarDef[currLang] !== 'undefined') {
    return transvarDef[currLang];

  } else {
    let lang = currLang.substring(0,2);
    if (typeof transvarDef[lang] !== 'undefined') {
      return transvarDef[lang];

    } else if (typeof transvarDef['_'] !== 'undefined') {
      return transvarDef['_'];
    }
  }
  return '';
} // translateVar


