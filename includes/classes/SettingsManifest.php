<?php
/**
 * Settings schema manifest.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Builds an API/UI friendly settings schema manifest.
 *
 * @since 4.0.0
 */
class SettingsManifest {

	const FORMAT         = 'powered-cache-settings-manifest';
	const FORMAT_VERSION = '1.0';

	/**
	 * Build a settings manifest from the current schema.
	 *
	 * @param array $context Runtime context used by dynamic defaults.
	 * @param bool  $include_deprecated Whether deprecated fields should be included.
	 *
	 * @return array
	 */
	public static function build( array $context = array(), $include_deprecated = true ) {
		return array(
			'format'         => self::FORMAT,
			'format_version' => self::FORMAT_VERSION,
			'plugin_version' => defined( 'POWERED_CACHE_VERSION' ) ? POWERED_CACHE_VERSION : '',
			'sections'       => self::sections(),
			'fields'         => self::fields( $context, $include_deprecated ),
		);
	}

	/**
	 * Return ordered settings sections.
	 *
	 * @return array
	 */
	public static function sections() {
		return array(
			'cache'             => array(
				'label' => 'Cache',
				'order' => 10,
			),
			'advanced'          => array(
				'label' => 'Advanced',
				'order' => 20,
			),
			'file_optimization' => array(
				'label' => 'File Optimization',
				'order' => 30,
			),
			'media'             => array(
				'label' => 'Media',
				'order' => 40,
			),
			'cdn'               => array(
				'label' => 'CDN',
				'order' => 50,
			),
			'preload'           => array(
				'label' => 'Preload',
				'order' => 60,
			),
			'database'          => array(
				'label' => 'Database',
				'order' => 70,
			),
			'integrations'      => array(
				'label' => 'Integrations',
				'order' => 80,
			),
			'misc'              => array(
				'label' => 'Misc',
				'order' => 90,
			),
		);
	}

	/**
	 * Return manifest fields keyed by setting name.
	 *
	 * @param array $context Runtime context used by dynamic defaults.
	 * @param bool  $include_deprecated Whether deprecated fields should be included.
	 *
	 * @return array
	 */
	public static function fields( array $context = array(), $include_deprecated = true ) {
		$fields = array();

		foreach ( SettingsSchema::fields( $context ) as $key => $field ) {
			if ( ! $include_deprecated && ! empty( $field['deprecated'] ) ) {
				continue;
			}

			$fields[ $key ] = self::field( $key, $field );
		}

		return $fields;
	}

	/**
	 * Return one manifest field.
	 *
	 * @param string $key Setting key.
	 * @param array  $field Schema field.
	 *
	 * @return array
	 */
	private static function field( $key, array $field ) {
		return array(
			'key'          => $key,
			'type'         => $field['type'],
			'default'      => $field['default'],
			'section'      => $field['section'],
			'sanitizer'    => $field['sanitizer'],
			'premium'      => (bool) $field['premium'],
			'dependencies' => array_values( $field['dependencies'] ),
			'enum'         => array_values( $field['enum'] ),
			'deprecated'   => (bool) $field['deprecated'],
		);
	}
}
