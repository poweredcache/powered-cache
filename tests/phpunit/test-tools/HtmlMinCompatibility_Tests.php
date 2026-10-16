<?php
/**
 * HTML minifier compatibility tests.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

use PoweredCache\Dependencies\voku\helper\HtmlMin;

/**
 * HTML minifier compatibility test case.
 */
class HtmlMinCompatibility_Tests extends TestCase {

	/**
	 * HTML minification does not emit deprecation notices.
	 */
	public function test_html_minification_does_not_emit_deprecation_notices() {
		$deprecations = array();

		set_error_handler(
			static function ( $severity, $message ) use ( &$deprecations ) {
				if ( $severity & ( E_DEPRECATED | E_USER_DEPRECATED ) ) {
					$deprecations[] = $message;

					return true;
				}

				return false;
			}
		);

		try {
			$html_min = new HtmlMin();
			$output   = $html_min->minify( '<!doctype html><html><body>  Hello  </body></html>' );
		} finally {
			restore_error_handler();
		}

		$this->assertEmpty( $deprecations );
		$this->assertStringContainsString( 'Hello', $output );
	}
}
