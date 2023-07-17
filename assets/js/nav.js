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
  nav: null,
  navWrapper: null,
  navElements: null,
  isTopNav: null,
  transitionTime: 200,
  arrowSvg: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
    '<path d="M15 12L9 6V18L15 12Z" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>' +
    '</svg>',

  init: function (nav) {
    this.nav = nav;
    this.navWrapper = nav.closest('.pfy-nav-wrapper');
    this.isTopNav = this.navWrapper.classList.contains('pfy-nav-top-horizontal');
    if (this.navWrapper.classList.contains('pfy-nav-animated')) {
      this.navWrapper.classList.remove('pfy-nav-animated');
      this.navWrapper.classList.add('pfy-nav-animation-active');
    }
    this.initNavHtml(nav);
    this.navElements = nav.querySelectorAll('a');

    PfyNav.setupMouseHandlers(nav);
    PfyNav.setupKeyHandlers(nav);
    PfyNav.initMobileMode();
  }, // init

  initMobileMode: function () {
    const body = document.body;
    const mobileMenuButton = document.getElementById('pfy-nav-menu-icon');
    mobileMenuButton.addEventListener('click', function (e) {
      e.stopPropagation();
      if (body.classList.contains('pfy-nav-mobile-open')) {
        body.classList.remove('pfy-nav-mobile-open');
      } else {
        body.classList.add('pfy-nav-mobile-open');
      }
      const mainNav = document.querySelector('.pfy-primary-nav');
      if (mainNav) {
        if (body.classList.contains('pfy-small-screen')) {
          if (mainNav.classList.contains('pfy-nav-top-horizontal')) {
            mainNav.classList.remove('pfy-nav-top-horizontal');
            mainNav.dataset.layout = 'pfy-nav-top-horizontal';
          }
        } else {
          if (mainNav.dataset.layout === 'pfy-nav-top-horizontal') {
            mainNav.classList.add('pfy-nav-top-horizontal');
          }
        }
      }
    });
  },

  initNavHtml: function (nav) {
    const liL1Elems = nav.querySelectorAll(':scope > ol > li');
    this._initNavHtml(liL1Elems, 1);
  }, // initNavHtml


  _initNavHtml: function (liElems, depth) {
    if (liElems) {
      liElems.forEach(function (liElem) {
        liElem.classList.add('pfy-lvl-' + depth);
        const subOlElem = liElem.querySelector(':scope > ol');
        if (subOlElem) {
          liElem.classList.add('pfy-has-children');

          // inject arrow into <a>:
          const aElems = liElem.querySelectorAll(':scope > a');
          if (aElems) {
            const arrowUrl = hostUrl + 'media/plugins/pgfactory/pagefactory/icons/nav-arrow.svg';
            aElems.forEach(function (aElem) {
              const text = aElem.textContent;
              const href = aElem.getAttribute('href');
              aElem.outerHTML = `<a href="${href}" aria-expanded="false"><span class='pfy-nav-label'>${text}</span><span class='pfy-nav-arrow' aria-hidden='true'>${PfyNav.arrowSvg}</span></a>`;
            });
          }

          // wrap sub <ol> in a <div>:
          if (depth === 1) {
            subOlElem.outerHTML = '<div class="pfy-nav-sub-wrapper" style="opacity: 0;"><ol>' +
              subOlElem.innerHTML + '</ol>';

            PfyNav.presetSubElemHeight(liElem);
          } else {
            subOlElem.outerHTML = '<div class="pfy-nav-sub-wrapper" ><ol>' + subOlElem.innerHTML + '</ol>';
          }

          // process all contained <li> recursively:
          const subLiElems = liElem.querySelectorAll(':scope > div > ol > li');
          if (subLiElems.length) {
            PfyNav._initNavHtml(subLiElems, depth + 1);
          }
        }
      });
    }
  }, // _initNavHtml


  setupMouseHandlers: function (nav) {
    const pfyNavSubWrappers = nav.querySelectorAll('.pfy-lvl-1.pfy-has-children');
    if (pfyNavSubWrappers) {
      pfyNavSubWrappers.forEach(function (pfyNavSubWrapper) {
        pfyNavSubWrapper.addEventListener('mouseenter', PfyNav.openBranch);
        pfyNavSubWrapper.addEventListener('mouseleave', function (ev) {
          const elem = ev.currentTarget;
          setTimeout(function () {
            PfyNav.closeBranch(elem);
          }, 400);
        });
      })
    }
  }, // setupMouseHandlers


  setupKeyHandlers: function (nav) {
    const liElems = nav.querySelectorAll('li');
    const navWrapper = nav.closest('.pfy-nav-wrapper');

    if (liElems) {
      liElems.forEach(function (liElem) {
        const aElem = liElem.querySelector('a');
        aElem.addEventListener('keydown', function (ev) {
          ev.stopPropagation();
          const aElem = ev.currentTarget;
          const liElem = aElem.closest('li');
          const key = ev.key;
          if (key === 'ArrowDown') {
            PfyNav.focusOnNext(liElem);

          } else if (key === 'ArrowUp') {
            PfyNav.focusOnPrevous(liElem);

          } else if (key === 'ArrowRight') {
            PfyNav.focusOnNextSibling(liElem);

          } else if (key === 'ArrowLeft') {
            PfyNav.focusOnPrevSibling(liElem);

          } else if (key === ' ') {
            if (liElem.classList.contains('pfy-lvl-1')) {
              PfyNav.toggleBranch(liElem, true);
            }
          }
        });

        const arrow = liElem.querySelector('.pfy-nav-arrow');
        if (arrow) {
          arrow.addEventListener('click', PfyNav.freezeBranchState);
        }
      });
    }
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
    if (liElem.classList.contains('pfy-lvl-1') && liElem.classList.contains('pfy-has-children')) {
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
      if (nextLi.classList.contains('pfy-lvl-1')) {
        PfyNav.closeAll(nextLi);
      }
    }
  }, // focusOnNext


  focusOnPrevous: function (liElem) {
    if (liElem.classList.contains('pfy-lvl-1')) {
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


  setFocusOn(currLi, offset) {
    offset = (typeof offset !== 'undefined') ? offset : 0;
    let nextA = currLi;
    if (currLi.tagName !== 'A') {
      nextA = PfyNav.getAElem(currLi, offset);
    }
    setTimeout(function () {
      nextA.focus();
    }, 50);
    return nextA.closest('li');
  }, // setFocusOn


  getAElem: function(currLi, offset) {
    const aElem = currLi.querySelector('a');
    let currI = false;
    let i = 0;
    PfyNav.navElements.forEach(function (a) {
      if (currI === false && a !== aElem) {
        i++;
      } else {
        currI = i;
      }
    });
    let nextI = currI + offset;
    const max = PfyNav.navElements.length - 1;
    nextI = Math.max(0, Math.min(max, nextI));
    return PfyNav.navElements[nextI];
  }, // getAElem


  freezeBranchState: function (ev) {
    ev.stopPropagation();
    ev.preventDefault();
    let liElem = ev.currentTarget;
    if (liElem.classList.contains('pfy-nav-arrow')) {
      liElem = liElem.closest('li');
    }
    if (liElem.closest('.pfy-nav-top-horizontal')) {
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


  toggleBranch: function (eventOrElem, closeOthers) {
    let liElem = null;
    if (typeof eventOrElem.currentTarget === 'undefined') {
      liElem = eventOrElem;
    } else {
      liElem = eventOrElem.currentTarget;
    }
    if (liElem.classList.contains('pfy-nav-arrow')) {
      liElem = liElem.closest('li');
    }
    const isOpen = liElem.classList.contains('pfy-open');
    if (isOpen) {
      PfyNav.closeBranch(liElem);
    } else {
      if (closeOthers) {
        PfyNav.closeAll(liElem);
      }
      PfyNav.openBranch(liElem);
    }
  }, // toggleBranch


  openBranch: function (eventOrElem, override) {
    let liElem = null;
    if (typeof eventOrElem.currentTarget === 'undefined') {
      liElem = eventOrElem;
    } else {
      liElem = eventOrElem.currentTarget;
    }
    override = (typeof override !== 'undefined');
    if (!override && liElem.classList.contains('pfy-branch-frozen')) {
      return;
    }

    PfyNav.presetSubElemHeight(liElem);

    if (liElem.classList.contains('pfy-lvl-1')) {
      const divElem = liElem.querySelector('div');
      if (divElem && typeof divElem.style !== 'undefined') {
        divElem.style.display = "block";
      }
    }

    liElem.classList.add('pfy-open');
    const aElem = liElem.querySelector(':scope > a');
    aElem.setAttribute('aria-expanded', true);
  }, // openBranch


  closeBranch: function (eventOrElem, override) {
    let liElem = null;
    if (typeof eventOrElem.currentTarget === 'undefined') {
      liElem = eventOrElem;
    } else {
      liElem = eventOrElem.currentTarget;
    }
    override = (typeof override !== 'undefined');
    if (!override && (liElem.classList.contains('pfy-branch-frozen'))) {
      return;
    } else {
      liElem.classList.remove('pfy-open');
      const aElem = liElem.querySelector(':scope > a');
      aElem.setAttribute('aria-expanded', false);
    }

    const divElem = liElem.querySelector('div');
    if (divElem) {
      setTimeout(function () {
        divElem.style.display = 'none';
      }, PfyNav.transitionTime);
    }
  }, // closeBranch


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
          liElem.classList.remove('pfy-branch-frozen')
        }
      })
    }
  }, // closeAll


  presetSubElemHeights: function(nav)
  {
    const pfyNavSubWrappers = nav.querySelectorAll('.pfy-lvl-1.pfy-has-children');
    if (pfyNavSubWrappers) {
      pfyNavSubWrappers.forEach(function (liElem) {
        PfyNav.presetSubElemHeight(liElem);
      });
    }
  }, // presetSubElemHeights


  presetSubElemHeight: function(liElem)
  {
    const subDivElem = liElem.querySelector(':scope > div');
    if (!subDivElem) {
      return;
    }
    subDivElem.style.opacity = 0;
    subDivElem.style.display = 'block';
    const olElem = liElem.querySelector(':scope > div > ol');
    if (!olElem) {
      return;
    }
    const h = olElem.offsetHeight;
    olElem.setAttribute('style', `margin-top: -${h}px`);
    subDivElem.style.display = 'none';
    subDivElem.style.removeProperty('opacity');
  }, // presetSubElemHeight

} // PfyNav



const navs = document.querySelectorAll('.pfy-nav');
if (navs) {
  navs.forEach(function (nav) {
    PfyNav.init(nav);  })
}
