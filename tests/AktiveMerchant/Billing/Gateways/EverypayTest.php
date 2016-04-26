<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\TestCase;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

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
        #$this->mock_request($this->successful_purchase_response());

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        print_r($response);
        #$this->assert_success($response);

        #$request_body = $this->request->getBody();
        /*$this->assertEquals(
            $this->purchase_request($this->options['order_id']),
            $request_body
        );*/

    }

    public function testSuccessfulAuthorize()
    {
        #$this->mock_request($this->successful_purchase_response());

        $response = $this->gateway->authorize(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        print_r($response);
        #$this->assert_success($response);

        #$request_body = $this->request->getBody();
        /*$this->assertEquals(
            $this->purchase_request($this->options['order_id']),
            $request_body
        );*/

    }

    public function testCapture()
    {
        $authorization = 'pmt_2f9rI3J0NzyZvGdl7X1xMmT9';
        $response = $this->gateway->capture(
            $this->amount,
            $authorization,
            $this->options
        );

        print_r($response);
    }

    public function testCredit()
    {
        $identification = 'pmt_2f9rI3J0NzyZvGdl7X1xMmT9';
        $response = $this->gateway->credit(
            $this->amount,
            $identification,
            $this->options
        );

        print_r($response);
    }
}
