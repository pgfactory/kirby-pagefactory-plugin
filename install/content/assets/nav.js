// Lizzy Nav.js

"use strict";


function LzyNav() {
    this.largeScreenClasses = '';
    this.hoverTimer = [];
}



LzyNav.prototype.init = function() {
    if (!$('#lzy').length) {
        alert("Warning: '#lzy'-Id missing within this page \n-> Lizzy's nav() objects not working.");
    }
    this.largeScreenClasses = $('.lzy-primary-nav .lzy-nav').attr('class');

    var isSmallScreen = ($(window).width() < screenSizeBreakpoint);
    this.adaptMainMenuToScreenSize( isSmallScreen );

    let parent = this;
    $(window).resize(function(){
        let w = $(this).width();
        let isSmallScreen = (w < screenSizeBreakpoint);
        parent.adaptMainMenuToScreenSize(isSmallScreen);
        parent.setHightOnHiddenElements();
    });


    if ($('.lzy-nav-collapsed, .lzy-nav-collapsible, .lzy-nav-top-horizontal').length) {
        this.setHightOnHiddenElements();
    }
    // now make sure that all collapsed links are not focussable:
    $('.lzy-nav [aria-hidden=true] a').attr('tabindex', -1);

    this.initEventHandlers();
    this.setupKeyboardEvents();
    this.openCurrentElement();
}; // init




LzyNav.prototype.initEventHandlers = function() {
    let parent = this;

    // menu button in mobile mode:
    $('#lzy-nav-menu-icon').click(function(e) {
        e.stopPropagation();
        parent.operateMobileMenuPane();
    });


    // mouse:
    $('.lzy-has-children > * > .lzy-nav-arrow').dblclick(function(e) {        // double click -> open all
        e.stopPropagation();
        let $parentLi = $(this).closest('.lzy-has-children');
        parent.toggleAccordion($parentLi, true, true);
        return false;
    });
    $('.lzy-has-children > a > .lzy-nav-arrow').click(function(e) {  // click arrow
        e.stopPropagation();
        let $parentLi = $(this).closest('.lzy-has-children');
        $parentLi.removeClass('lzy-hover');
        let deep = ($parentLi.closest('.lzy-nav-top-horizontal').length !== 0);
        parent.toggleAccordion($parentLi, deep);
        return false;
    });

    // hover:
    $('.lzy-nav-hoveropen .lzy-has-children').hover(
        function() {    // mouseover
            let $this = $(this);
            let tInx = $('> li', $this.parent()).index($this);
            if ((typeof parent.hoverTimer[tInx] !== 'undefined') && (parent.hoverTimer[tInx])) {
                clearTimeout(parent.hoverTimer[tInx]);
            }
            if ($('body').hasClass('touch') || $this.hasClass('lzy-open')) {
                return;
            }
            if ($this.closest('.lzy-nav').hasClass('lzy-nav-top-horizontal')) { // top-nav:
                if ($this.hasClass('lzy-lvl1')) {
                    $this.addClass('lzy-hover');
                    parent.openAccordion($this, true);
                }
            } else {        // side-nav or sitemap
                $this.addClass( 'lzy-hover' );
                parent.openAccordion($this);
            }
        },

        function() {     // mouseout
            let $this = $(this);
            let tInx = $('> li', $this.parent()).index($this);
            if ($('body').hasClass('touch')) {  // no-touch only
                return;
            }
            if ($this.hasClass('lzy-open')) {
                $this.removeClass('lzy-hover');
                return;
            }
            if ($this.closest('.lzy-nav').hasClass('lzy-nav-top-horizontal')) {  // top-nav
                if ($this.hasClass('lzy-lvl1')) {
                    parent.hoverTimer[tInx] = setTimeout(function () {
                        $this.removeClass('lzy-hover');
                        parent.closeAccordion($this, true);
                    }, 400);
                }
            } else {        // side-nav or sitemap
                $this.removeClass( 'lzy-hover' );
                parent.closeAccordion($this);
            }
        }
    );


    // activate animations now (avoiding flicker)
    $('.lzy-nav-animated').each(function() {
        $( this ).removeClass('lzy-nav-animated').addClass('lzy-nav-animation-active');
    });

    if ($('body').hasClass('lzy-small-screen')) {
        this.operateMobileMenuPane( false );
    }

    $('html').on('click', '.touch .lzy-nav-wrapper .lzy-has-children > a', function (event) {
        parent.handleAccordion(this, event);
    });
};




