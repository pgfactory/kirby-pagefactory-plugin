"use strict";

/*
  nav
    ol
      li.pfy-has-children
        a
        div.pfy-nav-sub-wrapper
          ol [margin-top: -Hpx  aria-hidden: true];
            li
              a
 */

class PfyNav {

  constructor(navWrapper) {
    this.navWrapper = navWrapper;
    this.transitionTimeMs = 300;
    this.arrowClicks = 0;
    this.arrowSvg = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" width="1em">' +
      '<path d="M15 12L9 6V18L15 12Z" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>' +
      '</svg>';

      this.init();
  } // constructor


  init() {
    //mylog('init: ' + this.navWrapper.getAttribute('id'));
    const navWrapper  = this.navWrapper;
    this.isPrimary    = navWrapper.classList.contains('pfy-primary-nav');
    this.isTopNav     = navWrapper.classList.contains('pfy-nav-horizontal');
    this.collapsed    = navWrapper.classList.contains('pfy-nav-collapsed')? 1 : false;
    this.collapsible  = navWrapper.classList.contains('pfy-nav-collapsible');
    this.hoveropen    = navWrapper.classList.contains('pfy-nav-hoveropen');
    this.preOpenCurr  = navWrapper.classList.contains('pfy-nav-open-current');
    this.navInx       = navWrapper.dataset.navInx;
    this.navElemInx   = 0;
    this.timer        = [];

    this.initNavHtml();

    this.adaptToWidth(); // invokes this.setupMouseHandlers() if it's primary nav

    this.setupKeyHandlers();

    if (this.preOpenCurr) {
      this.openCurrentElem();
    }

    this.initAnimation();
    this.setupResizeMonitor();

  } // init


  initNavHtml() {
    const navWrapper = this.navWrapper;
    this._initNavHtml(navWrapper.querySelectorAll('.pfy-nav > ol > li'), 1);
    this.fixNavLayout();

    if (this.isTopNav && this.isPrimary) {
      const liElems = navWrapper.querySelectorAll('.pfy-has-children');
      liElems.forEach(function (liElem) {
        const subElem = liElem.querySelector('.pfy-nav-sub-wrapper');
        if (subElem) {
          subElem.style.display = 'none';
        }
      });
    }
    if (this.collapsible) {
      domForEach(navWrapper, 'li.pfy-has-surrogate-elem > a .pfy-nav-label span', (el) => {
        el.classList.add('pfy-nav-reveal-controller');
      })
      domForEach(navWrapper, 'li.pfy-nav-no-direct-child > a .pfy-nav-label span', (el) => {
        el.classList.add('pfy-nav-reveal-controller');
      })
    }

  } // initNavHtml


