<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\AuthorizeNet;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

/**
 * Unit tests for AuthorizeNet gateway.
 *
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
class AuthorizeNetTest extends \AktiveMerchant\TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;
    public $recurring_options;

    /**
     * Setup
     */
    public function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('authorize_net');

        $this->gateway = new AuthorizeNet($login_info);

        $this->amount = 100;
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4111111111111111",
                "month" => "01",
                "year" => "2015",
                "verification_value" => "000"
            )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'Autorize.net Test Transaction',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
        );

        $this->recurring_options = array(
            'amount' => 100,
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'subscription_name' => 'Test Subscription 1',
            'billing_address' => array(
                'first_name' => 'John' . $this->gateway->generateUniqueId(),
                'last_name' => 'Smith'
            ),
            'frequency' => 11,
            'period' => 'months',
            'start_date' => date("Y-m-d", strtotime('tomorrow')),
            'occurrences' => 1
        );
    }

    /**
     * Tests
     */

    public function testInitialization()
    {
        $this->assertNotNull($this->gateway);

        $this->assertImplementation(
            array(
                'Charge',
                'Credit',
                'Recurring'
            )
        );

        $this->assertNotNull($this->creditcard);
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
        $this->assertEquals(
            'This transaction has been approved.',
            $response->message()
        );
    }

    private function successful_purchase_response()
    {
        return "1|1|1|This transaction has been approved.|C5SEPE|Y|2176052979|REF3241141575|Autorize.net Test Transaction|100.00|CC|auth_capture||John|Doe||1234 Street||WA|98004||||||||||||||||||7CAFB5DD55B21227198694F15ED9D7DA|P|2|||||||||||XXXX1111|Visa||||||||||||||||";
    }

    public function testSuccessfulAuthorization()
    {
        $this->mock_request($this->successful_authorize_response());
        $response = $this->gateway->authorize(
            $this->amount, $this->creditcard, $this->options
        );

        $this->assert_success($response);
        $this->assertEquals(
            'This transaction has been approved.',
            $response->message()
        );
    }

    private function successful_authorize_response()
    {
        return "1|1|1|This transaction has been approved.|5CNLO0|Y|2176052980|REF2023019825|Autorize.net Test Transaction|100.00|CC|auth_only||John|Doe||1234 Street||WA|98004||||||||||||||||||82691C74ECBFE2149CED8E40493E756C|P|2|||||||||||XXXX1111|Visa||||||||||||||||";
    }

    public function testAuthorizationAndCapture()
    {
        $this->mock_request($this->successful_authorize_for_capture_response());

        $response = $this->gateway->authorize(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);

        $authorization = $response->authorization();

        $this->mock_request($this->successful_capture_response());

        $capture = $this->gateway->capture(
            $this->amount,
            $authorization,
            $this->options
        );
        $this->assert_success($capture);
        $this->assertEquals(
            'This transaction has been approved.',
            $capture->message()
        );
    }

    private function successful_authorize_for_capture_response()
    {
        return "1|1|1|This transaction has been approved.|BOHBP5|Y|2176052981|REF1734948236|Autorize.net Test Transaction|100.00|CC|auth_only||John|Doe||1234 Street||WA|98004||||||||||||||||||62B9AE0010A65C5666734C43EA3D33BC|P|2|||||||||||XXXX1111|Visa||||||||||||||||";
    }

    private function successful_capture_response()
    {
        return "1|1|1|This transaction has been approved.|BOHBP5|P|2176052981|REF1734948236||100.00|CC|prior_auth_capture||||||||98004||||||||||||||||||62B9AE0010A65C5666734C43EA3D33BC|||||||||||||XXXX1111|Visa||||||||||||||||";
    }

    public function testSuccessfulRecurring()
    {
        $this->mock_request($this->successful_recurring_response());

        $response = $this->gateway->recurring(
            $this->amount,
            $this->creditcard,
            $this->recurring_options
        );

        $request_body = $this->request->getBody();
        $name = $this->recurring_options['billing_address']['first_name']
            . " "
            . $this->recurring_options['billing_address']['last_name'];
        $firstanme = $this->recurring_options['billing_address']['first_name'];
        $order_id = $this->recurring_options['order_id'];

        $this->assert_success($response);

        $subscription_id = $response->authorization();

        $this->mock_request($this->successful_update_recurring_response());
        $response = $this->gateway->updateRecurring(
            $subscription_id,
            $this->creditcard
        );
        $this->assert_success($response);

        $this->mock_request($this->successful_cancel_recurring_response());
        $response = $this->gateway->cancelRecurring($subscription_id);
        $this->assert_success($response);
    }

    private function successful_recurring_response()
    {
        return '<?xml version="1.0" encoding="utf-8"?><ARBCreateSubscriptionResponse xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd"><refId /><messages><resultCode>Ok</resultCode><message><code>I00001</code><text>Successful.</text></message></messages><subscriptionId>1494423</subscriptionId></ARBCreateSubscriptionResponse>';
    }

    private function successful_update_recurring_response()
    {
        return '<?xml version="1.0" encoding="utf-8"?><ARBUpdateSubscriptionResponse xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd"><messages><resultCode>Ok</resultCode><message><code>I00001</code><text>Successful.</text></message></messages></ARBUpdateSubscriptionResponse>';
    }

    private function successful_cancel_recurring_response()
    {
        return '<?xml version="1.0" encoding="utf-8"?><ARBCancelSubscriptionResponse xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd"><messages><resultCode>Ok</resultCode><message><code>I00001</code><text>Successful.</text></message></messages></ARBCancelSubscriptionResponse>';
    }

    public function testExpiredCreditCard()
    {
        $this->mock_request($this->successful_expired_card_response());

        $this->creditcard->year = 2004;
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );
        $this->assertEquals(
            'The credit card has expired.',
            $response->message()
        );
    }

    private function successful_expired_card_response()
    {
        return '3|1|8|The credit card has expired.||P|0|REF2122605833|Autorize.net Test Transaction|100.00|CC|auth_capture||John|Doe||1234 Street||WA|98004||||||||||||||||||8221EB3CA5ECBE7D801F4EF2AA88E191|||||||||||||XXXX1111|Visa||||||||||||||||';
    }

}
