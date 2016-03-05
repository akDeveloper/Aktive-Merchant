<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Event\RequestEvents;
use AktiveMerchant\TestCase;

class StripeTest extends TestCase
{
    public function setUp()
    {
        Base::mode('test');

        $options = $this->getFixtures()->offsetGet('stripe');

        $options['currency'] = 'EUR';

        $this->gateway = new Stripe($options);
        $this->amount = 1000;
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4242424242424242",
                "month" => "01",
                "year" => "17",
                "verification_value" => "123"
            )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'Test Transaction',
            'email' => 'andreas@larium.net',
            'ip' => '127.0.0.1',
            'address' => array(
                'address1' => '12 Ermou Street',
                'city' => 'Athens',
                'country' => 'Greece',
                'zip' => '10560',
                'state' => 'AT'
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
        $this->creditcard->number = '4000000000000002';
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_failure($response);
        $this->assertEquals('Your card was declined.', $response->message());
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
        $this->assertFalse($response->captured);

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
        $this->assertTrue($response->captured);

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
        $this->assertNotNull($response->sources->data[0]->id);

    }

    public function testSuccessPurchaseWithCustomer()
    {
        $customerToken = 'cus_81jD5qJGP9alqu';
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

    public function testSuccessUnstoreCard()
    {
        $customerToken = 'cus_81jD5qJGP9alqu';
        $cardToken = 'card_17lfl9KH7mEy5bciSIKQeEkE';

        $this->creditcard->token = $customerToken;
        $this->options['card_token'] = $cardToken;

        $this->mock_request($this->successCardUnstoreResponse());
        $response = $this->gateway->unstore(
            $customerToken,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->deleted);
    }

    private function successPurchaseResponse()
    {
        return '{
  "id": "ch_17lMF8KH7mEy5bciDmLj7TZc",
  "object": "charge",
  "amount": 1000,
  "amount_refunded": 0,
  "application_fee": null,
  "balance_transaction": "txn_17lMF9KH7mEy5bcigNd7wzp3",
  "captured": true,
  "created": 1457110654,
  "currency": "eur",
  "customer": null,
  "description": "Test Transaction",
  "destination": null,
  "dispute": null,
  "failure_code": null,
  "failure_message": null,
  "fraud_details": {},
  "invoice": null,
  "livemode": false,
  "metadata": {},
  "order": null,
  "paid": true,
  "receipt_email": "andreas@larium.net",
  "receipt_number": null,
  "refunded": false,
  "refunds": {
    "object": "list",
    "data": [],
    "has_more": false,
    "total_count": 0,
    "url": "/v1/charges/ch_17lMF8KH7mEy5bciDmLj7TZc/refunds"
  },
  "shipping": null,
  "source": {
    "id": "card_17lMF8KH7mEy5bciO1EiWgxG",
    "object": "card",
    "address_city": "Athens",
    "address_country": "Greece",
    "address_line1": "12 Ermou Street",
    "address_line1_check": "pass",
    "address_line2": null,
    "address_state": "AT",
    "address_zip": "10560",
    "address_zip_check": "pass",
    "brand": "Visa",
    "country": "US",
    "customer": null,
    "cvc_check": "pass",
    "dynamic_last4": null,
    "exp_month": 1,
    "exp_year": 2017,
    "fingerprint": "owuMLlcJyatuAUz7",
    "funding": "credit",
    "last4": "4242",
    "metadata": {},
    "name": "John Doe",
    "tokenization_method": null
  },
  "source_transfer": null,
  "statement_descriptor": null,
  "status": "succeeded"
}';
    }

    private function failedPurchaseResponse()
    {
        return '{ "error": { "message": "Your card was declined.", "type": "card_error", "code": "card_declined", "charge": "ch_17lNRgKH7mEy5bci9GG5P01I" } }';
    }

