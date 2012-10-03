<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once'config.php';

use AktiveMerchant\Billing\Gateways\PayflowUk;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

class PayflowUkTest extends AktiveMerchant\TestCase
{

    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    protected function setUp()
    {
        Base::mode('test');

        $this->gateway = new PayflowUk(array(
            'login' => PAYPAL_LOGIN,
            'password' => PAYPAL_PASS,
            'currency' => 'GBP'
        ));

        $this->amount = 100.00;

        $this->creditcard = new CreditCard(array(
            'number' => '5105105105105100',
            'month' => 11,
            'year' => 2009,
            'first_name' => 'Cody',
            'last_name' => 'Fauser',
            'verification_value' => '000',
            'type' => 'master'
        ));

        $this->options = array(
            'billing_address' => array(
                'name' => 'Cody Fauser',
                'address1' => '1234 Shady Brook Lane',
                'city' => 'Ottawa',
                'state' => 'ON',
                'country' => 'CA',
                'zip' => '90210',
                'phone' => '555-555-5555'
            ),
            'email' => 'cody@example.com'
        );
    }

    public function testInitialization()
    {
        $this->assertNotNull($this->gateway);
        $this->assertNotNull($this->creditcard);
    }

    function testAuthorizationAndCapture()
    {
        $auth = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
        $this->assertTrue($auth->success());
        $this->assertEquals('Approved', $auth->message());
        $capture = $this->gateway->capture($this->amount, $auth->authorization(), $this->options);
        $this->assertTrue($capture->success());
    }

}
