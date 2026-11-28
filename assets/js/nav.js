"use strict";

/*
 * nav.js – site navigation menus
 *
 * Markup as delivered by the CMS:
 *   div.pfy-nav-wrapper > nav.pfy-nav[hidden] > ol > li > a (+ nested ol)
 *
 * Structure after preparation (li with children):
 *   li.pfy-lvl-N.pfy-has-children[.pfy-open][.pfy-curr|.pfy-active]
 *     button.pfy-nav-toggle[aria-expanded][aria-controls]
 *       span.pfy-nav-arrow
 *       span.pfy-nav-label
 *     a
 *     div.pfy-nav-sub#pfy-nav-sub-X
 *       ol
 *         li.pfy-nav-surrogate   (if element has own page; link to that page)
 *         li ...
 *
 * Modes (classes on <nav> or its parent – moved to the wrapper by this script):
 *   .pfy-nav-horizontal      top nav with drop-downs
 *   .pfy-sitemap-horizontal  horizontal sitemap, everything open
 *   (default)                vertical tree -> wrapper gets .pfy-nav-vertical
 *     .pfy-nav-collapsed       all sub-trees initially closed
 *     .pfy-nav-open-current    sub-trees closed except path to current page
 *     double click (or Shift + click/Enter/Space) on an element opens/closes its entire sub-tree
 *
 * The primary nav (.pfy-nav-primary) is switched to vertical mode while
 * body.pfy-small-screen is set (mobile nav), and back when it is removed.
 */

console.debug('nav.js');

class PfyNav {

  static MODE_CLASSES = {
    horizontal: 'pfy-nav-horizontal',
    sitemap:    'pfy-sitemap-horizontal',
    vertical:   'pfy-nav-vertical',
  };

  static ARROW_SVG = '<svg viewBox="0 0 24 24" fill="none" width="1em" height="1em" focusable="false">' +
    '<path d="M15 12L9 6V18L15 12Z" stroke="currentColor" stroke-width="1" stroke-linejoin="round"/>' +
    '</svg>';

  static instances = [];
  static #idCounter = 0;


  // === static setup ===================================================
  static initAll() {
    const navs = [...document.querySelectorAll('nav.pfy-nav')];
    if (!navs.length) {
      return;
    }

    // make sure there is a primary nav:
    const hasPrimary = navs.some(nav =>
      nav.classList.contains('pfy-nav-primary') || nav.parentElement.classList.contains('pfy-nav-primary'));
    if (!hasPrimary) {
      navs[0].parentElement.classList.add('pfy-nav-primary');
    }

    navs.forEach(nav => PfyNav.instances.push(new PfyNav(nav)));

    // all navs are ready -> remove placeholder(s) used to avoid layout shift:
    document.querySelectorAll('.pfy-top-nav-placeholder').forEach(el => el.remove());

    PfyNav.#initGlobalHandlers();
  } // initAll


  static #initGlobalHandlers() {
    // react to body.pfy-small-screen being set/removed (and to mobile menu state):
    new MutationObserver(() => {
      PfyNav.instances.forEach(inst => inst.update());
    }).observe(document.body, { attributes: true, attributeFilter: ['class'] });

    // clicks anywhere:
    document.addEventListener('click', (ev) => {
      const menuButton = ev.target.closest('#pfy-nav-menu-icon');
      if (menuButton) {
        PfyNav.toggleMobileMenu();
        return;
      }
      PfyNav.instances.forEach(inst => {
        if (!inst.wrapper.contains(ev.target)) {
          inst.closeDropDowns();
          if (inst.mobile && PfyNav.isMobileMenuOpen()) {
            PfyNav.toggleMobileMenu(false, false);
          }
        }
      });
    });

