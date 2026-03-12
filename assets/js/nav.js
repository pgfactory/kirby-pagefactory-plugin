"use strict";

/*
  nav
    ol
      li.pfy-has-children
        a
        div.pfy-nav-sub-wrapper height:0
          ol [aria-hidden="true"]
            li
              a
 */
console.log('nav.js');

class PfyNav {

  static triggersInitialized = false;

  constructor(navWrapper) {
    this.navWrapper = navWrapper;
    this.transitionTimeMs = 300;
    this.arrowClicks = 0;
    this.arrowSvg = '<svg viewBox="0 0 24 24" fill="none" width="1em">' +
      '<path d="M15 12L9 6V18L15 12Z" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>' +
      '</svg>';

    this.init();
  } // constructor


  init() {
    const navWrapper  = this.navWrapper;
    this.isPrimary    = navWrapper.classList.contains('pfy-primary-nav');
    this.isTopNav     = navWrapper.classList.contains('pfy-nav-horizontal');
    this.collapsed    = navWrapper.classList.contains('pfy-nav-collapsed');
    this.collapsible  = navWrapper.classList.contains('pfy-nav-collapsible');
    this.preOpenCurr  = navWrapper.classList.contains('pfy-nav-open-current');
    this.navInx       = navWrapper.dataset.navInx;
    this.navElemInx   = 0;
    this.timer        = [];

    this.initNavHtml();

    this.prepareMobileMode();

    this.adaptToWidth();

    this.initTriggers();

    if (this.preOpenCurr) {
      this.openCurrentElem();
    }

    this.initAnimation();

  } // init


  initTriggers() {
    if (PfyNav.triggersInitialized) {
      return;
    }
    PfyNav.triggersInitialized = true;

    document.addEventListener('click', (ev) => {
      const el = ev.target;
      const isTopNav = !!el.closest('.pfy-nav-horizontal');

      // handle mobile menu button:
      const mobileMenuButton = el.closest('#pfy-nav-menu-icon');
      if (mobileMenuButton) {
        this.operateMobileMenu(mobileMenuButton, ev);
        return;
      }

      // check for non-nav-related events:
      if (!el.closest('.pfy-nav-wrapper')) {
        domForAll('.pfy-nav-collapsible .pfy-open', el => {
          console.log('close open branch');
          this.closeAll();
        })
        return;
      }
      // check for non-collapsible nav:
      if (!el.closest('.pfy-nav-collapsible')) {
        return;
      }
      const aEl = el.closest('a');
      if (!aEl) {
        return;
      }
      if (isTopNav) {
        // in top-nav, need to distinguish several cases:
        if (aEl.closest('.pfy-lvl-2') ||
          !(aEl.parentElement.classList.contains('pfy-has-surrogate-elem') || aEl.parentElement.classList.contains('pfy-has-children'))) {
          return;
        }
      } else {
        // outside top-nav, elements with aria-expanded attrib are menu operators, not links:
        if (!aEl.hasAttribute('aria-expanded')) {
          return;
        }
      }

      // handle collapsible nav branches:
      ev.preventDefault();
      ev.stopImmediatePropagation();
      if (isTopNav) {
        this.operateSubmenu(ev);
      } else {
        this.handleSingleAndDoubleClick(ev);
      }
    });

    // handle keys for nav manipulation:
    document.addEventListener('keydown', (ev) => {
      if (!ev.target.closest('.pfy-nav-wrapper')) {
        return;
      }
      this.keyHandlers(ev);
    });

    this.initResizeMonitor();

  } // initTriggers


