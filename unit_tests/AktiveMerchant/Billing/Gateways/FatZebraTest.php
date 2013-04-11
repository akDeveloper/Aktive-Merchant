<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\FatZebra;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

require_once 'config.php';

/**
 * Unit tests for Fat Zebra gateway.
 *
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
class FatZebraTest extends AktiveMerchant\TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    /**
     * Setup
     */
    public function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('fat_zebra');
        
        $login_info['region'] = 'CA';
        $this->gateway = new FatZebra($login_info);

        $this->amount = 10;
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "5123456789012346",
                "month" => "01",
                "year" => "2015",
                "verification_value" => "000"
            )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'ip' => '10.0.0.1',
        );
    }

    public function testSuccessfulPurchase()
    {
        $this->mock_request($this->successful_purchase_response($this->options['order_id']));
        
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );
        
        $this->assert_success($response);
        $this->assertTrue($response->test());
        
        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_purchase_request($this->options['order_id']),
            $request_body
        );
        
        $params = $response->params();
        
        $this->assertEquals('1000', $params->amount);
    }
    
    private function successful_purchase_request($order_id)
    {
        return '{"reference":"'.$order_id.'","amount":"1000","card_holder":"John Doe","card_number":"5123456789012346","card_expiry":"01\/2015","cvv":"000","customer_ip":"10.0.0.1"}';
    }

    private function successful_purchase_response($order_id)
    {
        return '{"successful":true,"response":{"authorization":1364259050,"id":"071-P-9GKS9WN0","card_number":"512345XXXXXX2346","card_holder":"John Doe","card_expiry":"2015-01-31T23:59:59+11:00","card_token":"dvyc48t8","amount":1000,"decimal_amount":10.0,"successful":true,"message":"Approved","reference":"'.$order_id.'","transaction_id":"071-P-9GKS9WN0","source":"API","currency":"AUD","settlement_date":null,"transaction_date":"2013-03-26T11:50:50+11:00","refund_identifier":null},"errors":[],"test":true}';
    }

    public function testFailedPurchase()
    {
        $this->mock_request($this->fail_purchase_response($this->options['order_id']));

        $this->creditcard->year = 2000;
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );
        
        $this->assert_failure($response);
        $this->assertTrue($response->test());
 
        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->fail_purchase_request($this->options['order_id']),
            $request_body
        );

        $this->assertEquals('Expiry date is invalid (expired)', $response->message());
         
        
    }
    
    private function fail_purchase_request($order_id)
    {
        return '{"reference":"'.$order_id.'","amount":"1000","card_holder":"John Doe","card_number":"5123456789012346","card_expiry":"01\/2000","cvv":"000","customer_ip":"10.0.0.1"}';
    }

    private function fail_purchase_response($order_id)
    {
        return '{"successful":false,"response":{"authorization":null,"id":"071-P-73WWMB1V","card_number":"512345XXXXXX2346","card_holder":"John Doe","card_expiry":"2000-01-31T23:59:59+11:00","card_token":null,"amount":1000,"decimal_amount":10.0,"successful":false,"message":null,"reference":"REF1148463708","transaction_id":"071-P-73WWMB1V","source":"API","currency":"AUD","settlement_date":null,"transaction_date":"2013-03-26T12:07:26+11:00","refund_identifier":null},"errors":["Expiry date is invalid (expired)"],"test":true}';
    }

    public function testSuccessfulStore()
    {
        $this->mock_request($this->successful_store_response());
        
        $response = $this->gateway->store($this->creditcard);
        
        $this->assert_success($response);
        $this->assertTrue($response->test());
        $this->assertTrue($response->authorization() !== null);
        
        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_store_request(),
            $request_body
        );
    }

    private function successful_store_request()
    {
        return '{"card_holder":"John Doe","card_number":"5123456789012346","card_expiry":"01\/2015","cvv":"000"}';
    }

    private function successful_store_response()
    {
        return '{"successful":true,"response":{"token":"t76bbrsj","card_holder":"John Doe","card_number":"512345XXXXXX2346","card_expiry":"2015-01-31T23:59:59+11:00","authorized":true,"transaction_count":0},"errors":[],"test":true}';
    }

    public function testSuccessfulPurchaseToken()
    {
        $this->mock_request($this->successful_purchase_token_response($this->options['order_id']));
        $this->options['cvv'] = '000';

        $response = $this->gateway->purchase(
            $this->amount,
            't76bbrsj',
            $this->options
        );
        
        $this->assert_success($response);
        $this->assertTrue($response->test());

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_purchase_token_request($this->options['order_id']),
            $request_body
        );

        $params = $response->params();
        
        $this->assertEquals('1000', $params->amount);
    }
    
    private function successful_purchase_token_request($order_id)
    {
        return '{"reference":"'.$order_id.'","amount":"1000","card_token":"t76bbrsj","customer_ip":"10.0.0.1"}';
    }

    private function successful_purchase_token_response($order_id)
    {
        return '{"successful":true,"response":{"authorization":1364262352,"id":"071-P-284ZBARA","card_number":"512345XXXXXX2346","card_holder":"John Doe","card_expiry":"2015-01-31","card_token":"t76bbrsj","amount":1000,"decimal_amount":10.0,"successful":true,"message":"Approved","reference":"'.$order_id.'","transaction_id":"071-P-284ZBARA","source":"API","currency":"AUD","settlement_date":null,"transaction_date":"2013-03-26T12:45:52+11:00","refund_identifier":null},"errors":[],"test":true}';
    }

    public function testFailedPurchaseToken()
    {

        $token = 'ddasDwas';
        $this->mock_request($this->fail_purchase_token_response($token));
        $this->options['cvv'] = '000';
        $response = $this->gateway->purchase(
            $this->amount,
            $token,
            $this->options
        );
        
        $this->assert_failure($response);
        $this->assertTrue($response->test());

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->fail_purchase_token_request($this->options['order_id'], $token),
            $request_body
        );
        
        $this->assertNull($response->params()->amount);
        $this->assertEquals("Card $token could not be found",$response->message());
    }
    
    private function fail_purchase_token_request($order_id, $token)
    {
        return '{"reference":"'.$order_id.'","amount":"1000","card_token":"'.$token.'","customer_ip":"10.0.0.1"}';
    }

    private function fail_purchase_token_response($token)
    {
        return '{"successful":false,"response":{},"errors":["Card '.$token.' could not be found"],"test":true}';
    }

    public function testSuccessfulCredit()
    {

        $id = '071-P-9GKS9WN0';
        $this->mock_request($this->successful_credit_response($id));
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId()
        );

        $response = $this->gateway->credit(
            $this->amount,
            $id,
            $this->options
        );
        
        $this->assert_success($response);
        $this->assertTrue($response->test());

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_credit_request($id),
            $request_body
        );
        $this->assertEquals(-($this->amount * 100), $response->params()->amount);
    }
    
    private function successful_credit_request($id)
    {
        return '{"transaction_id":"'.$id.'","reference":{},"amount":"1000"}';
    }

    private function successful_credit_response()
    {
        return '{"successful":true,"response":{"authorization":1364321405,"id":"071-R-Q5AL9D8","amount":-1000,"refunded":"Approved","message":"Approved","card_holder":"John Doe","card_number":"512345XXXXXX2346","card_expiry":"2015-01-31","card_type":"MasterCard","transaction_id":"071-R-Q5AL9D8","successful":true,"transaction_date":"2013-03-27T05:10:05+11:00"},"errors":[],"test":true}';
    }

    public function testSuccessfulCreatePlan()
    { 
        $plan = array(
            'order_id' => 'PLAN' . $this->gateway->generateUniqueId(),
            'amount' => '12',
            'name' => 'Gold',
            'description' => 'Gold Plan'
        );

        $this->mock_request($this->successful_createplan_response($plan['order_id']));
        
        $response = $this->gateway->createPlan($plan['amount'], $plan);
        
        $this->assert_success($response);
        $this->assertTrue($response->test());

        
        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_createplan_request($plan['order_id']),
            $request_body
        );
        
    }

    private function successful_createplan_request($order_id)
    {
        return '{"reference":"'.$order_id.'","amount":"1200","name":"Gold","description":"Gold Plan"}';
    }

    private function successful_createplan_response($order_id)
    {
        return '{"successful":true,"response":{"name":"Gold","id":"071-PL-ASM2VKAS","amount":1200,"reference":"'.$order_id.'","description":"Gold Plan","currency":null,"subscription_count":0},"errors":[],"test":true}';
    }

    public function testSuccessfulGetPlans()
    { 
        $this->mock_request($this->successful_getplans_response());
        
        $response = $this->gateway->getPlans();
        
        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    public function successful_getplans_response()
    {
       return '{"successful":true,"response":[{"name":"testplan1","id":"071-PL-RVV6LKKW","amount":100,"reference":"1d4c5f50-ff98-40fa-94d4-7719faeac267","description":"This is a test plan","currency":null,"subscription_count":0},{"name":"testplan1","id":"071-PL-7WLMNQGT","amount":100,"reference":"1ca42037-0353-4ae4-a883-baa1b4435dd8","description":"This is a test plan","currency":null,"subscription_count":0},{"name":"testplan1","id":"071-PL-IWR3Z76L","amount":100,"reference":"00b4178b-10fa-475c-9a56-0fd434fcfc14","description":"This is a test plan","currency":null,"subscription_count":0},{"name":"testplan1","id":"071-PL-U6AU1T6I","amount":100,"reference":"675d16e3-3b14-4c92-8bfe-eb5cdd4d9e71","description":"This is a test plan","currency":null,"subscription_count":0},{"name":"testplan1","id":"071-PL-2OSIGRCQ","amount":100,"reference":"ac437c0f-b382-484f-a576-ad7cc8ffb053","description":"This is a test plan","currency":null,"subscription_count":1},{"name":"testplan1","id":"071-PL-D25LUVRZ","amount":100,"reference":"a65e603a-fff1-4834-a920-d2f11579a96f","description":"This is a test plan","currency":null,"subscription_count":1},{"name":"testplan1","id":"071-PL-AJDN57EQ","amount":100,"reference":"edd6a5cf-101d-48d0-92de-e422e0187356","description":"This is a test plan","currency":null,"subscription_count":1},{"name":"testplan1","id":"071-PL-CA3FA8F8","amount":100,"reference":"4abbef96-b490-445a-b8d2-098794a676fe","description":"This is a test plan","currency":null,"subscription_count":0},{"name":"testplan1","id":"071-PL-IIBGYM6I","amount":100,"reference":"ec67ccd7-b53b-4c8a-9a94-06e5577ecd74","description":"This is a test plan","currency":null,"subscription_count":0},{"name":"testplan1","id":"071-PL-ZJ8UUMFN","amount":100,"reference":"e5c12a09-06f4-4003-aad2-5ad99ae60e50","description":"This is a test plan","currency":null,"subscription_count":1},{"name":"testplan1","id":"071-PL-WRJIBDV1","amount":100,"reference":"e59aa972-5cab-44f8-9e37-9dcd8ae47580","description":"This is a test plan","currency":null,"subscription_count":1},{"name":"testplan1","id":"071-PL-FTQ3H0PH","amount":100,"reference":"49e1137d-b2b7-4af8-b0fc-9a53f3302e54","description":"This is a test plan","currency":null,"subscription_count":1},{"name":"Gold","id":"071-PL-ASM2VKAS","amount":1000,"reference":"PLAN9026643195","description":"Gold Plan","currency":null,"subscription_count":0}],"errors":[],"test":true,"records":13,"total_records":13,"page":1,"total_pages":1}'; 
    }
    
    public function testSuccessfulGetSinglePlan()
    { 
        $this->mock_request($this->successful_get_single_plan_response());

        $plan_id ='071-PL-ASM2VKAS';
        $response = $this->gateway->getPlan($plan_id);
        
        $this->assert_success($response);
        $this->assertTrue($response->test());
        $this->assertEquals($plan_id, $response->params()->id);
    }

    public function successful_get_single_plan_response()
    {
       return '{"successful":true,"response":{"name":"Gold","id":"071-PL-ASM2VKAS","amount":1000,"reference":"PLAN9026643195","description":"Gold Plan","currency":null,"subscription_count":0},"errors":[],"test":true}'; 
    }

    public function testSuccessfulUpdatePlan()
    { 
        $this->mock_request($this->successful_update_plan_response());

        $plan_id ='071-PL-ASM2VKAS';
        $response = $this->gateway->updatePlan($plan_id, array('name' => 'The Gold Plan', 'description'=>'The Gold Plan Description'));
        
        $this->assert_success($response);
        $this->asserttrue($response->test());
    }

    public function successful_update_plan_response()
    {
       return '{"successful":true,"response":{"name":"The Gold Plan","id":"071-PL-ASM2VKAS","amount":1000,"reference":"PLAN9026643195","description":"The Gold Plan Description","currency":null,"subscription_count":0},"errors":[],"test":true}'; 
    }
}
