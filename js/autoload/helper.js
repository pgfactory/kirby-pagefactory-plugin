/*
 * helpers.js
 * Helper functions for Kirby PageFactory plugin
 */


var elem = document.getElementsByClassName('lzy-onload-css');
for (i=0;i<elem.length;i++) {
    elem[i].setAttribute('media', 'all');
    console.log('async load: ');
    console.log(elem[i]);
}

window.onresize = function() {
    adaptToWidth();
}
window.onload = function() {
    adaptToWidth();
}


function adaptToWidth() {
    console.log("window.innerWidth: " + window.innerWidth);
    console.log("document.documentElement.clientWidth;: " + document.documentElement.clientWidth);
    let windowWidth = document.documentElement.clientWidth;
  if (windowWidth < screenSizeBreakpoint) {
    document.body.classList.add('lzy-small-screen');
    document.body.classList.remove('lzy-large-screen');
  } else {
    document.body.classList.add('lzy-large-screen');
    document.body.classList.remove('lzy-small-screen');
  }
}


function mylog(str) {
    console.log(str);
}



function scrollIntoView( selector, container ) {
    // if (typeof container !== 'undefined') {
        let elem = document.querySelector( selector );
        elem.scrollIntoView(false);
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



function unTransvar( str ) {
    // looks for '{{ lzy-... }}' patterns, removes them.
    // Note: if site_enableFilesCaching is active, transvars will already be translated at this point,
    // so, this is just a fallback to beautify output during dev time
    if ( str.match(/{{/)) {
        // need to hide following line ('{{...}}') from being translated when preparing cache:
        const patt = String.fromCharCode(123) + '{\\s*(lzy-)?(.*?)\\s*' + String.fromCharCode(125) + '}';
        const re = new RegExp( patt, 'g');
        str = str.replace(re, '$2');
    }
    return str;
} // unTransvar



function pfyReload( arg, url, confirmMsg ) {
    let newUrl = window.location.pathname.replace(/\?.*/, '');
    if (typeof url !== 'undefined') {
        newUrl = url.trim();
    }
    if (typeof arg !== 'undefined') {
        newUrl = appendToUrl(newUrl, arg);
    }
    if (typeof confirmMsg !== 'undefined') {
        lzyConfirm(confirmMsg).then(function() {
            console.log('initiating page reload: "' + newUrl + '"');
            window.location.replace(newUrl);
        });
    } else {
        console.log('initiating page reload: "' + newUrl + '"');
        window.location.replace(newUrl);
    }
} // pfyReload
