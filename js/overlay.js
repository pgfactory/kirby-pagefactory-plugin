
// close overlay:
document.addEventListener("keyup", (e) => {
    if (e.key === "Escape") {
        closeOverlay();
    }
});
document.addEventListener('click', function(e) {
    if (e.target.classList.value.includes('lzy-close-overlay')) {
        closeOverlay();
    }
});

function closeOverlay() {
    let overlay = document.querySelector('.lzy-overlay');
    overlay.style.display = "none";
}