  keyHandlers(ev) {
    ev.stopPropagation();
    ev.stopImmediatePropagation();
    const aElem = ev.target;
    const liElem = aElem.closest('li');
    const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
    const isCollapsible = !!liElem.closest('.pfy-nav-collapsible');

    const key = ev.key;

    // === ArrowDown:
    if (key === 'ArrowDown') {
      this.focusOnNext(liElem);
      ev.preventDefault();

    // === ArrowUp:
    } else if (key === 'ArrowUp') {
      this.focusOnPrevious(liElem);
      ev.preventDefault();

    // === ArrowRight:
    } else if (key === 'ArrowRight') {
      if (isTopNav) {
        this.focusOnNextSibling(liElem);
      } else {
        if (liElem.classList.contains('pfy-has-children')) {
          this.openBranch(liElem);
        }
        this.setFocusOn(liElem);
      }
      ev.preventDefault();

    // === ArrowLeft:
    } else if (key === 'ArrowLeft') {
      if (isTopNav) {
        this.focusOnPrevSibling(liElem);
      } else {
        if (this.isOpen(liElem)) {
          this.closeBranch(liElem);
          this.setFocusOn(liElem);
        } else {
          this.setFocusOnParent(liElem);
        }
      }
      ev.preventDefault();

    // === Shift-Tab:
    } else if (ev.shiftKey && key === 'Tab') {
      const targetLi = ev.target.parentElement;
      const targetA = targetLi.querySelector('a');
      const activeAElems = this.getCurrentlyActiveAElements(targetLi);
      if (targetA.innerText === activeAElems[0].innerText) {
        return; // first element, continue with default action
      }
      ev.preventDefault();
      this.focusOnPrevious(targetLi);

    // === Tab:
    } else if (key === 'Tab') {
      const targetLi = ev.target.parentElement;
      const targetA = targetLi.querySelector('a');
      const activeAElems = this.getCurrentlyActiveAElements(targetLi);
      const targetCollapsible = !!targetLi.closest('.pfy-nav-collapsible');
      if (targetA.innerText === activeAElems[activeAElems.length - 1].innerText) {
        return; // last element, continue with default action
      }

      if (!targetCollapsible) {
        this.focusOnNext(targetLi);
        ev.preventDefault();
        return;
      }

      if (targetLi.closest('.pfy-open')) {
        this.focusOnNext(targetLi);
      } else {
        this.focusOnNextSibling(targetLi);
      }
      ev.preventDefault();

    } else if (isCollapsible && key === ' ') {
      this.toggleBranch(liElem, ev, isTopNav);
    }
  } // keyHandlers


  operateSubmenu(ev, recursive = false) {
    const parentLi = ev.target.closest('li');
    const aEl = parentLi.querySelector('a');
    const isTopNav = !!parentLi.closest('.pfy-nav-horizontal');

    if (isTopNav && !parentLi.classList.contains('pfy-lvl-1')) {
      ev.stopImmediatePropagation();
      return;
    }

    // handle special case: horizontal top nav:
    const closeOthers = !!parentLi.closest('.pfy-nav-horizontal.pfy-primary-nav');
    const isArrow = !!ev.target.closest('.pfy-nav-arrow');
    const isRevealController = !!aEl.getAttribute('aria-controls');
    if (isArrow || isRevealController) {
      this.toggleBranch(parentLi, ev, closeOthers, recursive);
    }
  } // operateSubmenu


  operateMobileMenu(mobileMenuButton, ev) {
    ev.stopImmediatePropagation();
    const isOpen = document.body.classList.toggle('pfy-nav-mobile-open');
    mobileMenuButton.setAttribute('aria-pressed', isOpen);
  } // operateMobileMenu


  initNavHtml() {
    const lvl1LiEls = this.navWrapper.querySelectorAll('.pfy-nav > ol > li');
    this._initNavHtmlRecursively(lvl1LiEls, 1);
    this.fixNavLayout();
  } // initNavHtml


