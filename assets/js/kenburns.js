/*
** Ken Burns Effect
*/

class KenBurns {
  imgEl = null;
  inx = null;
  animationOptions = null;
  animation = null;
  Ox = null;
  Oy = null;
  debug = false;
  crosshairEl = null;

  constructor(wrapperEl, options, animationOptions) {
    options = this.parseOptions(wrapperEl, options, animationOptions);
    if (!options) {
      return;
    }
    this.inx = this.animationOptions.inx;
    this.debug = this.animationOptions.debug;
    if (this.animationOptions.measure) {
      this.activateMeasure();
    }
    this.startKenBurns(options);
    this.setupPauseTrigger();
  } // constructor


  startKenBurns(options) {
    const imgEl = this.imgEl;
    let duration = this.animationOptions.duration;
    if (duration < 240) { // convert to ms if necessary
      duration *= 1000;
    }
    const easing = this.animationOptions.easing;
    imgEl.style.transformOrigin = options.origin;
    delete options.origin;

    if (this.debug) {
      this.activateDebug(options.transform);
      return;
    }

    this.animation = imgEl.animate(options, {
      duration: duration,
      easing:   easing,
      fill:     'forwards',
    });
  } // startKenBurns


  setupPauseTrigger() {
    this.imgEl.addEventListener('click', (ev) => {
      ev.stopPropagation();
      if (this.animation) {
        this.pauseAnimation();
      }
    });
  } // setupPauseTrigger


  pauseAnimation() {
    if (this.animation.playState === 'paused') {
      this.animation.play();
    } else {
      this.animation.pause();
    }
  } // pauseAnimation


  // === parseOptions ==================================================
  parseOptions(wrapperSel, options, animationOptions) {
    if (typeof wrapperSel === 'object' && (!options || Object.keys(options).length === 0)) {
      options = wrapperSel;
      wrapperSel = '.pfy-img-wrapper';
    }

    if (!animationOptions || typeof animationOptions !== 'object') {
      animationOptions = { duration: animationOptions || 10 };
    }
    this.animationOptions = animationOptions;

    options = {
      direction: 0,
      distance: 0,
      scale: [1.2, 1],
      origin: [0.5, 0.5],
      ...options
    };

    let wrapperEl = wrapperSel;
    if (typeof wrapperSel === 'string') {
      wrapperEl = document.querySelector(wrapperSel);
    }

    if (!wrapperEl) {
      console.error(`No element found for selector "${wrapperSel}"`);
      return false;
    }

    this.imgEl = wrapperEl.tagName === 'IMG' ? wrapperEl : wrapperEl.querySelector('img');

    if (!this.imgEl) {
      console.error(`No image element found for selector "${wrapperSel}"`);
      return false;
    }

    domForOne(this.imgEl, '^.pfy-img-wrapper', el => {
      el.style.overflow = 'hidden';
    });

    let { direction, distance, scale, origin } = options;

    // Normalize scale to array [startScale, endScale]
    if (!Array.isArray(scale)) {
      scale = [scale, 1];
    }

    const w = this.imgEl.width;
    const h = this.imgEl.height;
    const Ox = this.Ox = origin[0];
    const Oy = this.Oy = origin[1];
    const Sc1 = scale[0];
    const Sc2 = scale[1];

    const DEG = 180 / Math.PI;
    const RAD = Math.PI / 180;

    // Angles limiting the 4 major directions (pointing to each side of the image)
    const alpha1 = Math.atan((w * (1 - Ox)) / (h * Oy)) * DEG;
    const alpha2 = Math.atan((h * (1 - Oy)) / (w * (1 - Ox))) * DEG + 90;
    const alpha3 = Math.atan((w * Ox) / (h * (1 - Oy))) * DEG + 180;
    const alpha4 = 360 - Math.atan((w * Ox) / (h * Oy)) * DEG;

    direction = ((direction % 360) + 360) % 360;
    const dirRad = direction * RAD;

    // Normalize distance: 0..1 or 1.1%..100%
    distance = Math.max(Math.min(distance, 100), 0);
    if (distance > 1) {
      distance /= 100;
    }

    const tan = Math.tan(dirRad);
    const cot = 1 / tan;

    const f = (Sc1 - 1) / Sc1;
    const Tx = w * f;
    const Ty = h * f;
    const Tx1 = Tx * Ox;
    const Tx2 = Tx * (Ox - 1);
    const Ty1 = Ty * Oy;
    const Ty2 = Ty * (Oy - 1);

    let tx1, ty1, orient;

    if (direction > alpha4 || direction <= alpha1) {      // north:
      orient = 'north';
      tx1 = -Ty1 * tan;
      ty1 = Ty1;

    } else if (direction <= alpha2) {      // east:
      orient = 'east';
      tx1 = Tx2;
      ty1 = -Tx2 * cot;

    } else if (direction <= alpha3) {      // south:
      orient = 'south';
      tx1 = -Ty2 * tan;
      ty1 = Ty2;

    } else {      // west:
      orient = 'west';
      tx1 = Tx1;
      ty1 = -Tx1 * cot;
    }

    if (Ox === 0 && direction > 0 && direction <= 180) {
      tx1 = 0;
      console.warn(`nonsensical direction for Ox = 0: ${direction}`);
    } else if (Ox === 1 && direction >= 180 && direction <= 360) {
      tx1 = 0;
      console.warn(`nonsensical direction for Ox = 1: ${direction}`);
    }
    if (Oy === 0 && direction >= 90 && direction <= 270) {
      ty1 = 0;
      console.warn(`nonsensical direction for Oy = 0: ${direction}`);
    } else if (Oy === 1 && (direction >= 270 || direction <= 90)) {
      ty1 = 0;
      console.warn(`nonsensical direction for Oy = 1: ${direction}`);
    }

    tx1 = this.toPx(tx1 * distance);
    ty1 = this.toPx(ty1 * distance);

    const originStr = Math.round(Ox * 100) + '% ' + Math.round(Oy * 100) + '%';
    const transform = [`scale(${Sc1}) translate(${tx1}, ${ty1})`, `scale(${Sc2}) translate(0%, 0%)`];
    return {
      origin: originStr,
      transform,
    };
  } // parseOptions