LzyNav.prototype.handleAccordion = function( elem, ev ) {
    if ($( 'body' ).hasClass('touch') || $('html').hasClass('touchevents')) {
        let $parentLi = $(elem).parent();
        if ($parentLi.hasClass('lzy-open')) {
            return true;
        } else {
            this.toggleAccordion($parentLi, true);
            ev.preventDefault();
            return false;
        }
    }
}; // handleAccordion




LzyNav.prototype.toggleAccordion = function( $parentLi, deep, newState ) {
    let expanded = null;
    if (typeof newState === 'undefined') {
        expanded = $parentLi.hasClass('lzy-open');
    } else {
        expanded = !newState;
    }

    if ($parentLi.closest('.lzy-nav').hasClass('lzy-nav-top-horizontal')) {
        this.closeAllAccordions($parentLi, deep, true);

    } else if (expanded) { // -> close
        this.closeAccordion($parentLi, deep, true);
    }
    if (!expanded) { // -> open
        this.openAccordion($parentLi, deep, true);
        return true;
    }
    return false;
}; // toggleAccordion




LzyNav.prototype.openAccordion = function( $parentLi, deep, setOpenCls ) {
    if (typeof setOpenCls !== 'undefined') {
        $parentLi.addClass('lzy-open');
    }
    $('> a', $parentLi).attr({'aria-expanded': 'true'});  // make focusable
    if (deep === true) {
        $( 'div', $parentLi ).attr({'aria-hidden': 'false'});        // next div
        if (typeof setOpenCls !== 'undefined') {
            $('.lzy-has-children', $parentLi).addClass('lzy-open');       // parent li
        }
        $('a', $parentLi).attr({'tabindex': ''});  // set aria-expanded and make focusable

    } else {
        $( '> div', $parentLi ).attr({'aria-hidden': 'false'});
        $('> div > ol > li > a', $parentLi).attr({'tabindex': ''});  // make focusable
    }
}; // openAccordion




LzyNav.prototype.closeAccordion = function( $parentLi, deep, setOpenCls ) {    let $nextDiv = $('> div', $parentLi);
    if (deep === true) {
        $nextDiv = $('div', $parentLi);
    }
    $('> a', $parentLi).attr({'aria-expanded': 'false'});  // make focusable
    $('a', $parentLi).attr({'tabindex': ''});  // make focusable
    $nextDiv.attr({'aria-hidden': 'true'});        // next div
    if (typeof setOpenCls !== 'undefined') {
        $parentLi.removeClass('lzy-open');       // parent li
        $('.lzy-open', $parentLi).removeClass('lzy-open');       // parent li
    }
    $('li > a', $parentLi).attr('tabindex', '-1');             // make un-focusable

}; // closeAccordion




LzyNav.prototype.closeAllAccordions = function( $parentLi, setOpenCls ) {
    let $nav = $parentLi.closest('.lzy-nav');
    $('> a', $parentLi).attr({'aria-expanded': 'false'});  // make focusable
    $('a', $parentLi).attr({'tabindex': ''});  // make focusable
    $('.lzy-has-children', $nav).each(function() {
        let $elem = $(this);
        let $nextDivs = $('div', $elem);
        if (typeof setOpenCls !== 'undefined') {
            $elem.removeClass('lzy-open');
            $('li', $elem ).removeClass('lzy-open');            // all li below parent li
        }
        $nextDivs.attr({'aria-hidden':'true' });              // next div
        $('a', $nextDivs).attr('tabindex', '-1');            // make un-focusable
    });
}; // closeAllAccordions




