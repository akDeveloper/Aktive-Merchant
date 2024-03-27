<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use AktiveMerchant\Billing\CreditCard;

/**
 * CreditCardTest class.
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 */
class CreditCardTest extends TestCase
{

    public $creditcard;

    public function setUp(): void
    {
        $this->creditcard = new CreditCard(array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4381258770269608",
                "month" => "1",
                "year" => date('Y') + 1,
                "verification_value" => "000"
                )
        );
    }

    public function testSuccessfulValidateCreditcard()
    {
        $this->assertTrue($this->creditcard->isValid());
    }

    public function testSuccessfulGetDisplayNumber()
    {
        $this->assertEquals(
            'XXXX-XXXX-XXXX-9608',
            $this->creditcard->displayNumber()
        );
    }

    public function testSuccessfulGetLastDigits()
    {
        $this->assertEquals('9608', $this->creditcard->lastDigits());
    }

    public function testSuccessfulGetName()
    {
        $this->assertEquals('John Doe', $this->creditcard->name());
    }

    public function testSuccessfulExpireDate()
    {
        $expire_date = $this->creditcard->expireDate();
        $this->assertInstanceOf(
            '\AktiveMerchant\Billing\ExpiryDate',
            $expire_date
        );
        $this->assertFalse($expire_date->isExpired());
    }

    public function testFailedExpireDate()
    {
        $this->creditcard->year = 2000;
        $expire_date = $this->creditcard->expireDate();
        $this->assertInstanceOf(
            '\AktiveMerchant\Billing\ExpiryDate',
            $expire_date
        );
        $this->assertTrue($expire_date->isExpired());
    }

    public function testValidateCardToken()
    {
        $token = new CreditCard(['token' => '123']);
        $this->assertTrue($token->isValid());
    }

    public function testShuldFailForCardNumber()
    {
        $card = new CreditCard([
            "first_name" => "John",
            "last_name" => "Doe",
            "number" => "4381258770269607",
            "month" => "1",
            "year" => date('Y') + 1,
            "verification_value" => "000"
        ]);

        $this->assertFalse($card->isValid());
        $errors = $card->errors();
        $this->assertNotEmpty($errors);

        $this->assertEquals($errors['number'], 'is not a valid credit card number');
    }

    public function testShouldFailForCardType()
    {
        $card = new CreditCard([
            "first_name" => "John",
            "last_name" => "Doe",
            "number" => "4381258770269608",
            "month" => "1",
            "year" => date('Y') + 1,
            "verification_value" => "000",
        ]);
        $card->type = "master";

        $this->assertFalse($card->isValid());
        $errors = $card->errors();
        $this->assertNotEmpty($errors);

        $this->assertEquals($errors['type'], 'is not the correct card type');
    }
}