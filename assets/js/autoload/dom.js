
function domForEach(elem = document, pattern = null, fun = null) {
    let parentPattern, childPattern;
    [elem, pattern, parentPattern, childPattern, fun] = ParseDomForArgs(elem, pattern, fun);
    if (!pattern || !fun) {
       console.log('domForEach(): nothing to do');
       return;
   }

   const elems = elem.querySelectorAll(pattern);
   if (elems.length === 0) return;
   elems.forEach((el) => {
       if (parentPattern) el = el.closest(parentPattern);
       if (el && childPattern) el = el.querySelector(childPattern);
       if (el) fun(el);
   });
} // domForEach



function domForOne(elem = document, pattern = null, fun = null) {
   let parentPattern, childPattern;
   [elem, pattern, parentPattern, childPattern, fun] = ParseDomForArgs(elem, pattern, fun);
     if (!pattern || !fun) {
         console.log('domForEach(): nothing to do');
         return;
     }

     elem = elem.querySelector(pattern);
     if (elem && parentPattern) elem = elem.closest(parentPattern);
     if (elem && childPattern) elem = elem.querySelector(childPattern);
     if (elem) fun(elem);
} // domForOne


function ParseDomForArgs(elem, pattern, fun) {
  // Parse and assign arguments based on their types
  [elem, pattern, fun] = [
    ...[elem, pattern, fun].filter(arg => typeof arg !== 'undefined' && arg !== null)
  ].map(arg =>
    typeof arg === 'object' && !(arg instanceof HTMLElement) ? null : arg
  );

  if (typeof elem === 'string') [pattern, fun] = [elem, pattern, fun];
  else if (typeof elem === 'function') [fun] = [elem, pattern];

  if (typeof pattern === 'function') [fun] = [pattern, fun];

  let parentPattern = null;
  let childPattern = null;

  // Parse patterns for hierarchical selection
  let m = pattern.match(/(.*?) \^ (.*)/);
  if (m) {
    pattern = m[1];
    parentPattern = m[2];
    m = parentPattern.match(/(.*?) \| (.*)/);
    if (m) {
      parentPattern = m[1];
      childPattern = m[2];
    }
  }
  elem = elem instanceof HTMLElement ? elem : document;
  return [elem, pattern, parentPattern, childPattern, fun];
} // ParseDomForArgs