LzyNav.prototype.openCurrentElement = function() {
    $('.lzy-nav.lzy-nav-open-current .lzy-active').each(function () {
        let $parentLi = $( this );
        $parentLi.addClass('lzy-open');
        $( 'div', $parentLi ).attr({'aria-hidden': 'false'});        // next div
        $('a', $parentLi).attr({'tabindex': ''});  // set aria-expanded and make focusable
    });
}; // openCurrentElement




LzyNav.prototype.adaptMainMenuToScreenSize = function( smallScreen ) {
    let parent = this;
    if (smallScreen) {
        $('.lzy-primary-nav .lzy-nav')
            .removeClass('lzy-nav-top-horizontal lzy-nav-hover lzy-nav-colored lzy-nav-dark-theme lzy-nav-hoveropen')
            .addClass('lzy-nav-vertical lzy-nav-collapsed lzy-nav-open-current');

        if ($('.lzy-nav-small-tree').length) {
            this.openAccordion($('.lzy-primary-nav .lzy-has-children'), true, true); // open all
        } else {
            $('.lzy-primary-nav .lzy-active').each(function() {
                mylog( $(this) , false);
                parent.openAccordion( $(this), false, true );
            });
        }

    } else {
        // restore classes:
        $('.lzy-primary-nav .lzy-nav').attr('class', this.largeScreenClasses);
        $('.lzy-primary-nav .lzy-has-children').removeClass('lzy-open');
        $('body').removeClass('lzy-nav-mobile-open');
    }
}; // adaptMainMenuToScreenSize




LzyNav.prototype.operateMobileMenuPane = function( newState ) {
    let $nav = $( '.lzy-nav-wrapper' );
    let expanded = ($nav.attr('aria-expanded') === 'true');
    if (typeof newState !== 'undefined') {
        expanded = !newState;
    }
    if (expanded) {
        $nav.attr('aria-expanded', 'false');
        $('body').removeClass('lzy-nav-mobile-open');
        $('.lzy-primary-nav .lzy-nav a').attr('tabindex', '-1');            // make un-focusable

    } else {
        $nav.attr('aria-expanded', 'true');
        $('body').addClass('lzy-nav-mobile-open');
        let $primaryNav = $('.lzy-primary-nav .lzy-nav');
        $('> ol > li > a', $primaryNav).attr('tabindex', '0');            // make un-focusable
    }
}; // operateMobileMenuPane




LzyNav.prototype.setHightOnHiddenElements = function() {
    if (!($('html').hasClass('touchevents') || $('body').hasClass('touch'))) {
        $('#lzy .lzy-nav-accordion .lzy-has-children, ' +
            '#lzy .lzy-nav-top-horizontal .lzy-has-children, ' +
            '#lzy .lzy-nav-collapsed .lzy-has-children, ' +
            '#lzy .lzy-nav-collapsible .lzy-has-children').each(function () {
            let h = $('>div>ol', this).height() + 20 + 'px';                // set height to optimize animation
            $('>div>ol', this).css('margin-top', '-' + h);
        });
    }
}; // setHightOnHiddenElements