  _initNavHtml (liElems, depth) {
    const parent = this;
    if (liElems.length) {

      let inx = 0;
      liElems.forEach(function (liElem) {
        inx++;
        parent.navElemInx++;

        const subId = `nav-elem-${parent.navInx}-${parent.navElemInx}`;
        // apply level-class:
        liElem.classList.add('pfy-lvl-' + depth);

        const subOlElem = liElem.querySelector('ol,ul');
        let needsSurrogate = subOlElem && !liElem.classList.contains('pfy-nav-no-direct-child');
        needsSurrogate = needsSurrogate && (!parent.isTopNav || (depth === 1));

        // mark current-page (and its parent pages):
        const aElem = liElem.querySelector('a');
        const currPage = aElem.getAttribute('aria-current')? ' aria-current="page"' : '';
        if (currPage && !needsSurrogate) {
          liElem.classList.add('pfy-curr');
          let parentLiElem = liElem.parentElement.closest('li');
          while (parentLiElem) {
            parentLiElem.classList.add('pfy-active');
            parentLiElem = parentLiElem.parentElement.closest('li');
          }
        }

        // handle sub-branches:
        if (subOlElem) {
          liElem.classList.add('pfy-has-children');
          let ariaExpanded = parent.isTopNav ? 'false' : 'true';
          if (!parent.isTopNav) {
            liElem.classList.add('pfy-open');
          }

          // inject arrow into <a>:
          const text = liElem.querySelector('a').textContent;

          const href = aElem.getAttribute('href');

          aElem.outerHTML = `<a href="${href}" aria-expanded="${ariaExpanded}" aria-controls="${subId}"><span class='pfy-nav-label'>` +
            `<span>${text}</span></span><span class='pfy-nav-arrow' aria-hidden='true'>${parent.arrowSvg}</span></a>`;

          let olInnerHtml = subOlElem.innerHTML;

          if (needsSurrogate) {
            const aHref = aElem.getAttribute('href');
            const aText = aElem.innerHTML;
            olInnerHtml = '<li class="pfy-lvl-' + (depth + 1) + ` pfy-surrogate-elem"><a href="${aHref}">${aText}</a></li>` + olInnerHtml;
            liElem.classList.add('pfy-has-surrogate-elem');
          }
          subOlElem.outerHTML = `<div id="${subId}" class="pfy-nav-sub-wrapper"><ol>` + olInnerHtml + '</ol>';

          // process all contained <li> recursively:
          const subLiElems = liElem.querySelectorAll(':scope > div > ol > li');
          if (subLiElems.length) {
            parent._initNavHtml(subLiElems, depth + 1);
          }
        }
        if (currPage) {
          if (needsSurrogate) {
            domForOne(liElem, '.pfy-surrogate-elem', (surrogateLi) => {
              surrogateLi.classList.add('pfy-curr');
              surrogateLi.setAttribute('aria-current', 'page');
            })
            liElem.classList.add('pfy-active');
          } else {
            liElem.classList.add('pfy-curr');
            liElem.setAttribute('aria-current', 'page');
          }

          let parentLiElem = liElem.parentElement.closest('li');
          while (parentLiElem) {
            parentLiElem.classList.add('pfy-active');
            parentLiElem = parentLiElem.parentElement.closest('li');
          }
        }

      });
    }
  } // _initNavHtml


  fixNavLayout() {
    const navWrapper = this.navWrapper;
    const nav = navWrapper.querySelector('.pfy-nav');
    const fsVar = getComputedStyle(navWrapper).getPropertyValue('--pfy-nav-txt-size');
    if (!fsVar) {
      const fs = getComputedStyle(navWrapper).getPropertyValue('font-size');
      navWrapper.style.setProperty('--pfy-nav-txt-size', fs);
    }
    nav.style.display = 'block';
    const placeHolder = navWrapper.querySelector('.pfy-top-nav-placeholder');
    if (placeHolder) {
      placeHolder.style.display = 'none';
    }
  } // fixNavLayout


  adaptToWidth() {
    this.isSmallScreen = (window.innerWidth < screenSizeBreakpoint);
    if (this.prevScreenMode !== this.isSmallScreen) {
      this.prevScreenMode = this.isSmallScreen;
      if (this.isSmallScreen) {
        this.initMobileMode();
      } else {
        this.initDesktopMode();
      }
      this.setupMouseHandlers();
    }

    if (this.isPrimary) {
      if (this.isSmallScreen) {
        this.openCurrentElem();
      } else {
        this.closeAll();
      }
    }
  } //adaptToWidth


  // === desktop mode =====================================
  initDesktopMode () {
    // mylog('initDesktopMode()');
    const navWrapper = this.navWrapper;
    if (this.isTopNav && this.isPrimary) {
      this.setMobileMode(false);
      this.collapsible = true;
    }
    this.presetSubElemHeights();
    if (this.collapsed && !this.isTopNav && !this.isPrimary) {
      this.openCurrentElem();
    }
    this.presetSubElemHeights();
  } // initDesktopMode



