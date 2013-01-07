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
        return "PAYMENTREQUEST_0_SHIPTOSTREET=1234+Penny+Lane&PAYMENTREQUEST_0_SHIPTOCITY=Jonsetown&PAYMENTREQUEST_0_SHIPTOSTATE=NC&PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE=US&PAYMENTREQUEST_0_SHIPTOZIP=23456&METHOD=SetExpressCheckout&PAYMENTREQUEST_0_AMT=100.00&RETURNURL=http%3A%2F%2Fexample.com&CANCELURL=http%3A%2F%2Fexample.com&EMAIL=buyer%40email.com&USER=x&PWD=y&VERSION=94.0&SIGNATURE=z&PAYMENTREQUEST_0_CURRENCYCODE=EUR&PAYMENTREQUEST_0_PAYMENTACTION=Authorization";
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
        return "PAYMENTREQUEST_0_SHIPTOSTREET=1234+Penny+Lane&PAYMENTREQUEST_0_SHIPTOCITY=Jonsetown&PAYMENTREQUEST_0_SHIPTOSTATE=NC&PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE=US&PAYMENTREQUEST_0_SHIPTOZIP=23456&METHOD=SetExpressCheckout&PAYMENTREQUEST_0_AMT=100.00&RETURNURL=http%3A%2F%2Fexample.com&CANCELURL=http%3A%2F%2Fexample.com&EMAIL=buyer%40email.com&USER=x&PWD=y&VERSION=94.0&SIGNATURE=z&PAYMENTREQUEST_0_CURRENCYCODE=EUR&PAYMENTREQUEST_0_PAYMENTACTION=Sale";
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

    public function testSuccessCapture()
    {
        $options = array(
            'complete_type' => 'Complete'
        );
        $amount = 46;
        $authorization = '2HR32227AR146560V';

        $this->mock_request($this->successful_capture_response());

        $response = $this->gateway->capture($amount, $authorization, $options);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_capture_request(),
            $request_body
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    private function successful_capture_request()
    {
        return "METHOD=DoCapture&AMT=46.00&AUTHORIZATIONID=2HR32227AR146560V&COMPLETETYPE=Complete&USER=x&PWD=y&VERSION=94.0&SIGNATURE=z&PAYMENTREQUEST_0_CURRENCYCODE=EUR";
    }

    private function successful_capture_response()
    {
        return "AUTHORIZATIONID=2HR32227AR146560V&TIMESTAMP=2012%2d12%2d30T00%3a10%3a28Z&CORRELATIONID=63805563cde4f&ACK=Success&VERSION=94%2e0&BUILD=4181146&TRANSACTIONID=36D84615R8185650E&PARENTTRANSACTIONID=2HR32227AR146560V&TRANSACTIONTYPE=expresscheckout&PAYMENTTYPE=instant&ORDERTIME=2012%2d12%2d30T00%3a10%3a26Z&AMT=46%2e00&FEEAMT=1%2e63&TAXAMT=0%2e00&CURRENCYCODE=EUR&PAYMENTSTATUS=Completed&PENDINGREASON=None&REASONCODE=None&PROTECTIONELIGIBILITY=Eligible&PROTECTIONELIGIBILITYTYPE=ItemNotReceivedEligible%2cUnauthorizedPaymentEligible";
    }

    public function testSuccessCredit()
    {
        $options = array(
            'refund_type' => 'Full'
        );
        $amount = 46;
        $identification = '36D84615R8185650E';

        $this->mock_request($this->successful_credit_response());

        $response = $this->gateway->credit($amount, $identification, $options);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_credit_request(),
            $request_body
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    private function successful_credit_request()
    {
        return "REFUNDTYPE=Full&TRANSACTIONID=36D84615R8185650E&METHOD=RefundTransaction&USER=x&PWD=y&VERSION=94.0&SIGNATURE=z&PAYMENTREQUEST_0_CURRENCYCODE=EUR";
    }

    private function successful_credit_response()
    {
        return "REFUNDTRANSACTIONID=98R99007WE150102S&FEEREFUNDAMT=1%2e33&GROSSREFUNDAMT=46%2e00&NETREFUNDAMT=44%2e67&CURRENCYCODE=USD&TOTALREFUNDEDAMOUNT=46%2e00&TIMESTAMP=2012%2d12%2d30T00%3a13%3a21Z&CORRELATIONID=b906e26f283d&ACK=Success&VERSION=94%2e0&BUILD=4181146&REFUNDSTATUS=Instant&PENDINGREASON=None";
    }


    public function testSuccessVoid()
    {
        $authorization = '0NT65969AG744642L';

        $this->mock_request($this->successful_void_response());

        $response = $this->gateway->void($authorization);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_void_request(),
            $request_body
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    private function successful_void_request()
    {
        return "METHOD=DoVoid&AUTHORIZATIONID=0NT65969AG744642L&USER=x&PWD=y&VERSION=94.0&SIGNATURE=z&PAYMENTREQUEST_0_CURRENCYCODE=EUR";
    }

    private function successful_void_response()
    {
        return "AUTHORIZATIONID=0NT65969AG744642L&TIMESTAMP=2012%2d12%2d30T00%3a16%3a17Z&CORRELATIONID=931e3b8291997&ACK=Success&VERSION=94%2e0&BUILD=4181146";
    }
}
