<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\TestCase;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

class BeanstreamTest extends TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    public function setUp()
    {
        Base::mode('test');

        $options = $this->getFixtures()->offsetGet('beanstream');

        $this->gateway = new Beanstream($options);
        $this->amount = 100.00;
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "5100000010001004",
                "month" => "01",
                "year" => date('Y') + 1,
                "verification_value" => "123"
            )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'Test Transaction',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
        );
    }

    public function testSuccessfulPurchase()
    {
        $this->mock_request($this->successfulPurchaseResponse());
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
    }

    public function testSuccessfulAuthorize()
    {
        $this->mock_request($this->successfulAuthorizeResponse());
        $response = $this->gateway->authorize(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
    }

    public function testSuccessfulCapture()
    {
        $this->mock_request($this->successfulCaptureResponse());
        $response = $this->gateway->capture(
            $this->amount,
            '10000010'
        );

        $this->assert_success($response);
    }

    public function testSuccessfulCredit()
    {
        $this->mock_request($this->successfulCreditResponse());
        $response = $this->gateway->credit(
            $this->amount,
            '10000011'
        );

        $this->assert_success($response);
    }

    /*public function testSuccessfulVoid()
    {
        $response = $this->gateway->void(
            '10000016',
            array('amount' => $this->amount)
        );

        print_r($response);
    }*/

    public function successfulPurchaseResponse()
    {
        return '{"id":"10000008","approved":"1","message_id":"1","message":"Approved","auth_code":"TEST","created":"2016-05-16T00:06:57","order_number":"REF9666595385","type":"P","payment_method":"CC","card":{"card_type":"MC","last_four":"1004","cvd_match":0,"address_match":0,"postal_result":0},"links":[{"rel":"void","href":"https://www.beanstream.com/api/v1/payments/10000008/void","method":"POST"},{"rel":"return","href":"https://www.beanstream.com/api/v1/payments/10000008/returns","method":"POST"}]}';
    }

    public function successfulAuthorizeResponse()
    {
        return '{"id":"10000010","approved":"1","message_id":"1","message":"Approved","auth_code":"TEST","created":"2016-05-16T00:11:51","order_number":"REF1183576352","type":"PA","payment_method":"CC","card":{"card_type":"MC","last_four":"1004","cvd_match":0,"address_match":0,"postal_result":0},"links":[{"rel":"complete","href":"https://www.beanstream.com/api/v1/payments/10000010/completions","method":"POST"}]}';
    }

    public function successfulCaptureResponse()
    {
        return '{"id":"10000011","approved":"1","message_id":"1","message":"Approved","auth_code":"TEST","created":"2016-05-16T01:00:38","order_number":"REF1183576352","type":"PAC","payment_method":"CC","card":{"card_type":"MC","cvd_match":0,"address_match":0,"postal_result":0},"links":[{"rel":"return","href":"https://www.beanstream.com/api/v1/payments/10000011/returns","method":"POST"},{"rel":"complete","href":"https://www.beanstream.com/api/v1/payments/10000011/completions","method":"POST"}]}';
    }

    public function successfulCreditResponse()
    {
        return '{"id":"10000013","approved":"1","message_id":"1","message":"Approved","auth_code":"TEST","created":"2016-05-16T01:15:55","order_number":"REF1183576352","type":"R","payment_method":"CC","card":{"card_type":"MC","cvd_match":0,"address_match":0,"postal_result":0},"links":[{"rel":"void","href":"https://www.beanstream.com/api/v1/payments/10000013/void","method":"POST"},{"rel":"return","href":"https://www.beanstream.com/api/v1/payments/10000013/returns","method":"POST"}]}';
    }
}
