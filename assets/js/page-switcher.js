// Page Switcher

$( document ).ready(function() {

    var prevLink = $('.pfy-previous-page-link a').attr('href');
    var nextLink = $('.pfy-next-page-link a').attr('href');

    // Touch gesture handling:
	if ($('body').hasClass('touch')) {
		console.log('swipe detection activated');

        $('main').hammer().bind("swipeleft swiperight", function(ev) {
		    var overflow = $(ev.gesture.target).css('overflowX'); // bug: returns value of 'overflow', not 'overflowX'
            // if ((overflow == 'auto') || (overflow == 'scroll')) { // -> due to bug: not checking for 'auto'
            if (overflow === 'scroll') {
                console.log('page switching suppressed: was over scrolling element');
		        return;
            }
			if ((typeof prevLink !== 'undefined') && prevLink && (ev.type === 'swiperight')) {
                $( 'body' ).addClass('pfy-dimmed');
                window.location = prevLink;
			}
			if ((typeof nextLink !== 'undefined') && nextLink && (ev.type === 'swipeleft')) {
                $( 'body' ).addClass('pfy-dimmed');
                window.location = nextLink;
			}
		});
	}

	// Key handling:
    $( 'body' ).keydown( function (e) {
        if (isProtectedTarget()) {
            return document.defaultAction;
        }

        var keycode = e.key;

        // Standard arrow key handling:
        if ((keycode === 37) || (keycode === 33)) {	// left or pgup
            if (typeof prevLink !== 'undefined') {
                console.log('prevLink: ' + prevLink);
                e.preventDefault();
                window.location = prevLink;
                return false;
            } else {
                console.log('Error: prevLink is not defined');
            }
        }
        if ((keycode === 39) || (keycode === 34)) {	// right or pgdown
            if (typeof nextLink !== 'undefined') {
            console.log('nextLink: '+nextLink);
            e.preventDefault();
            window.location = nextLink;
            return false;
            } else {
                console.log('Error: nextLink is not defined');
            }
        }
        if (keycode === 115) {
            if (typeof simplemde === 'undefined') {	// F4 -> start editing mode
                e.preventDefault();
                window.location = '?edit';
                return false;
            }
        }
        return document.defaultAction;
    });
});




function isProtectedTarget()
{
    // Exceptions, where arrow keys should NOT switch page:
    if ($( document.activeElement ).closest('form').length ||	        // Focus within form field
        $( document.activeElement ).closest('input').length ||	        // Focus within input field
        $('.inhibitPageSwitch').length  ||				                // class .inhibitPageSwitch found
        $('.pfy-presentation-support').length  ||				                // class .pfy-presentation-support found
        ($('.ug-lightbox').length &&
            ($('.ug-lightbox').css('display') !== 'none')) ||            // special case: ug-album in full screen mode
        $( document.activeElement ).closest('.pfy-panels-widget').length	// Focus within pfy-panels-widget field
    )
    {
        // console.log('skipping page-switcher');
        return true;
    }
    return false;
}
