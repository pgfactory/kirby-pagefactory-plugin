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

const PfyNav = {
  isSmallScreen: (window.innerWidth < screenSizeBreakpoint),
  prevScreenMode: null,
  timer: [],
  isTopNav: null,
  collapsed: null,
  collapsible: false,
  hoveropen: false,
  arrowClicks: 0,
  initialized: false,

  transitionTimeMs: 300,
  transitionTimeS: null,

  arrowSvg: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
    '<path d="M15 12L9 6V18L15 12Z" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>' +
    '</svg>',



  init: function (nav) {
    this.initialized = false;
    const navWrapper = nav.closest('.pfy-nav-wrapper');
    this.transitionTimeS = (this.transitionTimeMs / 1000) + 's';
    this.isTopNav = navWrapper.classList.contains('pfy-nav-horizontal');
    this.collapsed = navWrapper.classList.contains('pfy-nav-collapsed')? 1 : false;
    this.hoveropen = navWrapper.classList.contains('pfy-nav-hoveropen');

    this.initNavHtml(navWrapper);

    this.adaptToWidth(navWrapper); // invokes this.setupMouseHandlers(navWrapper) if it's primary nav
    if (!this.initialized) {
      this.setupMouseHandlers(navWrapper);
    }
    this.setupKeyHandlers(navWrapper);
    this.surrogateElementHandler(navWrapper);
    if (this.collapsed && !this.isTopNav) {
      this.openCurrentLi(navWrapper);
    }
    this.initAnimation(navWrapper);
  }, // init


  // === desktop mode =====================================
  initDesktopMode: function () {
    mylog('initDesktopMode()');
    const navWrapper = document.querySelector('.pfy-primary-nav');
    this.setMobileMode(navWrapper, false);
    if (this.isTopNav) {
      this.collapsible = true;
      this.collapsed = false;
    }
    this.presetSubElemHeights(navWrapper);
    this.closeAllExcept(true, navWrapper);
  }, // initDesktopMode



  // === mobile mode =====================================
  initMobileMode: function () {
    mylog('initMobileMode()');
    const navWrapper = document.querySelector('.pfy-primary-nav');
    this.setMobileMode(navWrapper, true);

    this.presetSubElemHeights(navWrapper);

    this.preOpenElems(navWrapper, 99);

    const mobileMenuButton = document.getElementById('pfy-nav-menu-icon');
    if (!mobileMenuButton.dataset.initialized) {
      mobileMenuButton.dataset.initialized = true;
      mobileMenuButton.setAttribute('aria-controls', 'pfy-primary-nav');

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
    }
  }, // initMobileMode


  setMobileMode: function(navWrapper, activate){
    if (navWrapper) {
      if (activate) {    // small screen:
        navWrapper.dataset.classList = navWrapper.getAttribute('class');
        let cls = 'pfy-nav-wrapper pfy-primary-nav pfy-nav-indented pfy-nav-collapsible pfy-nav-animated pfy-encapsulated';
        if (navWrapper.classList.contains('pfy-mobile-nav-colored')) {
          cls += ' pfy-mobile-nav-colored';
        }
        navWrapper.setAttribute('class', cls);
        PfyNav.preOpenElems(navWrapper, 99);

      } else {                                              // large screen:
        if (typeof navWrapper.dataset.classList === 'string') {
          navWrapper.setAttribute('class', navWrapper.dataset.classList);
          navWrapper.dataset.classList = null;
        }
      }
    }
  }, // setMobileMode


  surrogateElementHandler: function(navWrapper) {
    const surrogateElements = navWrapper.querySelectorAll(':scope.pfy-nav-collapsible .pfy-has-surrogate-elem > a');
    if (surrogateElements) {
      surrogateElements.forEach(function (surrogateElement) {
        surrogateElement.addEventListener('click', function (ev) {
          const el = ev.target??ev.currentTarget?? false;
          if (!el) {
            return;
          }
          const isTopNav = el.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
          if (!isTopNav) {
            ev.preventDefault();
            ev.stopPropagation();
            PfyNav.toggleBranch(ev);
          }
        });
      });
    }
  }, // surrogateElementHandler


  initNavHtml: function (navWrapper) {
    this._initNavHtml(navWrapper.querySelectorAll('.pfy-nav > ol > li'), 1);
    this.fixNavLayout(navWrapper);
  }, // initNavHtml


  _initNavHtml: function (liElems, depth) {
    if (liElems.length) {
      const isCollapsible = liElems[0].closest('.pfy-nav-wrapper').classList.contains('pfy-nav-collapsible');

      let inx = 0;
      liElems.forEach(function (liElem) {
        // apply level-class:
        liElem.classList.add('pfy-lvl-' + depth);

        // mark current-page (and its parent pages):
        const aElem = liElem.querySelector('a');
        const currPage = aElem.getAttribute('aria-current')? ' aria-current="page"' : '';
        if (currPage) {
          liElem.classList.add('pfy-curr');
          let parentLiElem = liElem.parentElement.closest('li');
          while (parentLiElem) {
            parentLiElem.classList.add('pfy-active');
            parentLiElem = parentLiElem.parentElement.closest('li');
          }
        }

        // handle sub-branches:
        const subOlElem = liElem.querySelector('ol,ul');
        if (subOlElem) {
          let isSurrogateELem = liElem.classList.contains('pfy-has-surrogate-elem');
          liElem.classList.add('pfy-has-children');
          let ariaExpanded = PfyNav.isTopNav? 'false' : 'true';
          if (!PfyNav.isTopNav) {
            liElem.classList.add('pfy-open');
          }

          // inject arrow into <a>:
          const aElem = liElem.querySelector('a');
          const text = aElem.textContent;

          const href = aElem.getAttribute('href');
          const currPage = aElem.getAttribute('aria-current')? ' aria-current="page"' : '';
          if (isCollapsible || (PfyNav.isTopNav && (depth === 1))) {
            if (currPage) {
              aElem.outerHTML = `<a href="${href}" aria-expanded="${ariaExpanded}" aria-current="page"><span class="pfy-nav-label">` +
                `${text}</span><span class='pfy-nav-arrow' aria-hidden='true' aria-controls='pfy-primary-nav'>${PfyNav.arrowSvg}</span></a>`;
            } else {
              aElem.outerHTML = `<a href="${href}" aria-expanded="${ariaExpanded}"><span class='pfy-nav-label'>` +
                `${text}</span><span class='pfy-nav-arrow' aria-hidden='true' aria-controls='pfy-primary-nav'>${PfyNav.arrowSvg}</span></a>`;
            }

          } else {
            if (currPage) {
              aElem.outerHTML = `<a href="${href}" aria-current="page"><span class='pfy-nav-label'>${text}</span></a>`;
            } else {
              aElem.outerHTML = `<a href="${href}"><span class='pfy-nav-label'>${text}</span></a>`;
            }
          }

          if (currPage) {
            liElem.classList.add('pfy-curr');
            let parentLiElem = liElem.parentElement.closest('li');
            while (parentLiElem) {
              parentLiElem.classList.add('pfy-active');
              parentLiElem = parentLiElem.parentElement.closest('li');
            }
          }

          let olInnerHtml = subOlElem.innerHTML;

          // if liElem has children but is not yet a surrogate-elem, convert it into one now:
          if (isCollapsible && !liElem.classList.contains('pfy-has-surrogate-elem')) {
            const aHref = aElem.getAttribute('href');
            const aText = aElem.innerHTML;
            olInnerHtml = '<li class="pfy-lvl-' + (depth+1) + ` pfy-surrogate-elem"><a href="${aHref}">${aText}</a></li>` + olInnerHtml;
            liElem.classList.add('pfy-has-surrogate-elem');
          }

          // wrap sub <ol> in a <div>:
          if (PfyNav.collapsible && !(PfyNav.isTopNav && (depth > 1))) {
            subOlElem.outerHTML = '<div class="pfy-nav-sub-wrapper"><ol>' + olInnerHtml + '</ol>';
          } else {
            subOlElem.outerHTML = '<div class="pfy-nav-sub-wrapper"><ol>' + olInnerHtml + '</ol>';
          }
          liElem.dataset.inx = inx++;

          // process all contained <li> recursively:
          const subLiElems = liElem.querySelectorAll(':scope > div > ol > li');
          if (subLiElems.length) {
            PfyNav._initNavHtml(subLiElems, depth + 1);
          }
        }
      });
    }
  }, // _initNavHtml


  fixNavLayout: function (navWrapper) {
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
  }, // fixNavLayout


  initAnimation: function (navWrapper) {
    // switch classes from pfy-nav-animated to pfy-nav-animate after short delay:
    setTimeout(function () {
      if (navWrapper) {
        navWrapper.classList.remove('pfy-nav-animated');
        navWrapper.classList.add('pfy-nav-animate');
      }
    }, 100);
  }, // initAnimation


  setupMouseHandlers: function (navWrapper) {
    const isTopNav = navWrapper.classList.contains('pfy-nav-horizontal');
    const liElems = navWrapper.querySelectorAll('.pfy-has-children');
    if (liElems) {
      liElems.forEach(function (liElem) {
        const arrow = liElem.querySelector('.pfy-nav-arrow');
        if (arrow) {
          if (isTopNav) {
            arrow.addEventListener('click', PfyNav.freezeBranchState);
          } else {
            arrow.addEventListener('click', PfyNav.toggleBranch);
          }
        }
      });
    }

    if (!isTopNav || !this.hoveropen) {
      return;
    }
    const l1LiElems = navWrapper.querySelectorAll('.pfy-lvl-1.pfy-has-children');
    if (l1LiElems) {
      l1LiElems.forEach(function (l1LiElem) {
        l1LiElem.addEventListener('mouseenter', function (ev) {
          const elem = ev.currentTarget;
          if (PfyNav.timer[elem.dataset.inx]) {
            clearTimeout(PfyNav.timer[elem.dataset.inx]);
          }
          PfyNav.openBranch(elem);
        });
        l1LiElem.addEventListener('mouseleave', function (ev) {
          const elem = ev.currentTarget;
          PfyNav.timer[elem.dataset.inx] = setTimeout(function () {
            PfyNav.closeBranch(elem);
            PfyNav.timer[elem.dataset.inx] = false;
          }, 400);
        });
      })
    }
  }, // setupMouseHandlers


  setupKeyHandlers: function (navWrapper) {
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
          PfyNav.focusOnNext(liElem);

        } else if (key === 'ArrowUp') {
          PfyNav.focusOnPrevous(liElem);

        } else if (key === 'ArrowRight') {
          if (isTopNav) {
            PfyNav.focusOnNextSibling(liElem);
          } else {
            if (liElem.classList.contains('pfy-has-children')) {
              PfyNav.openBranch(liElem);
            }
            PfyNav.setFocusOn(liElem);
          }

        } else if (key === 'ArrowLeft') {
          if (isTopNav) {
            PfyNav.focusOnPrevSibling(liElem);
          } else {
            if (PfyNav.isOpen(liElem)) {
              PfyNav.closeBranch(liElem);
              PfyNav.setFocusOn(liElem);
            } else {
              PfyNav.setFocusOnParent(liElem);
            }
          }

        } else if (key === ' ') {
          if (isTopNav) {
            PfyNav.toggleBranch(liElem, true);
          } else {
            PfyNav.toggleBranch(liElem);
          }
        }
      });
    });
  }, // setupKeyHandlers


  focusOnPrevSibling: function (liElem) {
    if (liElem.classList.contains('pfy-lvl-1') && liElem.classList.contains('pfy-open')) {
      PfyNav.closeBranch(liElem);
    }
    const prevLiElem = liElem.previousElementSibling;
    if (prevLiElem) {
      PfyNav.setFocusOn(prevLiElem);
    } else {
      PfyNav.setFocusOn(liElem);
    }
  }, // focusOnPrevSibling


  focusOnNextSibling: function (liElem) {
    if (liElem.classList.contains('pfy-lvl-1') && liElem.classList.contains('pfy-open')) {
      PfyNav.closeBranch(liElem);
    }
    const nextLiElem = liElem.nextElementSibling;
    if (nextLiElem) {
      PfyNav.setFocusOn(nextLiElem);
    } else {
      PfyNav.setFocusOn(liElem);
    }
  }, // focusOnNextSibling


  focusOnNext: function (liElem) {
    const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
    if (isTopNav && liElem.classList.contains('pfy-lvl-1') && liElem.classList.contains('pfy-has-children')) {
      if (!liElem.classList.contains('pfy-open')) {
        PfyNav.openBranch(liElem);
      }
      const firstSubAElem = liElem.querySelector('div > ol > li > a');
      if (firstSubAElem) {
        PfyNav.setFocusOn(firstSubAElem);
      }

    // not first elem in branch:
    } else {
      const nextLi = PfyNav.setFocusOn(liElem, 1);
      if (isTopNav && nextLi.classList.contains('pfy-lvl-1')) {
        PfyNav.closeAllExcept(nextLi);
      }
    }
  }, // focusOnNext


  focusOnPrevous: function (liElem) {
    const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
    if (isTopNav && liElem.classList.contains('pfy-lvl-1')) {
      if (liElem.classList.contains('pfy-has-children') && liElem.classList.contains('pfy-open')) {
        PfyNav.closeBranch(liElem);
        PfyNav.setFocusOn(liElem, 0);
      } else {
        PfyNav.focusOnPrevSibling(liElem);
      }
    } else {
      PfyNav.setFocusOn(liElem, -1);
    }
  }, // focusOnPrevous


  setFocusOn: function (liElem, offset) {
    offset = (typeof offset !== 'undefined') ? offset : 0;
    let nextA = liElem;
    if (liElem.tagName !== 'A') {
      nextA = PfyNav.getAElem(liElem, offset);
    }
    setTimeout(function () {
      nextA.focus();
    }, 50);
    return nextA.closest('li');
  }, // setFocusOn


  setFocusOnParent: function (liElem){
    if (!liElem.classList.contains('pfy-lvl-1')) {
      const parentLi = liElem.parentElement.closest('li');
      PfyNav.setFocusOn(parentLi);
    } else {
      PfyNav.setFocusOn(liElem);
    }
  }, // setFocusOnParent


  getAElem: function(liElem, offset) {
    const aElem = liElem.querySelector('a');
    let activeAElems = this.getCurrentlyActiveAElments(liElem);
    const currI = Array.from(activeAElems).indexOf(aElem);
    let nextI = currI + offset;
    nextI = Math.max(0, Math.min(activeAElems.length - 1, nextI));
    return activeAElems[nextI];
  }, // getAElem


  getCurrentlyActiveAElments: function (liElem) {
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
  }, // getCurrentlyActiveAElments


  handleSingleAndDoubleClick: function (event, singleClickCallback, doubleClickCallback) {
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
        if (PfyNav.arrowClicks === 1) {
          PfyNav.arrowClicks = 0;
          singleClickCallback(event);
        }
      }, 250);
    }
  }, // handleSingleAndDoubleClick


  freezeBranchState: function (ev) {
    ev.stopPropagation();
    ev.preventDefault();
    let liElem = ev.currentTarget;
    if (liElem.classList.contains('pfy-nav-arrow')) {
      liElem = liElem.closest('li');
    }
    if (liElem.closest('.pfy-nav-horizontal')) {
      PfyNav.closeAllExcept(liElem);
    }
    const isOpen = liElem.classList.contains('pfy-open');
    if (liElem.classList.contains('pfy-branch-frozen')) {
      liElem.classList.remove('pfy-branch-frozen');
      if (isOpen) {
        PfyNav.closeBranch(liElem, true);
      }

    } else {
      liElem.classList.add('pfy-branch-frozen');
      if (!isOpen) {
        PfyNav.openBranch(liElem, true);
      }
    }
  }, // freezeBranchState


  toggleBranch: function (eventOrElem, closeOthers = false) {
    if (typeof eventOrElem.currentTarget === 'undefined') {
      PfyNav._toggleBranch(eventOrElem, closeOthers);

    } else { // event:
      eventOrElem.stopPropagation();
      eventOrElem.preventDefault();
      PfyNav.handleSingleAndDoubleClick(eventOrElem,
          function (eventOrElem) {
            PfyNav._toggleBranch(eventOrElem, closeOthers);
          },
          function (eventOrElem) {
            PfyNav._toggleBranch(eventOrElem, closeOthers, true);
          }
      );
    }
  }, // toggleBranch


  _toggleBranch: function (eventOrElem, closeOthers = false, recursive = false) {
    let liElem = null;
    if (typeof eventOrElem.currentTarget === 'undefined') {
      liElem = eventOrElem;
    } else if (eventOrElem.currentTarget) {
      liElem = eventOrElem.currentTarget
    } else if (eventOrElem.target) {
      liElem = eventOrElem.target
    }
    if (liElem) {
      liElem = liElem.closest('li');
      const isOpen = liElem.classList.contains('pfy-open');
      if (isOpen) {
        PfyNav.closeBranch(liElem, true, recursive);
      } else {
        if (closeOthers) {
          PfyNav.closeAllExcept(liElem);
        }
        PfyNav.openBranch(liElem,true, recursive);
      }
    }
  }, // _toggleBranch


  isOpen: function (liElem) {
    return liElem.classList.contains('pfy-open');
  }, // isOpen


  openBranch: function (eventOrElem, override = false, recursive = false) {
    let liElem = null;
    if (typeof eventOrElem.currentTarget === 'undefined') {
      liElem = eventOrElem;
    } else {
      liElem = eventOrElem.currentTarget;
    }

    const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
    if (isTopNav && !override && liElem.classList.contains('pfy-branch-frozen')) {
      return;
    }

    PfyNav.presetSubElemHeight(liElem);

    PfyNav.openLi(liElem);
    if (recursive) {
      const liElems = liElem.querySelectorAll('li');
      if (liElems) {
        liElems.forEach(function (liElem) {
          PfyNav.openLi(liElem, true);
        });
      }
    }
  }, // openBranch


  openLi: function(liElem, noDelay) {
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
  }, // openLi


  closeBranch: function (eventOrElem, override = false, recursive = false) {
    let liElem = null;
    if (typeof eventOrElem.currentTarget === 'undefined') {
      liElem = eventOrElem;
    } else {
      liElem = eventOrElem.currentTarget;
    }
    this.presetSubElemHeight(liElem, true);

    const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
    if (isTopNav && !override && (liElem.classList.contains('pfy-branch-frozen'))) {
      return;
    }
    PfyNav.closeLi(liElem);
    if (recursive) {
      const liElems = liElem.querySelectorAll('li');
      if (liElems) {
        liElems.forEach(function (liElem) {
          PfyNav.closeLi(liElem);
        });
      }
    }
  }, // closeBranch


  closeLi: function (liElem, noDelay) {
    liElem.classList.remove('pfy-open');
    const aElem = liElem.querySelector('a');
    aElem.setAttribute('aria-expanded', false);
    const divElem = liElem.querySelector('div');
    if (divElem) {
      if (typeof noDelay === 'undefined') {
        setTimeout(function () {
          divElem.style.display = 'none';
        }, PfyNav.transitionTimeMs);
      } else {
        divElem.style.display = 'none';
      }
    }
  }, // closeLi


  closeAll: function (navWrapper) {
    const liElems = navWrapper.querySelectorAll('.pfy-has-children');
    if (liElems) {
      liElems.forEach(function (liElem) {
        PfyNav.closeLi(liElem, true);
      });
    }
  }, // closeAll


  closeAllExcept: function (exceptSubBranch, navWrapper) {
    exceptSubBranch = (typeof exceptSubBranch !== 'undefined')? exceptSubBranch: false;
    if (typeof exceptSubBranch === 'object') {
      navWrapper = exceptSubBranch.closest('.pfy-nav-wrapper');
    }
    if (typeof navWrapper === 'undefined') {
      navWrapper = document.querySelector('.pfy-primary-nav');
    }

    const liElems = navWrapper.querySelectorAll('.pfy-lvl-1.pfy-has-children');
    if (liElems) {
      liElems.forEach(function (liElem) {
        if (liElem !== exceptSubBranch) {
          PfyNav.closeBranch(liElem, true);
          const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
          if (isTopNav) {
            liElem.classList.remove('pfy-branch-frozen');
          }
        }
      })
    }
  }, // closeAllExcept


  preOpenElems: function(navWrapper, collapseToDepth){
    const pfyNavSubWrappers = navWrapper.querySelectorAll('.pfy-has-children');
    if (pfyNavSubWrappers) {
      collapseToDepth = parseInt(collapseToDepth);
      pfyNavSubWrappers.forEach(function (liElem) {
        const cls = liElem.getAttribute('class');
        const depth = parseInt(cls.replace(/\D/g, ''));
        if (collapseToDepth > depth) {
          PfyNav.openBranch(liElem);
          if (PfyNav.isTopNav) {
            liElem.classList.add('pfy-branch-frozen');
          }
        }
      });
    }
  }, // preOpenElems


  openCurrentLi: function (navWrapper) {
    this.closeAll(navWrapper);
    let liElem = navWrapper.querySelector('.pfy-curr');
    if (!liElem) {
      return;
    }
    liElem = liElem.parentElement.closest('li');
    while (liElem) {
      this.openLi(liElem, true);
      liElem = liElem.parentElement.closest('li');
    }
  }, // openCurrentLi


  presetSubElemHeights: function(navWrapper){
     const collapsible = navWrapper.classList.contains('pfy-nav-collapsible');
     if (!collapsible) {
       return;
     }

    const isTopNav = navWrapper.classList.contains('pfy-nav-horizontal');
    let pfyNavSubWrappers;
    if (isTopNav) {
      pfyNavSubWrappers = navWrapper.querySelectorAll('.pfy-lvl-1.pfy-has-children');
    } else {
      pfyNavSubWrappers = navWrapper.querySelectorAll('.pfy-has-children');
    }
    if (pfyNavSubWrappers) {
      pfyNavSubWrappers.forEach(function (liElem) {
        PfyNav.presetSubElemHeight(liElem, true);
      });
      pfyNavSubWrappers.forEach(function (liElem) {
        const subDivElem = liElem.querySelector('div');
        subDivElem.style.display = 'none';
      });
    }
  }, // presetSubElemHeights


  presetSubElemHeight: function(liElem, leavOpen = false){
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
    if (!leavOpen) {
      subDivElem.style.display = 'none';
    }
  }, // presetSubElemHeight


  adaptToWidth: function(navWrapper) {
    this.isSmallScreen = (window.innerWidth < screenSizeBreakpoint);
    if (typeof navWrapper === 'undefined') {
      navWrapper = document.querySelector('.pfy-primary-nav');
    }
    if (navWrapper.classList.contains('pfy-primary-nav')) {
      if (this.prevScreenMode !== this.isSmallScreen) {
          this.prevScreenMode = this.isSmallScreen;
          if (this.isSmallScreen) {
              this.initMobileMode();
          } else {
              this.initDesktopMode();
          }
        this.setupMouseHandlers(navWrapper);
        this.initialized = true;
      }
    }
  }, //adaptToWidth

} // PfyNav



const navs = document.querySelectorAll('.pfy-nav');
if (navs) {
  navs.forEach(function (nav) {
    PfyNav.init(nav);
  });
}


// monitor screen size changes:
window.addEventListener("resize", function() {
  PfyNav.adaptToWidth();
});
