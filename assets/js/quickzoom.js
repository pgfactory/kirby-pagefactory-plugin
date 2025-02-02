"use strict";


class Quickzoom {
  margin = 1;
  overlayColor = '#444';

  constructor(targetSelector, options) {
    // setup global trigger to open zoomed images:
    document.body.addEventListener("click", (ev) => {
      const el = ev.target;
      if (el.closest(targetSelector)) {
        ev.stopPropagation();
        this.show(el);
      }
    });

    // setup key handlers per quickzoom image:
    const parent = this;
    domForEach(targetSelector, (el) => {
      el.addEventListener('keyup', (ev) => {
        const code = ev.code;
        const key = ev.key;
        const keyCode = ev.keyCode;
        if (ev.code === 'Space' || ev.code === 'Esc') {
          const overlay = document.querySelector('.pfy-quickzoom-overlay');
          if (overlay) {
            parent.close();
          } else {
            ev.preventDefault();
            parent.show(ev.target);
          }
        }
      });
    });
  } // constructor


  createOverlay(targetEl) {
    const parent = this;
    if (targetEl.tagName !== 'IMG') {
      targetEl.querySelector('img');
    }

//    const scrollTop = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;

    const src = targetEl.getAttribute('src');
    let imgWidth = targetEl.offsetWidth;
    let imgHeight = targetEl.offsetHeight;
    const aspectRatio = imgHeight / imgWidth;

    // inhibit scrolling of body:
    document.body.style.overflow = 'hidden';

    // define overlay:
    let overlay = document.createElement('div');
    overlay.classList.add('pfy-quickzoom-overlay');
    overlay.style.position        = 'fixed';
    overlay.style.inset           = 0;
    overlay.style.width           = '100dvw';
    overlay.style.height          = '100dvh';
    overlay.style.zIndex          = 10001;

    // define overlay image;
    const img = document.createElement('img');
    img.setAttribute('src', src);
    img.style.position = 'absolute';
    img.style.inset = 0;
    overlay.appendChild(img);
    document.body.appendChild(overlay);
    const overlayWidth = overlay.offsetWidth;
    const overlayHeight = overlay.offsetHeight;

    img.onload = function () {
      const naturalWidth = img.naturalWidth;
      const naturalHeight = img.naturalHeight;
      //mylog(`naturalWidth: ${naturalWidth} naturalHeight: ${naturalHeight}`);
      if (naturalWidth <= imgWidth || naturalHeight <= imgHeight) {
        // source image too small, skip quickzoom:
        parent.close();
        return;
      }

      overlay.style.backgroundColor = parent.overlayColor;
      let zoomedWidth = Math.min(naturalWidth, overlayWidth - 2 * parent.margin);
      let zoomedHeight = Math.min(naturalHeight, overlayHeight - 2 * parent.margin);
      let margTop = parent.margin;
      let margLeft = parent.margin;

      if (zoomedHeight/zoomedWidth < aspectRatio) {
        zoomedWidth = zoomedHeight / aspectRatio;
      } else {
        zoomedHeight = zoomedWidth * aspectRatio;
      }
      margLeft = (overlayWidth - zoomedWidth) / 2;
      margTop = (overlayHeight - zoomedHeight) / 2;
      img.style.width = zoomedWidth + 'px';
      img.style.height = zoomedHeight + 'px';
      img.style.top = margTop + 'px';
      img.style.left = margLeft + 'px';
      img.style.cursor = 'zoom-out';
      img.style.zIndex = '9999';

      // setup closing trigger:
      overlay.addEventListener('click', parent.close.bind(this));
      parent.overlay = overlay;
    }
  } // createOverlay


  show(targetEl) {
    this.createOverlay(targetEl);
  } // show

  close() {
    document.body.style.overflow = null;
    const overlay = document.querySelector('.pfy-quickzoom-overlay');
    overlay.remove();
  } // close
}

if (typeof quickzoom === 'undefined') {
  const quickzoom = new Quickzoom('.pfy-quickzoom');
}
