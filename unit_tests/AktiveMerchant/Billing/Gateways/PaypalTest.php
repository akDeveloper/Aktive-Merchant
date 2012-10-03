<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\Paypal;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

require_once 'config.php';

/**
 * Unit tests for Paypal Pro gataway.
 *
 * TODO: add tests for capture, void, credit actions. 
 *
 * @package Active-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class PaypalTest extends AktiveMerchant\TestCase
{

    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    public function setUp()
    {
        Base::mode('test');

        $this->gateway = new Paypal(array(
            'login' => 'x',
            'password' => 'y',
            'signature' => 'z',
            'currency' => 'USD'
        )
    );
        $this->amount = 100;
        $this->creditcard = new CreditCard(array(
            "first_name" => "John",
            "last_name" => "Doe",
            "number" => "4381258770269608",
            "month" => "1",
            "year" => "2015",
            "verification_value" => "000"
        )
    );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'email' => "buyer@email.com",
            'description' => 'Paypal Pro Test Transaction',
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

    /**
     * Tests
     */

    public function testInitialization() 
    {
        $this->assertNotNull($this->gateway);


        $this->assertNotNull($this->creditcard);

        $this->assertInstanceOf(
            '\\AktiveMerchant\\Billing\\Gateway', 
            $this->gateway
        );

        $this->assertInstanceOf(
            '\\AktiveMerchant\\Billing\\Interfaces\\Charge', 
            $this->gateway
        );
        
        $this->assertInstanceOf(
            '\\AktiveMerchant\\Billing\\Interfaces\\Credit', 
            $this->gateway
        );
    }

    public function testSuccessfulPurchase()
    {
        $this->mock_request($this->successful_purchase_response());
        
        $response = $this->gateway->purchase(
            $this->amount, 
            $this->creditcard, 
            $this->options
        );
        
        $this->assert_success($response);
        $this->assertTrue($response->test());
        
        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_purchase_request(),
            $request_body
        );
        
        $params = $response->params();
        
        $this->assertEquals('100.00', $params['AMT']);
        $this->assertEquals('USD', $params['CURRENCYCODE']);
    }

    private function successful_purchase_request()
    {
        return "CREDITCARDTYPE=Visa&ACCT=4381258770269608&EXPDATE=012015&CVV2=000&FIRSTNAME=John&LASTNAME=Doe&CURRENCYCODE=USD&STREET=1234+Penny+Lane&CITY=Jonsetown&STATE=NC&ZIP=23456&COUNTRYCODE=US&PAYMENTACTION=Sale&AMT=100.00&IPADDRESS=10.0.0.1&METHOD=DoDirectPayment&VERSION=59.0&PWD=y&USER=x&SIGNATURE=z";
    }

    private function successful_purchase_response()
    {
        return "TIMESTAMP=2012%2d10%2d03T11%3a25%3a41Z&CORRELATIONID=54dfa76fe1a2d&ACK=Success&VERSION=59%2e0&BUILD=3719653&AMT=100%2e00&CURRENCYCODE=USD&AVSCODE=X&CVV2MATCH=M&TRANSACTIONID=97K55025R8596081L";
    }

    public function testSuccessfulAuthorization()
    {
        $this->mock_request($this->successful_authorize_response());
        
        $response = $this->gateway->authorize(
            $this->amount, 
            $this->creditcard, 
            $this->options
        );
        
        $this->assert_success($response);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_authorize_request(),
            $request_body
        ); 

        $params = $response->params();
        $this->assertEquals('100.00', $params['AMT']);
        $this->assertEquals('USD', $params['CURRENCYCODE']);
    }

    private function successful_authorize_request()
    {
        return "CREDITCARDTYPE=Visa&ACCT=4381258770269608&EXPDATE=012015&CVV2=000&FIRSTNAME=John&LASTNAME=Doe&CURRENCYCODE=USD&STREET=1234+Penny+Lane&CITY=Jonsetown&STATE=NC&ZIP=23456&COUNTRYCODE=US&PAYMENTACTION=Authorization&AMT=100.00&IPADDRESS=10.0.0.1&METHOD=DoDirectPayment&VERSION=59.0&PWD=y&USER=x&SIGNATURE=z";
    }

    private function successful_authorize_response()
    {
        return "TIMESTAMP=2012%2d09%2d21T15%3a26%3a21Z&CORRELATIONID=d529596d5684b&ACK=Success&VERSION=59%2e0&BUILD=3719653&AMT=100%2e00&CURRENCYCODE=USD&AVSCODE=X&CVV2MATCH=M&TRANSACTIONID=0ML60749PE351283S";
    }
    
    public function testFailedPurchase()
    {
        $this->mock_request($this->failure_purchase_response());
        
        $this->creditcard->number = '234234234234';
        
        $response = $this->gateway->purchase(
            $this->amount, 
            $this->creditcard, 
            $this->options
        );

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->failure_purchase_request(),
            $request_body
        );         
        
        $this->assert_failure($response);
        $this->assertTrue($response->test());
        $this->assertEquals('This transaction cannot be processed. Please enter a valid credit card number and type.', $response->message());
    }

    private function failure_purchase_request()
    {
        return "CREDITCARDTYPE=Visa&ACCT=234234234234&EXPDATE=012015&CVV2=000&FIRSTNAME=John&LASTNAME=Doe&CURRENCYCODE=USD&STREET=1234+Penny+Lane&CITY=Jonsetown&STATE=NC&ZIP=23456&COUNTRYCODE=US&PAYMENTACTION=Sale&AMT=100.00&IPADDRESS=10.0.0.1&METHOD=DoDirectPayment&VERSION=59.0&PWD=y&USER=x&SIGNATURE=z";
    }

    private function failure_purchase_response()
    {
        return "TIMESTAMP=2012%2d09%2d21T15%3a26%3a22Z&CORRELATIONID=278789b5f369&ACK=Failure&VERSION=59%2e0&BUILD=3719653&L_ERRORCODE0=10527&L_SHORTMESSAGE0=Invalid%20Data&L_LONGMESSAGE0=This%20transaction%20cannot%20be%20processed%2e%20Please%20enter%20a%20valid%20credit%20card%20number%20and%20type%2e&L_SEVERITYCODE0=Error&AMT=100%2e00&CURRENCYCODE=USD";
    }

    public function testFailedAuthorization()
    {
        $this->mock_request($this->failure_authorize_response());
        
        $this->creditcard->number = '234234234234';
        
        $response = $this->gateway->authorize(
            $this->amount, 
            $this->creditcard, 
            $this->options
        );

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->failure_authorize_request(),
            $request_body
        );         

        $this->assert_failure($response);
        $this->assertTrue($response->test());
        $this->assertEquals('This transaction cannot be processed. Please enter a valid credit card number and type.', $response->message());
    }
    
    private function failure_authorize_request()
    {
        return "CREDITCARDTYPE=Visa&ACCT=234234234234&EXPDATE=012015&CVV2=000&FIRSTNAME=John&LASTNAME=Doe&CURRENCYCODE=USD&STREET=1234+Penny+Lane&CITY=Jonsetown&STATE=NC&ZIP=23456&COUNTRYCODE=US&PAYMENTACTION=Authorization&AMT=100.00&IPADDRESS=10.0.0.1&METHOD=DoDirectPayment&VERSION=59.0&PWD=y&USER=x&SIGNATURE=z";
    }

    private function failure_authorize_response()
    {
        return "TIMESTAMP=2012%2d09%2d21T15%3a26%3a24Z&CORRELATIONID=bf9675817026&ACK=Failure&VERSION=59%2e0&BUILD=3719653&L_ERRORCODE0=10527&L_SHORTMESSAGE0=Invalid%20Data&L_LONGMESSAGE0=This%20transaction%20cannot%20be%20processed%2e%20Please%20enter%20a%20valid%20credit%20card%20number%20and%20type%2e&L_SEVERITYCODE0=Error&AMT=100%2e00&CURRENCYCODE=USD";
    }
}