  // === mobile mode =====================================
  initMobileMode () {

    if (this.collapsed) {
      this.openCurrentElem();
    }

    if (!this.isPrimary) {
      return;
    }
    this.setMobileMode(true);

    const mobileMenuButton = document.getElementById('pfy-nav-menu-icon');
    if (!mobileMenuButton) {
      mylog('Error: mobileMenuButton not found.');
      return;
    }
    if (!mobileMenuButton.dataset.initialized) {
      mobileMenuButton.dataset.initialized = true;

      // set button handler:
      mobileMenuButton.addEventListener('click', function (e) {
        e.stopPropagation();
        const button = e.currentTarget;
        if (document.body.classList.contains('pfy-nav-mobile-open')) {
          document.body.classList.remove('pfy-nav-mobile-open');
          button.setAttribute('aria-pressed', false);
        } else {
          document.body.classList.add('pfy-nav-mobile-open');
          button.setAttribute('aria-pressed', true);
        }
      });

      const main = document.querySelector('.pfy-main');
      domForOne('.pfy-main', (el) => {
        el.addEventListener('click', (ev) => {
          const mobileNavOpen = document.body.classList.contains('pfy-nav-mobile-open');
          if (mobileNavOpen) {
            ev.stopImmediatePropagation();
            document.body.classList.remove('pfy-nav-mobile-open');
            mobileMenuButton.setAttribute('aria-pressed', false);
          }
        })
      })
    }
  } // initMobileMode


  setMobileMode(activate){
    const navWrapper = this.navWrapper;
    if (!this.isPrimary) {
      return;
    }

    if (activate) {    // small screen:
      navWrapper.dataset.classList = navWrapper.classList.value;
      let cls = 'pfy-nav-wrapper pfy-mobile-nav pfy-primary-nav pfy-nav-indented pfy-nav-collapsible pfy-nav-animated pfy-encapsulated';
      if (navWrapper.classList.contains('pfy-mobile-nav-colored')) {
        cls += ' pfy-mobile-nav-colored';
      }
      //mylog('setMobileMode: ' + this.navWrapper.getAttribute('id'));
      navWrapper.classList.value = cls;
      this.openCurrentElem(navWrapper);

    } else {                                              // large screen:
      if (typeof navWrapper.dataset.classList === 'string') {
        navWrapper.classList.value = navWrapper.dataset.classList;
        navWrapper.dataset.classList = null;
      }
    }
    this.presetSubElemHeights();
  } // setMobileMode


  initAnimation () {
    const navWrapper = this.navWrapper;
    // switch classes from pfy-nav-animated to pfy-nav-animate after short delay:
    if (navWrapper && navWrapper.classList.contains('pfy-nav-animated')) {
      navWrapper.classList.remove('pfy-nav-animated');
      navWrapper.classList.add('pfy-nav-animate');
    }
  } // initAnimation


  setupMouseHandlers () {
    const navWrapper = this.navWrapper;
    const parent = this;
    const isTopNav = navWrapper.classList.contains('pfy-nav-horizontal');
    if (this.hoveropen) {
      // if hoveropen is enabled, activate click on arrow:
      const liElems = navWrapper.querySelectorAll('.pfy-has-children');
      if (liElems) {
        liElems.forEach(function (liElem) {
          const arrow = liElem.querySelector('.pfy-nav-arrow');
          if (arrow) {
            if (isTopNav) {
              arrow.addEventListener('click', parent.freezeBranchState);
            } else {
              arrow.addEventListener('click', parent.toggleBranch);
            }
          }
        });
      }
      if (!isTopNav) { // hover-open and NOT topNav -> no need to set up hover-handlers on arrows
        return;
      }

    } else { // no hover-open:
      if (isTopNav) {
        // setup click handler for top nav elems with children:
        domForEach(navWrapper, '.pfy-lvl-1.pfy-has-children > a', (el) => {
          el.addEventListener('click', (ev) => {
            parent.toggleBranch(ev, true);
          });
        })

        // for top nav: clicks outside of nav to close open branches:
        document.body.addEventListener('click', (ev) => {
          ev.stopImmediatePropagation();
          if (!parent.isSmallScreen && parent.navBranchIsOpen) {
            const navEl = document.querySelector('.pfy-primary-nav');
            parent.closeAll(navEl);
          }
        });

      } else if (this.collapsible) {
        // setup collapse-handlers on arrows:
        domForEach(navWrapper, '.pfy-has-children > a .pfy-nav-arrow', (el) => {
          el.addEventListener('click', (ev) => {
            parent.toggleBranch(ev);
          });
        })

        // setup collapse-handlers on elements without direct children:
        domForEach(navWrapper, '.pfy-nav-no-direct-child > a', (el) => {
          el.addEventListener('click', (ev) => {
            parent.toggleBranch(ev);
          });
        })
        domForEach(navWrapper, '.pfy-has-surrogate-elem > a', (el) => {
          el.addEventListener('click', (ev) => {
            parent.toggleBranch(ev);
          });
        })
      }

      return;
    }

    const l1AElems = navWrapper.querySelectorAll('.pfy-lvl-1.pfy-has-children > a');
    if (l1AElems) {
      l1AElems.forEach(function (l1AElem) {
        // set mouseenter-trigger on a elem:
        l1AElem.addEventListener('mouseenter', function (ev) {
          const elem = ev.currentTarget.parentElement;
          if ((typeof parent.timer === 'object') && (typeof parent.timer[elem.dataset.inx] !== 'undefined') && parent.timer[elem.dataset.inx]) {
            clearTimeout(parent.timer[elem.dataset.inx]);
          }
          parent.openBranch(elem);
        });

        // set mouseleave-trigger on li elem:
        l1AElem.parentElement.addEventListener('mouseleave', function (ev) {
          const elem = ev.currentTarget;
          if ((typeof parent.timer === 'object') && (typeof parent.timer[elem.dataset.inx] !== 'undefined')) {
            parent.timer[elem.dataset.inx] = setTimeout(function () {
              parent.closeBranch(elem);
              parent.timer[elem.dataset.inx] = false;
            }, parent.transitionTimeMs);
          }
        });
      })
    }
  } // setupMouseHandlers


