<?php

require_once dirname(__FILE__) . '/../../../config.php';

class PayflowUkTest extends PHPUnit_Framework_TestCase 
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    protected function setUp() 
    {
        Merchant_Billing_Base::mode('test');

        $this->gateway = new Merchant_Billing_PayflowUk( array(
            'login' => PAYPAL_LOGIN,
            'password' => PAYPAL_PASS,
            'currency' => 'GBP'
        ));

        $this->amount = 100.00;
        
        $this->creditcard = new Merchant_Billing_CreditCard(array(
            'number' => '5105105105105100',
            'month' => 11,
            'year' => 2009,
            'first_name' => 'Cody',
            'last_name' => 'Fauser',
            'verification_value' => '000',
            'type' => 'master'
        ));

        $this->options = array(
            'billing_address' => array(
                'name' => 'Cody Fauser',
                'address1' => '1234 Shady Brook Lane',
                'city' => 'Ottawa',
                'state' => 'ON',
                'country' => 'CA',
                'zip' => '90210',
                'phone' => '555-555-5555'
            ),
            'email' => 'cody@example.com'
        );
    }
    
    function testAuthorizationAndCapture()
    {
        $auth = $this->gateway->authorize($this->amount, $this->creditcard, $this->options); 
        $this->assertTrue($auth->success());
        $this->assertEquals('Approved', $auth->message());
        $capture = $this->gateway->capture($this->amount, $auth->authorization(), $this->options);
        $this->assertTrue($capture->success());
    }

}

?>
