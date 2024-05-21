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
    const sitemap = document.querySelector('.pfy-sitemap');
    if (sitemap) {
      if (sitemap.classList.contains('pfy-nav-indented')) {
        sitemap.dataset.keepIndented = true;
      }
      sitemap.classList.add('pfy-nav-indented');
    }
  } else {
    document.body.classList.remove('pfy-small-screen');
    document.body.classList.add('pfy-large-screen');
    const sitemap = document.querySelector('.pfy-sitemap');
    if (sitemap) {
      if (!sitemap.dataset.keepIndented) {
        sitemap.classList.remove('pfy-nav-indented');
      }
    }
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
      mylog(`Copied to clipboard: "${str}"`);

      // flash the element for user feedback:
      const container = textareaEl.closest('.pfy-has-copy-btn');
      container.classList.add('pfy-flash-copied');
      setTimeout(function() {
        container.classList.remove('pfy-flash-copied');
      }, 1000);
    } catch (err) {
      mylog('Failed to copy: ' + err);
    }
  } // copyToClipboard
  mylog('Copy button(s) initialized.');
} // initCopyButton


function createHash(size = 8) {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.-_';
  // first char: exclude digits and special chars
  let n = Math.random()*52;
  let hash = chars.substring(n, n+1);
  for (let i=0; i<size-2; i++) {
    n = Math.random()*65;
    hash += chars.substring(n, n+1);
  }
  // last letter: exclude special chars
  n = Math.random()*62;
  hash += chars.substring(n, n+1);
  return hash;
} // createHash


document.addEventListener('DOMContentLoaded', function() {
  initCopyButton();
  adaptToWidth();
});
