<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\Vme;
use AktiveMerchant\Billing\Base;

/**
 * Unit tests for Visa Me gateway
 *
 * @package Active-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 */
require_once 'config.php';

class VmeTest extends AktiveMerchant\TestCase
{
    public $gateway;

    public function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('vme');
        
        $this->gateway = new Vme($login_info);
    }

    public function testSuccessfulCheckoutDetails()
    {
        $callid = '101060426'; 
        $this->mock_request($this->successful_checkout_details($callid));
        
        $response = $this->gateway->getCheckoutDetails($callid);
    }

    private function successful_checkout_details($callid)
    {
       return '{  "status" : 200,  "callId" : "'.$callid.'",  "state" : "CANCEL",  "merchTrans" : "SKU0001",  "productId" : "Testproduct1",  "currency" : "USD",  "amount" : "1.00",  "total" : "",  "shippingDetail" : {    "name" : "VISA",    "addressline1" : "Address Str 7",    "addressline2" : "",    "city" : "New Yoirk",    "countrycode" : "US",    "postalcode" : "10000",    "stateprovincecode" : "NY",    "phone" : "3145565478"  },  "userDetail" : {    "email" : "test@example.com"  }}';
    }

    public function testFailCheckoutDetails()
    {
        $callid = 'xxx'; 
        $this->mock_request($this->fail_checkout_details($callid));
        
        $response = $this->gateway->getCheckoutDetails($callid);

        $this->assertEquals('NOT_FOUND', $response->message());
    }

    private function fail_checkout_details($callid)
    {
       return '{  "status" : 400,  "source" : "PROCESSING",  "code" : "VALIDATION",  "detail" : [ {    "field" : "callid",    "value" : "'.$callid.'",    "annotation" : "NOT_FOUND"  } ]}';
    }
    
    public function testSuccessfulPurchase()
    {
        $callid = '101091008'; 
        $this->mock_request($this->successful_purchase($callid));
        
        $response = $this->gateway->purchase('1.00', $callid, array('total'=>'1.00'));
    }

    private function successful_purchase($callid)
    {
       return '{"status" : 200,  "callId" : "'.$callid.'"}';
    }

    public function testSuccessfulAutohrize()
    {
        $callid = '101285336'; 
        $this->mock_request($this->successful_authorize($callid));
        
        $response = $this->gateway->authorize('1.00', $callid);
    }

    private function successful_authorize($callid)
    {
       return '{  "status" : 200,  "callId" : "'.$callid.'",  "paytxnid" : "100710995",  "state" : "AUTHORIZED",  "currency" : "USD",  "amount" : "1.00"}';
    }
}