  setupKeyHandlers () {
    const navWrapper = this.navWrapper;
    const parent = this;
    const liElems = navWrapper.querySelectorAll('li');

    if (!liElems) {
      return;
    }

    liElems.forEach(function (liElem) {
      const aElem = liElem.querySelector('a');
      aElem.addEventListener('keydown', function (ev) {
        ev.stopPropagation();
        const aElem = ev.currentTarget;
        const liElem = aElem.closest('li');
        const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');

        const key = ev.key;
        if (key === 'ArrowDown') {
          parent.focusOnNext(liElem);

        } else if (key === 'ArrowUp') {
          parent.focusOnPrevous(liElem);

        } else if (key === 'ArrowRight') {
          if (isTopNav) {
            parent.focusOnNextSibling(liElem);
          } else {
            if (liElem.classList.contains('pfy-has-children')) {
              parent.openBranch(liElem);
            }
            parent.setFocusOn(liElem);
          }

        } else if (key === 'ArrowLeft') {
          if (isTopNav) {
            parent.focusOnPrevSibling(liElem);
          } else {
            if (parent.isOpen(liElem)) {
              parent.closeBranch(liElem);
              parent.setFocusOn(liElem);
            } else {
              parent.setFocusOnParent(liElem);
            }
          }

        } else if (ev.shiftKey && key === 'Tab') {
          const liElem = ev.target.parentElement;
          const aElem = liElem.querySelector('a');
          const activeAElems = parent.getCurrentlyActiveAElments(liElem);
          if (aElem.innerText === activeAElems[0].innerText) {
            // is last element, so continue with default action
            return;
          }
          ev.preventDefault();
          parent.focusOnPrevous(liElem);

        } else if (key === 'Tab') {
          const liElem = ev.target.parentElement;
          const aElem = liElem.querySelector('a');
          const activeAElems = parent.getCurrentlyActiveAElments(liElem);
          if (aElem.innerText === activeAElems[activeAElems.length - 1].innerText) {
            // is first element, so continue with default action
            return;
          }

          if (liElem.closest('.pfy-open')) {
            parent.focusOnNext(liElem);
          } else {
            parent.focusOnNextSibling(liElem);
          }
          ev.preventDefault();

        } else if (key === ' ') {
          ev.preventDefault();
          if (isTopNav) {
            parent.toggleBranch(liElem, true);
          } else {
            parent.toggleBranch(liElem);
          }
        }
      });
    });
  } // setupKeyHandlers


  focusOnPrevSibling (liElem) {
    if (liElem.classList.contains('pfy-lvl-1') && liElem.classList.contains('pfy-open')) {
      this.closeBranch(liElem);
    }
    const prevLiElem = liElem.previousElementSibling;
    if (prevLiElem) {
      this.setFocusOn(prevLiElem);
    } else {
      this.setFocusOn(liElem);
    }
  } // focusOnPrevSibling


