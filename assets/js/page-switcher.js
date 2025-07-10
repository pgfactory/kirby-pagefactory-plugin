// Page Switcher

"use strict";

 let touchstartX = 0
 let touchendX = 0
 let touchstartY = 0
 let touchendY = 0
 const swipeMinDistanceX = 10;
 const swipeMaxDistanceY = 5;

document.addEventListener("DOMContentLoaded", function () {
  const prevLinkElem = document.querySelector('.pfy-previous-page-link a');
  let prevLink = '';
  if (prevLinkElem) {
    prevLink = prevLinkElem.getAttribute('href');
  }
  const nextLinkElem = document.querySelector('.pfy-next-page-link a');
  let nextLink = '';
  if (nextLinkElem) {
    nextLink = nextLinkElem.getAttribute('href');
  }

  // Key handling:
  document.body.addEventListener("keydown", function (e) {
    if (isProtectedTarget()) {
      return;
    }

    const keycode = e.key;

    // Standard arrow key handling:
    if (keycode === 'ArrowLeft') { // left
      if (prevLink) {
        console.log('prevLink: ' + prevLink);
        e.preventDefault();
        window.location.href = prevLink;
        return false;
      }
    }
    if (keycode === 'ArrowRight') { // right
      if (nextLink) {
        console.log('nextLink: ' + nextLink);
        e.preventDefault();
        window.location.href = nextLink;
        return false;
      }
    }
    return document.defaultAction;
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
      const dY = Math.abs(touchstartX - e.changedTouches[0].screenY);
      const isHorizontalSwipe = true;
      if (isHorizontalSwipe && (touchendX < touchstartX - swipeMinDistanceX)) { // swiped left
        showBusySpinner();
        window.location.href = nextLink;

      } else if (isHorizontalSwipe && (touchendX > touchstartX + swipeMinDistanceX)) { // swiped right
        showBusySpinner();
        window.location.href = prevLink;
      }
    });
  }

}); // document ready


function isProtectedTarget() {
  // Exceptions, where arrow keys should NOT switch page:
  const activeElement = document.activeElement;
  return !!(activeElement.closest('form') || // Focus within form field
    activeElement.closest('input') || // Focus within input field
    document.querySelector('.inhibitPageSwitch') || // class .inhibitPageSwitch found
    document.querySelector('.pfy-presentation-active') || // class .pfy-presentation-active found
    document.querySelector('.baguetteBox-open') || // gallery img open
    (document.querySelector('.ug-lightbox') &&
      window.getComputedStyle(document.querySelector('.ug-lightbox')).display !== 'none') || // special case: ug-album in full screen mode
    activeElement.closest('.pfy-nav') ||
    activeElement.closest('.pfy-panels-widget'));
} // isProtectedTarget

