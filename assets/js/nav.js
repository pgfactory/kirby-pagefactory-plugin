// PageFactory Nav.js

"use strict";


function PfyNav() {
    this.largeScreenClasses = '';
    this.hoverTimer = [];
}



PfyNav.prototype.init = function() {
    // hide collapsed sub-trees:
    $('li.pfy-has-children > div > ol').css('margin-top', '-10000px');

    if (!$('#pfy').length) {
        alert("Warning: '#pfy'-Id missing within this page \n-> PageFactory's nav() objects not working.");
    }
    this.largeScreenClasses = $('.pfy-primary-nav').attr('class');

    var isSmallScreen = ($(window).width() < screenSizeBreakpoint);
    this.adaptMainMenuToScreenSize( isSmallScreen );

    let parent = this;
    $(window).resize(function() {
        let w = $(this).width();
        let isSmallScreen = (w < screenSizeBreakpoint);
        parent.adaptMainMenuToScreenSize(isSmallScreen);
        parent.setHightOnHiddenElements();
        // mylog('resize window');
    });


    if ($('.pfy-nav-collapsed, .pfy-nav-collapsible, .pfy-nav-top-horizontal').length) {
        this.setHightOnHiddenElements();
    }
    // now make sure that all collapsed links are not focusable:
    $('.pfy-nav [aria-hidden=true] a').attr('tabindex', -1);

    this.initEventHandlers();
    this.setupKeyboardEvents();
    this.openCurrentElement();
}; // init




PfyNav.prototype.initEventHandlers = function() {
    let parent = this;

    // menu button in mobile mode:
    $('#pfy-nav-menu-icon').click(function(e) {
        e.stopPropagation();
        parent.operateMobileMenuPane();
    });


    // mouse:
    $('.pfy-has-children > * > .pfy-nav-arrow').dblclick(function(e) {        // double click -> open all
        e.stopPropagation();
        let $parentLi = $(this).closest('.pfy-has-children');
        parent.toggleAccordion($parentLi, true, true);
        return false;
    });
    $('.pfy-has-children > a > .pfy-nav-arrow').click(function(e) {  // click arrow
        e.stopPropagation();
        let $parentLi = $(this).closest('.pfy-has-children');
        $parentLi.removeClass('pfy-hover');
        let deep = ($parentLi.closest('.pfy-nav-top-horizontal').length !== 0);
        parent.toggleAccordion($parentLi, deep);
        return false;
    });

    // hover:
    $('.pfy-nav-hoveropen .pfy-has-children').hover(
        function() {    // mouseover
            let $this = $(this);
            let tInx = $('> li', $this.parent()).index($this);
            if ((typeof parent.hoverTimer[tInx] !== 'undefined') && (parent.hoverTimer[tInx])) {
                clearTimeout(parent.hoverTimer[tInx]);
            }
            if ($('body').hasClass('touch') || $this.hasClass('pfy-open')) {
                return;
            }
            if ($this.closest('.pfy-nav').hasClass('pfy-nav-top-horizontal')) { // top-nav:
                if ($this.hasClass('pfy-lvl1')) {
                    $this.addClass('pfy-hover');
                    parent.openAccordion($this, true);
                }
            } else {        // side-nav or sitemap
                $this.addClass( 'pfy-hover' );
                parent.openAccordion($this);
            }
        },

        function() {     // mouseout
            let $this = $(this);
            let tInx = $('> li', $this.parent()).index($this);
            if ($('body').hasClass('touch')) {  // no-touch only
                return;
            }
            if ($this.hasClass('pfy-open')) {
                $this.removeClass('pfy-hover');
                return;
            }
            if ($this.closest('.pfy-nav').hasClass('pfy-nav-top-horizontal')) {  // top-nav
                if ($this.hasClass('pfy-lvl1')) {
                    parent.hoverTimer[tInx] = setTimeout(function () {
                        $this.removeClass('pfy-hover');
                        parent.closeAccordion($this, true);
                    }, 400);
                }
            } else {        // side-nav or sitemap
                $this.removeClass( 'pfy-hover' );
                parent.closeAccordion($this);
            }
        }
    );


    // activate animations now (avoiding flicker)
    $('.pfy-nav-animated').each(function() {
        $( this ).removeClass('pfy-nav-animated').addClass('pfy-nav-animation-active');
    });

    if ($('body').hasClass('pfy-small-screen')) {
        this.operateMobileMenuPane( false );
    }

    $('html').on('click', '.touch .pfy-nav-wrapper .pfy-has-children > a', function (event) {
        parent.handleAccordion(this, event);
    });
};