LzyNav.prototype.setupKeyboardEvents = function() {
    let parent = this;
    // supports: left/right, up/down, space and home
    $('.lzy-nav a').keydown(function (event) {
        event.stopPropagation();
        let keyCode = event.keyCode;
        let isHorizontal = ($(this).closest('.lzy-nav-vertical').length === 0);
        let $this = $(this);

        if (isHorizontal) {
            if (keyCode === 39) {           // right
                event.preventDefault();
                $('> a',$this.parent().next()).focus();

            } else if (keyCode === 37) {    // left
                event.preventDefault();
                $('> a',$this.parent().prev()).focus();

            } else if (keyCode === 38) {    // up
                event.preventDefault();
                if ($this.parent().hasClass('lzy-lvl1')) {
                    parent.toggleAccordion($this.parent(),false, false);
                } else {
                    $.tabPrev();
                }

            } else if (keyCode === 40) {    // down
                event.preventDefault();
                let expanded = $this.closest('.lzy-lvl1').hasClass('lzy-open');
                if (expanded) { // if open -> close
                    $.tabNext();
                } else {
                    parent.toggleAccordion($this.parent(), false, true);
                }
            }

        } else {
            if (keyCode === 40) {    // down
                event.preventDefault();
                let expanded = $this.closest('.lzy-open').hasClass('lzy-open');
                if (expanded) {
                    let $l = $this;
                    console.log($l);
                    if ( $this.parent().is(':last-child') ) {
                        mylog('last-child');
                    }
                    $.tabNext();
                } else {
                    $('> a', $this.parent().next()).focus();
                }

            } else if (keyCode === 38) {    // up
                event.preventDefault();
                $('> a',$this.parent().prev()).focus();

            } else if (keyCode === 37) {    // left
                event.preventDefault();
                if ($this.parent().hasClass('lzy-lvl1')) {
                    parent.toggleAccordion($this.parent(),false, false);
                } else {
                    $.tabPrev();
                }

            } else if (keyCode === 39) {           // right
                event.preventDefault();
                let expanded = $this.closest('.lzy-lvl1').hasClass('lzy-open');
                if (expanded) { // if open -> close
                    $.tabNext();
                } else {
                    parent.toggleAccordion($this.parent(), false, true);
                }
            }
        }
        if (keyCode === 36) {    // home
            event.preventDefault();
            $('.lzy-lvl1:first-child > a', $this.closest('ol')).focus();

        } else if (keyCode === 35) {    // end
            event.preventDefault();
            $('.lzy-lvl1:last-child > a', $this.closest('ol')).focus();

        } else if (keyCode === 32) {    // space
            event.preventDefault();
            parent.toggleAccordion($this.parent(), true);
        }
    });
}; // setupKeyboardEvents





(function ( $ ) {
    var nav = new LzyNav();
    nav.init();
}( jQuery ));




function focusNextElement( reverse, activeElem ) {
    /*check if an element is defined or use activeElement*/
    activeElem = activeElem instanceof HTMLElement ? activeElem : document.activeElement;

    let queryString = [
            'a:not([disabled]):not([tabindex="-1"])',
            'button:not([disabled]):not([tabindex="-1"])',
            'input:not([disabled]):not([tabindex="-1"])',
            'select:not([disabled]):not([tabindex="-1"])',
            '[tabindex]:not([disabled]):not([tabindex="-1"])'
            /* add custom queries here */
        ].join(','),
        queryResult = Array.prototype.filter.call(document.querySelectorAll(queryString), elem => {
            /*check for visibility while always include the current activeElement*/
            return elem.offsetWidth > 0 || elem.offsetHeight > 0 || elem === activeElem;
        }),
        indexedList = queryResult.slice().filter(elem => {
            /* filter out all indexes not greater than 0 */
            return elem.tabIndex == 0 || elem.tabIndex == -1 ? false : true;
        }).sort((a, b) => {
            /* sort the array by index from smallest to largest */
            return a.tabIndex != 0 && b.tabIndex != 0
                ? (a.tabIndex < b.tabIndex ? -1 : b.tabIndex < a.tabIndex ? 1 : 0)
                : a.tabIndex != 0 ? -1 : b.tabIndex != 0 ? 1 : 0;
        }),
        focusable = [].concat(indexedList, queryResult.filter(elem => {
            /* filter out all indexes above 0 */
            return elem.tabIndex == 0 || elem.tabIndex == -1 ? true : false;
        }));

    let thisIndex = focusable.indexOf(activeElem);
    let nextElem = null;
    if (reverse) {
        nextElem = (focusable[thisIndex - 1]);
        // nextElem = (focusable[thisIndex - 1] || focusable[focusable.length - 1]);
    } else {
        nextElem = (focusable[thisIndex + 1] );
        // nextElem = (focusable[thisIndex + 1] || focusable[0]);
    }
    $( nextElem ).focus();
    /* if reverse is true return the previous focusable element
       if reverse is false return the next focusable element */
    // return reverse ? (focusable[focusable.indexOf(activeElem) - 1] || focusable[focusable.length - 1])
    //     : (focusable[focusable.indexOf(activeElem) + 1] || focusable[0]);
} // focusNextElement