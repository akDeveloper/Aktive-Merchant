<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Event\RequestEvents;
use AktiveMerchant\TestCase;

class PinTest extends TestCase
{
    public function setUp()
    {
        Base::mode('test');

        $options = $this->getFixtures()->offsetGet('pin_payments');

        $this->gateway = new Pin($options);
        $this->amount = 1000;
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4200000000000000",
                "month" => "01",
                "year" => date('Y')+5,
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
        $this->creditcard->number = '4100000000000001';
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
        $this->assertEquals('Pending', $response->message());
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

    public function testSuccessPurchaseFromStoredCard()
    {
        $this->creditcard->token = 'card_eyB0zmaj_52lAB-tz7wKgw';

        $this->mock_request($this->successStoredCardPurchaseResponse());
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
        return '{"response":{"token":"ch_MXERky4IR37FQiNGpepqFg","success":true,"amount":1000,"currency":"AUD","description":"Test Transaction","email":"andreas@larium.net","ip_address":"127.0.0.1","created_at":"2016-03-02T22:14:40Z","status_message":"Success","error_message":null,"card":{"token":"card_q6i8LFWz1swp71IsdoHKxw","scheme":"visa","display_number":"XXXX-XXXX-XXXX-0000","expiry_month":1,"expiry_year":2017,"name":"John Doe","address_line1":"12 Ermou Street","address_line2":null,"address_city":"Athens","address_postcode":"10560","address_state":"AT","address_country":"Greece","customer_token":null,"primary":null},"transfer":[],"amount_refunded":0,"total_fees":48,"merchant_entitlement":952,"refund_pending":false,"authorisation_expired":false,"captured":true,"settlement_currency":"AUD"}}';
    }

    private function failedPurchaseResponse()
    {
        return '{"error":"card_declined","error_description":"The card was declined","charge_token":"ch_sD-aJYVBNKlwKsxJn6m5dA"}';
    }

    private function successAuthorizeResponse()
    {
        return '{"response":{"token":"ch_j5P9f54JVixrZ0JG3JXV2A","success":true,"amount":1000,"currency":"AUD","description":"Test Transaction","email":"andreas@larium.net","ip_address":"127.0.0.1","created_at":"2016-03-03T11:01:29Z","status_message":"Successful Authorisation","error_message":null,"card":{"token":"card_AucyJYBMNlJA9HZ85pWjwQ","scheme":"visa","display_number":"XXXX-XXXX-XXXX-0000","expiry_month":1,"expiry_year":2017,"name":"John Doe","address_line1":"12 Ermou Street","address_line2":null,"address_city":"Athens","address_postcode":"10560","address_state":"AT","address_country":"Greece","customer_token":null,"primary":null},"transfer":[],"amount_refunded":0,"total_fees":null,"merchant_entitlement":null,"refund_pending":false,"authorisation_expired":false,"captured":false,"settlement_currency":"AUD"}}';
    }

    private function successCaptureResponse()
    {
        return '{"response":{"token":"ch_j5P9f54JVixrZ0JG3JXV2A","success":true,"amount":1000,"currency":"AUD","description":"Test Transaction","email":"andreas@larium.net","ip_address":"127.0.0.1","created_at":"2016-03-03T11:01:29Z","status_message":"Success","error_message":null,"card":{"token":"card_AucyJYBMNlJA9HZ85pWjwQ","scheme":"visa","display_number":"XXXX-XXXX-XXXX-0000","expiry_month":1,"expiry_year":2017,"name":"John Doe","address_line1":"12 Ermou Street","address_line2":null,"address_city":"Athens","address_postcode":"10560","address_state":"AT","address_country":"Greece","customer_token":null,"primary":null},"transfer":[],"amount_refunded":0,"total_fees":48,"merchant_entitlement":952,"refund_pending":false,"authorisation_expired":false,"captured":true,"settlement_currency":"AUD"}}';
    }

    private function successCreditResponse()
    {
        return '{"response":{"token":"rf_oKxWXXpdrGbfsfvgkaHFfg","success":null,"amount":1000,"currency":"AUD","charge":"ch_OwOBohkNQuWKpQsPtJ_huA","created_at":"2016-03-03T11:36:44Z","error_message":null,"status_message":"Pending"}}';
    }

    private function successStoreResponse()
    {
        return '{"response":{"token":"cus_xGuu-GHoUVXM4PvWH60qVw","email":"andreas@larium.net","created_at":"2016-03-03T13:09:15Z","card":{"token":"card_OvFyF8o-Zv1p2OgM5HD5-A","scheme":"visa","display_number":"XXXX-XXXX-XXXX-0000","expiry_month":1,"expiry_year":2017,"name":"John Doe","address_line1":"12 Ermou Street","address_line2":null,"address_city":"Athens","address_postcode":"10560","address_state":"AT","address_country":"Greece","customer_token":"cus_xGuu-GHoUVXM4PvWH60qVw","primary":true}}}';
    }

    private function successCustomerPurchaseResponse()
    {
        return '{"response":{"token":"ch_0_g9QUIxIHTxstB9gUwirw","success":true,"amount":1000,"currency":"AUD","description":"Test Transaction","email":"andreas@larium.net","ip_address":"127.0.0.1","created_at":"2016-03-03T14:25:58Z","status_message":"Success","error_message":null,"card":{"token":"card_OvFyF8o-Zv1p2OgM5HD5-A","scheme":"visa","display_number":"XXXX-XXXX-XXXX-0000","expiry_month":1,"expiry_year":2017,"name":"John Doe","address_line1":"12 Ermou Street","address_line2":null,"address_city":"Athens","address_postcode":"10560","address_state":"AT","address_country":"Greece","customer_token":"cus_xGuu-GHoUVXM4PvWH60qVw","primary":true},"transfer":[],"amount_refunded":0,"total_fees":48,"merchant_entitlement":952,"refund_pending":false,"authorisation_expired":false,"captured":true,"settlement_currency":"AUD"}}';
    }

    private function successStoredCardPurchaseResponse()
    {
        return '{"response":{"token":"ch__U8op9Y1iS7HVlEMtVDQlw","success":true,"amount":1000,"currency":"AUD","description":"Test Transaction","email":"andreas@larium.net","ip_address":"127.0.0.1","created_at":"2019-01-01T11:12:51Z","status_message":"Success","error_message":null,"card":{"token":"card_eyB0zmaj_52lAB-tz7wKgw","scheme":"visa","display_number":"XXXX-XXXX-XXXX-0000","issuing_country":"AU","expiry_month":1,"expiry_year":2024,"name":"John Doe","address_line1":"12 Ermou Street","address_line2":null,"address_city":"Athens","address_postcode":"10560","address_state":"AT","address_country":"Greece","customer_token":"cus_31M-sXR9wUd2Z5M82BGlkQ","primary":true},"transfer":[],"amount_refunded":0,"total_fees":48,"merchant_entitlement":952,"refund_pending":false,"authorisation_expired":false,"captured":true,"captured_at":"2019-01-01T11:12:51Z","settlement_currency":"AUD","active_chargebacks":false,"metadata":{}}}';
    }
}
