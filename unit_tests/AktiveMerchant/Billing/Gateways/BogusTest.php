<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\Bogus;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

class BogusTest extends \AktiveMerchant\TestCase
{
    protected $creditcard;

    public function setUp()
    {
        Base::mode('test');
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "1",
                "month" => "01",
                "year" => "2015",
                "verification_value" => "000"
            )
        );
    }

    public function testSuccessPurchase()
    {
        $gateway = new Bogus();

        $response = $gateway->purchase(100, $this->creditcard);

        $this->assert_success($response);
    }

    public function testFailPurchase()
    {
        $gateway = new Bogus();
        $this->creditcard->number = 3;
        $response = $gateway->purchase(100, $this->creditcard);
        $this->assert_failure($response);
    }

    /**
     * @expectedException AktiveMerchant\Billing\Exception
     */
    public function testExceptionPurchase()
    {
        $gateway = new Bogus();
        $this->creditcard->number = 2;
        $response = $gateway->purchase(100, $this->creditcard);
    }
}