    // Escape closes the mobile menu:
    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape' && PfyNav.isMobileMenuOpen() && !ev.defaultPrevented) {
        PfyNav.toggleMobileMenu(false);
      }
    });

    const menuButton = document.getElementById('pfy-nav-menu-icon');
    const primary = PfyNav.instances.find(inst => inst.isPrimary);
    if (menuButton && primary) {
      if (!primary.nav.id) {
        primary.nav.id = 'pfy-nav-primary';
      }
      menuButton.setAttribute('aria-controls', primary.nav.id);
      menuButton.setAttribute('aria-expanded', PfyNav.isMobileMenuOpen() ? 'true' : 'false');
    }
  } // #initGlobalHandlers


  static isMobileMenuOpen() {
    return document.body.classList.contains('pfy-nav-mobile-open');
  } // isMobileMenuOpen


  static toggleMobileMenu(open = !PfyNav.isMobileMenuOpen(), moveFocus = true) {
    const menuButton = document.getElementById('pfy-nav-menu-icon');
    document.body.classList.toggle('pfy-nav-mobile-open', open);
    if (menuButton) {
      menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    const primary = PfyNav.instances.find(inst => inst.isPrimary);
    if (!primary) {
      return;
    }
    primary.syncInert();
    if (!moveFocus) {
      return;
    }
    if (open) {
      const rows = primary.rows();
      const curr = rows.find(r => r.li.classList.contains('pfy-curr'));
      (curr || rows[0])?.el.focus({ preventScroll: true });
    } else if (menuButton && primary.wrapper.contains(document.activeElement)) {
      menuButton.focus();
    }
  } // toggleMobileMenu



  // === instance ========================================================
  constructor(nav) {
    this.nav = nav;
    this.wrapper = nav.parentElement;
    this.isPrimary = this.#hasClass('pfy-nav-primary');
    this.baseMode = this.#detectMode();
    this.collapsed = this.#hasClass('pfy-nav-collapsed');
    this.openCurrent = this.#hasClass('pfy-nav-open-current');
    this.mode = null;
    this.mobile = null;

    // mode classes live on the wrapper from now on:
    Object.values(PfyNav.MODE_CLASSES).forEach(cls => nav.classList.remove(cls));
    if (this.isPrimary) {
      this.wrapper.classList.add('pfy-nav-primary');
      nav.classList.remove('pfy-nav-primary');
    }

    this.#prepare();
    this.update();

    nav.addEventListener('click', ev => this.#onClick(ev));
    nav.addEventListener('dblclick', ev => this.#onDblClick(ev));
    nav.addEventListener('keydown', ev => this.#onKeydown(ev));
    nav.addEventListener('focusout', ev => {
      if (ev.relatedTarget && !this.wrapper.contains(ev.relatedTarget)) {
        this.closeDropDowns();
      }
    });

    nav.hidden = false;
  } // constructor


  #hasClass(cls) {
    return this.nav.classList.contains(cls) || this.wrapper.classList.contains(cls);
  } // #hasClass


  #detectMode() {
    if (this.#hasClass(PfyNav.MODE_CLASSES.horizontal)) {
      return 'horizontal';
    }
    if (this.#hasClass(PfyNav.MODE_CLASSES.sitemap)) {
      return 'sitemap';
    }
    return 'vertical';
  } // #detectMode


  // === DOM preparation =================================================
  #prepare() {
    const rootList = this.nav.querySelector(':scope > ol, :scope > ul');
    if (!rootList) {
      return;
    }
    this.#prepareList(rootList, 1);

    // mark current page and its ancestors:
    this.nav.querySelectorAll('a[aria-current="page"]').forEach(a => {
      const li = a.closest('li');
      li.classList.add('pfy-curr');
      let parentLi = li.parentElement.closest('li');
      while (parentLi && this.nav.contains(parentLi)) {
        parentLi.classList.add('pfy-active');
        parentLi = parentLi.parentElement.closest('li');
      }
    });
  } // #prepare


  #prepareList(list, level) {
    [...list.children].forEach(li => {
      if (li.tagName !== 'LI') {
        return;
      }
      li.classList.add(`pfy-lvl-${level}`);
      const a = li.querySelector(':scope > a');
      const subList = li.querySelector(':scope > ol, :scope > ul');
      if (!subList || !subList.querySelector('li')) {
        return;
      }
      li.classList.add('pfy-has-children');

      // wrap sub-tree in div:
      const id = `pfy-nav-sub-${++PfyNav.#idCounter}`;
      const subWrapper = document.createElement('div');
      subWrapper.className = 'pfy-nav-sub';
      subWrapper.id = id;
      subList.replaceWith(subWrapper);
      subWrapper.append(subList);

      // toggle button (its appearance depends on mode, see nav.scss):
      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'pfy-nav-toggle';
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-controls', id);
      toggle.innerHTML = `<span class="pfy-nav-arrow" aria-hidden="true">${PfyNav.ARROW_SVG}</span>` +
        '<span class="pfy-nav-label"></span>';
      toggle.querySelector('.pfy-nav-label').textContent = a ? a.textContent.trim() : '';
      li.prepend(toggle);

      // surrogate: elements with own content get a link to their page as first sub-element
      //   (shown in vertical mode and in horizontal drop-downs, where the element itself is the toggle):
      if (a && !li.classList.contains('pfy-nav-no-direct-child')) {
        const surrogateLi = document.createElement('li');
        surrogateLi.className = 'pfy-nav-surrogate';
        surrogateLi.append(a.cloneNode(true));
        subList.prepend(surrogateLi);
      }

      this.#prepareList(subList, level + 1);
    });
  } // #prepareList


  // === mode handling ===================================================
  update() {
    const mobile = this.isPrimary && document.body.classList.contains('pfy-small-screen');
    const mode = mobile ? 'vertical' : this.baseMode;
    if (mode !== this.mode || mobile !== this.mobile) {
      this.mode = mode;
      this.mobile = mobile;
      const wrapper = this.wrapper;

      // suppress transitions while switching:
      wrapper.classList.add('pfy-nav-no-transition');
      Object.values(PfyNav.MODE_CLASSES).forEach(cls => wrapper.classList.remove(cls));
      wrapper.classList.add(PfyNav.MODE_CLASSES[mode]);
      wrapper.classList.toggle('pfy-nav-mobile', mobile);
      this.#placeSiteSearch(mobile);
      this.#applyInitialState();
      requestAnimationFrame(() => requestAnimationFrame(() => {
        wrapper.classList.remove('pfy-nav-no-transition');
      }));
    }
    this.syncInert();
  } // update


  #placeSiteSearch(mobile) {
    // mobile (vertical) nav: search field on top (shown without toggle, see nav.scss);
    // otherwise: after the nav.
    const search = this.wrapper.querySelector(':scope > .pfy-sitesearch-wrapper');
    if (!search) {
      return;
    }
    const nav = this.wrapper.querySelector(':scope > .pfy-nav');
    if (mobile) {
      this.wrapper.prepend(search);
    } else if (nav) {
      nav.after(search);
    }
  } // #placeSiteSearch


  syncInert() {
    // closed mobile nav is hidden behind the page -> take it out of tab order and a11y tree:
    this.wrapper.inert = !!(this.mobile && !PfyNav.isMobileMenuOpen());
  } // syncInert


  #applyInitialState() {
    const openCurrent = this.mobile || this.openCurrent;
    this.#branches().forEach(li => {
      let open;
      if (this.mode === 'sitemap') {
        open = true;
      } else if (this.mode === 'horizontal') {
        open = !li.classList.contains('pfy-lvl-1');
      } else if (openCurrent) {
        open = li.classList.contains('pfy-active') || li.classList.contains('pfy-curr');
      } else {
        open = !this.collapsed;
      }
      this.#setOpen(li, open);
    });
  } // #applyInitialState


  #branches() {
    return [...this.nav.querySelectorAll('li.pfy-has-children')];
  } // #branches


  #setOpen(li, open) {
    li.classList.toggle('pfy-open', open);
    li.querySelector(':scope > .pfy-nav-toggle')?.setAttribute('aria-expanded', open ? 'true' : 'false');
    // inert: unreachable immediately, even while the closing animation is still running:
    const subWrapper = li.querySelector(':scope > .pfy-nav-sub');
    if (subWrapper) {
      subWrapper.inert = !open;
    }
  } // #setOpen


  isOpen(li) {
    return li.classList.contains('pfy-open');
  } // isOpen


  toggle(li, open = !this.isOpen(li)) {
    if (!li?.classList.contains('pfy-has-children')) {
      return;
    }
    if (open && this.mode === 'horizontal') {
      this.closeDropDowns(li);
    }
    this.#setOpen(li, open);
  } // toggle


  // opens/closes element and all its descendants:
  toggleSubtree(li, open = !this.isOpen(li)) {
    if (!li?.classList.contains('pfy-has-children')) {
      return;
    }
    this.#setOpen(li, open);
    li.querySelectorAll('li.pfy-has-children').forEach(child => this.#setOpen(child, open));
  } // toggleSubtree


  closeDropDowns(except = null) {
    if (this.mode !== 'horizontal') {
      return;
    }
    this.nav.querySelectorAll('li.pfy-lvl-1.pfy-open').forEach(li => {
      if (li !== except) {
        this.#setOpen(li, false);
      }
    });
  } // closeDropDowns


  // === focus helpers ===================================================
  #isDisplayed(el) {
    return el.getClientRects().length > 0;
  } // #isDisplayed


  #isReachable(el) {
    // not inside a closed sub-tree:
    let sub = el.closest('.pfy-nav-sub');
    while (sub && this.nav.contains(sub)) {
      if (!this.isOpen(sub.parentElement)) {
        return false;
      }
      sub = sub.parentElement.closest('.pfy-nav-sub');
    }
    return true;
  } // #isReachable


  #rowTarget(li) {
    const a = li.querySelector(':scope > a');
    if (a && this.#isDisplayed(a)) {
      return a;
    }
    const toggle = li.querySelector(':scope > .pfy-nav-toggle');
    if (toggle && this.#isDisplayed(toggle)) {
      return toggle;
    }
    return null;
  } // #rowTarget


  // returns visible rows ({li, el}) in document order, optionally limited to a sub-tree:
  rows(root = this.nav) {
    const rows = [];
    root.querySelectorAll('li').forEach(li => {
      if (!this.#isReachable(li)) {
        return;
      }
      const el = this.#rowTarget(li);
      if (el) {
        rows.push({ li, el });
      }
    });
    return rows;
  } // rows


  #focusRow(rows, index) {
    const row = rows[index];
    if (row) {
      row.el.focus({ preventScroll: true });
    }
    return !!row;
  } // #focusRow


  // === event handlers ==================================================
  #onClick(ev) {
    const toggle = ev.target.closest('.pfy-nav-toggle');
    if (!toggle || !this.nav.contains(toggle)) {
      return;
    }
    ev.preventDefault();
    const li = toggle.parentElement;
    if (this.mode === 'vertical') {
      // 2nd click of a double click: ignore, the dblclick handler takes over:
      if (ev.detail > 1) {
        return;
      }
      // Shift + click/Enter/Space: keyboard equivalent of double click:
      if (ev.shiftKey) {
        this.toggleSubtree(li);
        return;
      }
    }
    this.toggle(li);
  } // #onClick


  #onDblClick(ev) {
    const toggle = ev.target.closest('.pfy-nav-toggle');
    if (!toggle || !this.nav.contains(toggle) || this.mode !== 'vertical') {
      return;
    }
    ev.preventDefault();
    // the 1st click has already toggled the element -> propagate its new state to the whole sub-tree:
    const li = toggle.parentElement;
    this.toggleSubtree(li, this.isOpen(li));
  } // #onDblClick


  #onKeydown(ev) {
    if (ev.altKey || ev.ctrlKey || ev.metaKey) {
      return;
    }
    const el = ev.target.closest('a, button');
    const li = el?.closest('li');
    if (!li || !this.nav.contains(li)) {
      return;
    }
    let handled;
    if (this.mode === 'horizontal') {
      handled = this.#keyHorizontal(ev.key, el, li);
    } else {
      handled = this.#keyVertical(ev.key, el, li);
    }
    if (handled) {
      ev.preventDefault();
      ev.stopPropagation();
    }
  } // #onKeydown


  #keyHorizontal(key, el, li) {
    const topLi = li.closest('.pfy-lvl-1');
    const topRows = this.rows().filter(r => r.li.classList.contains('pfy-lvl-1'));
    const topInx = topRows.findIndex(r => r.li === topLi);
    const subWrapper = topLi.querySelector(':scope > .pfy-nav-sub');
    const onTopLevel = (li === topLi);

    const gotoTop = (inx) => {
      const n = topRows.length;
      this.closeDropDowns();
      return this.#focusRow(topRows, (inx + n) % n);
    };

    switch (key) {
      case 'ArrowRight':
        return gotoTop(topInx + 1);
      case 'ArrowLeft':
        return gotoTop(topInx - 1);
      case 'Escape':
        if (!this.isOpen(topLi)) {
          return false;
        }
        this.toggle(topLi, false);
        topLi.querySelector(':scope > .pfy-nav-toggle')?.focus();
        return true;
    }

    if (onTopLevel) {
      switch (key) {
        case 'Home':
          return gotoTop(0);
        case 'End':
          return gotoTop(topRows.length - 1);
        case 'ArrowDown':
        case 'ArrowUp':
          if (!subWrapper) {
            return false;
          }
          this.toggle(topLi, true);
          const subRows = this.rows(subWrapper);
          return this.#focusRow(subRows, key === 'ArrowDown' ? 0 : subRows.length - 1);
      }
      return false;
    }

    // inside drop-down:
    const subRows = this.rows(subWrapper);
    const inx = subRows.findIndex(r => r.li === li);
    switch (key) {
      case 'ArrowDown':
        return this.#focusRow(subRows, (inx + 1) % subRows.length);
      case 'ArrowUp':
        if (inx === 0) {
          topLi.querySelector(':scope > .pfy-nav-toggle')?.focus();
          return true;
        }
        return this.#focusRow(subRows, inx - 1);
      case 'Home':
        return this.#focusRow(subRows, 0);
      case 'End':
        return this.#focusRow(subRows, subRows.length - 1);
    }
    return false;
  } // #keyHorizontal


  #keyVertical(key, el, li) {
    const rows = this.rows();
    const inx = rows.findIndex(r => r.li === li);
    const hasChildren = li.classList.contains('pfy-has-children') && this.mode === 'vertical';
    const parentLi = li.parentElement.closest('li');

    switch (key) {
      case 'ArrowDown':
        return this.#focusRow(rows, inx + 1);
      case 'ArrowUp':
        return this.#focusRow(rows, inx - 1);
      case 'Home':
        return this.#focusRow(rows, 0);
      case 'End':
        return this.#focusRow(rows, rows.length - 1);
    }
    if (this.mode !== 'vertical') {
      return false;
    }

    switch (key) {
      case 'ArrowRight':
        if (!hasChildren) {
          return false;
        }
        if (!this.isOpen(li)) {
          this.toggle(li, true);
          return true;
        }
        return this.#focusRow(this.rows(li.querySelector(':scope > .pfy-nav-sub')), 0);

      case 'ArrowLeft':
        if (hasChildren && this.isOpen(li)) {
          this.toggle(li, false);
          if (!this.#isDisplayed(el)) {
            this.#rowTarget(li)?.focus();
          }
          return true;
        }
        if (parentLi && this.nav.contains(parentLi)) {
          this.#rowTarget(parentLi)?.focus();
          return true;
        }
        return false;

      case 'Escape':
        if (!parentLi || !this.nav.contains(parentLi) || (this.mobile && PfyNav.isMobileMenuOpen())) {
          return false; // let the document handler close the mobile menu
        }
        this.toggle(parentLi, false);
        parentLi.querySelector(':scope > .pfy-nav-toggle')?.focus();
        return true;
    }
    return false;
  } // #keyVertical

} // PfyNav


 // === SiteSearch ===================================================================

