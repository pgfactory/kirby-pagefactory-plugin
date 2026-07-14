// Animated accordion based on <details> element
// Ref: https://css-tricks.com/how-to-animate-the-details-element/

class Accordion {
  constructor(el) {
    this.el = el;
    this.summary = el.querySelector('summary');
    this.content = el.querySelector('.mdp-accordion-body');
    this.duration = 200;
    this.animation = null;
    this.isClosing = false;
    this.isExpanding = false;
    this.savedStyle = null;
    this.autoCloseContainer = el.closest('.mdp-accordion-auto-close');
    // Store instance on element for cross-instance access (closeSiblings)
    el._accordion = this;
    this.summary.addEventListener('click', (e) => this.onClick(e));
  }

  onClick(e) {
    // Don't intercept clicks on links within summary
    if (e.target.tagName === 'A') {
      return;
    }
    e.preventDefault();

    if (this.isClosing || !this.el.open) {
      if (this.autoCloseContainer) {
        this.closeSiblings();
      }
      this.open();
    } else if (this.isExpanding || this.el.open) {
      this.close();
    }
  }

  open() {
    this.savedStyle = this.el.getAttribute('style');
    this.el.style.height = `${this.el.offsetHeight}px`;
    this.el.open = true;
    window.requestAnimationFrame(() => this.expand());
  }

  expand() {
    this.isExpanding = true;
    const startHeight = `${this.el.offsetHeight}px`;
    const endHeight = `${this.summary.offsetHeight + this.content.offsetHeight}px`;

    if (this.animation) {
      this.animation.cancel();
    }

    this.animation = this.el.animate(
      { height: [startHeight, endHeight] },
      { duration: this.duration, easing: 'ease-out' }
    );
    this.animation.onfinish = () => this.onAnimationFinish(true);
    this.animation.oncancel = () => { this.isExpanding = false; };
  }

  close() {
    this.isClosing = true;
    this.savedStyle = this.el.getAttribute('style');
    const startHeight = `${this.el.offsetHeight}px`;
    const endHeight = `${this.summary.offsetHeight}px`;

    if (this.animation) {
      this.animation.cancel();
    }

    this.animation = this.el.animate(
      { height: [startHeight, endHeight] },
      { duration: this.duration, easing: 'ease-out' }
    );
    this.animation.onfinish = () => this.onAnimationFinish(false);
    this.animation.oncancel = () => { this.isClosing = false; };
  }

  onAnimationFinish(open) {
    if (!open) {
      this.el.open = false;
    }
    this.animation = null;
    this.isClosing = false;
    this.isExpanding = false;
    if (this.savedStyle) {
      this.el.setAttribute('style', this.savedStyle);
    } else {
      this.el.removeAttribute('style');
    }
    this.savedStyle = null;
  }

  closeSiblings() {
    if (!this.autoCloseContainer) return;
    this.autoCloseContainer.querySelectorAll('details').forEach(el => {
      if (el.open && el !== this.el) {
        if (el._accordion) {
          el._accordion.close();
        } else {
          el.open = false;
        }
      }
    });
  }

} // Accordion


document.addEventListener('DOMContentLoaded', () => {
  const cssSupport = CSS.supports('selector(::details-content)');
  const elems = document.querySelectorAll('.mdp-accordion-group > details.mdp-accordion, .mdp-accordion details');
  const ignoredTypes = new Set(['button', 'submit', 'cancel', 'hidden']);

  elems.forEach(el => {
    if (!cssSupport) {
      new Accordion(el);
    }

    // Pre-open if it contains non-empty form fields, unless .mdp-accordion-initially-closed is present
    if (el.classList.contains('mdp-accordion-initially-closed')) {
      return;
    }
    el.querySelectorAll('input, textarea').forEach(formEl => {
      const type = formEl.getAttribute('type');
      if (ignoredTypes.has(type) || formEl.classList.contains('pfy-reveal-controller')) {
        return;
      }
      if (formEl.innerText || formEl.value || formEl.dataset.value) {
        el.open = true;
      }
    });
  });

  // Open all accordions for printing
  window.addEventListener('beforeprint', () => {
    document.querySelectorAll('details.mdp-accordion, .mdp-accordion details').forEach(el => {
      el.removeAttribute('name');
      el.open = true;
    });
  });
});
