// Page Switcher

"use strict";

// let touchstartX = 0
// let touchendX = 0
// let touchstartY = 0
// let touchendY = 0
// const swipeMinDistanceX = 10;
// const swipeMaxDistanceY = 5;

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
    if (keycode === 'ArrowLeft' || keycode === 'ArrowUp') { // left or pgup
      if (prevLink) {
        console.log('prevLink: ' + prevLink);
        e.preventDefault();
        window.location.href = prevLink;
        return false;
      }
    }
    if (keycode === 'ArrowRight' || keycode === 'ArrowDown') { // right or pgdown
      if (nextLink) {
        console.log('nextLink: ' + nextLink);
        e.preventDefault();
        window.location.href = nextLink;
        return false;
      }
    }
    return document.defaultAction;
  });

  /*
  if (false) {
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
        // window.location.href = nextLink;
        document.body.classList.add('right');
        document.body.classList.remove('left');

      } else if (isHorizontalSwipe && (touchendX > touchstartX + swipeMinDistanceX)) { // swiped right
        // window.location.href = prevLink;
        document.body.classList.add('left');
        document.body.classList.remove('right');
      }
    });
  }
  */
}); // document ready


function isProtectedTarget() {
  // Exceptions, where arrow keys should NOT switch page:
  const activeElement = document.activeElement;
  return !!(activeElement.closest('form') || // Focus within form field
    activeElement.closest('input') || // Focus within input field
    document.querySelector('.inhibitPageSwitch') || // class .inhibitPageSwitch found
    document.querySelector('.pfy-presentation-support') || // class .pfy-presentation-support found
    document.querySelector('.baguetteBox-open') || // galery img open
    (document.querySelector('.ug-lightbox') &&
      window.getComputedStyle(document.querySelector('.ug-lightbox')).display !== 'none') || // special case: ug-album in full screen mode
    activeElement.closest('.pfy-nav') ||
    activeElement.closest('.pfy-panels-widget'));
} // isProtectedTarget

