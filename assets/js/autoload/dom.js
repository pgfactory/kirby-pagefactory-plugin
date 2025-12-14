/*
 *  dom.js
 *  Syntax:
 *    sel1 ^closestSel sel2...
 *    sel1 ^{closestSel1 ...} sel2...
 *    sel1 ^{closestSel1 ...} sel2..., sel3...
 *
 *    > sel1      -> :scope > sel1
 *      [+>|~]
 */


function domForAll(elem = document, pattern = null, fun = null) {
  domForEach(elem, pattern, fun);
} // domForAll


function domForEach(elem = document, pattern = null, fun = null) {
  [elem, pattern, fun] = handleParentPattern(elem, pattern, fun);
  if (pattern) {
    if (elem instanceof NodeList) {
      elem.forEach(elem => {
        const elems = elem.querySelectorAll(pattern);
        if (elems) {
          elems.forEach((el) => {
            fun(el);
          });
        }
      })

    } else {
      const elems = elem.querySelectorAll(pattern);
      if (elems) {
        elems.forEach((el) => {
          fun(el);
        });
      }
    }
  }
} // domForEach


function domForOne(elem = document, pattern = null, fun = null) {
  [elem, pattern, fun] = handleParentPattern(elem, pattern, fun);
  if (pattern) {
    if (elem instanceof NodeList) {
        elem.forEach(el => {
          el = el.querySelector(pattern);
          if (el) {
            fun(el);
          }
        })
        return;
    } else {
      elem = elem.querySelector(pattern);
    }
  }
  if (elem) {
    fun(elem);
  }
} // domForOne


function handleParentPattern(elem, pattern, fun) {
  [elem, pattern, parentPattern, fun] = parseDomForArgs(elem, pattern, fun);
  if (!fun) {
    console.log('domForXY(): nothing to do');
    return [false, false, false];
  }
  if (!elem || !elem instanceof Element) {
    console.log(`DOM element is not a valid: ${elem}`);
    return [false, false, false];
  }

  if (parentPattern) {
    if (elem === document) {
      console.log('When using ^ in pattern, elem must be defined (other than document).');
    }
    elem = elem.closest(parentPattern);
  }
  if (!elem) {
    return [false, false, false];
  }
  return [elem, pattern, fun];
} // handleParentPattern


function parseDomForArgs(elem, pattern, fun) {
  if (typeof elem !== 'object') {
    const tmp = elem;
    fun = pattern;
    pattern = tmp;
    elem = document;
  }

  let m;
  let parentPattern = '';
  pattern = pattern.trim();
  if (!pattern.includes('^')) {   // normal case, i.e. without parent selector syntax:
    // check whether pattern contains multiple comma separated segments:
    if (pattern.includes(',')) {
      let subPatterns = pattern.split(',');
      pattern = '';
      subPatterns.forEach((pat) => {
        if (pat.match(/[+>|~]/)) {
          pat = ':scope ' + pat;
        }
        pattern += pat.trimEnd() + ',';
      });
      pattern = pattern.slice(0, -1);

    } else {
      if (pattern.match(/[+>|~]/)) {
        pattern = ':scope ' + pattern;
      }
    }

  } else {
    if (pattern.includes(',')) {
      alert('Syntax error is argument: ' + pattern);
      return;
    }

    m = pattern.match(/\^(\S*)\s*(.*)/);
    if (m) {
      parentPattern = m[1];
      pattern = m[2];
    }
  }
  return [elem, pattern, parentPattern, fun];
} // parseDomForArgs


function domReady(fun)
{
  document.addEventListener('DOMContentLoaded', fun);
} // domReady


function handleEvent(selector, func, trigger = 'click', containerEl = null) {
  document.addEventListener('DOMContentLoaded', () => {
    //console.log(`registring event handler for "${selector}"`);
    document.addEventListener(trigger, (ev) => {
      if (containerEl && !containerEl.contains(ev.target)) {
        return;
      }
      if (!ev.target.closest(selector)) {
        return;
      }
      func(ev, ev.target);
    });
  });
} // handleEvent
