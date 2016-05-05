<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\TestCase;

class CentinelTest extends TestCase
{

    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    protected function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('centinel');

        $this->gateway = new Centinel($login_info);

        $this->amount = 100.00;

        $this->creditcard = new CreditCard(array(
            'number' => '5105105105105100',
            'month' => 11,
            'year' => date('Y') + 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'verification_value' => '000',
            'type' => 'master'
        ));

        $this->options = array(
            'billing_address' => array(
                'name' => 'John Doe',
                'address1' => '1234 my address',
                'city' => 'Neverland',
                'state' => 'ON',
                'country' => 'CA',
                'zip' => '90210',
                'phone' => '555-555-5555'
            ),
            'email' => 'john@example.com',
            'order_id' => '123456',
            'payload' => 'payload',
            'transaction_id' => '78910'
        );
    }

    public function testInitialization()
    {
        $this->assertNotNull($this->gateway);
        $this->assertNotNull($this->creditcard);
    }

    public function testLookup()
    {
        $this->mock_request($this->successfulLookupResponse());

        $auth = $this->gateway->lookup(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $request_body = $this->getRequestBody();
        $this->assertEquals(
            $this->successfulLookupRequest(),
            $request_body
        );

        $this->assertTrue($auth->success());
    }

    public function testAuthenticate()
    {
        $this->mock_request($this->successfulAuthenticateResponse());

        $auth = $this->gateway->authenticate($this->options);

        $request_body = $this->getRequestBody();
        $this->assertEquals(
            $this->successfulAuthenticateRequest(),
            $request_body
        );

        $this->assertTrue($auth->success());
    }

    private function successfulLookupRequest()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<CardinalMPI><MsgType>cmpi_lookup</MsgType><Version>1.7</Version><ProcessorId>z</ProcessorId><MerchantId>x</MerchantId><TransactionPwd>y</TransactionPwd><TransactionType>C</TransactionType><OrderNumber>123456</OrderNumber><CurrencyCode>978</CurrencyCode><Amount>100</Amount><CardNumber>5105105105105100</CardNumber><CardExpMonth>11</CardExpMonth><CardExpYear>2017</CardExpYear></CardinalMPI>';
    }

    private function successfulAuthenticateRequest()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<CardinalMPI><MsgType>cmpi_authenticate</MsgType><Version>1.7</Version><ProcessorId>z</ProcessorId><MerchantId>x</MerchantId><TransactionPwd>y</TransactionPwd><TransactionType>C</TransactionType><TransactionId>78910</TransactionId><PAResPayload>payload</PAResPayload></CardinalMPI>';
    }

    private function successfulLookupResponse()
    {
        return '<CardinalMPI> <TransactionType>C</TransactionType> <ErrorNo>0</ErrorNo> <ErrorDesc></ErrorDesc> <TransactionId>75f986t76f6</TransactionId> <OrderId>2584</OrderId> <Payload>eNpVUk1TwjAQ/SsM402nSUuKwSC/3gSoH5PL</Payload> <Enrolled>Y</Enrolled> <ACSUrl>https://www.somewebsite.com/acs</ACSUrl> <EciFlag>07</EciFlag> </CardinalMPI>';
    }

    private function successfulAuthenticateResponse()
    {
        return '<CardinalMPI> <ErrorDesc></ErrorDesc> <ErrorNo>0</ErrorNo> <PAResStatus>Y</PAResStatus> <SignatureVerification>Y</SignatureVerification> <Cavv>AAAAAAAAAAAAAAAAAAAAAAAAA=</Cavv> <EciFlag>05</EciFlag> <Xid>k4Vf36ijnJX54kwHQNqUr8/ruvs=</Xid> </CardinalMPI>';
    }

    private function getRequestBody()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . str_replace('cmpi_msg=', null, urldecode($this->request->getBody()));
    }
}
