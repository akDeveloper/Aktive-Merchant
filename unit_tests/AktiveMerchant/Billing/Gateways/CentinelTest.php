<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once'config.php';

use AktiveMerchant\Billing\Gateways\Centinel;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

class CentinelTest extends AktiveMerchant\TestCase
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
            'email' => 'cody@example.com',
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

    function testLookup()
    {
        $this->mock_request($this->successful_lookup_response());
        
        $auth = $this->gateway->lookup(
            $this->amount, 
            $this->creditcard, 
            $this->options
        );
        
        $this->assertTrue($auth->success());
    }

    function testAuthenticate()
    {
        $this->mock_request($this->successful_authenticate_response());
        
        $auth = $this->gateway->authenticate($this->options);
        
        $this->assertTrue($auth->success());
    }

    private function successful_lookup_response()
    {
    return '<CardinalMPI>
<TransactionType>C</TransactionType>
<ErrorNo>0</ErrorNo>
<ErrorDesc></ErrorDesc>
<TransactionId>75f986t76f6</TransactionId>
<OrderId>2584</OrderId>
<Payload>eNpVUk1TwjAQ/SsM402nSUuKwSC/3gSoH5PL</Payload>
<Enrolled>Y</Enrolled>
<ACSUrl>https://www.somewebsite.com/acs</ACSUrl>
<EciFlag>07</EciFlag>
</CardinalMPI>';
    }

    private function successful_authenticate_response()
    {
    return '<CardinalMPI>
<ErrorDesc></ErrorDesc>
<ErrorNo>0</ErrorNo>
<PAResStatus>Y</PAResStatus>
<SignatureVerification>Y</SignatureVerification>
<Cavv>AAAAAAAAAAAAAAAAAAAAAAAAA=</Cavv>
<EciFlag>05</EciFlag>
<Xid>k4Vf36ijnJX54kwHQNqUr8/ruvs=</Xid>
</CardinalMPI>';
    }

}
