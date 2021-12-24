// reveal.js

// plug-in to select all focusable elements (https://coderwall.com/p/jqsanw/jquery-find-every-focusable-elements)
// Usage: $('#my-container').find(':focusable')
jQuery.extend(jQuery.expr[':'], {
    focusable: function(el, index, selector){
        return $(el).is('a, button, :input, [tabindex]');
    }
});

// init:
$('.lzy-reveal-controller-elem').each(function() {
	const $this = $( this );
	let $target = null;

	if ($this.prop('tagName') === 'SELECT') {		// case dropdown:
		$('[data-reveal-target]', $this).each(function () {
			$target = $( $(this).attr('data-reveal-target') );
			if (!$target.parent().hasClass('lzy-reveal-container')) {
				$target.wrap("<div class='lzy-reveal-container'></div>").show();
				$target.css('margin-top', '-10000px');
			}
			if ( this.selected ) {
				$target.parent().addClass('lzy-elem-revealed');
				$(this).attr('aria-expanded', 'true');
			} else {
				$(this).attr('aria-expanded', 'false');
			}
		});

	} else {											// case radio and checkbox:
		const targetSel = $this.attr('data-reveal-target');
		$target = $( targetSel );
		if ( !$target.length ) {
			$target = $( $('[data-reveal-target]', $this).attr('data-reveal-target') );
		}
		if (!$target.parent().hasClass('lzy-reveal-container')) {
			$target.wrap("<div class='lzy-reveal-container'></div>").show();
			$target.css('margin-top', '-10000px');
		}
		if (this.checked) {
			$this.attr('aria-expanded', 'true');
			$target.parent().addClass('lzy-elem-revealed');
		} else {
			$this.attr('aria-expanded', 'false');
		}
	}

	let $revealContainer = $target.closest('.lzy-reveal-container');
	$revealContainer.find(':focusable').each(function (){
		let $el = $(this);
		let tabindex = $el.attr('tabindex');
		if (typeof tabindex === 'undefined') {
			tabindex = 0;
		}
		$el.addClass('lzy-focus-disabled').attr('tabindex', -1).data('tabindex', tabindex);
	});
}); // init



// initialize target height:
$('.lzy-reveal-container').each(function() {
	const $revealContainer = $(this);
	let $target = $('> div', $revealContainer);
	const boundingBox = $target[0].getBoundingClientRect();
	const marginTop = (-100 - Math.round(boundingBox.height)) + 'px'; // incl. some safety margin
	$target.css({ transition: 'margin-top 0', marginTop: marginTop });
	$revealContainer.hide();
});



// setup triggers:
$('body').on('change', '.lzy-reveal-controller-elem', function(e) {
	lzyOperateRevealPanel( this );
});
$('body').on('click', '.lzy-reveal-controller-elem', function(e) {
	e.stopImmediatePropagation();
	e.stopPropagation();
});



function lzyOperateRevealPanel( that )
{
	const $revealController = $( that );
	let type = false;
	let $target = null;

	if ($revealController.prop('tagName') === 'SELECT') {				// case dropdown:
		type = 'dropdown';
		$target = $( $( ':selected', $revealController ).attr('data-reveal-target') );

	} else {											// case radio and checkbox:
		type = $revealController[0].type;
		$target = $( $revealController.attr('data-reveal-target') );
	}

	if ( type === 'dropdown') {							// case select:
		$('[data-reveal-target]', $revealController).each(function () {
			$( $(this).attr('data-reveal-target') ).parent().removeClass('lzy-elem-revealed');
			$(this).attr('aria-expanded', 'false');
		});

		// open selected:
		$target.parent().addClass('lzy-elem-revealed');
		$revealController.attr('aria-expanded', 'true');
		return;

	} else if (type === 'radio') { 						// case radio: close all others
		$revealController.parent().siblings().each(function() {
			const $revealController1 = $('.lzy-reveal-controller-elem', $( this ));
			const $target1 = $( $revealController1.attr('data-reveal-target') );
			const $container1 = $target1.parent();
			$revealController1.attr('aria-expanded', 'false');
			$container1.removeClass('lzy-elem-revealed');
		});

	}

	// now operate:
	const $revealContainer = $target.closest('.lzy-reveal-container');
	$target.css({ transition: 'margin-top 0.3s' });

	if ( !$revealContainer.hasClass('lzy-elem-revealed') ) { // open:
		$revealContainer.show();
		setTimeout(function () {
			$revealController.attr('aria-expanded', 'true');
			$target.parent().addClass('lzy-elem-revealed');
		}, 30);

		// ensable all focusable elements inside reveal-container:
		$('.lzy-focus-disabled', $revealContainer).each(function () {
			let $el = $( this );
			let tabindex = $el.data('tabindex');
			if ((typeof tabindex !== 'number') || (tabindex < 0)) {
				tabindex = 0;
			}
			$el.attr('tabindex', tabindex);
		});
		if ($('textarea', $revealContainer).length) {
			setTimeout(function () {
				$('textarea', $revealContainer).focus();
			}, 500);
		}

	} else { // close:
		const boundingBox = $target[0].getBoundingClientRect();
		const marginTop = (-30 - Math.round(boundingBox.height)) + 'px';
		$target.css({ marginTop: marginTop });

		$revealController.attr('aria-expanded', 'false');
		$target.parent().removeClass('lzy-elem-revealed');

		// disable all focusable elements inside reveal-container:
		$('.lzy-focus-disabled', $revealContainer).each(function () {
			let $this = $( this );
			let tabindex = $this.attr('tabindex');
			if ((typeof tabindex !== 'number') || (tabindex < 0)) {
				tabindex = 0;
			}
			$this.data('tabindex', tabindex);
			$this.attr('tabindex', -1);
		});
		setTimeout(function () {
			$revealContainer.hide();
		}, 300);
	}
} // lzyOperateRevealPanel


