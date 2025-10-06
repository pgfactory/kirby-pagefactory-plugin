// DraggableElement.js

// *** untested ***

class DraggableElement {
  constructor(element) {
    if (!(element instanceof HTMLElement)) {
      throw new Error('The provided argument must be an instance of HTMLElement.');
    }
    this.element = element;
    this.isDragging = false;
    this.offsetX = 0;
    this.offsetY = 0;

    // Bind event handlers to the class instance
    this.handleMouseDown = this.handleMouseDown.bind(this);
    this.handleMouseMove = this.handleMouseMove.bind(this);
    this.handleMouseUp = this.handleMouseUp.bind(this);

    // Add event listeners
    this.element.addEventListener('mousedown', this.handleMouseDown);
    document.addEventListener('mousemove', this.handleMouseMove);
    document.addEventListener('mouseup', this.handleMouseUp);
  }

  setElementPosition(x, y) {
    this.element.style.left = `${x}px`;
    this.element.style.top = `${y}px`;
  }

  handleMouseDown(event) {
    this.isDragging = true;
    const rect = this.element.getBoundingClientRect();
    this.offsetX = event.clientX - rect.left;
    this.offsetY = event.clientY - rect.top;

    // Prevent text selection and other default behaviors
    event.preventDefault();
  }

  handleMouseMove(event) {
    if (!this.isDragging) return;

    const containerRect = this.element.parentElement.getBoundingClientRect();
    let x = event.clientX - this.offsetX;
    let y = event.clientY - this.offsetY;

    // Ensure the element stays within the bounds of its parent container
    x = Math.max(0, Math.min(x, containerRect.width - this.element.offsetWidth));
    y = Math.max(0, Math.min(y, containerRect.height - this.element.offsetHeight));

    this.setElementPosition(x, y);
  }

  handleMouseUp() {
    this.isDragging = false;
  }
}

// Example usage:
// const crosshair = document.getElementById('crosshair');
// new DraggableElement(crosshair);
