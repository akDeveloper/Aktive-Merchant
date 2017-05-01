<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Event\RequestEvents;
use AktiveMerchant\TestCase;

class TrexleTest extends TestCase
{
    public function setUp()
    {
        Base::mode('test');

        $options = $this->getFixtures()->offsetGet('trexle');

        $this->gateway = new Trexle($options);
        $this->amount = 1000;
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Milwood",
                "number" => "4242424242424242",
                "month" => "01",
                "year" => "17",
                "verification_value" => "123"
            )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'Test Transaction',
            'email' => 'john@trexle.com',
            'ip' => '127.0.0.1',
            'address' => array(
                'address1' => '456 My Street',
                'city' => 'Ottawa',
                'country' => 'Canada',
                'zip' => 'K1C2N6',
                'state' => 'ON'
            )
        );
    }

    public function testSuccessPurchase()
    {
        $this->mock_request($this->successPurchaseResponse());
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertNotNull($response->authorization());
    }

    public function testFailPurchase()
    {
        $this->mock_request($this->failedPurchaseResponse());
        $this->creditcard->number = '4000000000000119';
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_failure($response);
    }

    public function testSuccessAuthorize()
    {
        $this->mock_request($this->successAuthorizeResponse());
        $response = $this->gateway->authorize(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertNotNull($response->authorization());

        return $response->authorization();
    }

    /**
     * @depends testSuccessAuthorize
     */
    public function testSuccessCapture($authorization)
    {
        $this->mock_request($this->successCaptureResponse());
        $response = $this->gateway->capture(
            $this->amount,
            $authorization,
            $this->options
        );

        $this->assert_success($response);
        $this->assertNotNull($response->authorization());

        return $response->authorization();
    }

    /**
     * @depends testSuccessCapture
     */
    public function testSuccessCredit($authorization)
    {
        $this->mock_request($this->successCreditResponse());
        $response = $this->gateway->credit(
            $this->amount,
            $authorization
        );

        $this->assert_success($response);
        $this->assertNotNull($response->authorization());
    }

    public function testSuccessStoreCustomer()
    {
        $this->mock_request($this->successStoreResponse());
        $response = $this->gateway->store(
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertNotNull($response->authorization());
        $this->assertNotNull($response->card->token);

        return $response->authorization();
    }

    /**
     * @depends testSuccessStoreCustomer
     */
    public function testSuccessPurchaseWithCustomer($customerToken)
    {
        $this->creditcard->token = $customerToken;

        $this->mock_request($this->successCustomerPurchaseResponse());
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertNotNull($response->authorization());
    }

    private function successPurchaseResponse()
    {
        return '{
      "response":{
      "token":"charge_0cfad7ee5ffe75f58222bff214bfa5cc7ad7c367",
      "success":true,
      "captured":true
   }
   }';
    }

    private function failedPurchaseResponse()
    {
        return '{
     "error":"Payment failed",
     "detail":"An error occurred while processing your card. Try again in a little bit."
     }';
    }

    private function successAuthorizeResponse()
    {
        return '{
      "response":{
      "token":"charge_0cfad7ee5ffe75f58222bff214bfa5cc7ad7c367",
      "success":true,
      "captured":false
   }
   }';
    }

    private function successCaptureResponse()
    {
        return '{
      "response":{
      "token":"charge_6e47a330dca67ec7f696e8b650db22fe69bb8499",
      "success":true,
      "captured":true
   }
   }';
    }

    private function successCreditResponse()
    {
        return '{
      "response":{
      "token":"token_2cb443cf26b6ecdadd8144d1fac8240710aa41f1",
      "card":{
         "token":"token_f974687e4e866d6cca534e1cd42236817d315b3a",
         "primary":true
      }
     }
    }';
    }

    private function successStoreResponse()
    {
        return '{
      "response":{
      "token":"token_940ade441a23d53e04017f53af6c3a1eae9978ae",
      "card":{
         "token":"token_9a3f559962cbf6828e2cc38a02023565b0294548",
         "scheme":"master",
         "display_number":"XXXX-XXXX-XXXX-4444",
         "expiry_year":2019,
         "expiry_month":9,
         "cvc":123,
         "name":"John Milwood",
         "address_line1":"456 My Street",
         "address_line2":null,
         "address_city":"Ottawa",
         "address_state":"ON",
         "address_postcode":"K1C2N6",
         "address_country":"CA",
         "primary":true
      }
   }
   }';
    }

    private function successCustomerPurchaseResponse()
    {
        return '{
      "response":{
      "token":"charge_6e47a330dca67ec7f696e8b650db22fe69bb8499",
      "success":true,
      "captured":true
   }
   }';
    }
}
