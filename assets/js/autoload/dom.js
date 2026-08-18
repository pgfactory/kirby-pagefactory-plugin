/*
 *  dom.js — DOM query helpers
 *
 *  Pattern syntax:
 *    ^closestSel childSel  -> elem.closest(closestSel).querySelector(childSel)
 *    ^closestSel           -> elem.closest(closestSel)
 *    > sel                 -> :scope > sel  (also for +, ~)
 */


/**
 * Alias for {@link domForEach}. Runs `fun` for every element matching
 * `pattern` within `elem` (or within each node of `elem` if it is a NodeList).
 *
 * @param {Node|NodeList|string} [elem=document] - Root element/NodeList to
 *   search within. May be omitted, in which case this argument is treated as
 *   `pattern` and the search root defaults to `document`.
 * @param {string|Function} [pattern=null] - CSS selector pattern (see the
 *   pattern syntax documented at the top of this file). May be omitted, in
 *   which case this argument is treated as `fun`.
 * @param {function(Element): void} [fun=null] - Callback invoked once per
 *   matched element, receiving the matched element as its only argument.
 * @returns {void}
 */
function domForAll(elem = document, pattern = null, fun = null) {
  domForEach(elem, pattern, fun);
} // domForAll


/**
 * Finds every element matching `pattern` within `elem` and invokes `fun` on
 * each match. If `elem` is a NodeList, the pattern is applied independently
 * to every node in the list and matches from all nodes are visited.
 *
 * Supports the flexible calling convention handled by {@link parseDomForArgs}
 * (i.e. `elem` may be omitted) and the `^parentSelector` / combinator prefix
 * syntax handled by {@link handleParentPattern}.
 *
 * Errors thrown by `fun` or by an invalid selector are caught per-node and
 * logged via `console.debug` rather than propagated.
 *
 * @param {Node|NodeList|string} [elem=document] - Root element/NodeList to
 *   search within. May be omitted, in which case this argument is treated as
 *   `pattern` and the search root defaults to `document`.
 * @param {string|Function} [pattern=null] - CSS selector pattern (see the
 *   pattern syntax documented at the top of this file). May be omitted, in
 *   which case this argument is treated as `fun`. If it resolves to a falsy
 *   value after argument parsing, the function returns without doing anything.
 * @param {function(Element): void} [fun=null] - Callback invoked once per
 *   matched element, receiving the matched element as its only argument.
 * @returns {void}
 */
function domForEach(elem = document, pattern = null, fun = null) {
  [elem, pattern, fun] = handleParentPattern(elem, pattern, fun);
  if (!pattern) {
    return;
  }
  const nodes = elem instanceof NodeList ? elem : [elem];
  nodes.forEach(node => {
    try {
      const elems = node.querySelectorAll(pattern);
      if (elems.length !== 0) {
        elems.forEach(el => fun(el));
      }
    } catch (error) {
      console.debug(error);
    }
  });
} // domForEach


/**
 * Finds the first element matching `pattern` within `elem` and invokes `fun`
 * on it. If `elem` is a NodeList, the pattern is applied independently to
 * each node in the list and `fun` is invoked (at most once) per node for its
 * first match.
 *
 * Supports the flexible calling convention handled by {@link parseDomForArgs}
 * (i.e. `elem` may be omitted) and the `^parentSelector` / combinator prefix
 * syntax handled by {@link handleParentPattern}.
 *
 * Errors thrown by `fun` are caught per-element and logged via
 * `console.error` rather than propagated.
 *
 * @param {Node|NodeList|string} [elem=document] - Root element/NodeList to
 *   search within. May be omitted, in which case this argument is treated as
 *   `pattern` and the search root defaults to `document`.
 * @param {string|Function} [pattern=null] - CSS selector pattern (see the
 *   pattern syntax documented at the top of this file). May be omitted, in
 *   which case this argument is treated as `fun`. If falsy after argument
 *   parsing, `fun` is invoked directly on `elem` (or on each node of `elem`
 *   if it is a NodeList) without querying for a child element.
 * @param {function(Element): void} [fun=null] - Callback invoked with the
 *   matched element (or with `elem`/each node itself when no pattern is
 *   supplied).
 * @returns {void}
 */