  focusOnNextSibling (liElem) {
    if (liElem.classList.contains('pfy-lvl-1') && liElem.classList.contains('pfy-open')) {
      this.closeBranch(liElem);
    }
    const nextLiElem = liElem.nextElementSibling;
    if (nextLiElem) {
      this.setFocusOn(nextLiElem);
    } else {
      this.setFocusOn(liElem);
    }
  } // focusOnNextSibling


  focusOnNext (liElem) {
    const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
    if (isTopNav && liElem.classList.contains('pfy-lvl-1') && liElem.classList.contains('pfy-has-children')) {
      if (!liElem.classList.contains('pfy-open')) {
        this.openBranch(liElem);
      }
      const firstSubAElem = liElem.querySelector('div > ol > li > a');
      if (firstSubAElem) {
        this.setFocusOn(firstSubAElem);
      }

      // not first elem in branch:
    } else {
      const nextLi = this.setFocusOn(liElem, 1);
      if (isTopNav && nextLi.classList.contains('pfy-lvl-1')) {
        this.closeAllExcept(nextLi);
      }
    }
  } // focusOnNext


  focusOnPrevous (liElem) {
    const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
    if (isTopNav && liElem.classList.contains('pfy-lvl-1')) {
      if (liElem.classList.contains('pfy-has-children') && liElem.classList.contains('pfy-open')) {
        this.closeBranch(liElem);
        this.setFocusOn(liElem, 0);
      } else {
        this.focusOnPrevSibling(liElem);
      }
    } else {
      this.setFocusOn(liElem, -1);
    }
  } // focusOnPrevous


  setFocusOn (liElem, offset) {
    offset = (typeof offset !== 'undefined') ? offset : 0;
    let nextA = liElem;
    if (liElem.tagName !== 'A') {
      nextA = this.getAElem(liElem, offset);
    }
    setTimeout(function () {
      nextA.focus();
    }, 50);
    return nextA.closest('li');
  } // setFocusOn


  setFocusOnParent (liElem){
    if (!liElem.classList.contains('pfy-lvl-1')) {
      const parentLi = liElem.parentElement.closest('li');
      this.setFocusOn(parentLi);
    } else {
      this.setFocusOn(liElem);
    }
  } // setFocusOnParent


  getAElem(liElem, offset) {
    const aElem = liElem.querySelector('a');
    let activeAElems = this.getCurrentlyActiveAElments(liElem);
    const currI = Array.from(activeAElems).indexOf(aElem);
    let nextI = currI + offset;
    return activeAElems[nextI];
  } // getAElem


  getCurrentlyActiveAElments (liElem) {
    const activeAElems = [];
    function traverse(liElem) {
      const aElem = liElem.querySelector('a');
      if (aElem) {
        activeAElems.push(aElem);
      }
      if (liElem.classList.contains('pfy-has-children')) {
        const childDivElem = liElem.querySelector('div');
        if (childDivElem && childDivElem.style.display !== 'none') {
          const childLiElems = childDivElem.querySelectorAll(':scope > ol > li');
          childLiElems.forEach(function (childLiElem) {
            traverse(childLiElem);
          });
        }
      }
    }
    const navL1LiElements = liElem.closest('.pfy-nav').querySelectorAll(':scope > ol > li');
    navL1LiElements.forEach(liElem => traverse(liElem));
    return activeAElems;
  } // getCurrentlyActiveAElments


  handleSingleAndDoubleClick (event, singleClickCallback, doubleClickCallback) {
    const parent = this;
    const el = event.target??event.currentTarget?? false;
    if (!el) {
      return;
    }
    const isTopNav = el.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
    if (isTopNav) {
      doubleClickCallback(event);
      return;
    }

    this.arrowClicks++;
    if (this.arrowClicks > 1) {
      this.arrowClicks = 0;
      doubleClickCallback(event);
    } else {
      setTimeout(function () {
        if (parent.arrowClicks === 1) {
          parent.arrowClicks = 0;
          singleClickCallback(event);
        }
      }, 250);
    }
  } // handleSingleAndDoubleClick


