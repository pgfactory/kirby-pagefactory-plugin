

// DragImageCrosshair
//  -> used by kernburns.js

class DragImageCrosshair {
  constructor(element) {
    if (!(element instanceof HTMLElement)) {
      throw new Error('The provided argument must be an instance of HTMLElement.');
    }
    console.log('DragImageCrosshair', element);
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
  } // constructor

  handleMouseDown(event) {
    this.isDragging = true;
    this.offsetX = event.clientX - parseInt(this.element.style.left);
    this.offsetY = event.clientY - parseInt(this.element.style.top);

    // Prevent text selection and other default behaviors
    event.preventDefault();
  } // handleMouseDown

  handleMouseMove(event) {
    if (!this.isDragging) return;

    let x = event.clientX - this.offsetX;
    let y = event.clientY - this.offsetY;

    // Ensure the element stays within the bounds of its parent container
    const wrapper = this.element.parentElement;
    const width = parseInt(wrapper.clientWidth);
    const height = parseInt(wrapper.clientHeight);
    x = Math.max(0, Math.min(x, width));
    y = Math.max(0, Math.min(y, height));

    this.setElementPosition(x, y);
  } // handleMouseMove

  setElementPosition(x, y) {
    this.element.style.left = `${x}px`;
    this.element.style.top = `${y}px`;
  } // setElementPosition


  handleMouseUp() {
    this.isDragging = false;
    const el = this.element;
    const wrapper = el.parentElement;
    const width = wrapper.clientWidth;
    const height = wrapper.clientHeight;
    const x = parseInt(el.style.left);
    const y = parseInt(el.style.top);
    console.log(`Pos: ${(x/width*100).toFixed(0)}%, ${(y/height*100).toFixed(0)}%`);
  } // handleMouseUp
} // DragImageCrosshair

// Example usage:
// const crosshair = document.getElementById('crosshair');
// new DragImageCrosshair(crosshair);
