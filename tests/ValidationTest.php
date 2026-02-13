<?php
use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase {
    public function testValidateName() {
        $this->assertTrue(validate_name('John Doe'));
        $this->assertFalse(validate_name('A'));
    }

    public function testValidateEmail() {
        $this->assertTrue(validate_email('user@example.com'));
        $this->assertFalse(validate_email('not-an-email'));
    }

    public function testValidatePhone() {
        $this->assertTrue(validate_phone('+254 700 000000'));
        $this->assertFalse(validate_phone('x12345'));
    }

    public function testValidateUsername() {
        $this->assertTrue(validate_username('user_01'));
        $this->assertFalse(validate_username('ab'));
    }

    public function testValidatePassword() {
        $this->assertTrue(validate_password('longenough'));
        $this->assertFalse(validate_password('short'));
    }
}