  freezeBranchState (ev) {
    ev.stopPropagation();
    ev.preventDefault();
    let liElem = ev.currentTarget;
    if (liElem.classList.contains('pfy-nav-arrow')) {
      liElem = liElem.closest('li');
    }
    if (liElem.closest('.pfy-nav-horizontal')) {
      this.closeAllExcept(liElem);
    }
    const isOpen = liElem.classList.contains('pfy-open');
    if (liElem.classList.contains('pfy-branch-frozen')) {
      liElem.classList.remove('pfy-branch-frozen');
      if (isOpen) {
        this.closeBranch(liElem, true);
      }

    } else {
      liElem.classList.add('pfy-branch-frozen');
      if (!isOpen) {
        this.openBranch(liElem, true);
      }
    }
  } // freezeBranchState


  toggleBranch (eventOrElem, closeOthers = false) {
    const parent = this;
    if (typeof eventOrElem.currentTarget === 'undefined') {
      this._toggleBranch(eventOrElem, closeOthers);

    } else { // event:
      eventOrElem.stopPropagation();
      eventOrElem.preventDefault();
      this.handleSingleAndDoubleClick(eventOrElem,
        function (eventOrElem) {
          parent._toggleBranch(eventOrElem, closeOthers);
        },
        function (eventOrElem) {
          parent._toggleBranch(eventOrElem, closeOthers, true);
        }
      );
    }
  } // toggleBranch


  _toggleBranch (eventOrElem, closeOthers = false, recursive = false) {
    let liElem = null;
    let targ = null;
    if (typeof eventOrElem.currentTarget === 'undefined') {
      targ = eventOrElem;
    } else if (eventOrElem.currentTarget) {
      targ = eventOrElem.currentTarget
    } else if (eventOrElem.target) {
      targ = eventOrElem.target
    }
    if (targ) {
      liElem = targ.closest('li');
      const isOpen = liElem.classList.contains('pfy-open');
      if (isOpen) {
        this.closeBranch(liElem, true, recursive);
      } else {
        if (closeOthers) {
          this.closeAllExcept(liElem);
        }
        this.openBranch(liElem,true, recursive);
      }
    }
  } // _toggleBranch


  isOpen (liElem) {
    return liElem.classList.contains('pfy-open');
  } // isOpen


  openBranch (eventOrElem, override = false, recursive = false) {
    const parent = this;
    let liElem = null;
    if (typeof eventOrElem.currentTarget === 'undefined') {
      liElem = eventOrElem;
    } else {
      liElem = eventOrElem.currentTarget;
    }
    this.navBranchIsOpen = true;

    const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
    if (isTopNav && !override && liElem.classList.contains('pfy-branch-frozen')) {
      return;
    }

    this.presetSubElemHeight(liElem);

    this.openLi(liElem);
    if (recursive) {
      const liElems = liElem.querySelectorAll('li');
      if (liElems) {
        liElems.forEach(function (liElem) {
          parent.openLi(liElem, true);
        });
      }
    }
  } // openBranch


  openLi(liElem, noDelay) {
    const divElem = liElem.querySelector('div');
    if (divElem && typeof divElem.style !== 'undefined') {
      divElem.style.display = null;
    }
    const aElem = liElem.querySelector('a');
    aElem.setAttribute('aria-expanded', true);
    if (typeof noDelay === 'undefined') {
      setTimeout(function () {
        liElem.classList.add('pfy-open');
      }, 50);
    } else {
      liElem.classList.add('pfy-open');
    }
  } // openLi


  closeBranch (eventOrElem, override = false, recursive = false) {
    const parent = this;
    let liElem = null;
    if (typeof eventOrElem.currentTarget === 'undefined') {
      liElem = eventOrElem;
    } else {
      liElem = eventOrElem.currentTarget;
    }
    this.presetSubElemHeight(liElem, true);
    this.navBranchIsOpen = false;

    const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
    if (isTopNav && !override && (liElem.classList.contains('pfy-branch-frozen'))) {
      return;
    }
    this.closeLi(liElem);
    if (recursive) {
      const liElems = liElem.querySelectorAll('li');
      if (liElems) {
        liElems.forEach(function (liElem) {
          parent.closeLi(liElem);
        });
      }
    }
  } // closeBranch


