// Page Switcher

"use strict";

(function () {
  let touchstartX = 0;
  let touchendX = 0;
  let touchstartY = 0;
  let touchendY = 0;
  const swipeMinDistanceX = 20;
  const swipeMaxDistanceY = 10;

  document.addEventListener("DOMContentLoaded", function () {
    const prevLinkElem = document.querySelector('.pfy-previous-page-link a');
    const prevLink = prevLinkElem ? prevLinkElem.getAttribute('href') : '';
    const nextLinkElem = document.querySelector('.pfy-next-page-link a');
    const nextLink = nextLinkElem ? nextLinkElem.getAttribute('href') : '';

    // Key handling:
    document.body.addEventListener("keydown", function (e) {
      if (isProtectedTarget()) {
        return;
      }

      const keycode = e.key;

      if (keycode === 'ArrowLeft' && prevLink) {
        e.preventDefault();
        window.location.href = prevLink;
      } else if (keycode === 'ArrowRight' && nextLink) {
        e.preventDefault();
        window.location.href = nextLink;
      }
    });


    const touchSupport = ('ontouchstart' in window || window.navigator.msPointerEnabled);
    if (touchSupport && typeof pfyPageSwipeEnabled !== 'undefined' && pfyPageSwipeEnabled) {
      // Swipe handling:
      document.addEventListener('touchstart', e => {
        touchstartX = e.changedTouches[0].screenX;
        touchstartY = e.changedTouches[0].screenY;
      });

      document.addEventListener('touchend', e => {
        touchendX = e.changedTouches[0].screenX;
        touchendY = e.changedTouches[0].screenY;

        // inhibit page-switch if swipe had vertical movement:
        if (Math.abs(touchstartY - touchendY) > swipeMaxDistanceY) {
          return;
        }

        // inhibit page-switch if swipe was inside scrollable area:
        if (isInsideScrollableArea(e.target)) {
          return;
        }

        if (touchendX < touchstartX - swipeMinDistanceX && nextLink) {
          showBusySpinner();
          window.location.href = nextLink;
        } else if (touchendX > touchstartX + swipeMinDistanceX && prevLink) {
          showBusySpinner();
          window.location.href = prevLink;
        }
      });
    }

  }); // document ready


  function isProtectedTarget() {
    const activeElement = document.activeElement;
    return !!(activeElement.closest('form') ||
      activeElement.closest('input') ||
      document.querySelector('.inhibitPageSwitch') ||
      document.querySelector('.pfy-presentation-active') ||
      document.querySelector('.baguetteBox-open') ||
      (document.querySelector('.ug-lightbox') &&
        window.getComputedStyle(document.querySelector('.ug-lightbox')).display !== 'none') ||
      activeElement.closest('.pfy-nav') ||
      activeElement.closest('.pfy-panels-widget'));
  }


  function isInsideScrollableArea(target) {
    let el = target;

    while (el && el !== document.body) {
      const style = window.getComputedStyle(el);
      const overflowX = style.getPropertyValue('overflow-x');

      if (overflowX === 'auto' || overflowX === 'scroll') {
        return true;
      }

      el = el.parentElement;
    }
    return false;
  }

})();
