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
function domForEach(elem = document, pattern = null, fun = null) {
    let parentPattern, childPattern;
    [elem, pattern, parentPattern, childPattern, fun] = parseDomForArgs(elem, pattern, fun);
    if (!fun) {
       console.log('domForEach(): nothing to do');
       return;
   }

   const elems = elem.querySelectorAll(pattern);
   if (elems.length === 0) return;
   elems.forEach((el) => {
       if (parentPattern) el = el.closest(parentPattern);
       if (el && childPattern) {
         const els = el.querySelectorAll(childPattern);
         if (els) {
           els.forEach((el) => {
             fun(el);
           });
         }
       } else {
         fun(el);
       }
   });
} // domForEach


function domForOne(elem = document, pattern = null, fun = null) {
   let parentPattern, childPattern;
   [elem, pattern, parentPattern, childPattern, fun] = parseDomForArgs(elem, pattern, fun);
     if (!fun) {
         console.log('domForEach(): nothing to do');
         return;
     }

     if (elem && pattern) elem = elem.querySelector(pattern);
     if (elem && parentPattern) elem = elem.closest(parentPattern);
     if (elem && childPattern) elem = elem.querySelector(childPattern);
     if (elem) fun(elem);
} // domForOne


function parseDomForArgs(elem, pattern, fun) {

  if (typeof elem !== 'object') {
    const tmp = elem;
    fun = pattern;
    pattern = tmp;
    elem = document;
  }

  let m;
  let parentPattern = '';
  let childPattern = '';
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
    m = pattern.match(/(.*)\^(.*)/);
    if (m) {
      pattern = m[1];
      parentPattern = m[2];
      m = parentPattern.match(/^\s*\{(.*?)}(.*)/);
      if (m) {
        parentPattern = m[1];
        childPattern = m[2];

      } else {
        m = parentPattern.match(/(.*?)\s+(.*)/);
        if (m) {
          parentPattern = m[1];
          childPattern = m[2];
        }
      }
    }
  }
  return [elem, pattern, parentPattern, childPattern, fun];
} // parseDomForArgs

