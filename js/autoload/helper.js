/*
 * helpers.js
 * Helper functions for Kirby PageFactory plugin
 */

var elem = document.getElementsByClassName('lzy-async-load');
for (i=0;i<elem.length;i++) {
    elem[i].setAttribute('media', 'all');
    console.log('async load: ');
    console.log(elem[i]);
}


function adaptToWidth() {
  if (window.innerWidth < screenSizeBreakpoint) {
    document.body.classList.add('lzy-small-screen');
    document.body.classList.remove('lzy-large-screen');
  } else {
    document.body.classList.add('lzy-large-screen');
    document.body.classList.remove('lzy-small-screen');
  }
}
window.onresize = function() {
  adaptToWidth();
}
window.onload = function() {
  adaptToWidth();
}


function mylog(str) {
    console.log(str);
}