function domForOne(elem = document, pattern = null, fun = null) {
  [elem, pattern, fun] = handleParentPattern(elem, pattern, fun);
  if (pattern) {
    if (elem instanceof NodeList) {
      elem.forEach(el => {
        el = el.querySelector(pattern);
        if (el) {
          try {
            fun(el);
          } catch (error) {
            console.error(error);
          }
        }
      });
      return;
    }
    elem = elem.querySelector(pattern);
  }
  if (elem) {
    try {
      fun(elem);
    } catch (error) {
      console.error(error);
    }
  }
} // domForOne


/**
 * Normalizes arguments for {@link domForEach} and {@link domForOne} and
 * resolves any `^parentSelector` prefix in `pattern` by walking up to the
 * closest matching ancestor via `Element.closest()`.
 *
 * Delegates initial argument normalization (optional `elem`, splitting off
 * the parent pattern) to {@link parseDomForArgs}.
 *
 * @param {Node|NodeList|string} elem - Root element/NodeList, or (per the
 *   overloaded calling convention) the pattern itself when `elem` is omitted
 *   by the caller.
 * @param {string|Function} pattern - CSS selector pattern, or the callback
 *   when `pattern` is omitted by the caller.
 * @param {Function} fun - Callback to invoke on matches, or `undefined` when
 *   omitted by the caller.
 * @returns {[Node|NodeList|null, string|null, Function|null]} A 3-tuple of
 *   `[elem, pattern, fun]` ready for use by the calling function. Returns
 *   `[null, null, null]` if arguments are invalid, if there is no callback,
 *   if `elem` is neither a `Node` nor a `NodeList`, if a `^` pattern is used
 *   against a non-`Element` root (e.g. `document`), or if `closest()` finds
 *   no matching ancestor.
 */
function handleParentPattern(elem, pattern, fun) {
  let parentPattern;
  [elem, pattern, parentPattern, fun] = parseDomForArgs(elem, pattern, fun);
  if (!fun) {
    console.debug('domForXY(): nothing to do');
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


/**
 * Parses and normalizes the raw arguments passed to {@link domForEach} /
 * {@link domForOne}, supporting an overloaded calling convention where
 * `elem` may be omitted (in which case it defaults to `document` and the
 * following arguments shift left).
 *
 * Also splits out the `^parentSelector` prefix syntax from `pattern`, and
 * auto-prefixes bare combinator selectors (`>`, `+`, `~`) with `:scope ` so
 * they behave as scoped queries relative to `elem` rather than the whole
 * document. Comma-separated selector lists are supported in the non-`^`
 * case (each part is auto-prefixed independently); combining `^` with a
 * comma-separated list is not supported and is treated as an error.
 *
 * @param {Node|NodeList|string} elem - Root element/NodeList, or the pattern
 *   itself if the caller omitted `elem` (detected by `typeof elem !== 'object'`).
 * @param {string|Function} pattern - CSS selector pattern (or the `^`-prefixed
 *   parent pattern syntax), or the callback if `elem` was omitted by the
 *   caller.
 * @param {Function} [fun] - Callback to invoke on matches, or `undefined` if
 *   `elem` was omitted by the caller and `pattern` holds the callback.
 * @returns {[Node|NodeList|null, string|null, string|null, Function|null]} A
 *   4-tuple of `[elem, pattern, parentPattern, fun]`. `parentPattern` is the
 *   empty string when no `^` prefix is present. Returns `[null, null, null, null]`
 *   if `pattern` is not a string (e.g. no usable pattern was supplied), or
 *   `[elem, '', '', null]` if `^` is combined with a comma-separated selector
 *   list (a logged syntax error).
 */
function parseDomForArgs(elem, pattern, fun) {
  if (typeof elem !== 'object') {
    fun = pattern;
    pattern = elem;
    elem = document;
  }

  let parentPattern = '';
  if (typeof pattern !== 'string') {
    return [null, null, null, null];
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
      return [elem, '', '', null, null];
    }
    const m = pattern.match(/\^(\S*)\s*(.*)/);
    if (m) {
      parentPattern = m[1];
      pattern = m[2];
    }
  }

  return [elem, pattern, parentPattern, fun];
} // parseDomForArgs


/**
 * Registers `fun` to run once the initial HTML document has been completely
 * loaded and parsed (i.e. on the `DOMContentLoaded` event), without waiting
 * for stylesheets, images, and subframes to finish loading.
 *
 * @param {EventListenerOrEventListenerObject} fun - Handler invoked when
 *   `DOMContentLoaded` fires on `document`.
 * @returns {void}
 */
function domReady(fun) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fun);
    } else {
      fun(); // Already loaded
    }
} // domReady
