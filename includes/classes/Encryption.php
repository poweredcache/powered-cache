<?php
/**
 * Encryption class.
 *
 * @see     https://felix-arntz.me/blog/storing-confidential-data-in-wordpress/
 * @package PoweredCache
 */

namespace PoweredCache;

/**
 * Class Encryption.
 * Encrypting and decrypting data.
 */
final class Encryption {

	/**
	 * Key to use for encryption.
	 *
	 * @since 3.4
	 * @var string
	 */
	private $key;

	/**
	 * Salt to use for encryption.
	 *
	 * @since 3.4
	 * @var string
	 */
	private $salt;

	/**
	 * Constructor.
	 *
	 * @since 3.4
	 */
	public function __construct() {
		$this->key  = $this->get_default_key();
		$this->salt = $this->get_default_salt();
	}

	/**
	 * Encrypts a value.
	 * If a user-based key is set, that key is used. Otherwise the default key is used.
	 *
	 * @param string $value Value to encrypt.
	 *
	 * @return string|bool Encrypted value, or false on failure.
	 * @since 3.4
	 */
	public function encrypt( $value ) {
		if ( ! extension_loaded( 'openssl' ) ) {
			return $value;
		}

		$method = 'aes-256-ctr';
		$ivlen  = openssl_cipher_iv_length( $method );
		$iv     = openssl_random_pseudo_bytes( $ivlen );

		$raw_value = openssl_encrypt( $value . $this->salt, $method, $this->key, 0, $iv );
		if ( ! $raw_value ) {
			return false;
		}

		return base64_encode( $iv . $raw_value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypts a value.
	 * If a user-based key is set, that key is used. Otherwise the default key is used.
	 *
	 * @param string $raw_value Value to decrypt.
	 *
	 * @return string|bool Decrypted value, or false on failure.
	 * @since 3.4
	 */
	public function decrypt( $raw_value ) {
		if ( ! extension_loaded( 'openssl' ) ) {
			return $raw_value;
		}

		$raw_value = base64_decode( $raw_value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		$method = 'aes-256-ctr';
		$ivlen  = openssl_cipher_iv_length( $method );
		$iv     = substr( $raw_value, 0, $ivlen );

		$raw_value = substr( $raw_value, $ivlen );

		$value = openssl_decrypt( $raw_value, $method, $this->key, 0, $iv );
		if ( ! $value || substr( $value, - strlen( $this->salt ) ) !== $this->salt ) {
			return false;
		}

		return substr( $value, 0, - strlen( $this->salt ) );
	}

	/**
	 * Gets the default encryption key
	 *
	 * @return string Default (not user-based) encryption key.
	 * @since 3.4
	 */
	private function get_default_key() {
		if ( defined( 'POWERED_CACHE_ENCRYPTION_KEY' ) && '' !== POWERED_CACHE_ENCRYPTION_KEY ) {
			return POWERED_CACHE_ENCRYPTION_KEY;
		}

		if ( defined( 'LOGGED_IN_KEY' ) && '' !== LOGGED_IN_KEY ) {
			return LOGGED_IN_KEY;
		}

		// If this is reached, you're either not on a live site or have a serious security issue.
		return 'this-is-not-a-secret-key';
	}

	/**
	 * Gets the encryption salt
	 *
	 * @return string Encryption salt.
	 * @since 3.4
	 */
	private function get_default_salt() {
		if ( defined( 'POWERED_CACHE_ENCRYPTION_SALT' ) && '' !== POWERED_CACHE_ENCRYPTION_SALT ) {
			return POWERED_CACHE_ENCRYPTION_SALT;
		}

		if ( defined( 'LOGGED_IN_SALT' ) && '' !== LOGGED_IN_SALT ) {
			return LOGGED_IN_SALT;
		}

		// If this is reached, you're either not on a live site or have a serious security issue.
		return 'this-is-not-a-secret-salt';
	}
}
