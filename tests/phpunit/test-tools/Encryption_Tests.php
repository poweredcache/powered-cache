<?php
/**
 * Encryption tests
 */

use PHPUnit\Framework\TestCase;
use PoweredCache\Encryption;
use PoweredCache as Base;

class Encryption_Test extends Base\TestCase {
	private $encryption;

	public function setUp(): void {
		$this->encryption = new Encryption();
	}

	public function testEncrypt(): void {
		$originalString  = 'test string';
		$encryptedString = $this->encryption->encrypt( $originalString );

		$this->assertNotEquals( $originalString, $encryptedString );
	}

	public function testDecrypt(): void {
		$originalString  = 'test string';
		$encryptedString = $this->encryption->encrypt( $originalString );
		$decryptedString = $this->encryption->decrypt( $encryptedString );

		$this->assertNotEquals( $originalString, $encryptedString );
		$this->assertEquals( $originalString, $decryptedString );
	}
}
