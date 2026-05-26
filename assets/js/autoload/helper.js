/*
 * helpers.js
 * Helper functions for Kirby PageFactory plugin
 */


document.addEventListener('DOMContentLoaded', function() {
  execLateLoading();
  initCopyButton();
  initToDoLists();
  adaptToWidth();
  scrollAnchorIntoView();
  initBusySpinner();
});

window.addEventListener('resize', function() {
  adaptToWidth();
});



function execLateLoading() {
 // perform late loading:
  const cssElems = document.getElementsByClassName('pfy-onload-css');
  for (let i = 0; i < cssElems.length; i++) {
    cssElems[i].setAttribute('media', 'all');
  }

  const srcElems = document.getElementsByClassName('pfy-onload');
  for (let i = 0; i < srcElems.length; i++) {
    const src = srcElems[i].getAttribute('data-src');
    srcElems[i].setAttribute('src', src);
    srcElems[i].removeAttribute('data-src');
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
} // adaptToWidth


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


function timeStamp(short = false, toLocale = false) {
  let out = '';
  if (toLocale) {
    // timestamp in local language:
    const now = new Date();
    let options = {
      year: 'numeric',
      month: 'numeric',
      day: 'numeric',
    };
    if (!short) {
      Object.assign(options, {
        hour: 'numeric',
        minute: 'numeric',
        second: 'numeric'
      });
    }
    out = now.toLocaleString(undefined, options);

  } else {
    // timestamp in ISO format:
    const now = new Date().toISOString();
    if (short) {
      out = now.substring(0, 10);
    } else {
      out = now;
    }
  }
  return out;
} // timeStamp


function isEmpty(value) {
  if (value === null || value === undefined) return true;
  if (typeof value === 'boolean') return false;
  if (typeof value === 'number') return isNaN(value);
  if (typeof value === 'string') return value.trim() === '';
  if (Array.isArray(value)) return value.length === 0 || value.every(item => isEmpty(item));
  if (value instanceof Map || value instanceof Set) return value.size === 0;
  if (value instanceof Date) return isNaN(value.getTime());
  if (typeof value === 'object') {
    const keys = Object.keys(value);
    return keys.length === 0 || keys.every(key => isEmpty(value[key]));
  }
  return false;
} // isEmpty


function foreach(obj, fun) {
  Object.entries(obj).forEach(entry => {
    const [key, value] = entry;
    fun(key, value);
  });
} // foreach


// usage: await sleep(<duration>);
function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
} // sleep


function scrollIntoView(selector) {
  const elem = document.querySelector(selector);
  if (elem !== null) {
    elem.scrollIntoView(false);
  }
} // scrollIntoView



