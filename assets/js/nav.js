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
  timer: [],
  nav: null,
  navWrapper: null,
  navL1LiElements: null,
  isTopNav: null,
  collapsed: null,
  collapsible: false,
  hoveropen: false,
  arrowClicks: 0,

  transitionTimeMs: 300,
  transitionTimeS: null,

  arrowSvg: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
    '<path d="M15 12L9 6V18L15 12Z" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>' +
    '</svg>',

  init: function (nav) {
    this.nav = nav;
    this.navWrapper = nav.closest('.pfy-nav-wrapper');
    this.navL1LiElements = this.nav.querySelectorAll(':scope > ol > li');
    this.transitionTimeS = (this.transitionTimeMs / 1000) + 's';
    this.isTopNav = this.navWrapper.classList.contains('pfy-nav-horizontal');
    this.collapsed = this.navWrapper.classList.contains('pfy-nav-collapsed')? 1 : false;
    this.collapsible = this.navWrapper.classList.contains('pfy-nav-collapsible');
    if (this.isTopNav) {
      this.collapsible = true;
      this.collapsed = false;
    } else {
      if (this.collapsed) {
        this.collapsible = true;
      } else if (this.collapsible) {
        this.collapsed = 99;
      }
    }
    this.hoveropen = this.navWrapper.classList.contains('pfy-nav-hoveropen');
    this.fixNavLayout(nav);

    this.initNavHtml(nav);
    if (this.collapsible) {
      this.presetSubElemHeights(nav);
    }
    if (this.collapsed) {
      this.preOpenElems(nav, this.collapsed);
      if (this.navWrapper.classList.contains('pfy-nav-open-current')) {
        this.openCurrentLi();
      }
    }

    PfyNav.setupMouseHandlers(nav);
    PfyNav.setupKeyHandlers(nav);
    PfyNav.initMobileMode();
    PfyNav.pseudoElementHandler(nav);
  }, // init


  initMobileMode: function () {
    const body = document.body;
    const mobileMenuButton = document.getElementById('pfy-nav-menu-icon');
    if (!mobileMenuButton.dataset.initialized) {
      mobileMenuButton.dataset.initialized = true;
      mobileMenuButton.addEventListener('click', function (e) {
        e.stopPropagation();
        if (body.classList.contains('pfy-nav-mobile-open')) {
          body.classList.remove('pfy-nav-mobile-open');
          PfyNav.setMobileMode(false);
        } else {
          body.classList.add('pfy-nav-mobile-open');
          PfyNav.setMobileMode(true);
        }
      });
    }
  }, // initMobileMode


  setMobileMode: function(activate){
    const mainNav = document.querySelector('.pfy-primary-nav');
    if (mainNav) {
      if (activate) {    // small screen:
        const classList = mainNav.getAttribute('class');
        mainNav.dataset.classList = classList;
        mainNav.setAttribute('class', 'pfy-nav-wrapper pfy-primary-nav pfy-nav-vertical pfy-nav-indented pfy-nav-collapsible pfy-nav-animated pfy-encapsulated');
        PfyNav.preOpenElems(mainNav, 99);

      } else {                                              // large screen:
        setTimeout(function () {
          mainNav.setAttribute('class', mainNav.dataset.classList);
          mainNav.dataset.classList = null;
        }, 500);
      }
    }
  }, // setMobileMode


  pseudoElementHandler: function(nav) {
    const pseudoElements = nav.querySelectorAll('.pfy-pseudo-elem > a');
    if (pseudoElements) {
      pseudoElements.forEach(function (pseudoElement) {
        pseudoElement.addEventListener('click', function (ev) {
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
  }, // pseudoElementHandler


  initNavHtml: function (nav) {
    this._initNavHtml(this.navL1LiElements, 1);
  }, // initNavHtml


  _initNavHtml: function (liElems, depth) {
    if (liElems) {
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
          liElem.classList.add('pfy-has-children');
          let isPseudoELem = liElem.classList.contains('pfy-pseudo-elem');

          // inject arrow into <a>:
          const aElem = liElem.querySelector('a');
          const text = aElem.textContent;
          const href = aElem.getAttribute('href');
          const currPage = aElem.getAttribute('aria-current')? ' aria-current="page"' : '';
          if (PfyNav.collapsible && !(PfyNav.isTopNav && (depth > 1))) {
            if (currPage) {
              aElem.outerHTML = `<a href="${href}" aria-expanded="false" aria-current="page"><span class='pfy-nav-label'>${text}</span><span class='pfy-nav-arrow' aria-hidden='true'>${PfyNav.arrowSvg}</span></a>`;
            } else {
              aElem.outerHTML = `<a href="${href}" aria-expanded="false"><span class='pfy-nav-label'>${text}</span><span class='pfy-nav-arrow' aria-hidden='true'>${PfyNav.arrowSvg}</span></a>`;
            }
          } else {
            if (currPage) {
              aElem.outerHTML = `<a href="${href}" aria-expanded="false" aria-current="page"><span class='pfy-nav-label'>${text}</span></a>`;
            } else {
              aElem.outerHTML = `<a href="${href}" aria-expanded="false"><span class='pfy-nav-label'>${text}</span></a>`;
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

          // if liElem has children but is not yet a pseudo-elem, convert it into one now:
          if (isCollapsible && !liElem.classList.contains('pfy-pseudo-elem')) {
            const aHref = aElem.getAttribute('href');
            const aText = aElem.innerHTML;
            olInnerHtml = '<li class="pfy-lvl-' + (depth+1) + `"><a href="${aHref}">${aText}</a></li>` + olInnerHtml;
            liElem.classList.add('pfy-pseudo-elem');
          }

          // wrap sub <ol> in a <div>:
          if (PfyNav.collapsible && !(PfyNav.isTopNav && (depth > 1))) {
            subOlElem.outerHTML = '<div class="pfy-nav-sub-wrapper" style="display:none;"><ol>' + olInnerHtml + '</ol>';
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


  fixNavLayout: function (nav) {
    const fsVar = getComputedStyle(this.navWrapper).getPropertyValue('--pfy-nav-txt-size');
    if (!fsVar) {
      const fs = getComputedStyle(this.navWrapper).getPropertyValue('font-size');
      this.navWrapper.style.setProperty('--pfy-nav-txt-size', fs);
    }
    nav.style.display = 'block';
    const placeHolder = this.navWrapper.querySelector('.pfy-top-nav-placeholder');
    placeHolder.style.display = 'none';
  }, // fixNavLayout


  setupMouseHandlers: function (nav) {
    const isTopNav = this.navWrapper.classList.contains('pfy-nav-horizontal');
    const liElems = nav.querySelectorAll('.pfy-has-children');
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
    const l1LiElems = nav.querySelectorAll('.pfy-lvl-1.pfy-has-children');
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


  setupKeyHandlers: function (nav) {
    const liElems = nav.querySelectorAll('li');
    const navWrapper = nav.closest('.pfy-nav-wrapper');

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
        PfyNav.closeAll(nextLi);
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


  setFocusOnParent: function (liElem)
  {
    if (!liElem.classList.contains('pfy-lvl-1')) {
      const parentLi = liElem.parentElement.closest('li');
      PfyNav.setFocusOn(parentLi);
    } else {
      PfyNav.setFocusOn(liElem);
    }
  }, // setFocusOnParent


  getAElem: function(liElem, offset) {
    const aElem = liElem.querySelector('a');
    let activeAElems = this.getCurrentlyActiveAElments();
    const currI = Array.from(activeAElems).indexOf(aElem);
    let nextI = currI + offset;
    nextI = Math.max(0, Math.min(activeAElems.length - 1, nextI));
    return activeAElems[nextI];
  }, // getAElem


  getCurrentlyActiveAElments: function () {
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
    this.navL1LiElements.forEach(liElem => traverse(liElem));
    return activeAElems;
  }, // getCurrentlyActiveAElments


  freezeBranchState: function (ev) {
    ev.stopPropagation();
    ev.preventDefault();
    let liElem = ev.currentTarget;
    if (liElem.classList.contains('pfy-nav-arrow')) {
      liElem = liElem.closest('li');
    }
    if (liElem.closest('.pfy-nav-horizontal')) {
      PfyNav.closeAll(liElem);
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
          PfyNav.closeAll(liElem);
        }
        PfyNav.openBranch(liElem,true, recursive);
      }
    }
  }, // _toggleBranch


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
          PfyNav.openLi(liElem);
        });
      }
    }
  }, // openBranch


  openLi: function(liElem) {
    const divElem = liElem.querySelector('div');
    if (divElem && typeof divElem.style !== 'undefined') {
      divElem.style.display = null;
    }
    const aElem = liElem.querySelector('a');
    aElem.setAttribute('aria-expanded', true);
    setTimeout(function () {
      liElem.classList.add('pfy-open');
    }, 50);
  }, // openLi


  closeBranch: function (eventOrElem, override = false, recursive = false) {
    let liElem = null;
    if (typeof eventOrElem.currentTarget === 'undefined') {
      liElem = eventOrElem;
    } else {
      liElem = eventOrElem.currentTarget;
    }
    this.presetSubElemHeight(liElem, true);

    // const isTopNav = this.navWrapper.classList.contains('pfy-nav-horizontal');
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


  closeLi: function (liElem) {
    liElem.classList.remove('pfy-open');
    const aElem = liElem.querySelector('a');
    aElem.setAttribute('aria-expanded', false);
    const divElem = liElem.querySelector('div');
    if (divElem) {
      setTimeout(function () {
        divElem.style.display = 'none';
      }, PfyNav.transitionTimeMs);
    }
  }, // closeLi


  closeAll: function (except) {
    let nav = PfyNav.nav;
    except = (typeof except !== 'undefined')? except: false;
    if (except) {
      nav = except.closest('nav');
    }
    const liElems = nav.querySelectorAll('.pfy-lvl-1.pfy-has-children');
    if (liElems) {
      liElems.forEach(function (liElem) {
        if (liElem !== except) {
          PfyNav.closeBranch(liElem, true);
          const isTopNav = liElem.closest('.pfy-nav-wrapper').classList.contains('pfy-nav-horizontal');
          if (isTopNav) {
            liElem.classList.remove('pfy-branch-frozen');
          }
        }
      })
    }
  }, // closeAll


  preOpenElems: function(nav, collapsed)
  {
    const pfyNavSubWrappers = nav.querySelectorAll('.pfy-has-children');
    if (pfyNavSubWrappers) {
      collapsed = parseInt(collapsed);
      pfyNavSubWrappers.forEach(function (liElem) {
        const cls = liElem.getAttribute('class');
        const depth = parseInt(cls.replace(/\D/g, ''));
        if (collapsed > depth) {
          PfyNav.openBranch(liElem);
          if (PfyNav.isTopNav) {
            liElem.classList.add('pfy-branch-frozen');
          }
        }
      });
    }
  }, // preOpenElems


  openCurrentLi: function ()
  {
    let liElem = this.nav.querySelector('.pfy-curr');
    this.openLi(liElem);
    liElem = liElem.parentElement.closest('li');
    while (liElem) {
      this.openLi(liElem);
      liElem = liElem.parentElement.closest('li');
    }
  }, // openCurrentLi


  presetSubElemHeights: function(nav)
  {
    const isTopNav = this.navWrapper.classList.contains('pfy-nav-horizontal');
    let pfyNavSubWrappers;
    if (isTopNav) {
      pfyNavSubWrappers = nav.querySelectorAll('.pfy-lvl-1.pfy-has-children');
    } else {
      pfyNavSubWrappers = nav.querySelectorAll('.pfy-has-children');
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


  presetSubElemHeight: function(liElem, leavOpen = false)
  {
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

} // PfyNav



const navs = document.querySelectorAll('.pfy-nav');
if (navs) {
  navs.forEach(function (nav) {
    PfyNav.init(nav);  })
}
