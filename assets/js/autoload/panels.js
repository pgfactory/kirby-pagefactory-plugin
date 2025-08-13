
const currPanelKey = 'pfyCurrPanelKey_' + pageId;

function pfyOpenPanel(el, offset) {
  const panel = el.closest('.pfy-panel');
  const panelInx = parseInt(panel.classList.value.match(/pfy-panel-(\d+)/)[1]) + offset;
  const currPanelClass = `.pfy-panels-wrapper .pfy-panel-${panelInx}`;
  sessionStorage.setItem(currPanelKey, currPanelClass + '/' + Date.now());

  const nextpanel = document.querySelector(currPanelClass);
  if (nextpanel) {
      const allPanels = document.querySelectorAll(`.pfy-panels-wrapper .pfy-panel`);
      if (allPanels) {
          allPanels.forEach(el => {
              el.classList.remove('pfy-panel-open');
          });
      }
      nextpanel.classList.add('pfy-panel-open');
  }
} // pfyOpenPanel


document.addEventListener('click', (ev) => {
  if (!ev.target.closest('.pfy-panel-arrows button')) {
    return;
  }
  const el = ev.target;
  const panel = el.closest('.pfy-panel');
  if (el.closest('.pfy-panel-arrow-prev')) {
      pfyOpenPanel(el, -1);
  } else if (el.closest('.pfy-panel-arrow-next')) {
      pfyOpenPanel(el, +1);
  } else if (el.closest('.pfy-panel-header-center')) {
      const targetClass = '.pfy-panel-' + el.dataset.panel;
      const targetEl = document.querySelector(targetClass);
      pfyOpenPanel(targetEl, 0);
  }
}); // click


document.addEventListener('DOMContentLoaded', () => {
  const currPanel = sessionStorage.getItem(currPanelKey);
  if (currPanel) {
    let currPanelClass = currPanel.split('/');
    if (parseInt(currPanelClass[1]) > (Date.now() - 3600000)) {
      currPanelClass = currPanelClass[0];
      const currPanel = document.querySelector(currPanelClass);
      if (currPanel) {
          const allPanels = document.querySelectorAll(`.pfy-panels-wrapper .pfy-panel`);
          if (allPanels) {
              allPanels.forEach(el => {
                  el.classList.remove('pfy-panel-open');
              });
          }
      currPanel.classList.add('pfy-panel-open');
      }
    }
  }
  setTimeout(() => {
    domForAll('.pfy-panel', el => {
      el.style.transitionDuration = '0.25s';
    });
  }, 50);
}); // DOMContentLoaded
