<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\Paypal;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

/**
 * Unit tests for Paypal Pro gataway.
 *
 * TODO: add tests for capture, void, credit actions.
 *
 * @package Active-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class PaypalTest extends \AktiveMerchant\TestCase
{

    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    public function setUp()
    {
        Base::mode('test');

        $options = $this->getFixtures()->offsetGet('paypal_pro');

        $options['currency'] = 'USD';

        $this->gateway = new Paypal($options);
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

        $this->assertImplementation(
            array(
                'Charge',
                'Credit'
            )
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
        return "CREDITCARDTYPE=Visa&ACCT=4381258770269608&EXPDATE=012015&CVV2=000&FIRSTNAME=John&LASTNAME=Doe&CURRENCYCODE=USD&STREET=1234+Penny+Lane&CITY=Jonsetown&STATE=NC&ZIP=23456&COUNTRYCODE=US&PAYMENTACTION=Sale&AMT=100.00&IPADDRESS=10.0.0.1&METHOD=DoDirectPayment&VERSION=85.0&PWD=y&USER=x&SIGNATURE=z";
    }

    private function successful_purchase_response()
    {
        return "TIMESTAMP=2012%2d10%2d03T11%3a25%3a41Z&CORRELATIONID=54dfa76fe1a2d&ACK=Success&VERSION=85%2e0&BUILD=3719653&AMT=100%2e00&CURRENCYCODE=USD&AVSCODE=X&CVV2MATCH=M&TRANSACTIONID=97K55025R8596081L";
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
        return "CREDITCARDTYPE=Visa&ACCT=4381258770269608&EXPDATE=012015&CVV2=000&FIRSTNAME=John&LASTNAME=Doe&CURRENCYCODE=USD&STREET=1234+Penny+Lane&CITY=Jonsetown&STATE=NC&ZIP=23456&COUNTRYCODE=US&PAYMENTACTION=Authorization&AMT=100.00&IPADDRESS=10.0.0.1&METHOD=DoDirectPayment&VERSION=85.0&PWD=y&USER=x&SIGNATURE=z";
    }

    private function successful_authorize_response()
    {
        return "TIMESTAMP=2012%2d09%2d21T15%3a26%3a21Z&CORRELATIONID=d529596d5684b&ACK=Success&VERSION=85%2e0&BUILD=3719653&AMT=100%2e00&CURRENCYCODE=USD&AVSCODE=X&CVV2MATCH=M&TRANSACTIONID=0ML60749PE351283S";
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
        return "CREDITCARDTYPE=Visa&ACCT=234234234234&EXPDATE=012015&CVV2=000&FIRSTNAME=John&LASTNAME=Doe&CURRENCYCODE=USD&STREET=1234+Penny+Lane&CITY=Jonsetown&STATE=NC&ZIP=23456&COUNTRYCODE=US&PAYMENTACTION=Sale&AMT=100.00&IPADDRESS=10.0.0.1&METHOD=DoDirectPayment&VERSION=85.0&PWD=y&USER=x&SIGNATURE=z";
    }

    private function failure_purchase_response()
    {
        return "TIMESTAMP=2012%2d09%2d21T15%3a26%3a22Z&CORRELATIONID=278789b5f369&ACK=Failure&VERSION=85%2e0&BUILD=3719653&L_ERRORCODE0=10527&L_SHORTMESSAGE0=Invalid%20Data&L_LONGMESSAGE0=This%20transaction%20cannot%20be%20processed%2e%20Please%20enter%20a%20valid%20credit%20card%20number%20and%20type%2e&L_SEVERITYCODE0=Error&AMT=100%2e00&CURRENCYCODE=USD";
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
        return "CREDITCARDTYPE=Visa&ACCT=234234234234&EXPDATE=012015&CVV2=000&FIRSTNAME=John&LASTNAME=Doe&CURRENCYCODE=USD&STREET=1234+Penny+Lane&CITY=Jonsetown&STATE=NC&ZIP=23456&COUNTRYCODE=US&PAYMENTACTION=Authorization&AMT=100.00&IPADDRESS=10.0.0.1&METHOD=DoDirectPayment&VERSION=85.0&PWD=y&USER=x&SIGNATURE=z";
    }

    private function failure_authorize_response()
    {
        return "TIMESTAMP=2012%2d09%2d21T15%3a26%3a24Z&CORRELATIONID=bf9675817026&ACK=Failure&VERSION=85%2e0&BUILD=3719653&L_ERRORCODE0=10527&L_SHORTMESSAGE0=Invalid%20Data&L_LONGMESSAGE0=This%20transaction%20cannot%20be%20processed%2e%20Please%20enter%20a%20valid%20credit%20card%20number%20and%20type%2e&L_SEVERITYCODE0=Error&AMT=100%2e00&CURRENCYCODE=USD";
    }

    public function testSuccessfulCapture()
    {
        $authorization = '2RU58210F2652241X';
        $options = array('complete_type' => 'Complete');

        $this->mock_request($this->successful_capture_response());

        $response = $this->gateway->capture(
            $this->amount,
            $authorization,
            $options
        );

        $this->assert_success($response);
    }

    private function successful_capture_response()
    {
        return 'AUTHORIZATIONID=2RU58210F2652241X&TIMESTAMP=2012%2d10%2d03T22%3a36%3a05Z&CORRELATIONID=6d4a97f2c657b&ACK=Success&VERSION=85%2e0&BUILD=3881757&TRANSACTIONID=93E31369SF483774H&PARENTTRANSACTIONID=2RU58210F2652241X&RECEIPTID=5383%2d3682%2d3657%2d9782&TRANSACTIONTYPE=webaccept&PAYMENTTYPE=instant&ORDERTIME=2012%2d10%2d03T22%3a36%3a03Z&AMT=100%2e00&TAXAMT=0%2e00&CURRENCYCODE=USD&PAYMENTSTATUS=Pending&PENDINGREASON=multicurrency&REASONCODE=None&PROTECTIONELIGIBILITY=Ineligible';
    }

    public function testSuccessfulVoid()
    {
        $authorization = '7GL42127193626438';

        $this->mock_request($this->successful_void_response());

        $response = $this->gateway->void($authorization);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_void_request(),
            $request_body
        );

        $this->assert_success($response);

        $this->assertEquals($authorization, $response->authorization());
    }

    private function successful_void_request()
    {
        return 'AUTHORIZATIONID=7GL42127193626438&NOTE=&METHOD=DoVoid&VERSION=85.0&PWD=y&USER=x&SIGNATURE=z';
    }

    private function successful_void_response()
    {
        return 'AUTHORIZATIONID=7GL42127193626438&TIMESTAMP=2012%2d10%2d03T23%3a38%3a43Z&CORRELATIONID=f46e615eaa237&ACK=Success&VERSION=85%2e0&BUILD=3881757';
    }

    public function testSuccessfulCredit()
    {
        $identification = '2DH16869J1538591S';

        $this->mock_request($this->successful_credit_response());

        $options = array('refund_type'=>'Full');
        $response = $this->gateway->credit(
            $this->amount,
            $identification,
            $options
        );

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_credit_request(),
            $request_body
        );

        $this->assert_success($response);
    }

    private function successful_credit_request()
    {
        return 'REFUNDTYPE=Full&TRANSACTIONID=2DH16869J1538591S&INVNUM=&NOTE=&METHOD=RefundTransaction&VERSION=85.0&PWD=y&USER=x&SIGNATURE=z';
    }

    private function successful_credit_response()
    {
        return 'REFUNDTRANSACTIONID=4N964245YG5030150&FEEREFUNDAMT=3%2e90&GROSSREFUNDAMT=100%2e00&NETREFUNDAMT=96%2e10&CURRENCYCODE=USD&TOTALREFUNDEDAMOUNT=100%2e00&TIMESTAMP=2012%2d10%2d04T00%3a12%3a51Z&CORRELATIONID=a6504a7a7d694&ACK=Success&VERSION=85%2e0&BUILD=3881757&REFUNDSTATUS=Delayed&PENDINGREASON=echeck';
    }
}
