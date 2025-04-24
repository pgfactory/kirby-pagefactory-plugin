/*
** Ken Burns Effect
*/

class KenBurns {
  constructor(wrapperEl, options = {}) {
    console.log('KenBurns');
    options = this.parseOptions(wrapperEl, options);
    if (!options) {
      return;
    }
    this.applyKenBurns(options);
  } // constructor


  applyKenBurns(options) {
    const { duration, origin, direction, transform1, transform2 } = options;
    console.log(transform1);
    console.log(transform2);

    this.debug(transform1);
    this.imgEl.style.transformOrigin = `${origin[0] * 100}% ${origin[1] * 100}%`;
    this.imgEl.style.transitionProperty = 'transform';
    this.imgEl.style.transitionTimingFunction = 'linear';
    this.imgEl.style.transform = transform1;

    setTimeout(() => {
      this.imgEl.style.transitionDuration = duration;
      this.imgEl.style.transform = transform2;
    }, 10);
  } // applyKenBurns


  stopKenBurns() {
    document.querySelectorAll('.pfy-img').forEach(imgEl => {
      imgEl.style.scale = '1';
      imgEl.style.transform = 'unset';
      imgEl.style.transitionDuration = 'unset';
      imgEl.style.transitionProperty = 'scale, translate';
      imgEl.style.transitionTimingFunction = 'linear';
    });
  } // stopKenBurns


  parseOptions(wrapperSel, options) {
    if (typeof wrapperSel === 'object' && this.isEmpty(options)) {
      options = wrapperSel;
      wrapperSel = '.pfy-image-wrapper';
    }
    options = {
      duration: '10s',
      direction: null,
      distance: null,
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

    if (!this.imgEl) {
      console.error(`No image element found for selector "${wrapperSel}"`);
      return false;
    }

    if (typeof options.duration === 'number') {
      options.duration += 's';
    } else if (!options.duration.match(/[^\d.]/)) {
      options.duration += 's';
    }
    if (typeof options.scale === 'object' && options.scale.length === 1) {
      options.scale = options.scale[0];
    }

    if (typeof options.scale === 'number') {
      const scale = options.scale;
      options.scale = [];
      options.scale[0] = scale < 1 ? 1 / scale : 1;
      options.scale[1] = scale < 1 ? 1 : scale;
    }

    let { duration, direction, distance, scale, origin } = options;

    // direction:
    if (direction && typeof direction === 'string' && direction.match(/^rand/)) {
      direction = Math.round(Math.random() * 360);
    }

    // distance:
    if (direction !== null && distance === null) {
      distance = 1;
    } else if (distance && typeof distance === 'string' && distance.match(/^rand/)) {
      let val = parseFloat(distance.replace(/[^\d.]/g, ''));
      if (isNaN(val)) {
        val = 1;
      }
      distance = Math.random() * Math.max(0, Math.min(1, val));
    } else {
      distance = Math.min(1, Math.max(-1, distance));
    }
    direction = (direction !== null) ? (direction % 360 + 360) % 360 : 0;
    mylog(`direction: ${direction}`);
    const rad = (direction * Math.PI) / 180;

    // scale:
    let scale1, scale2 = 1;
    if (scale && typeof scale === 'string' && scale.match(/rand/)) {
      let val = parseFloat(scale.replace(/[^\d.]/g, ''));
      if (isNaN(val)) {
        val = 0.3;
      }
      scale = Math.random() * 0.2 + 0.9;
      scale = Math.random() * val + (1 - val/2);
      mylog(`scale: ${scale}`);
    }
    if (!scale) {
      const maxDist = distance * Math.max(Math.abs(Math.sin(rad)), Math.abs(Math.cos(rad)));
      scale = 1 + maxDist;
      scale1 = scale;
      scale2 = scale;
    } else if (typeof scale === 'number') {
      scale1 = (scale < 1) ? 1 / scale : 1;
      scale2 = (scale < 1) ? 1 : scale;
    } else if (typeof scale === 'object') {
      scale1 = Math.max(1, scale[0]);
      scale2 = Math.max(1, scale[1]);
    }

    const absDist1 = 0.5 * (1 - 1/scale1);
    const absDist2 = -0.5 * (1 - 1/scale2);

    const tan = Math.tan(rad);

    let tx1, tx2, ty1, ty2;
    if (direction < 45 || direction >= 315) {
      tx1 = absDist1 * tan;
      ty1 = absDist1;
      tx2 = absDist2 * tan;
      ty2 = absDist2;
    } else if (direction < 135) {
      tx1 = absDist1;
      ty1 = absDist1 / tan;
      tx2 = absDist2;
      ty2 = absDist2 / tan;
    } else if (direction < 225) {
      tx1 = -absDist1 * tan;
      ty1 = -absDist1;
      tx2 = -absDist2 * tan;
      ty2 = -absDist2;
    } else { // direction < 315
      tx1 = -absDist1;
      ty1 = -absDist1 / tan;
      tx2 = -absDist2;
      ty2 = -absDist2 / tan;
    }

    tx1 = this.toPercent(tx1 * distance);
    ty1 = this.toPercent(-ty1 * distance);
    tx2 = this.toPercent(tx2 * distance);
    ty2 = this.toPercent(-ty2 * distance);

    const transform1 = `scale(${scale1}) translate(${tx1}, ${ty1})`;
    const transform2 = `scale(${scale2}) translate(${tx2}, ${ty2})`;

    return {
      duration,
      origin,
      direction,
      transform1,
      transform2,
    };
  } // parseOptions


  isEmpty(obj) {
    for(var prop in obj) {
      if(obj.hasOwnProperty(prop))
        return false;
    }
    return true;
  } // isEmpty

  toPercent(x) {
    return (Math.round(x * 10000) / 100) + '%';
  } // toPercent

  debug(transform1) {
    if (!document.body.classList.contains('debug')) {
      return;
    }
    domForOne('head', el => {
      const newStyle = document.createElement("style");
      newStyle.innerHTML = `\n.pfy-image {\n\ttransform: ${transform1};\n }\n`;
      el.append(newStyle);
    })
  } // debug

} // class KenBurns


// Example usage:
// const wrapperEl = document.querySelector('.kenburns-wrapper');
// if (wrapperEl) {
//   new KenBurns(wrapperEl, {
//     duration: '10s',
//     direction: 'random',
//     distance: 1,
//     scale: null,
//     origin: [0.5, 0.5]
//   });
// } else {
//   console.error('No wrapper element found with class .kenburns-wrapper');
// }
