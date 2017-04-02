"use strict";

var PCLL_options = PCLL_options || {};

var PCLL = {

	_ticking: false,

	check: function () {

		if ( PCLL._ticking ) {
			return;
		}

		PCLL._ticking = true;

		if ( 'undefined' == typeof ( PCLL.threshold ) ) {
			if ( 'undefined' != typeof ( PCLL_options.threshold ) ) {
				PCLL.threshold = parseInt( PCLL_options.threshold );
			} else {
				PCLL.threshold = 200;
			}
		}

		var winH = document.documentElement.clientHeight || body.clientHeight;

		var updated = false;

		var els = document.getElementsByClassName('lazy-hidden');
		[].forEach.call( els, function( el, index, array ) {

			var elemRect = el.getBoundingClientRect();

			if ( winH - elemRect.top + PCLL.threshold > 0 ) {
				PCLL.show( el );
				updated = true;
			}

		} );

		PCLL._ticking = false;
		if ( updated ) {
			PCLL.check();
		}
	},

	show: function( el ) {
		el.className = el.className.replace( /(?:^|\s)lazy-hidden(?!\S)/g , '' );
		el.addEventListener( 'load', function() {
			el.className += " lazy-loaded";
			PCLL.customEvent( el, 'lazyloaded' );
		}, false );

		var type = el.getAttribute('data-lazy-type');

		if ( 'image' == type ) {
			el.setAttribute( 'src', el.getAttribute('data-lazy-src') );
			if ( null != el.getAttribute('data-lazy-srcset') ) {
				el.setAttribute( 'srcset', el.getAttribute('data-lazy-srcset') );
			}
		} else if ( 'iframe' == type ) {
			var s = el.getAttribute('data-lazy-src'),
				div = document.createElement('div');

			div.innerHTML = s;
			var iframe = div.firstChild;
			el.parentNode.replaceChild( iframe, el );
		}

	},

	customEvent: function( el, eventName ) {
		var event;

		if ( document.createEvent ) {
			event = document.createEvent( "HTMLEvents" );
			event.initEvent( eventName, true, true );
		} else {
			event = document.createEventObject();
			event.eventType = eventName;
		}

		event.eventName = eventName;

		if ( document.createEvent ) {
			el.dispatchEvent( event );
		} else {
			el.fireEvent( "on" + event.eventType, event );
		}
	}

}

window.addEventListener( 'load', PCLL.check, false );
window.addEventListener( 'scroll', PCLL.check, false );
window.addEventListener( 'resize', PCLL.check, false );
document.getElementsByTagName( 'body' ).item( 0 ).addEventListener( 'post-load', PCLL.check, false );


