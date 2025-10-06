/*
** Ken Burns Effect
*/

class KenBurns {
  imgEl = null;
  animationOptions = null;
  Ox = null;
  Oy = null;
  debug = false;
  measure = false;

  constructor(wrapperEl, options, animationOptions) {
    console.log('KenBurns');

    options = this.parseOptions(wrapperEl, options, animationOptions);
    if (!options) {
      return;
    }
    this.debug = animationOptions.debug;
    if (animationOptions.measure) {
      this.activateMeasure();
    }
    this.startKenBurns(options);
    this.setupPauseTrigger();
  } // constructor


  startKenBurns(options) {
    const imgEl           = this.imgEl;
    let duration                = this.animationOptions.duration;
    if (duration < 240) { // conver to ms, if necessary
      duration *= 1000;
    }
    const easing                = this.animationOptions.easing;
    imgEl.style.transformOrigin = options.origin;
    delete options.origin;

    // debugging:
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
      if (typeof this.animation !== 'undefined') {
        this.pauseAnimation();
      }
    })
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
    if (typeof wrapperSel === 'object' && this.isEmpty(options)) {
      options = wrapperSel;
      wrapperSel = '.pfy-img-wrapper';
    }

    if (typeof animationOptions === 'undefined') {
      animationOptions = { duration: 10 };
    } else if (typeof animationOptions !== 'object') {
      animationOptions = { duration: animationOptions };
    }
    this.animationOptions = animationOptions;

    options = {
      direction: 0,
      distance: 0,
      scale: 1.2,
      origin: [0.5, 0.5],
      ...options
    };
    let wrapperEl = wrapperSel;
    if (typeof wrapperSel === 'string') {
      wrapperEl = document.querySelector(wrapperSel);
    }

    if (wrapperEl.tagName !== 'IMG') {
      this.imgEl = wrapperEl ? wrapperEl.querySelector('img') : null;
    } else {
      this.imgEl = wrapperEl;
    }
    domForOne(this.imgEl, '^.pfy-img-wrapper', el => {
      el.style.overflow = 'hidden';
    });

    if (!this.imgEl) {
      console.error(`No image element found for selector "${wrapperSel}"`);
      return false;
    }

    let { direction, distance, scale, origin } = options;
    const w = this.imgEl.width;
    const h = this.imgEl.height;
    const Ox = this.Ox = origin[0];
    const Oy = this.Oy = origin[1];
    const Sc1 = scale[0];
    const Sc2 = scale[1];

    const deg = 180 / Math.PI;
    const rad = Math.PI / 180;

    let alpha1, alpha2, alpha3, alpha4; // angles limiting the 4 major directions (i.e. pointing to one of the 4 sides of the image)

    alpha1 = Math.atan((w * (1 - Ox)) / (h * Oy)) * deg;
    alpha2 = Math.atan((h * (1 - Oy)) / (w * (1 - Ox))) * deg + 90;
    alpha3 = Math.atan((w * Ox) / (h * (1 - Oy))) * deg + 180;
    alpha4 = 360 - Math.atan((w * Ox) / (h * Oy)) * deg;

    direction = (direction % 360);
    const dirRad = direction * rad; // convert to rad

    // normalize distance: 0..1 or 1.1%..100%
    distance = Math.max(Math.min(distance, 100), 0);
    if (distance > 1) {
      distance = distance / 100;
    }

    const tan = Math.tan(dirRad);
    const cot = 1 / tan;

    const f = ((Sc1 - 1) / Sc1);
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

    if (Ox === 0 && (direction > 0 && direction <= 180)) {
      tx1 = 0;
      console.log(`nonsensical direction for Ox = 0: ${direction}`);
    } else if (Ox === 1 && (direction >= 180 && direction <= 360)) {
      tx1 = 0;
      console.log(`nonsensical direction for Ox = 1: ${direction}`);
    }
    if (Oy === 0 && (direction >= 90 && direction <= 270)) {
      ty1 = 0;
      console.log(`nonsensical direction for Oy = 0: ${direction}`);
    } else if (Oy === 1 && (direction >= 270 || direction <= 90)) {
      ty1 = 0;
      console.log(`nonsensical direction for Oy = 1: ${direction}`);
    }


    tx1 = this.toPx(tx1 * distance);
    ty1 = this.toPx(ty1 * distance);

    origin = parseInt(Ox * 100) + '% ' + parseInt(Oy * 100) + '%';
    const transform = [`scale(${Sc1}) translate(${tx1}, ${ty1})`, `scale(${Sc2}) translate(0%, 0%)`];
    console.log(`dir: ${orient} ${direction} | scale: [${Sc1}, ${Sc2}] | transl: [${tx1}, ${ty1}] | origin: [${Ox}, ${Oy}] | distance: ${distance}`);
    return {
      origin,
      transform,
    };
  } // parseOptions


  isEmpty(obj) {
    for(var prop in obj) {
      if(obj.hasOwnProperty(prop))
        return false;
    }
    return true;
  } // isEmpty


  toPx(x) {
    return Math.round(x) + 'px';
  } // toPx


  activateDebug(transform){
    console.log(`debug: start position  [${transform[0]}]`);
    this.showTransformOrigin();
    this.imgEl.style.transform = transform[0];
    this.imgEl.dataset.kbDebug = 0;
    this.imgEl.addEventListener('click', (ev) => {
      ev.stopPropagation();
      if (typeof this.animation !== 'undefined') {
        this.animation.pause();
      }
      const debugInx = this.imgEl.dataset.kbDebug === '1'? 0 : 1;
      this.imgEl.dataset.kbDebug = debugInx;
      console.log(`debug: ${debugInx? 'end position':'start position'}  [${transform[debugInx]}]`);
      this.imgEl.style.transform = transform[debugInx];

    })
  } // activateDebug


  showTransformOrigin() {
    const wrapper = this.imgEl.parentElement;
    const div = document.createElement("div");
    div.setAttribute('id', 'pfy-crosshair');
    wrapper.appendChild(div);

    const newStyle = document.createElement("style");
    const w = 10;
    const h = this.imgEl.width / this.imgEl.height * 10;
    newStyle.innerHTML = `
    #pfy-crosshair {
      position: absolute;
      width: ${w}%;
      height: ${h}%;
      transform: translate(-50%, -50%);
    }

    #pfy-crosshair::before,
      #pfy-crosshair::after {
      content: '';
      position: absolute;
      background-color: red;
      outline: 1px solid yellow;
    }

    #pfy-crosshair::before { /* Horizontal line */
      top: 50%;
      left: 0;
      width: 100%;
      height: 1.5px;
    }

    #pfy-crosshair::after { /* Vertical line */
      top: 0;
      left: 50%;
      width: 1.5px;
      height: 100%;
    }
    `;
    document.head.append(newStyle);
    this.setCrosshairPosition(this.Ox, this.Oy);
  } // showTransformOrigin


  setCrosshairPosition(xPercent, yPercent) {
    const crosshair = document.getElementById('pfy-crosshair');
    const container = this.imgEl.parentElement;

    // Calculate the position based on percentages
    const xPosition = (container.offsetWidth * xPercent);
    const yPosition = (container.offsetHeight * yPercent);
    //console.log(`setCrosshairPosition: ${xPercent*100}% ${yPercent*100}%`);

    // Set the position of the crosshair
    crosshair.style.left = `${xPosition}px`;
    crosshair.style.top = `${yPosition}px`;

    // Optionally, you can make the crosshair visible if it's initially hidden
    crosshair.style.display = 'block';
  } // setCrosshairPosition


  activateMeasure() {
    this.showTransformOrigin();
    const crosshairEl = document.getElementById('pfy-crosshair');
    new DragImageCrosshair(crosshairEl);
  } // activateMeasure

} // class KenBurns


  // Example usage:
  // const wrapperEl = document.querySelector('.kenburns-wrapper');
  // if (wrapperEl) {
  //   new KenBurns(wrapperEl, {
  //     duration: 1000, // milliseconds
  //     direction: 'random', // or 180
  //     distance: 1,   // [0..1]
  //     scale: [2, 1.8],
  //     origin: [0.5, 0.5]
  //   });
  // } else {
  //   console.error('No wrapper element found with class .kenburns-wrapper');
  // }