class PfyDialog {
  static #instances = new WeakMap();

  /** Returns exactly one instance per <dialog>. */
  static for(dialog, options) {
    if (!this.#instances.has(dialog)) {
      this.#instances.set(dialog, new PfyDialog(dialog, options));
    }
    return this.#instances.get(dialog);
  }

  constructor(dialog, { backdropClose = true } = {}) {
    this.dialog = dialog;
    this.backdropClose = backdropClose;
    this.dx = 0;
    this.dy = 0;
    this.drag = null;
    this.pressedOnBackdrop = false;
    this.onResize = () => this.#clamp();

    dialog.addEventListener('click', (e) => this.#onClick(e));
    dialog.addEventListener('pointerdown', (e) => this.#onPointerDown(e));
    dialog.addEventListener('pointermove', (e) => this.#onPointerMove(e));
    dialog.addEventListener('pointerup', (e) => this.#endDrag(e));
    dialog.addEventListener('pointercancel', (e) => this.#endDrag(e));
    dialog.addEventListener('close', () => {          // also fires on Esc
      window.removeEventListener('resize', this.onResize);
      this.#setOffset(0, 0);
    });
  }

  open() {
    if (!this.dialog.open) {
      this.dialog.showModal();
      window.addEventListener('resize', this.onResize);
    }
    return this;
  }

  close() {
    this.dialog.close();
    return this;
  }

  // ---- Close button & click on backdrop ----
  #onClick(e) {
    if (e.target.closest('.pfy-sitesearch-popup-close-button')) {
      this.close();
      return;
    }
    if (this.backdropClose && this.pressedOnBackdrop
      && e.target === this.dialog && this.#isOutside(e)) {
      this.close();
    }
  }

  #isOutside(e) {
    const r = this.dialog.getBoundingClientRect();
    return e.clientX < r.left || e.clientX > r.right
      || e.clientY < r.top || e.clientY > r.bottom;
  }

  // ---- Drag ----
  #onPointerDown(e) {
    // remember whether the click *started* on the backdrop (prevents closing after text selection)
    this.pressedOnBackdrop = e.target === this.dialog && this.#isOutside(e);

    const handle = e.target.closest('.pfy-draggable');
    if (!handle || e.button !== 0 || e.target.closest('button')) return;

    const r = this.dialog.getBoundingClientRect();
    const vw = document.documentElement.clientWidth;
    const vh = document.documentElement.clientHeight;
    this.drag = {
      handle,
      id: e.pointerId,
      startX: e.clientX,
      startY: e.clientY,
      baseDx: this.dx,
      baseDy: this.dy,
      // allowed range so the dialog stays fully inside the window
      minDx: this.dx - r.left,
      maxDx: this.dx + vw - r.right,
      minDy: this.dy - r.top,
      maxDy: this.dy + vh - r.bottom,
    };
    handle.setPointerCapture(e.pointerId);
  }

  #onPointerMove(e) {
    const d = this.drag;
    if (!d || e.pointerId !== d.id) return;
    const dx = Math.min(Math.max(d.baseDx + e.clientX - d.startX, d.minDx), d.maxDx);
    const dy = Math.min(Math.max(d.baseDy + e.clientY - d.startY, d.minDy), d.maxDy);
    this.#setOffset(dx, dy);
  }

  #endDrag(e) {
    const d = this.drag;
    if (!d || e.pointerId !== d.id) return;
    d.handle.releasePointerCapture?.(d.id);
    this.drag = null;
  }