function translateVar(transvarDef) {
  if (typeof transvarDef === 'undefined') {
    return '';
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
  return transvarDef;
} // translateVar




 // === copy content button ==============================
 // targets for copy button are identified by class .pfy-has-copy-btn.
function initCopyButton() {
  const copyBtnElems = document.querySelectorAll('.pfy-has-copy-btn');
  if (!copyBtnElems.length) {
    return; // no copy buttons found
  }

  let inx = 0;
  // loop over targets:
  copyBtnElems.forEach(function (targetEl) {
    inx++;
    // prepare button element:
    const btnDiv = document.createElement("div");
    btnDiv.innerHTML = '<div>⎘</div>';
    btnDiv.classList.add('pfy-copy-btn','pfy-button');

    // wrap target in DIV.pfy-has-copy-btn
    const parentEl = targetEl.parentElement;
    targetEl.classList.remove('pfy-has-copy-btn');
    targetEl.classList.add('pfy-copy-container');
    const html = targetEl.outerHTML;
    targetEl.outerHTML = '<div class="pfy-has-copy-btn pfy-has-copy-btn-'+inx+'">'+html+'</div>';

    // append button to wrapper:
    const wrapper = parentEl.querySelector('.pfy-has-copy-btn-' + inx);
    wrapper.appendChild(btnDiv);
  });

 // set up event handler for copy buttons:
  const copyButtons = document.querySelectorAll('.pfy-copy-btn');
  if (copyButtons) {
    // loop over copy buttons:
    copyButtons.forEach(function (btnEl) {
      btnEl.addEventListener('click', function (e) {
        const containerEl = this.parentElement.querySelector('.pfy-copy-container');
        // get content:
        let  txt = containerEl.value;
        if (typeof txt === 'undefined') {
          txt = containerEl.innerText;
        }
        // if content not empty, copy it to clipboard:
        if (txt) {
          txt = txt.replace(/^\n+|\n+$/g, '');
          copyToClipboard(txt, containerEl);
        }
      });
    });
  }

 // copy to clipboard
  async function copyToClipboard(str, textareaEl) {
    try {
      // write to clipboard:
      await navigator.clipboard.writeText(str);
      console.debug(`Copied to clipboard: "${str}"`);

      // flash the element for user feedback:
      const container = textareaEl.closest('.pfy-has-copy-btn');
      container.classList.add('pfy-flash-copied');
      setTimeout(function() {
        container.classList.remove('pfy-flash-copied');
      }, 1000);
    } catch (err) {
      console.debug('Failed to copy: ' + err);
    }
  } // copyToClipboard
} // initCopyButton


function createHash(size = 8) {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.-_';
  // first char: exclude digits and special chars
  let n = Math.floor(Math.random() * 52);
  let hash = chars.substring(n, n + 1);
  for (let i = 0; i < size - 2; i++) {
    n = Math.floor(Math.random() * 65);
    hash += chars.substring(n, n + 1);
  }
  // last letter: exclude special chars
  n = Math.floor(Math.random() * 62);
  hash += chars.substring(n, n + 1);
  return hash;
} // createHash


function pullScript(url, callback){
  pull(url, function loadReturn(data, status, xhr){
    if(status === 200){
      var script = document.createElement('script');
      script.innerHTML = data; // Instead of setting .src set .innerHTML
      document.querySelector('head').appendChild(script);
    }
    if (typeof callback != 'undefined'){
      // If callback was given skip an execution frame and run callback passing relevant arguments
      setTimeout(function runCallback(){callback(data, status, xhr)}, 0);
    }
  });
}


/*
 * https://stackoverflow.com/questions/16839698/jquery-getscript-alternative-in-native-javascript#answer-74353637
 * Usage: pullScript(URL);
 */
function pull(url, callback) {
  var xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function() {
    if (xhr.readyState === XMLHttpRequest.DONE) {
      callback(xhr.responseText, xhr.status, xhr);
    }
  };
  xhr.open('GET', url, true);
  xhr.setRequestHeader('accept', '*/*;q=0.5, text/javascript, application/javascript, application/ecmascript, application/x-ecmascript');
  xhr.setRequestHeader('x-requested-with', 'XMLHttpRequest');
  xhr.send();
}


function scrollAnchorIntoView() {
  let anchor = window.location.hash;
  if (anchor) {
    anchor = anchor.substring(1);
    const target = document.getElementById(anchor);
    if (target) {
      target.scrollIntoView({behavior: "smooth"});
    }
  }

} // scrollAnchorIntoView

function showBusySpinner() {
  const spinnerOverlay = document.querySelector('.pfy-spinner-overlay');
  if (!spinnerOverlay) return;
  spinnerOverlay.style.display = 'block';
  document.body.dataset.overflow = document.body.style.overflow;
  document.body.style.overflow = 'hidden';
  console.debug('spinnerOverlay activated');
} // showBusySpinner


function hideBusySpinner() {
  const spinnerOverlay = document.querySelector('.pfy-spinner-overlay');
  if (!spinnerOverlay) return;
  spinnerOverlay.style.display = 'none';
  document.body.style.overflow = document.body.dataset.overflow;
  document.body.removeAttribute('data-overflow');
  console.debug('spinnerOverlay deactivated');
} // hideBusySpinner


function initBusySpinner() {
  const spinnerImg = document.querySelector('.pfy-spinner-overlay img');
  if (!spinnerImg) return;
  const url = spinnerImg.dataset.src;
  spinnerImg.setAttribute('src', url);
} // initBusySpinner


function initToDoLists() {
  document.body.addEventListener('click', (ev) => {
    if (!ev.target.closest('.mdp-todo-list li')) {
      return;
    }
    const liEl = ev.target;
    liEl.classList.toggle('checked');
    liEl.toggleAttribute('aria-checked');
  });
} // initToDoLists


function removeUrlQueryParam(url, paramToRemove) {
  const urlObj = new URL(url);
  urlObj.searchParams.delete(paramToRemove);
  return urlObj.toString();
} // removeUrlQueryParam


function isElementDimmed(element) {
  let currentElement = element;
  while (currentElement) {
    const opacity = parseFloat(window.getComputedStyle(currentElement).opacity);
    if (opacity < 1) {
      return true;
    }
    currentElement = currentElement.parentElement;
  }

  return false;
} // isElementDimmed


/*
    Register a global event handler
    -> must be called inside jsReady
 */
function pfyHandleEvent(selector, func, trigger = 'click', containerEl = null) {
  console.debug(`registering event handler for "${selector}"`);
  document.addEventListener(trigger, (ev) => {
    if (containerEl && !containerEl.contains(ev.target)) {
      return;
    }
    if (!ev.target.closest(selector)) {
      return;
    }
    func(ev, ev.target);
  });
} // pfyHandleEvent

