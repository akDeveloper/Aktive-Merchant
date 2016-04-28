<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\TestCase;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Event\RequestEvents;

class EverypayTest extends TestCase
{
    public function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('everypay');

        $this->gateway = new Everypay($login_info);

        $this->amount = 10.00;
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4111111111111111",
                "month" => "01",
                "year" => date('Y') + 1,
                "verification_value" => "123"
            )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'Everypay Test Transaction',
            'email' => 'test@example.com',
            'phone' => '+30211212121',
        );
    }

    public function testSuccessfulPurchase()
    {
        $this->mock_request($this->successPurchaseResponse());

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertRegExp('/^pmt/', $response->authorization());
        $this->assertEquals('Captured', $response->message());

    }

    private function successPurchaseResponse()
    {
        return '{ "token": "pmt_8aos88URXiaa8gKzkciu5Vdf", "date_created": "2016-04-28T21:04:17+0300", "description": "Everypay Test Transaction", "currency": "EUR", "status": "Captured", "amount": 1000, "refund_amount": 0, "fee_amount": 44, "payee_email": "test@example.com", "payee_phone": "+30211212121", "refunded": false, "refunds": [], "installments_count": 0, "installments": [], "card": { "expiration_month": "01", "expiration_year": "2017", "last_four": "1111", "type": "Visa", "holder_name": "John Doe", "supports_installments": false, "max_installments": 0 } }';
    }

    public function testSuccessfulAuthorize()
    {
        $this->mock_request($this->successAuthorizeResponse());

        $response = $this->gateway->authorize(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertRegExp('/^pmt/', $response->authorization());
        $this->assertEquals('Pre authorized', $response->message());

        return $response->authorization();
    }

    private function successAuthorizeResponse()
    {
        return '{ "token": "pmt_tLzz3ICU8v1FqI3aWfga5oqd", "date_created": "2016-04-28T21:06:08+0300", "description": "Everypay Test Transaction", "currency": "EUR", "status": "Pre authorized", "amount": 1000, "refund_amount": 0, "fee_amount": 44, "payee_email": "test@example.com", "payee_phone": "+30211212121", "refunded": false, "refunds": [], "installments_count": 0, "installments": [], "card": { "expiration_month": "01", "expiration_year": "2017", "last_four": "1111", "type": "Visa", "holder_name": "John Doe", "supports_installments": false, "max_installments": 0 } }';
    }

    /**
     * @depends testSuccessfulAuthorize
     */
    public function testSuccessfulCapture($authorization)
    {
        $this->mock_request($this->successCaptureResponse());

        $response = $this->gateway->capture(
            $this->amount,
            $authorization,
            $this->options
        );

        $this->assert_success($response);
        $this->assertRegExp('/^pmt/', $response->authorization());
        $this->assertEquals('Captured', $response->message());

        return $response->authorization();
    }

    private function successCaptureResponse()
    {
        return '{ "token": "pmt_tLzz3ICU8v1FqI3aWfga5oqd", "date_created": "2016-04-28T21:06:08+0300", "description": "Everypay Test Transaction", "currency": "EUR", "status": "Captured", "amount": 1000, "refund_amount": 0, "fee_amount": 44, "payee_email": "test@example.com", "payee_phone": "+30211212121", "refunded": false, "refunds": [], "installments_count": 0, "installments": [], "card": { "expiration_month": "01", "expiration_year": "2017", "last_four": "1111", "type": "Visa", "holder_name": "John Doe", "supports_installments": false, "max_installments": 0 } }';
    }

    /**
     * @depends testSuccessfulCapture
     */
    public function testSuccessfulCredit($identification)
    {
        $this->mock_request($this->successCreditResponse());

        $response = $this->gateway->credit(
            $this->amount,
            $identification,
            $this->options
        );

        $this->assert_success($response);
        $this->assertRegExp('/^pmt/', $response->authorization());
        $this->assertEquals('Refunded', $response->message());
    }

    private function successCreditResponse()
    {
        return '{ "token": "pmt_tLzz3ICU8v1FqI3aWfga5oqd", "date_created": "2016-04-28T21:06:08+0300", "description": "Everypay Test Transaction", "currency": "EUR", "status": "Refunded", "amount": 1000, "refund_amount": 1000, "fee_amount": 0, "payee_email": "test@example.com", "payee_phone": "+30211212121", "refunded": true, "refunds": [ { "token": "ref_IOvZv1QspHxgWezKztWiRHHY", "status": "Captured", "date_created": "2016-04-28T21:14:23+0300", "amount": 1000, "fee_amount": 44, "description": null } ], "installments_count": 0, "installments": [], "card": { "expiration_month": "01", "expiration_year": "2017", "last_four": "1111", "type": "Visa", "holder_name": "John Doe", "supports_installments": false, "max_installments": 0 } }';
    }
}
