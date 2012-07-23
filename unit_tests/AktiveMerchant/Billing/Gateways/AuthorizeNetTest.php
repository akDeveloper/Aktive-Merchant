<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\AuthorizeNet;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

/**
 * AuthorizeNetTest class.
 *
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
require_once 'config.php';

class AuthorizeNetTest extends AktiveMerchant\TestCase
{

    public $gateway;
    public $amount;
    public $options;
    public $creditcard;
    public $recurring_options;

    /**
     * Setup
     */
    function setUp()
    {
        Base::mode('test');

        $login_info = array(
            'login' => AUTH_NET_LOGIN,
            'password' => AUTH_NET_PASS);
        $this->gateway = new AuthorizeNet($login_info);

        $this->amount = 100;
        $this->creditcard = new CreditCard(array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4111111111111111",
                "month" => "01",
                "year" => "2015",
                "verification_value" => "000"
                )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generate_unique_id(),
            'description' => 'Autorize.net Test Transaction',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
        );

        $this->recurring_options = array(
            'amount' => 100,
            'subscription_name' => 'Test Subscription 1',
            'billing_address' => array(
                'first_name' => 'John' . $this->gateway->generate_unique_id(),
                'last_name' => 'Smith'
            ),
            'length' => 11,
            'unit' => 'months',
            'start_date' => date("Y-m-d", time()),
            'occurrences' => 1
        );
    }

    /**
     * Tests
     */
    
    public function testInitialization() {

      $this->assertNotNull($this->gateway);
      
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
      
      $this->assertInstanceOf(
          '\\AktiveMerchant\\Billing\\Interfaces\\Recurring', 
          $this->gateway
      );
      
      $this->assertNotNull($this->creditcard);
    }
    
    public function testSuccessfulPurchase()
    {
        $response = $this->gateway->purchase(
            $this->amount, $this->creditcard, $this->options
        );
        
        $this->assert_success($response);
        $this->assertEquals(
            'This transaction has been approved.', 
            $response->message()
        );
    }
    
    public function testSuccessfulAuthorization()
    {
        $response = $this->gateway->authorize(
            $this->amount, $this->creditcard, $this->options
        );
        
        $this->assert_success($response);
        $this->assertEquals(
            'This transaction has been approved.', 
            $response->message()
        );
    }

    public function testAuthorizationAndCapture()
    {
        $response = $this->gateway->authorize(
            $this->amount, 
            $this->creditcard, 
            $this->options
        );
        
        $this->assert_success($response);

        $authorization = $response->authorization();

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

    public function testSuccessfulRecurring()
    {
        $response = $this->gateway->recurring(
            $this->amount, 
            $this->creditcard, 
            $this->recurring_options
        );
        $this->assert_success($response);

        $subscription_id = $response->authorization();

        $response = $this->gateway->update_recurring(
            $subscription_id, 
            $this->creditcard
        );
        $this->assert_success($response);

        $response = $this->gateway->cancel_recurring($subscription_id);
        $this->assert_success($response);
    }

    public function testExpiredCreditCard()
    {
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

}
