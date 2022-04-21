/* eslint-disable no-unused-vars, radix */
// eslint-disable-next-line no-use-before-define
// eslint-disable-next-line camelcase

'use strict';

var PCLL_options = PCLL_options || {};

var PCLL = ( function() {
	var PCLL = {

		_lastCheckTs: 0,
		_checkDebounceTimeoutRunning: false,

		init: function() {
			PCLL.threshold = PCLL.getOptionIntValue( 'threshold', 200 );
			PCLL.recheckDelay = PCLL.getOptionIntValue( 'recheck_delay', 250 );
			PCLL.debounce = PCLL.getOptionIntValue( 'debounce', 50 );
			PCLL.checkRecurring();
			return PCLL;
		},

		check: function( fromDebounceTimeout ) {
			var tstamp, winH, updated, els;
			if ( true === fromDebounceTimeout ) {
				PCLL._checkDebounceTimeoutRunning = false;
			}
			tstamp = performance.now();
			if ( tstamp < PCLL._lastCheckTs + PCLL.debounce ) {
				if ( ! PCLL._checkDebounceTimeoutRunning ) {
					PCLL._checkDebounceTimeoutRunning = true;
					setTimeout( function() {
						PCLL.check( true );
					}, PCLL.debounce );
				}
				return;
			}
			PCLL._lastCheckTs = tstamp;

			winH = document.documentElement.clientHeight || body.clientHeight;
			updated = false;
			els = document.getElementsByClassName( 'lazy-hidden' );

			[].forEach.call( els, function( el, index, array ) {
				var elemRect = el.getBoundingClientRect();

				// do not lazy-load images that are hidden with display:none or have a width/height of 0
				if ( ! elemRect.width || ! elemRect.height ) {
					return;
				}

				if ( 0 < winH - elemRect.top + PCLL.threshold ) {
					PCLL.show( el );
					updated = true;
				}
			});

			if ( updated ) {
				PCLL.check();
			}
		},

		checkRecurring: function() {
			PCLL.check();
			setTimeout( PCLL.checkRecurring, PCLL.recheckDelay );
		},

		show: function( el ) {
			var type, s, div, iframe;
			el.className = el.className.replace( /(?:^|\s)lazy-hidden(?!\S)/g, '' );
			el.addEventListener( 'load', function() {
				el.className += ' lazy-loaded';
				PCLL.customEvent( el, 'lazyloaded' );
			}, false );

			type = el.getAttribute( 'data-lazy-type' );

			if ( 'image' == type ) {
				if ( null != el.getAttribute( 'data-lazy-srcset' ) ) {
					el.setAttribute( 'srcset', el.getAttribute( 'data-lazy-srcset' ) );
				}
				if ( null != el.getAttribute( 'data-lazy-sizes' ) ) {
					el.setAttribute( 'sizes', el.getAttribute( 'data-lazy-sizes' ) );
				}
				el.setAttribute( 'src', el.getAttribute( 'data-lazy-src' ) );
			} else if ( 'iframe' == type ) {
				s = el.getAttribute( 'data-lazy-src' );
				div = document.createElement( 'div' );

				div.innerHTML = s;
				iframe = div.firstChild;
				el.parentNode.replaceChild( iframe, el );
			}
		},

		customEvent: function( el, eventName ) {
			var event;

			if ( document.createEvent ) {
				event = document.createEvent( 'HTMLEvents' );
				event.initEvent( eventName, true, true );
			} else {
				event = document.createEventObject();
				event.eventType = eventName;
			}

			event.eventName = eventName;

			if ( document.createEvent ) {
				el.dispatchEvent( event );
			} else {
				el.fireEvent( 'on' + event.eventType, event );
			}
		},

		getOptionIntValue: function( name, defaultValue ) {
			// eslint-disable-next-line camelcase
			if ( 'undefined' !== typeof ( PCLL_options[name]) ) {
				// eslint-disable-next-line camelcase
				return parseInt( PCLL_options[name]);
			}
			return defaultValue;
		}
	};
	return PCLL.init();
}() );

window.addEventListener( 'load', PCLL.check, false );
window.addEventListener( 'scroll', PCLL.check, false );
window.addEventListener( 'resize', PCLL.check, false );
document.getElementsByTagName( 'body' ).item( 0 ).addEventListener( 'post-load', PCLL.check, false );