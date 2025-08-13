// https://css-tricks.com/how-to-animate-the-details-element/

class Accordion {
    constructor(el, closeAll = false) {
      // Store the <details> element
      this.el = el;
      // Store the <summary> element
      this.summary = el.querySelector('summary');
      // Store the <div class="mdp-accordion-body"> element
      this.content = el.querySelector('.mdp-accordion-body');

      this.duration = 200;
      // Store the animation object (so we can cancel it if needed)
      this.animation = null;
      // Store if the element is closing
      this.isClosing = false;
      // Store if the element is expanding
      this.isExpanding = false;
      this.autoCloseAllEl = el.closest('.mdp-accordion-auto-close');
      // Detect user clicks on the summary element
      this.summary.addEventListener('click', (e) => this.onClick(e));
    }

    onClick(e) {
      // operate accordion unless summary is a link:
      if (e.target.tagName === 'A') {
        return;
      }

      // Stop browser's default behaviour
      e.preventDefault();

      // handle autoClose feature:
      if (this.autoCloseAllEl) {
        this.closeSiblings();
      }
      // Add an overflow on the <details> to avoid content overflowing
      // Check if the element is being closed or is already closed
      if (this.isClosing || !this.el.open) {
        this.open();
      // Check if the element is being opened or is already open
      } else if (this.isExpanding || this.el.open) {
        this.close();
      }
    } // onClick

    open() {
      // Apply a fixed height on the element
      this.el.dataset.style = this.el.getAttribute('style');
      this.el.style.height = `${this.el.offsetHeight}px`;
      // Force the [open] attribute on the details element
      this.el.open = true;
      // Wait for the next frame to call the expand function
      window.requestAnimationFrame(() => this.expand());
    } // open

    expand() {
      // Set the element as "being expanding"
      this.isExpanding = true;
      // Get the current fixed height of the element
      const startHeight = `${this.el.offsetHeight}px`;
      // Calculate the open height of the element (summary height + content height)
      const endHeight = `${this.summary.offsetHeight + this.content.offsetHeight}px`;

      // If there is already an animation running
      if (this.animation) {
        // Cancel the current animation
        this.animation.cancel();
      }

      // Start a WAAPI animation
      this.animation = this.el.animate({
        // Set the keyframes from the startHeight to endHeight
        height: [startHeight, endHeight]
      }, {
        duration: this.duration,
        easing: 'ease-out'
      });
      // When the animation is complete, call onAnimationFinish()
      this.animation.onfinish = () => this.onAnimationFinish(true);
      // If the animation is cancelled, isExpanding variable is set to false
      this.animation.oncancel = () => this.isExpanding = false;
    } // expand


  close() {
    // Set the element as "being closed"
    this.isClosing = true;

    // Store the current height of the element
    const startHeight = `${this.el.offsetHeight}px`;
    // Calculate the height of the summary
    const endHeight = `${this.summary.offsetHeight}px`;

    // If there is already an animation running
    if (this.animation) {
      // Cancel the current animation
      this.animation.cancel();
    } // close

    // Start a WAAPI animation
    this.animation = this.el.animate({
      // Set the keyframes from the startHeight to endHeight
      height: [startHeight, endHeight]
    }, {
      duration: this.duration,
      easing: 'ease-out'
    });

    // When the animation is complete, call onAnimationFinish()
    this.animation.onfinish = () => this.onAnimationFinish(false);
    // If the animation is cancelled, isClosing variable is set to false
    this.animation.oncancel = () => this.isClosing = false;
  } // close


  onAnimationFinish(open, el) {
      // Set the open attribute based on the parameter
      if (typeof el === 'undefined') {
        el = this.el;
      }

      if (!open) {
        el.open = null;
      }
      // Clear the stored animation
      this.animation = null;
      // Reset isClosing & isExpanding
      this.isClosing = false;
      this.isExpanding = false;
      // Remove the overflow hidden and the fixed height
      el.setAttribute('style', el.dataset.style);
    } // onAnimationFinish


    closeSiblings() {
      const parent = this;
      if (this.autoCloseAllEl) {
        const elems = this.autoCloseAllEl.querySelectorAll('details');
        if (elems) {
            elems.forEach(el => {
            if (el.open && parent.el !== el) {
              parent.closeSibling(el);
            }
          });
        }
      }
    } // closeSiblings


  closeSibling(el) {
    // Store the current height of the element
    const startHeight = `${el.offsetHeight}px`;
    const summary = el.querySelector('summary');
    // Calculate the height of the summary
    const endHeight = `${summary.offsetHeight}px`;

    // If there is already an animation running
    if (el.animation) {
      // Cancel the current animation
      el.animation.cancel();
    } // close

    // Start a WAAPI animation
    el.animation = el.animate({
      // Set the keyframes from the startHeight to endHeight
      height: [startHeight, endHeight]
    }, {
      duration: this.duration,
      easing: 'ease-out'
    });

    // When the animation is complete, call onAnimationFinish()
    el.animation.onfinish = () => this.onAnimationFinish(false, el);
    // If the animation is cancelled, isClosing variable is set to false
    el.animation.oncancel = () => this.isClosing = false;
  } // closeSibling

} // Accordion


document.addEventListener('DOMContentLoaded', ev => {
    const cssSupport = CSS.supports("selector(::details-content)");
    const elems = ev.target.querySelectorAll('details.mdp-accordion, .mdp-accordion details');
    if (elems) {
      elems.forEach((el) => {
        if (!cssSupport) {
          new Accordion(el);
        }
        const formFields = el.querySelectorAll('input,textarea');
        if (formFields) {
          const ignore = 'button,submit,cancel,hidden';
          formFields.forEach(formEl => {
            const type = formEl.getAttribute('type');
            if (ignore.includes(type) || formEl.classList.contains('pfy-reveal-controller')) {
              return;
            }
            if (formEl.innerText || formEl.value || formEl.dataset.value) {
              // console.log(`type: ${type} innerText: ${formEl.innerText}, value: ${formEl.value}`);
              el.open = true;
            }
          });
        }
      });
    } // initialize


  // for printing, open all accordions:
  if (window.matchMedia('print').matches) {
    console.log('opening accordions for printing...');
    const elems = elem.querySelectorAll('details.mdp-accordion, .mdp-accordion details');
    if (elems) {
      elems.forEach((el) => {
        el.removeAttribute('name');
        el.open = true;
      });
    }
  } // open for printing
}); // DOMContentLoaded