  #setOffset(dx, dy) {
    this.dx = dx;
    this.dy = dy;
    this.dialog.style.setProperty('--pfy-sitesearch-popup-dx', `${dx}px`);
    this.dialog.style.setProperty('--pfy-sitesearch-popup-dy', `${dy}px`);
  }

  // push back into the window after a window resize
  #clamp() {
    const r = this.dialog.getBoundingClientRect();
    const vw = document.documentElement.clientWidth;
    const vh = document.documentElement.clientHeight;
    let { dx, dy } = this;
    if (r.right > vw) dx -= r.right - vw;
    if (r.left < 0) dx -= r.left;
    if (r.bottom > vh) dy -= r.bottom - vh;
    if (r.top < 0) dy -= r.top;
    if (dx !== this.dx || dy !== this.dy) this.#setOffset(dx, dy);
  }
} // PfyDialog


domReady(() => {
  PfyNav.initAll();

  domForEach('.pfy-sitesearch-wrapper', el => {
    initSitesearchIcon(el);
  });
  domForOne('#pfy-sitesearch-results', el => {
    PfyDialog.for(el).open();
  });

  // remove search query from URL:
  const url = new URL(location.href);
  if (url.searchParams.has('q')) {
    url.searchParams.delete('q');
    history.replaceState(history.state, '', url);
  }
});


function initSitesearchIcon(root) {
  var toggle = root.querySelector('.pfy-sitesearch-toggle');
  var input  = root.querySelector('input[type="search"]');
  if (!toggle || !input) {
    return;
  }
  toggle.hidden = false;
  function setOpen(open, focus) {
    root.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open && focus) {
      input.focus(); input.select();
    }
  }
  setOpen(false);
  toggle.addEventListener('click', function () {
    setOpen(!root.classList.contains('is-open'), true);
  });
  root.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      setOpen(false); toggle.focus();
    }
  });
  document.addEventListener('click', function (e) {
    if (!root.contains(e.target)) {
      setOpen(false);
    }
  });
  root.addEventListener('focusout', function (e) {
    if (e.relatedTarget && !root.contains(e.relatedTarget)) {
      setOpen(false);
    }
  });
} // initSitesearchIcon

