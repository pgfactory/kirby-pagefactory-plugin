/*
 *  dom.js — DOM query helpers
 *
 *  Pattern syntax:
 *    ^closestSel childSel  -> elem.closest(closestSel).querySelector(childSel)
 *    ^closestSel           -> elem.closest(closestSel)
 *    > sel                 -> :scope > sel  (also for +, ~)
 */


function domForAll(elem = document, pattern = null, fun = null) {
  domForEach(elem, pattern, fun);
} // domForAll


function domForEach(elem = document, pattern = null, fun = null) {
  [elem, pattern, fun] = handleParentPattern(elem, pattern, fun);
  if (!pattern) {
    return;
  }
  const nodes = elem instanceof NodeList ? elem : [elem];
  nodes.forEach(node => {
    node.querySelectorAll(pattern).forEach(el => fun(el));
  });
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
      });
      return;
    }
    elem = elem.querySelector(pattern);
  }
  if (elem) {
    fun(elem);
  }
} // domForOne


function handleParentPattern(elem, pattern, fun) {
  let parentPattern;
  [elem, pattern, parentPattern, fun] = parseDomForArgs(elem, pattern, fun);
  if (!fun) {
    console.log('domForXY(): nothing to do');
    return [null, null, null];
  }
  if (!(elem instanceof Node) && !(elem instanceof NodeList)) {
    console.log(`DOM element is not valid: ${elem}`);
    return [null, null, null];
  }

  if (parentPattern) {
    if (!(elem instanceof Element)) {
      console.error('When using ^ in pattern, elem must be an Element (not document).');
      return [null, null, null];
    }
    elem = elem.closest(parentPattern);
  }
  if (!elem) {
    return [null, null, null];
  }
  return [elem, pattern, fun];
} // handleParentPattern


function parseDomForArgs(elem, pattern, fun) {
  if (typeof elem !== 'object') {
    fun = pattern;
    pattern = elem;
    elem = document;
  }

  let parentPattern = '';
  if (typeof pattern !== 'string') {
    return [null, null, null];
  }
  pattern = pattern.trim();

  if (!pattern.includes('^')) {
    // Normal case: auto-prefix combinators with :scope
    const parts = pattern.split(',');
    pattern = parts.map(pat => {
      pat = pat.trim();
      return pat.match(/^[+>~]/) ? ':scope ' + pat : pat;
    }).join(', ');

  } else {
    if (pattern.includes(',')) {
      console.error('Syntax error in argument: comma not supported with ^: ' + pattern);
      return [elem, '', '', null];
    }
    const m = pattern.match(/\^(\S*)\s*(.*)/);
    if (m) {
      parentPattern = m[1];
      pattern = m[2];
    }
  }

  return [elem, pattern, parentPattern, fun];
} // parseDomForArgs


function domReady(fun) {
  document.addEventListener('DOMContentLoaded', fun);
} // domReady