  toPx(x) {
    return Math.round(x) + 'px';
  } // toPx


  activateDebug(transform) {
    console.debug(`debug: start position  [${transform[0]}]`);
    this.imgEl.style.transform = transform[0];
    this.imgEl.dataset.kbDebug = 0;
    this.imgEl.addEventListener('click', (ev) => {
      ev.stopPropagation();
      if (this.animation) {
        this.animation.pause();
      }
      const debugInx = this.imgEl.dataset.kbDebug === '1' ? 0 : 1;
      this.imgEl.dataset.kbDebug = debugInx;
      console.debug(`debug: ${debugInx ? 'end position' : 'start position'}  [${transform[debugInx]}]`);
      this.imgEl.style.transform = transform[debugInx];
    });
    this.activateMeasure();
  } // activateDebug


  activateMeasure() {
    this.showTransformOrigin();
    new DragImageCrosshair(this.crosshairEl);
  } // activateMeasure


  showTransformOrigin() {
    const wrapper = this.imgEl.parentElement;
    const div = document.createElement('div');
    div.className = 'pfy-crosshair';
    wrapper.appendChild(div);
    this.crosshairEl = div;

    if (!document.querySelector('style[data-pfy-crosshair]')) {
      const aspectRatio = this.imgEl.width / this.imgEl.height;
      const w = 10;
      const h = aspectRatio * 10;
      const style = document.createElement('style');
      style.setAttribute('data-pfy-crosshair', '');
      style.textContent = `
      .pfy-crosshair {
        position: absolute;
        width: ${w}%;
        height: ${h}%;
        transform: translate(-50%, -50%);
        cursor: grab;
      }
      .pfy-crosshair::before,
      .pfy-crosshair::after {
        content: '';
        position: absolute;
        background-color: red;
        outline: 1px solid yellow;
      }
      .pfy-crosshair::before { /* Horizontal line */
        top: 50%;
        left: 0;
        width: 100%;
        height: 1.5px;
      }
      .pfy-crosshair::after { /* Vertical line */
        top: 0;
        left: 50%;
        width: 1.5px;
        height: 100%;
      }`;
      document.head.appendChild(style);
    }
    this.setCrosshairPosition(this.Ox, this.Oy);
  } // showTransformOrigin


  setCrosshairPosition(xPercent, yPercent) {
    const container = this.imgEl.parentElement;
    this.crosshairEl.style.left = `${container.offsetWidth * xPercent}px`;
    this.crosshairEl.style.top = `${container.offsetHeight * yPercent}px`;
    this.crosshairEl.style.display = 'block';
  } // setCrosshairPosition

} // class KenBurns
