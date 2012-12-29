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

        $options = $this->getFixtures()->offsetGet('paypal_express');
        
        $options['currency'] = 'EUR';

        $this->gateway = new PaypalExpress($options);
        $this->amount = 100;

        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
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
        
        $this->mock_request($this->successful_setup_authorize_response());
        
        $response = $this->gateway->setupAuthorize($this->amount, $options);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_setup_authorize_request(),
            $request_body
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
        
        $token = $response->token();
        $this->assertFalse(empty($token));
    }

    private function successful_setup_authorize_request()
    {
        return "METHOD=SetExpressCheckout&PAYMENTREQUEST_0_AMT=100.00&RETURNURL=http%3A%2F%2Fexample.com&CANCELURL=http%3A%2F%2Fexample.com&EMAIL=buyer%40email.com&PAYMENTREQUEST_0_PAYMENTACTION=Authorization&USER=x&PWD=y&VERSION=63.0&SIGNATURE=z&PAYMENTREQUEST_0_CURRENCYCODE=EUR";
    }

    private function successful_setup_authorize_response()
    {
        return "TOKEN=EC%2d8LV868742E9298213&TIMESTAMP=2012%2d10%2d04T00%3a33%3a08Z&CORRELATIONID=b11ac5e2a3057&ACK=Success&VERSION=63%2e0&BUILD=3881757";
    }

    public function testSetExpressPurchase()
    {
        $options = array(
            'return_url' => 'http://example.com',
            'cancel_return_url' => 'http://example.com',
        );
        $options = array_merge($this->options, $options);
        
        $this->mock_request($this->successful_setup_purchase_response());
        
        $response = $this->gateway->setupPurchase($this->amount, $options);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_setup_purchase_request(),
            $request_body
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
        
        $token = $response->token();
        $this->assertFalse(empty($token));
    }

    private function successful_setup_purchase_request()
    {
        return "METHOD=SetExpressCheckout&PAYMENTREQUEST_0_AMT=100.00&RETURNURL=http%3A%2F%2Fexample.com&CANCELURL=http%3A%2F%2Fexample.com&EMAIL=buyer%40email.com&PAYMENTREQUEST_0_PAYMENTACTION=Sale&USER=x&PWD=y&VERSION=63.0&SIGNATURE=z&PAYMENTREQUEST_0_CURRENCYCODE=EUR";
    }

    private function successful_setup_purchase_response()
    {
        return "TOKEN=EC%2d2KK82117LS1153937&TIMESTAMP=2012%2d10%2d04T00%3a33%3a10Z&CORRELATIONID=5d59521b7935a&ACK=Success&VERSION=63%2e0&BUILD=3881757";
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailAmount()
    {
       $this->gateway->amount('string');
    }
}