  _initNavHtmlRecursively(liElems, depth) {
    if (!liElems.length) {
      return;
    }

    liElems.forEach((liElem) => {
      this.navElemInx++;

      const subId = `nav-elem-${this.navInx}-${this.navElemInx}`;
      // apply level-class:
      liElem.classList.add('pfy-lvl-' + depth);
      const subOlElem = liElem.querySelector('ol,ul');
      let needsSurrogate = false;
      if (this.collapsible) {
        needsSurrogate = subOlElem && !liElem.classList.contains('pfy-nav-no-direct-child');
      }

      // detect current page:
      const aElem = liElem.querySelector('a');
      const isCurrent = !!aElem.getAttribute('aria-current');

      // handle sub-branches:
      if (subOlElem) {
        liElem.classList.add('pfy-has-children');
        const ariaExpanded = this.isTopNav ? 'false' : 'true';
        if (!this.isTopNav) {
          liElem.classList.add('pfy-open');
        }

        // capture values before outerHTML replacement invalidates aElem reference:
        const text = aElem.textContent;
        const href = aElem.getAttribute('href');
        const origInnerHTML = aElem.innerHTML;

        aElem.outerHTML = `<a href="${href}" aria-expanded="${ariaExpanded}" aria-controls="${subId}">` +
            `<span class='pfy-nav-label'><span>${text}</span></span>` +
            `<span class='pfy-nav-arrow' aria-hidden='true'>${this.arrowSvg}</span></a>`;

        let olInnerHtml = subOlElem.innerHTML;

        if (needsSurrogate) {
          olInnerHtml = `<li class="pfy-lvl-${depth + 1} pfy-surrogate-elem"><a href="${href}">${origInnerHTML}</a></li>` + olInnerHtml;
          liElem.classList.add('pfy-has-surrogate-elem');
        }
        subOlElem.outerHTML = `<div id="${subId}" class="pfy-nav-sub-wrapper"><ol>${olInnerHtml}</ol></div>`;

        // process all contained <li> recursively:
        const subLiElems = liElem.querySelectorAll(':scope > div > ol > li');
        if (subLiElems.length) {
          this._initNavHtmlRecursively(subLiElems, depth + 1);
        }
      }

      // mark current page and ancestors:
      if (isCurrent) {
        if (needsSurrogate) {
          domForOne(liElem, '.pfy-surrogate-elem', (surrogateLi) => {
            surrogateLi.classList.add('pfy-curr');
            surrogateLi.setAttribute('aria-current', 'page');
          });
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
  } // _initNavHtmlRecursively


  fixNavLayout() {
    const nav = this.navWrapper.querySelector('.pfy-nav');
    nav.style.display = '';
    const placeHolder = this.navWrapper.querySelector('.pfy-top-nav-placeholder');
    if (placeHolder) {
      placeHolder.style.display = 'none';
    }
  } // fixNavLayout


  adaptToWidth() {
    this.isSmallScreen = (window.innerWidth < screenSizeBreakpoint);
    if (this.prevScreenMode !== this.isSmallScreen) {
      this.prevScreenMode = this.isSmallScreen;
      if (this.isSmallScreen) {
        this.activateMobileMode();
      } else {
        this.activateDesktopMode();
      }
    }

    if (this.isPrimary && !this.isTopNav) {
      if (this.isSmallScreen) {
        this.openCurrentElem();
      } else {
        this.closeAll();
      }
    }
  } // adaptToWidth


  prepareMobileMode() {
    if (this.isPrimary) {
      this.navWrapper.dataset.classList = this.navWrapper.classList.value;
    }
  } // prepareMobileMode


  // === desktop mode =====================================
  activateDesktopMode() {
    if (this.isPrimary) {
      this.navWrapper.classList.value = this.navWrapper.dataset.classList;
    }
    if (this.collapsed && !this.isTopNav && !this.isPrimary) {
      this.openCurrentElem();
    }
  } // activateDesktopMode


  // === mobile mode =====================================
  activateMobileMode() {
    if (this.collapsed) {
      this.openCurrentElem();
    }

    if (!this.isPrimary) {
      return;
    }

    // set mobile specific classes:
    let cls = 'pfy-nav-wrapper pfy-mobile-nav pfy-primary-nav pfy-nav-indented pfy-nav-collapsible pfy-nav-animated pfy-encapsulated';
    if (this.navWrapper.classList.contains('pfy-mobile-nav-colored')) {
      cls += ' pfy-mobile-nav-colored';
    }
    this.navWrapper.classList.value = cls;
    this.openCurrentElem();
  } // activateMobileMode


  initAnimation() {
    const navWrapper = this.navWrapper;
    // switch class from pfy-nav-animated to pfy-nav-animate:
    if (navWrapper.classList.contains('pfy-nav-animated')) {
      navWrapper.classList.replace('pfy-nav-animated', 'pfy-nav-animate');
    }
  } // initAnimation


  focusOnPrevSibling(liElem) {
    if (liElem.classList.contains('pfy-lvl-1') && liElem.classList.contains('pfy-open')) {
      this.closeBranch(liElem);
    }
    this.setFocusOn(liElem.previousElementSibling || liElem);
  } // focusOnPrevSibling


  focusOnNextSibling(liElem) {
    if (liElem.classList.contains('pfy-lvl-1') && liElem.classList.contains('pfy-open')) {
      this.closeBranch(liElem);
    }
    this.setFocusOn(liElem.nextElementSibling || liElem);
  } // focusOnNextSibling


  focusOnNext(liElem) {
    const isCollapsible = !!liElem.closest('.pfy-nav-collapsible');
    const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
    if (isTopNav && liElem.classList.contains('pfy-lvl-1') && liElem.classList.contains('pfy-has-children')) {
      if (!liElem.classList.contains('pfy-open')) {
        this.openBranch(liElem);
      }
      const firstSubAElem = liElem.querySelector('div > ol > li > a');
      if (firstSubAElem) {
        this.setFocusOn(firstSubAElem);
      }
    } else {
      const nextLi = this.setFocusOn(liElem, 1);
      if (nextLi && isTopNav && isCollapsible && nextLi.classList.contains('pfy-lvl-1')) {
        this.closeAllExcept(nextLi);
      }
    }
  } // focusOnNext


  focusOnPrevious(liElem) {
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
  } // focusOnPrevious


  setFocusOn(liElem, offset = 0) {
    let nextA = liElem;
    if (liElem.tagName !== 'A') {
      nextA = this.getAElem(liElem, offset);
    }
    if (!nextA) {
      return false;
    }
    setTimeout(() => { nextA.focus(); }, 50);
    return nextA.closest('li');
  } // setFocusOn


  setFocusOnParent(liElem) {
    if (!liElem.classList.contains('pfy-lvl-1')) {
      this.setFocusOn(liElem.parentElement.closest('li'));
    } else {
      this.setFocusOn(liElem);
    }
  } // setFocusOnParent


  getAElem(liElem, offset) {
    const aElem = liElem.querySelector('a');
    const activeAElems = this.getCurrentlyActiveAElements(liElem);
    const currI = Array.from(activeAElems).indexOf(aElem);
    return activeAElems[currI + offset];
  } // getAElem


  getCurrentlyActiveAElements(liElem) {
    const activeAElems = [];
    function traverse(li) {
      // skip invisible elements:
      if (window.getComputedStyle(li).getPropertyValue('display') === 'none') {
        return;
      }
      const aElem = li.querySelector('a');
      if (aElem) {
        activeAElems.push(aElem);
      }
      // recursive descent:
      if (li.classList.contains('pfy-has-children')) {
        const childDivElem = li.querySelector('div');
        if (childDivElem && childDivElem.style.display !== 'none') {
          childDivElem.querySelectorAll(':scope > ol > li').forEach(traverse);
        }
      }
    }
    liElem.closest('.pfy-nav').querySelectorAll(':scope > ol > li').forEach(traverse);
    return activeAElems;
  } // getCurrentlyActiveAElements


  handleSingleAndDoubleClick(event) {
    const el = event.target ?? event.currentTarget ?? false;
    if (!el) {
      return;
    }

    this.arrowClicks++;
    if (this.arrowClicks > 1) {
      this.arrowClicks = 0;
      this.operateSubmenu(event, true); // double click
    } else {
      setTimeout(() => {
        if (this.arrowClicks === 1) {
          this.arrowClicks = 0;
          this.operateSubmenu(event); // single click
        }
      }, 250);
    }
  } // handleSingleAndDoubleClick


  freezeBranchState(ev) {
    ev.stopPropagation();
    ev.preventDefault();
    let liElem = ev.currentTarget;
    if (liElem.classList.contains('pfy-nav-arrow')) {
      liElem = liElem.closest('li');
    }
    if (liElem.closest('.pfy-nav-horizontal')) {
      this.closeAllExcept(liElem);
    }
    if (liElem.classList.contains('pfy-branch-frozen')) {
      liElem.classList.remove('pfy-branch-frozen');
      if (this.isOpen(liElem)) {
        this.closeBranch(liElem, true);
      }
    } else {
      liElem.classList.add('pfy-branch-frozen');
      if (!this.isOpen(liElem)) {
        this.openBranch(liElem, true);
      }
    }
  } // freezeBranchState


  toggleBranch(liElem, ev, closeOthers = false, recursive = false) {
    if (ev) {
      ev.stopImmediatePropagation();
      ev.preventDefault();
    }
    if (this.isOpen(liElem)) {
      this.closeBranch(liElem, true, recursive);
    } else {
      if (closeOthers) {
        this.closeAllExcept(liElem);
      }
      this.openBranch(liElem, true, recursive);
    }
  } // toggleBranch


  isOpen(liElem) {
    return liElem.classList.contains('pfy-open');
  } // isOpen


  openBranch(liElem, override = false, recursive = false) {
    const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
    if (isTopNav && !override && liElem.classList.contains('pfy-branch-frozen')) {
      return;
    }

    this.openLi(liElem);
    if (recursive) {
      liElem.querySelectorAll('li').forEach((childLi) => {
        this.openLi(childLi, true);
      });
    }
  } // openBranch


  openLi(liElem, noDelay = false) {
    const divElem = liElem.querySelector('div');
    if (divElem && typeof divElem.style !== 'undefined') {
      divElem.style.display = '';
    }
    if (liElem.classList.contains('pfy-has-children')) {
      liElem.querySelector('a').setAttribute('aria-expanded', true);
    }
    if (noDelay) {
      liElem.classList.add('pfy-open');
    } else {
      setTimeout(() => { liElem.classList.add('pfy-open'); }, 50);
    }
  } // openLi


  closeBranch(liElem, override = false, recursive = false) {
    const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
    if (isTopNav && !override && liElem.classList.contains('pfy-branch-frozen')) {
      return;
    }
    this.closeLi(liElem);
    if (recursive) {
      liElem.querySelectorAll('li').forEach((childLi) => {
        this.closeLi(childLi);
      });
    }
  } // closeBranch


  closeLi(liElem, noDelay = false) {
    liElem.classList.remove('pfy-open');
    if (liElem.classList.contains('pfy-has-children')) {
      liElem.querySelector('a').setAttribute('aria-expanded', false);
    }
    const divElem = liElem.querySelector('div');
    if (divElem) {
      if (noDelay) {
        divElem.style.display = 'none';
      } else {
        setTimeout(() => { divElem.style.display = 'none'; }, this.transitionTimeMs);
      }
    }
  } // closeLi


  closeAll(navWrapper = this.navWrapper) {
    navWrapper.querySelectorAll('.pfy-has-children').forEach((liElem) => {
      this.closeLi(liElem, true);
    });
  } // closeAll


  closeAllExcept(exceptSubBranch = false, navWrapper) {
    if (typeof exceptSubBranch === 'object') {
      navWrapper = exceptSubBranch.closest('.pfy-nav-wrapper');
    }
    if (!navWrapper) {
      navWrapper = document.querySelector('.pfy-primary-nav');
      if (!navWrapper) {
        console.log('no primary nav present');
        return;
      }
    }

    navWrapper.querySelectorAll('.pfy-lvl-1.pfy-has-children').forEach((liElem) => {
      if (liElem !== exceptSubBranch) {
        this.closeBranch(liElem, true);
        if (liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal')) {
          liElem.classList.remove('pfy-branch-frozen');
        }
      }
    });
  } // closeAllExcept


  openCurrentElem() {
    // find current elem:
    const aElem = this.navWrapper.querySelector('[aria-current="page"]');
    if (!aElem) {
      return;
    }
    const currLi = aElem.parentElement;
    let liElem = currLi;

    // close all branches recursively:
    this.closeAll();

    // open all from current elem up to top level:
    while (liElem) {
      this.openLi(liElem, true);
      liElem = liElem.parentElement.closest('li');
    }

    // open level below current elem:
    if (currLi && currLi.classList.contains('pfy-has-children')) {
      domForEach(currLi, 'li', (el) => {
        this.openLi(el, true);
      });
    }
  } // openCurrentElem


  initResizeMonitor() {
    window.addEventListener('resize', () => {
      this.adaptToWidth();
    });
  } // initResizeMonitor

} // PfyNav


domReady(() => {
  domForEach('.pfy-nav-wrapper', (navWrapper) => {
    new PfyNav(navWrapper);
  });
});
