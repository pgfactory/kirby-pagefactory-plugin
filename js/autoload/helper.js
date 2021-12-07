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