    private function successAuthorizeResponse()
    {
        return '{
  "id": "ch_17lO44KH7mEy5bcixT0o0SdV",
  "object": "charge",
  "amount": 1000,
  "amount_refunded": 0,
  "application_fee": null,
  "balance_transaction": null,
  "captured": false,
  "created": 1457117656,
  "currency": "eur",
  "customer": null,
  "description": "Test Transaction",
  "destination": null,
  "dispute": null,
  "failure_code": null,
  "failure_message": null,
  "fraud_details": {},
  "invoice": null,
  "livemode": false,
  "metadata": {},
  "order": null,
  "paid": true,
  "receipt_email": "andreas@larium.net",
  "receipt_number": null,
  "refunded": false,
  "refunds": {
    "object": "list",
    "data": [],
    "has_more": false,
    "total_count": 0,
    "url": "/v1/charges/ch_17lO44KH7mEy5bcixT0o0SdV/refunds"
  },
  "shipping": null,
  "source": {
    "id": "card_17lO44KH7mEy5bcieTP2ghXC",
    "object": "card",
    "address_city": "Athens",
    "address_country": "Greece",
    "address_line1": "12 Ermou Street",
    "address_line1_check": "pass",
    "address_line2": null,
    "address_state": "AT",
    "address_zip": "10560",
    "address_zip_check": "pass",
    "brand": "Visa",
    "country": "US",
    "customer": null,
    "cvc_check": "pass",
    "dynamic_last4": null,
    "exp_month": 1,
    "exp_year": 2017,
    "fingerprint": "owuMLlcJyatuAUz7",
    "funding": "credit",
    "last4": "4242",
    "metadata": {},
    "name": "John Doe",
    "tokenization_method": null
  },
  "source_transfer": null,
  "statement_descriptor": null,
  "status": "succeeded"
}';
    }

    private function successCaptureResponse()
    {
        return '{
  "id": "ch_17lO44KH7mEy5bcixT0o0SdV",
  "object": "charge",
  "amount": 1000,
  "amount_refunded": 0,
  "application_fee": null,
  "balance_transaction": "txn_17lO4JKH7mEy5bcina5fg6bs",
  "captured": true,
  "created": 1457117656,
  "currency": "eur",
  "customer": null,
  "description": "Test Transaction",
  "destination": null,
  "dispute": null,
  "failure_code": null,
  "failure_message": null,
  "fraud_details": {},
  "invoice": null,
  "livemode": false,
  "metadata": {},
  "order": null,
  "paid": true,
  "receipt_email": "andreas@larium.net",
  "receipt_number": null,
  "refunded": false,
  "refunds": {
    "object": "list",
    "data": [],
    "has_more": false,
    "total_count": 0,
    "url": "/v1/charges/ch_17lO44KH7mEy5bcixT0o0SdV/refunds"
  },
  "shipping": null,
  "source": {
    "id": "card_17lO44KH7mEy5bcieTP2ghXC",
    "object": "card",
    "address_city": "Athens",
    "address_country": "Greece",
    "address_line1": "12 Ermou Street",
    "address_line1_check": "pass",
    "address_line2": null,
    "address_state": "AT",
    "address_zip": "10560",
    "address_zip_check": "pass",
    "brand": "Visa",
    "country": "US",
    "customer": null,
    "cvc_check": "pass",
    "dynamic_last4": null,
    "exp_month": 1,
    "exp_year": 2017,
    "fingerprint": "owuMLlcJyatuAUz7",
    "funding": "credit",
    "last4": "4242",
    "metadata": {},
    "name": "John Doe",
    "tokenization_method": null
  },
  "source_transfer": null,
  "statement_descriptor": null,
  "status": "succeeded"
}';
    }

    private function successCreditResponse()
    {
        return '{
  "id": "re_17lPtFKH7mEy5bcinIie66en",
  "object": "refund",
  "amount": 1000,
  "balance_transaction": "txn_17lPtFKH7mEy5bciTHmTvfnQ",
  "charge": "ch_17lO44KH7mEy5bcixT0o0SdV",
  "created": 1457124673,
  "currency": "eur",
  "metadata": {},
  "reason": null,
  "receipt_number": null
}';
    }

    private function successStoreResponse()
    {
        return '{
  "id": "cus_81jD5qJGP9alqu",
  "object": "customer",
  "account_balance": 0,
  "created": 1457185675,
  "currency": null,
  "default_source": "card_17lfl9KH7mEy5bciSIKQeEkE",
  "delinquent": false,
  "description": null,
  "discount": null,
  "email": "andreas@larium.net",
  "livemode": false,
  "metadata": {},
  "shipping": null,
  "sources": {
    "object": "list",
    "data": [
      {
        "id": "card_17lfl9KH7mEy5bciSIKQeEkE",
        "object": "card",
        "address_city": "Athens",
        "address_country": "Greece",
        "address_line1": "12 Ermou Street",
        "address_line1_check": "pass",
        "address_line2": null,
        "address_state": "AT",
        "address_zip": "10560",
        "address_zip_check": "pass",
        "brand": "Visa",
        "country": "US",
        "customer": "cus_81jD5qJGP9alqu",
        "cvc_check": "pass",
        "dynamic_last4": null,
        "exp_month": 1,
        "exp_year": 2017,
        "fingerprint": "owuMLlcJyatuAUz7",
        "funding": "credit",
        "last4": "4242",
        "metadata": {},
        "name": "John Doe",
        "tokenization_method": null
      }
    ],
    "has_more": false,
    "total_count": 1,
    "url": "/v1/customers/cus_81jD5qJGP9alqu/sources"
  },
  "subscriptions": {
    "object": "list",
    "data": [],
    "has_more": false,
    "total_count": 0,
    "url": "/v1/customers/cus_81jD5qJGP9alqu/subscriptions"
  }
}';
    }

    private function successCustomerPurchaseResponse()
    {
        return '{
  "id": "ch_17lfp6KH7mEy5bciQYNBEMD9",
  "object": "charge",
  "amount": 1000,
  "amount_refunded": 0,
  "application_fee": null,
  "balance_transaction": "txn_17lfp6KH7mEy5bcipgVpu9CC",
  "captured": true,
  "created": 1457185920,
  "currency": "eur",
  "customer": "cus_81jD5qJGP9alqu",
  "description": "Test Transaction",
  "destination": null,
  "dispute": null,
  "failure_code": null,
  "failure_message": null,
  "fraud_details": {},
  "invoice": null,
  "livemode": false,
  "metadata": {},
  "order": null,
  "paid": true,
  "receipt_email": "andreas@larium.net",
  "receipt_number": null,
  "refunded": false,
  "refunds": {
    "object": "list",
    "data": [],
    "has_more": false,
    "total_count": 0,
    "url": "/v1/charges/ch_17lfp6KH7mEy5bciQYNBEMD9/refunds"
  },
  "shipping": null,
  "source": {
    "id": "card_17lfl9KH7mEy5bciSIKQeEkE",
    "object": "card",
    "address_city": "Athens",
    "address_country": "Greece",
    "address_line1": "12 Ermou Street",
    "address_line1_check": "pass",
    "address_line2": null,
    "address_state": "AT",
    "address_zip": "10560",
    "address_zip_check": "pass",
    "brand": "Visa",
    "country": "US",
    "customer": "cus_81jD5qJGP9alqu",
    "cvc_check": "pass",
    "dynamic_last4": null,
    "exp_month": 1,
    "exp_year": 2017,
    "fingerprint": "owuMLlcJyatuAUz7",
    "funding": "credit",
    "last4": "4242",
    "metadata": {},
    "name": "John Doe",
    "tokenization_method": null
  },
  "source_transfer": null,
  "statement_descriptor": null,
  "status": "succeeded"
}';
    }

    private function successCardUnstoreResponse()
    {
        return '{
  "deleted": true,
  "id": "card_17lfl9KH7mEy5bciSIKQeEkE"
}';
    }
}
