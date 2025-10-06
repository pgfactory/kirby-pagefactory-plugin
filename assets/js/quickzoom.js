"use strict";

console.log('quickzoom loaded');

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
        imgEl.onload = function () {
//console.log(`imgEl.naturalWidth: ${imgEl.naturalWidth},  imgEl.offsetWidth: ${imgEl.offsetWidth}`);
         if (imgEl.naturalWidth === imgEl.offsetWidth) {
           wrapperEl.classList.remove('pfy-quickzoom');
         }
        }
      })
    });
  } // removeQuickzoomForNaturalSizeImages


  show(targetEl) {
    const parent = this;
    if (targetEl.tagName !== 'IMG') {
      targetEl.querySelector('img');
    }

    let src = targetEl.getAttribute('data-src');
    if (!src) {
      src = targetEl.getAttribute('src');
    }
    let imgWidth = targetEl.offsetWidth;
    let imgHeight = targetEl.offsetHeight;
    const aspectRatio = imgHeight / imgWidth;

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
        parent.hide();
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
      img.style.width     = zoomedWidth + 'px';
      img.style.height    = zoomedHeight + 'px';
      img.style.top       = margTop + 'px';
      img.style.left      = margLeft + 'px';
      img.style.zIndex    = '9999';

      // inhibit scrolling of body:
      document.body.dataset.overflow = document.body.style.overflow ?? '';
      document.body.style.overflow = 'hidden';

      // setup closing trigger:
      overlay.addEventListener('click', ev => {
        parent.hide(ev);
      });

      // parent.setupZoomHandler();
      parent.overlay = overlay;
    }
  } // createOverlay



  hide(ev) {
    if (typeof ev !== 'undefined') {
      ev.stopPropagation();
    }
    domForOne('.pfy-quickzoom-overlay', el => el.remove());
    document.body.style.overflow  = document.body.dataset.overflow;
    document.body.removeAttribute('data-overflow');
  } // hide


  setupZoomHandler(overlay, imgEl) {
    let enlarged = false;
    imgEl.addEventListener('click', ev => {
      ev.stopPropagation();
      if (enlarged) {
        console.log('zooming in');
        imgEl.style.width = imgEl.dataset.width;
        imgEl.style.height = imgEl.dataset.height;
        imgEl.style.top = imgEl.dataset.top;
        enlarged = false;

      } else {
        console.log('zooming out');
        imgEl.dataset.width = imgEl.style.width;
        imgEl.dataset.height = imgEl.style.height;
        imgEl.dataset.top = imgEl.style.top;
        imgEl.style.maxWidth = 'none';
        imgEl.style.width = null;
        imgEl.style.height = null;
        imgEl.style.top = 0;
        enlarged = true;
      }
    });
    imgEl.addEventListener('pointerdown', ev => {
      ev.stopPropagation();
      let translX;
      let translY;

      if (typeof imgEl.dataset.translX === 'undefined') {
        translX = 0;
      } else {
        translX = parseFloat(imgEl.dataset.translX);
      }
      if (typeof imgEl.dataset.translY === 'undefined') {
        translY = 0;
      } else {
        translY = parseFloat(imgEl.dataset.translY);
      }
      let mouseX = 0;
      let mouseY = 0;
      let mouseX0 = 0;
      let mouseY0 = 0;
      // start dragging:
      dragMouseDown(ev);

      function dragMouseDown(ev) {
        ev.preventDefault();
        mouseX = mouseX0 = ev.clientX;
        mouseY = mouseY0 = ev.clientY;
        document.onpointerup = closeDragElement;
        document.onpointermove = elementDrag;
      }

      function elementDrag(ev) {
        ev.preventDefault();
        mouseX = ev.clientX;
        mouseY = ev.clientY;
        const dx = translX + mouseX - mouseX0;
        const dy = translY + mouseY - mouseY0;
        imgEl.style.transform = `translate(${dx}px, ${dy}px)`;
      }

      function closeDragElement() {
        // stop moving when mouse button is released:
        imgEl.dataset.translX = translX + mouseX - mouseX0;
        imgEl.dataset.translY = translY + mouseY - mouseY0;
        document.onpointerup = null;
        document.onpointermove = null;
      }
    })
  } // setupZoomHandler

//  zoomHandler(overlay, imgEl) {
//    let enlarged = false;
//    imgEl.addEventListener('click', ev => {
//      ev.stopPropagation();
//      if (enlarged) {
//        console.log('zooming in');
//        imgEl.style.width = imgEl.dataset.width;
//        imgEl.style.height = imgEl.dataset.height;
//        imgEl.style.top = imgEl.dataset.top;
//        enlarged = false;
//
//      } else {
//        console.log('zooming out');
//        imgEl.dataset.width = imgEl.style.width;
//        imgEl.dataset.height = imgEl.style.height;
//        imgEl.dataset.top = imgEl.style.top;
//        imgEl.style.maxWidth = 'none';
//        imgEl.style.width = null;
//        imgEl.style.height = null;
//        imgEl.style.top = 0;
//        enlarged = true;
//      }
//    });
//
//    let isDragging = false;
//    let offset = { x: 0, y: 0 };
//
//    imgEl.addEventListener('mousedown', (e) => {
//      if (!enlarged) return;
//      isDragging = true;
//      offset.x = e.clientX - imgEl.offsetLeft;
//      offset.y = e.clientY - imgEl.offsetTop;
//      document.body.style.cursor = 'move';
//    });
//
//    document.addEventListener('mousemove', (e) => {
//      if (!isDragging) return;
//
//      const rect = overlay.getBoundingClientRect();
//      let newX = e.clientX - offset.x;
//      let newY = e.clientY - offset.y;
//
//      // Restrict the image within the bounds of the container
//      newX = Math.max(newX, rect.left);
//      newX = Math.min(newX, rect.right - imgEl.offsetWidth);
//      newY = Math.max(newY, rect.top);
//      newY = Math.min(newY, rect.bottom - imgEl.offsetHeight);
//console.log(`left: ${newX}px  top: ${newY}px`);
//      imgEl.style.left = `${newX}px`;
//      imgEl.style.top = `${newY}px`;
//    });
//
//    document.addEventListener('mouseup', () => {
//      isDragging = false;
//      document.body.style.cursor = 'default';
//    });
//
//  } // zoomHandler

} // Quickzoom

if (typeof quickzoom === 'undefined') {
  const quickzoom = new Quickzoom('.pfy-quickzoom');
}
