/*
 * helpers.js
 * Helper functions for Kirby PageFactory plugin
 */


// perform late loading:
var elem = document.getElementsByClassName('pfy-onload-css');
for (i=0;i<elem.length;i++) {
    let href = elem[i].getAttribute('href');
    elem[i].setAttribute('media', 'all');
    console.log('async load: ' + href);
}

elem = document.getElementsByClassName('pfy-onload');
for (i=0;i<elem.length;i++) {
    let src = elem[i].getAttribute('data-src');
    elem[i].setAttribute('src', src);
    elem[i].removeAttribute('data-src');
    console.log('async load: ' + src);
}

window.onresize = function() {
    adaptToWidth();
}


function adaptToWidth() {
    // console.log("window.innerWidth: " + window.innerWidth);
    // console.log("document.documentElement.clientWidth;: " + document.documentElement.clientWidth);
    let windowWidth = document.documentElement.clientWidth;
  if (windowWidth < screenSizeBreakpoint) {
    document.body.classList.remove('pfy-large-screen');
    document.body.classList.add('pfy-small-screen');
  } else {
    document.body.classList.remove('pfy-small-screen');
    document.body.classList.add('pfy-large-screen');
  }
}


function mylog(str) {
    console.log(str);
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



function execAjaxPromise(cmd, options, url) {
    return new Promise(function(resolve) {

        if (typeof url === 'undefined') {
            url = appRoot;
        }
        url = appendToUrl(url, cmd);
        $.ajax({
            method: 'POST',
            url: url,
            data: options
        })
            .done(function ( json ) {
                resolve( json );
            });
    });
} // execAjax



function appendToUrl(url, arg) {
    if (!arg) {
        return url;
    }
    arg = arg.replace(/^[?&]/, '');
    if (url.match(/\?/)) {
        url = url + '&' + arg;
    } else {
        url = url + '?' + arg;
    }
    return url;
} // appendToUrl



function translateVar(transvarDef)
{
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



function camelize(str) {
  return str.replace(/(?:^\w|[A-Z]|\b\w|\s+)/g, function(match, index) {
    if (+match === 0) return "";
    return index === 0 ? match.toLowerCase() : match.toUpperCase();
  });
} // camelize



function pfyReload( arg, url, confirmMsg ) {
    let newUrl = window.location.pathname.replace(/\?.*/, '');
    if (typeof url !== 'undefined') {
        newUrl = url.trim();
    }
    if (typeof arg !== 'undefined') {
        newUrl = appendToUrl(newUrl, arg);
    }
    if (typeof confirmMsg !== 'undefined') {
        pfyConfirm(confirmMsg).then(function() {
            console.log('initiating page reload: "' + newUrl + '"');
            window.location.replace(newUrl);
        });
    } else {
        console.log('initiating page reload: "' + newUrl + '"');
        window.location.replace(newUrl);
    }
} // pfyReload