  closeLi (liElem, noDelay) {
    liElem.classList.remove('pfy-open');
    const aElem = liElem.querySelector('a');
    aElem.setAttribute('aria-expanded', false);
    const divElem = liElem.querySelector('div');
    if (divElem) {
      if (typeof noDelay === 'undefined') {
        setTimeout(function () {
          divElem.style.display = 'none';
        }, this.transitionTimeMs);
      } else {
        divElem.style.display = 'none';
      }
    }
  } // closeLi


  closeAll (navWrapper) {
    const parent = this;
    if (typeof navWrapper === 'undefined') {
      navWrapper = this.navWrapper;
    }
    this.navBranchIsOpen = false;

    const liElems = navWrapper.querySelectorAll('.pfy-has-children');
    if (liElems) {
      liElems.forEach(function (liElem) {
        parent.closeLi(liElem, true);
      });
    }
  } // closeAll


  closeAllExcept (exceptSubBranch, navWrapper) {
    const parent = this;
    exceptSubBranch = (typeof exceptSubBranch !== 'undefined')? exceptSubBranch: false;
    if (typeof exceptSubBranch === 'object') {
      navWrapper = exceptSubBranch.closest('.pfy-nav-wrapper');
    }
    if (!navWrapper) {
      navWrapper = document.querySelector('.pfy-primary-nav');
      if (!navWrapper) {
        mylog('no primary nav present');
        return;
      }
    }

    const liElems = navWrapper.querySelectorAll('.pfy-lvl-1.pfy-has-children');
    if (liElems) {
      liElems.forEach(function (liElem) {
        if (liElem !== exceptSubBranch) {
          parent.closeBranch(liElem, true);
          const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
          if (isTopNav) {
            liElem.classList.remove('pfy-branch-frozen');
          }
        }
      })
    }
  } // closeAllExcept


  openCurrentElem () {
    const navWrapper = this.navWrapper;
    const parent = this;

    // find current elem:
    let aElem = navWrapper.querySelector('[aria-current="page"]');
    if (!aElem) {
      return;
    }
    const currLi = aElem.parentElement;
    let liElem =  currLi;

    // close all branches recursively:
    this.closeAll(navWrapper);

    // open all from current elem up to top level:
    while (liElem) {
      this.openLi(liElem, true);
      liElem = liElem.parentElement.closest('li');
    }

    // open level below current elem:
    if (currLi && currLi.classList.contains('pfy-has-children')) {
      domForEach(currLi, 'li', (el) => {
        parent.openLi(el, true);
      })
    }
  } // openCurrentElem


  presetSubElemHeights(){
    let navWrapper = this.navWrapper;
    if (!this.collapsible) {
      return;
    }

    const parent = this;
    let pfyNavSubWrappers;
    if (this.navWrapper.classList.contains('pfy-nav-horizontal')) {
      pfyNavSubWrappers = navWrapper.querySelectorAll('.pfy-lvl-1.pfy-has-children');
    } else {
      pfyNavSubWrappers = navWrapper.querySelectorAll('.pfy-has-children');
    }

    if (pfyNavSubWrappers) {
      pfyNavSubWrappers.forEach(function (liElem) {
        parent.presetSubElemHeight(liElem, true);
      });
    }
  } // presetSubElemHeights


  presetSubElemHeight(liElem, leaveOpen = false){
    const subDivElem = liElem.querySelector('div');
    if (!subDivElem) {
      return;
    }
    const olElem = liElem.querySelector('div > ol');
    if (!olElem) {
      return;
    }
    subDivElem.style.display = null;
    const h = olElem.offsetHeight;
    olElem.style.marginTop = -h + 'px';
    if (subDivElem && !leaveOpen) {
      subDivElem.style.display = 'none';
    }
  } // presetSubElemHeight


  setupResizeMonitor() {
    const parent = this;
    window.addEventListener('resize', function() {
      parent.adaptToWidth();
    });
  } // setupResizeMonitor

} // PfyNav


document.addEventListener('DOMContentLoaded', function() {
  domForEach('.pfy-nav-wrapper', (navWrapper) => {
    new PfyNav(navWrapper);
  })
});

