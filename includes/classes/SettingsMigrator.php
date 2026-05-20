<?php
/**
 * Settings migrator.
 *
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Migrates legacy settings into the current 4.0 setting shape.
 *
 * @since 4.0.0
 */
class SettingsMigrator {

	const TARGET_VERSION = '4.0.0';

	/**
	 * Return the migration target version.
	 *
	 * @return string
	 */
	public function target_version() {
		return self::TARGET_VERSION;
	}

	/**
	 * Return known migration steps.
	 *
	 * The steps are descriptive metadata for tooling, tests, and future upgrade
	 * screens. Runtime migration stays non-destructive unless explicitly asked
	 * to drop deprecated keys.
	 *
	 * @return array
	 */
	public function steps() {
		return array(
			'accepted_query_strings' => array(
				'id'              => 'accepted_query_strings',
				'since'           => '3.0.0',
				'source_keys'     => array( 'accepted_query_strings' ),
				'target_keys'     => array( 'ignored_query_strings' ),
				'preserve_source' => true,
			),
			'js_execution_method'    => array(
				'id'              => 'js_execution_method',
				'since'           => '3.2.0',
				'source_keys'     => array( 'js_execution_method' ),
				'target_keys'     => array( 'js_defer', 'js_delay', 'combine_js' ),
				'preserve_source' => true,
			),
		);
	}

	/**
	 * Return migration steps that apply to a settings payload.
	 *
	 * @param array $settings Raw settings.
	 *
	 * @return array
	 */
	public function applicable_steps( array $settings ) {
		$applicable = array();

		foreach ( $this->steps() as $id => $step ) {
			foreach ( $step['source_keys'] as $source_key ) {
				if ( ! empty( $settings[ $source_key ] ) ) {
					$applicable[ $id ] = $step;
					break;
				}
			}
		}

		return $applicable;
	}

	/**
	 * Migrate known legacy settings.
	 *
	 * @param array $settings Raw settings.
	 * @param bool  $drop_deprecated Whether deprecated keys should be removed.
	 *
	 * @return array
	 */
	public function migrate( array $settings, $drop_deprecated = false ) {
		$settings = $this->migrate_accepted_query_strings( $settings );
		$settings = $this->migrate_js_execution_method( $settings );

		if ( $drop_deprecated ) {
			$settings = $this->drop_deprecated_keys( $settings );
		}

		return $settings;
	}

	/**
	 * Prepare settings for current storage.
	 *
	 * Storage migration is intentionally non-destructive so existing 3.x
	 * installs, rollbacks, and integrations keep legacy keys after saving from
	 * the 4.0 UI.
	 *
	 * @param array $settings Settings payload.
	 *
	 * @return array
	 */
	public function for_storage( array $settings ) {
		return $this->migrate( $settings );
	}

	/**
	 * Return deprecated schema keys.
	 *
	 * @return array
	 */
	public function deprecated_keys() {
		$deprecated = array();

		foreach ( SettingsSchema::fields() as $key => $field ) {
			if ( ! empty( $field['deprecated'] ) ) {
				$deprecated[] = $key;
			}
		}

		return $deprecated;
	}

	/**
	 * Migrate old accepted query string setting.
	 *
	 * @param array $settings Raw settings.
	 *
	 * @return array
	 */
	private function migrate_accepted_query_strings( array $settings ) {
		if ( ! empty( $settings['accepted_query_strings'] ) && empty( $settings['ignored_query_strings'] ) ) {
			$settings['ignored_query_strings'] = $settings['accepted_query_strings'];
		}

		return $settings;
	}

	/**
	 * Migrate deprecated JS execution mode setting.
	 *
	 * @param array $settings Raw settings.
	 *
	 * @return array
	 */
	private function migrate_js_execution_method( array $settings ) {
		if ( empty( $settings['js_execution_method'] ) ) {
			return $settings;
		}

		if ( in_array( $settings['js_execution_method'], array( 'async', 'defer' ), true ) ) {
			$settings['js_defer'] = true;
		}

		if ( 'delayed' === $settings['js_execution_method'] || 'delay' === $settings['js_execution_method'] ) {
			$settings['js_delay']   = true;
			$settings['combine_js'] = false;
		}

		return $settings;
	}

	/**
	 * Drop deprecated schema keys and legacy aliases.
	 *
	 * @param array $settings Settings payload.
	 *
	 * @return array
	 */
	private function drop_deprecated_keys( array $settings ) {
		$deprecated_keys = array_merge(
			$this->deprecated_keys(),
			array(
				'accepted_query_strings',
			)
		);

		foreach ( $deprecated_keys as $deprecated_key ) {
			unset( $settings[ $deprecated_key ] );
		}

		return $settings;
	}
}
