"use strict";

class Quickzoom {
  margin = 1;
  overlayColor = '#404040f2';

  constructor() {
    this.removeQuickzoomForNaturalSizeImages();
    // setup global trigger to open zoomed images:
    document.body.addEventListener("click", (ev) => {
      const wrapperEl = ev.target.closest('.pfy-quickzoom');
      if (wrapperEl) {
        ev.stopPropagation();
        domForOne(wrapperEl, '.pfy-img', imgEl => {
          this.show(imgEl);
        });
      }
    });
  } // constructor


  // for all images that can't be zoomed, remove class pfy-quickzoom:
  removeQuickzoomForNaturalSizeImages() {
    domForAll('.pfy-quickzoom', wrapperEl => {
      domForOne(wrapperEl, '.pfy-img', imgEl => {
        imgEl.onload = () => {
          if (imgEl.naturalWidth === imgEl.offsetWidth) {
            wrapperEl.classList.remove('pfy-quickzoom');
          }
        };
      });
    });
  } // removeQuickzoomForNaturalSizeImages


  show(targetEl) {
    if (targetEl.tagName !== 'IMG') {
      targetEl = targetEl.querySelector('img');
      if (!targetEl) return;
    }

    const src = targetEl.getAttribute('data-src') || targetEl.getAttribute('src');
    const imgWidth = targetEl.offsetWidth;
    const imgHeight = targetEl.offsetHeight;
    const aspectRatio = imgHeight / imgWidth;

    // define overlay:
    const overlay = document.createElement('div');
    overlay.classList.add('pfy-quickzoom-overlay');
    overlay.style.position = 'fixed';
    overlay.style.inset = 0;
    overlay.style.width = '100dvw';
    overlay.style.height = '100dvh';
    overlay.style.zIndex = 10001;

    // define overlay image:
    const img = document.createElement('img');
    img.setAttribute('src', src);
    img.style.position = 'absolute';
    img.style.inset = 0;
    overlay.appendChild(img);
    document.body.appendChild(overlay);
    const overlayWidth = overlay.offsetWidth;
    const overlayHeight = overlay.offsetHeight;

    img.onload = () => {
      const naturalWidth = img.naturalWidth;
      const naturalHeight = img.naturalHeight;
      if (naturalWidth <= imgWidth && naturalHeight <= imgHeight) {
        // source image too small, skip quickzoom:
        this.hide();
        return;
      }

      overlay.style.backgroundColor = this.overlayColor;
      let zoomedWidth = Math.min(naturalWidth, overlayWidth - 2 * this.margin);
      let zoomedHeight = Math.min(naturalHeight, overlayHeight - 2 * this.margin);

      if (zoomedHeight / zoomedWidth < aspectRatio) {
        zoomedWidth = zoomedHeight / aspectRatio;
      } else {
        zoomedHeight = zoomedWidth * aspectRatio;
      }
      const margLeft = (overlayWidth - zoomedWidth) / 2;
      const margTop = (overlayHeight - zoomedHeight) / 2;
      img.style.width = zoomedWidth + 'px';
      img.style.height = zoomedHeight + 'px';
      img.style.top = margTop + 'px';
      img.style.left = margLeft + 'px';
      img.style.zIndex = '9999';

      // inhibit scrolling of body:
      document.body.dataset.overflow = document.body.style.overflow ?? '';
      document.body.style.overflow = 'hidden';

      // setup closing triggers:
      overlay.addEventListener('click', ev => {
        this.hide(ev);
      });
      document.addEventListener('keydown', ev => {
        if (ev.key === 'Escape') {
          this.hide(ev);
        }
      }, { once: true });
    };
  } // show


  hide(ev) {
    if (ev) {
      ev.stopPropagation();
    }
    domForOne('.pfy-quickzoom-overlay', el => el.remove());
    document.body.style.overflow = document.body.dataset.overflow;
    document.body.removeAttribute('data-overflow');
  } // hide

} // Quickzoom

const quickzoom = new Quickzoom();
