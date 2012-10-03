<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\PaypalExpress;
use AktiveMerchant\Billing\Base;

/**
 * Unit tests for Paypal Express gateway
 *
 * @package Active-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 */
require_once 'config.php';

class PaypalExpressTest extends AktiveMerchant\TestCase
{

    public $gateway;
    public $amount;
    public $options;

    public function setUp()
    {
        Base::mode('test');

        $this->gateway = new PaypalExpress(array(
            'login' => PAYPAL_LOGIN,
            'password' => PAYPAL_PASS,
            'signature' => PAYPAL_SIG,
            'currency' => 'EUR'
        )
    );
        $this->amount = 100;

        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generate_unique_id(),
            'email' => "buyer@email.com",
            'description' => 'Paypal Express Test Transaction',
            'billing_address' => array(
                'address1' => '1234 Penny Lane',
                'city' => 'Jonsetown',
                'state' => 'NC',
                'country' => 'US',
                'zip' => '23456'
            ),
            'ip' => '10.0.0.1'
        );
    }

    // Tests 

    public function testInitialization() 
    {
        $this->assertNotNull($this->gateway);

        $this->assertInstanceOf(
            '\\AktiveMerchant\\Billing\\Gateway', 
            $this->gateway
        );
    }

    public function testSetExpressAuthorization()
    {
        $options = array(
            'return_url' => 'http://example.com',
            'cancel_return_url' => 'http://example.com',
        );
        $options = array_merge($this->options, $options);
        $response = $this->gateway->setup_authorize($this->amount, $options);
        $this->assert_success($response);
        $this->assertTrue($response->test());
        $token = $response->token();
        $this->assertFalse(empty($token));
    }

    public function testSetExpressPurchase()
    {
        $options = array(
            'return_url' => 'http://example.com',
            'cancel_return_url' => 'http://example.com',
        );
        $options = array_merge($this->options, $options);
        $response = $this->gateway->setup_purchase($this->amount, $options);
        $this->assert_success($response);
        $this->assertTrue($response->test());
        $token = $response->token();
        $this->assertFalse(empty($token));
    }
}
