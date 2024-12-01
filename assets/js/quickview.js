"use strict";


class QuickView {
  constructor(targetSelector) {
    this.overlay = null;
    this.targetClass = targetSelector.replace(/^\./, '');
    const parent = this;
    const targets = document.querySelectorAll(targetSelector);
    if (targets.length) {
      targets.forEach(function (target) {
        target.addEventListener('click', function(e) {
          quickView.showQuickView(e.currentTarget);
        });
      });
    }
  }

  createOverlay() {
    this.overlay = document.createElement('div');
    this.overlay.classList.add(this.targetClass + '-overlay');
    document.body.appendChild(this.overlay);
  }

  showQuickView(target) {
    quickView.createOverlay();
    const largeImageSrc = target.getAttribute('data-qvsrc');
    const imageElement = document.createElement('img');
    imageElement.src = largeImageSrc;

    this.overlay.innerHTML = '';
    this.overlay.appendChild(imageElement);

    this.overlay.addEventListener('click', this.closeQuickView.bind(this));
  }

  closeQuickView(event) {
      this.overlay.remove();
  }
}


const quickView = new QuickView('.pfy-quickview');