PfyNav.prototype.handleAccordion = function( elem, ev ) {
    if ($( 'body' ).hasClass('touch') || $('html').hasClass('touchevents')) {
        let $parentLi = $(elem).parent();
        if ($parentLi.hasClass('pfy-open')) {
            return true;
        } else {
            this.toggleAccordion($parentLi, true);
            ev.preventDefault();
            return false;
        }
    }
}; // handleAccordion




PfyNav.prototype.toggleAccordion = function( $parentLi, deep, newState ) {
    let expanded = null;
    if (typeof newState === 'undefined') {
        expanded = $parentLi.hasClass('pfy-open');
    } else {
        expanded = !newState;
    }

    if ($parentLi.closest('.pfy-nav').hasClass('pfy-nav-top-horizontal')) {
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




PfyNav.prototype.openAccordion = function( $parentLi, deep, setOpenCls ) {
    if (typeof setOpenCls !== 'undefined') {
        $parentLi.addClass('pfy-open');
    }
    $('> a', $parentLi).attr({'aria-expanded': 'true'});  // make focusable
    if (deep === true) {
        $( 'div', $parentLi ).attr({'aria-hidden': 'false'});        // next div
        if (typeof setOpenCls !== 'undefined') {
            $('.pfy-has-children', $parentLi).addClass('pfy-open');       // parent li
        }
        $('a', $parentLi).attr({'tabindex': ''});  // set aria-expanded and make focusable

    } else {
        $( '> div', $parentLi ).attr({'aria-hidden': 'false'});
        $('> div > ol > li > a', $parentLi).attr({'tabindex': ''});  // make focusable
    }
}; // openAccordion




PfyNav.prototype.closeAccordion = function( $parentLi, deep, setOpenCls ) {    let $nextDiv = $('> div', $parentLi);
    if (deep === true) {
        $nextDiv = $('div', $parentLi);
    }
    $('> a', $parentLi).attr({'aria-expanded': 'false'});  // make focusable
    $('a', $parentLi).attr({'tabindex': ''});  // make focusable
    $nextDiv.attr({'aria-hidden': 'true'});        // next div
    if (typeof setOpenCls !== 'undefined') {
        $parentLi.removeClass('pfy-open');       // parent li
        $('.pfy-open', $parentLi).removeClass('pfy-open');       // parent li
    }
    $('li > a', $parentLi).attr('tabindex', '-1');             // make un-focusable

}; // closeAccordion




PfyNav.prototype.closeAllAccordions = function( $parentLi, setOpenCls ) {
    let $nav = $parentLi.closest('.pfy-nav');
    $('> a', $parentLi).attr({'aria-expanded': 'false'});  // make focusable
    $('a', $parentLi).attr({'tabindex': ''});  // make focusable
    $('.pfy-has-children', $nav).each(function() {
        let $elem = $(this);
        let $nextDivs = $('div', $elem);
        if (typeof setOpenCls !== 'undefined') {
            $elem.removeClass('pfy-open');
            $('li', $elem ).removeClass('pfy-open');            // all li below parent li
        }
        $nextDivs.attr({'aria-hidden':'true' });              // next div
        $('a', $nextDivs).attr('tabindex', '-1');            // make un-focusable
    });
}; // closeAllAccordions




PfyNav.prototype.openCurrentElement = function() {
    $('.pfy-nav-open-current .pfy-active, .pfy-nav-open-current .pfy-curr').each(function () {
        let $parentLi = $( this );
        $parentLi.addClass('pfy-open');
        $( 'div', $parentLi ).attr({'aria-hidden': 'false'});        // next div
        $('a', $parentLi).attr({'tabindex': ''});  // set aria-expanded and make focusable
    });
}; // openCurrentElement




PfyNav.prototype.adaptMainMenuToScreenSize = function( smallScreen ) {
    let parent = this;
    if (smallScreen) {
        $('.pfy-primary-nav')
        // $('.pfy-primary-nav .pfy-nav')
            .removeClass('pfy-nav-top-horizontal pfy-nav-hover pfy-nav-colored pfy-nav-dark-theme pfy-nav-hoveropen')
            .addClass('pfy-nav-vertical pfy-nav-collapsed pfy-nav-open-current');

        if ($('.pfy-nav-small-tree').length) {
            this.openAccordion($('.pfy-primary-nav .pfy-has-children'), true, true); // open all
        } else {
            $('.pfy-primary-nav .pfy-active').each(function() {
                mylog( $(this) , false);
                parent.openAccordion( $(this), false, true );
            });
        }

        scrollIntoView('.pfy-curr', '.pfy-primary-nav');

    } else {
        // restore classes:
        $('.pfy-primary-nav').attr('class', this.largeScreenClasses);
        // $('.pfy-primary-nav .pfy-nav').attr('class', this.largeScreenClasses);
        $('.pfy-primary-nav .pfy-has-children').removeClass('pfy-open');
        $('body').removeClass('pfy-nav-mobile-open');
    }
}; // adaptMainMenuToScreenSize




PfyNav.prototype.operateMobileMenuPane = function( newState ) {
    let $nav = $( '.pfy-nav-wrapper' );
    let expanded = ($nav.attr('aria-expanded') === 'true');
    if (typeof newState !== 'undefined') {
        expanded = !newState;
    }
    if (expanded) {
        $nav.attr('aria-expanded', 'false');
        $('body').removeClass('pfy-nav-mobile-open');
        $('.pfy-primary-nav .pfy-nav a').attr('tabindex', '-1');            // make un-focusable

    } else {
        $nav.attr('aria-expanded', 'true');
        $('body').addClass('pfy-nav-mobile-open');
        let $primaryNav = $('.pfy-primary-nav .pfy-nav');
        $('> ol > li > a', $primaryNav).attr('tabindex', '0');            // make un-focusable
    }
}; // operateMobileMenuPane




PfyNav.prototype.setHightOnHiddenElements = function() {
    $('.pfy-nav-collapsible .pfy-has-children').addClass('pfy-open');

    if (!($('html').hasClass('touchevents') || $('body').hasClass('touch'))) {
        $('#pfy .pfy-nav-top-horizontal .pfy-has-children, ' +
            '#pfy .pfy-nav-collapsible .pfy-has-children, ' +
            '#pfy .pfy-nav-collapsed .pfy-has-children').each(function () {
            let h = $('>div>ol', this).height() + 20 + 'px';                // set height to optimize animation
            $('>div>ol', this).css('margin-top', '-' + h);
        });
    }
}; // setHightOnHiddenElements




PfyNav.prototype.setupKeyboardEvents = function() {
    let parent = this;
    // supports: left/right, up/down, space and home
    $('.pfy-nav a').keydown(function (event) {
        event.stopPropagation();
        let keyCode = event.keyCode;
        let isHorizontal = ($(this).closest('.pfy-nav-vertical').length === 0);
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
                if ($this.parent().hasClass('pfy-lvl1')) {
                    parent.toggleAccordion($this.parent(),false, false);
                } else {
                    $.tabPrev();
                }

            } else if (keyCode === 40) {    // down
                event.preventDefault();
                let expanded = $this.closest('.pfy-lvl1').hasClass('pfy-open');
                if (expanded) { // if open -> close
                    $.tabNext();
                } else {
                    parent.toggleAccordion($this.parent(), false, true);
                }
            }

        } else {
            if (keyCode === 40) {    // down
                event.preventDefault();
                let expanded = $this.closest('.pfy-open').hasClass('pfy-open');
                if (expanded) {
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
                if ($this.parent().hasClass('pfy-lvl1')) {
                    parent.toggleAccordion($this.parent(),false, false);
                } else {
                    $.tabPrev();
                }

            } else if (keyCode === 39) {           // right
                event.preventDefault();
                let expanded = $this.closest('.pfy-lvl1').hasClass('pfy-open');
                if (expanded) { // if open -> close
                    $.tabNext();
                } else {
                    parent.toggleAccordion($this.parent(), false, true);
                }
            }
        }
        if (keyCode === 36) {    // home
            event.preventDefault();
            $('.pfy-lvl1:first-child > a', $this.closest('ol')).focus();

        } else if (keyCode === 35) {    // end
            event.preventDefault();
            $('.pfy-lvl1:last-child > a', $this.closest('ol')).focus();

        } else if (keyCode === 32) {    // space
            event.preventDefault();
            parent.toggleAccordion($this.parent(), true);
        }
    });
}; // setupKeyboardEvents





(function ( $ ) {
    var nav = new PfyNav();
    nav.init();
}( jQuery ));




function focusNextElement( reverse, activeElem ) {
    // check if an element is defined or use activeElement:
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
    } else {
        nextElem = (focusable[thisIndex + 1] );
    }
    $( nextElem ).focus();
} // focusNextElement
