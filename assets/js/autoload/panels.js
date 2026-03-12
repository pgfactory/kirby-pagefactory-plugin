
const currPanelKey = 'pfyCurrPanelKey_' + pageId;


function clearAllPanels() {
  const allPanels = document.querySelectorAll('.pfy-panels-wrapper .pfy-panel');
  allPanels.forEach(panel => {
    panel.classList.remove('pfy-panel-open');
  });
} // clearAllPanels


function pfyOpenPanel(el, offset) {
  const panel = el.closest('.pfy-panel');
  if (!panel) {
    return;
  }
  const match = panel.classList.value.match(/pfy-panel-(\d+)/);
  if (!match) {
    return;
  }
  const panelInx = parseInt(match[1]) + offset;
  const currPanelClass = `.pfy-panels-wrapper .pfy-panel-${panelInx}`;
  const nextPanel = document.querySelector(currPanelClass);
  if (nextPanel) {
    sessionStorage.setItem(currPanelKey, currPanelClass + '/' + Date.now());
    clearAllPanels();
    nextPanel.classList.add('pfy-panel-open');
  }
} // pfyOpenPanel


document.addEventListener('click', (ev) => {
  const el = ev.target;
  if (el.closest('.pfy-panel-arrow-prev')) {
    pfyOpenPanel(el, -1);
  } else if (el.closest('.pfy-panel-arrow-next')) {
    pfyOpenPanel(el, +1);
  } else if (el.closest('.pfy-panel-header-center')) {
    const targetEl = document.querySelector('.pfy-panel-' + el.dataset.panel);
    if (targetEl) {
      pfyOpenPanel(targetEl, 0);
    }
  }
}); // click


document.addEventListener('DOMContentLoaded', () => {
  const stored = sessionStorage.getItem(currPanelKey);
  if (stored) {
    const parts = stored.split('/');
    const timestamp = parseInt(parts[1]);
    if (timestamp > (Date.now() - 3600000)) {
      const panel = document.querySelector(parts[0]);
      if (panel) {
        clearAllPanels();
        panel.classList.add('pfy-panel-open');
      }
    }
  }
  setTimeout(() => {
    domForAll('.pfy-panel', el => {
      el.style.transitionDuration = '0.25s';
    });
  }, 50);
}); // DOMContentLoaded
