<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\PaypalExpress;
use AktiveMerchant\Billing\Base;

/**
 * Unit tests for Paypal Express gateway
 *
 * @package Active-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 */

class PaypalExpressTest extends \AktiveMerchant\TestCase
{

    public $gateway;
    public $amount;
    public $options;

    public function setUp()
    {
        Base::mode('test');

        $options = $this->getFixtures()->offsetGet('paypal_express');

        $options['currency'] = 'EUR';

        $this->gateway = new PaypalExpress($options);
        $this->amount = 100;

        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'email' => "buyer@email.com",
            'description' => 'Paypal Express Test Transaction',
            'billing_address' => array(
                'address1' => '1234 Penny Lane',
                'city' => 'Jonsetown',
                'state' => 'NC',
                'country' => 'US',
                'zip' => '23456'
            ),
            'ip' => '10.0.0.1'
        );
    }

    // Tests

    public function testInitialization()
    {
        $this->assertNotNull($this->gateway);

        $this->assertInstanceOf(
            '\\AktiveMerchant\\Billing\\Gateway',
            $this->gateway
        );
    }

    public function testSetExpressAuthorization()
    {
        $options = array(
            'return_url' => 'http://example.com',
            'cancel_return_url' => 'http://example.com',
        );
        $options = array_merge($this->options, $options);

        $this->mock_request($this->successful_setup_authorize_response());

        $response = $this->gateway->setupAuthorize($this->amount, $options);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_setup_authorize_request(),
            $request_body
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());

        $token = $response->token();
        $this->assertFalse(empty($token));
    }

    private function successful_setup_authorize_request()
    {
        return "PAYMENTREQUEST_0_SHIPTOSTREET=1234+Penny+Lane&PAYMENTREQUEST_0_SHIPTOCITY=Jonsetown&PAYMENTREQUEST_0_SHIPTOSTATE=NC&PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE=US&PAYMENTREQUEST_0_SHIPTOZIP=23456&METHOD=SetExpressCheckout&PAYMENTREQUEST_0_AMT=100.00&RETURNURL=http%3A%2F%2Fexample.com&CANCELURL=http%3A%2F%2Fexample.com&EMAIL=buyer%40email.com&USER=x&PWD=y&VERSION=94.0&SIGNATURE=z&PAYMENTREQUEST_0_CURRENCYCODE=EUR&PAYMENTREQUEST_0_PAYMENTACTION=Authorization";
    }

    private function successful_setup_authorize_response()
    {
        return "TOKEN=EC%2d8LV868742E9298213&TIMESTAMP=2012%2d10%2d04T00%3a33%3a08Z&CORRELATIONID=b11ac5e2a3057&ACK=Success&VERSION=63%2e0&BUILD=3881757";
    }

    public function testGetDetailsForToken()
    {

        $this->mock_request($this->successful_get_details_for_token_response());

        $token = 'EC-81B81448TC460182J';
        $payer_id = '3L2BZTVKN8N7G';

        $response = $this->gateway->get_details_for($token, $payer_id);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_get_details_for_token_request(),
            $request_body
        );

        $this->assert_success($response);

        $address = array(
            'name' => 'Andreas Kollaros',
            'address1' => '1234 Penny Lane',
            'address2' => '2nd Floor',
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27701',
            'country_code' => 'US',
            'country' => 'United States',
            'address_status' => 'Confirmed',
        );
        $this->assertEquals($address, $response->address());
    }

    private function successful_get_details_for_token_request()
    {
        return "METHOD=GetExpressCheckoutDetails&TOKEN=EC-81B81448TC460182J&USER=x&PWD=y&VERSION=94.0&SIGNATURE=z&PAYMENTREQUEST_0_CURRENCYCODE=EUR";
    }

    private function successful_get_details_for_token_response()
    {
        return "TOKEN=EC%2d81B81448TC460182J&CHECKOUTSTATUS=PaymentActionNotInitiated&TIMESTAMP=2013%2d07%2d10T20%3a39%3a32Z&CORRELATIONID=ffb0d99d6c73e&ACK=Success&VERSION=94%2e0&BUILD=6825724&EMAIL=andreas%2ekollaros%2epersonal%40gmail%2ecom&PAYERID=3L2BZTVKN8N7G&PAYERSTATUS=verified&FIRSTNAME=Andreas&LASTNAME=Kollaros&COUNTRYCODE=GB&SHIPTONAME=Andreas%20Kollaros&SHIPTOSTREET=1234%20Penny%20Lane&SHIPTOSTREET2=2nd%20Floor&SHIPTOCITY=Durham&SHIPTOSTATE=NC&SHIPTOZIP=27701&SHIPTOCOUNTRYCODE=US&SHIPTOCOUNTRYNAME=United%20States&ADDRESSSTATUS=Confirmed&CURRENCYCODE=EUR&AMT=10%2e00&SHIPPINGAMT=0%2e00&HANDLINGAMT=0%2e00&TAXAMT=0%2e00&INSURANCEAMT=0%2e00&SHIPDISCAMT=0%2e00&PAYMENTREQUEST_0_CURRENCYCODE=EUR&PAYMENTREQUEST_0_AMT=10%2e00&PAYMENTREQUEST_0_SHIPPINGAMT=0%2e00&PAYMENTREQUEST_0_HANDLINGAMT=0%2e00&PAYMENTREQUEST_0_TAXAMT=0%2e00&PAYMENTREQUEST_0_INSURANCEAMT=0%2e00&PAYMENTREQUEST_0_SHIPDISCAMT=0%2e00&PAYMENTREQUEST_0_INSURANCEOPTIONOFFERED=false&PAYMENTREQUEST_0_SHIPTONAME=Andreas%20Kollaros&PAYMENTREQUEST_0_SHIPTOSTREET=1234%20Penny%20Lane&PAYMENTREQUEST_0_SHIPTOSTREET2=2nd%20Floor&PAYMENTREQUEST_0_SHIPTOCITY=Durham&PAYMENTREQUEST_0_SHIPTOSTATE=NC&PAYMENTREQUEST_0_SHIPTOZIP=27701&PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE=US&PAYMENTREQUEST_0_SHIPTOCOUNTRYNAME=United%20States&PAYMENTREQUEST_0_ADDRESSSTATUS=Confirmed&PAYMENTREQUESTINFO_0_ERRORCODE=0";
    }

    public function testAuthorize()
    {

        $this->mock_request($this->successful_authorize_response());

        $amount = 10;
        $token = 'EC-81B81448TC460182J';
        $payer_id = '3L2BZTVKN8N7G';
        $options = array('token' => $token, 'payer_id' => $payer_id);

        $response = $this->gateway->authorize($amount, $options);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_authorize_request(),
            $request_body
        );

        $this->assert_success($response);
    }

    private function successful_authorize_request()
    {
        return "METHOD=DoExpressCheckoutPayment&PAYMENTREQUEST_0_AMT=10.00&TOKEN=EC-81B81448TC460182J&PAYERID=3L2BZTVKN8N7G&USER=x&PWD=y&VERSION=94.0&SIGNATURE=z&PAYMENTREQUEST_0_CURRENCYCODE=EUR&PAYMENTREQUEST_0_PAYMENTACTION=Authorization";
    }

    private function successful_authorize_response()
    {
        return "TOKEN=EC%2d81B81448TC460182J&SUCCESSPAGEREDIRECTREQUESTED=false&TIMESTAMP=2013%2d07%2d10T20%3a39%3a36Z&CORRELATIONID=39d0dd30dc347&ACK=Success&VERSION=94%2e0&BUILD=6825724&INSURANCEOPTIONSELECTED=false&SHIPPINGOPTIONISDEFAULT=false&PAYMENTINFO_0_TRANSACTIONID=7D898616SW447534J&PAYMENTINFO_0_TRANSACTIONTYPE=expresscheckout&PAYMENTINFO_0_PAYMENTTYPE=instant&PAYMENTINFO_0_ORDERTIME=2013%2d07%2d10T20%3a39%3a35Z&PAYMENTINFO_0_AMT=10%2e00&PAYMENTINFO_0_FEEAMT=0%2e69&PAYMENTINFO_0_TAXAMT=0%2e00&PAYMENTINFO_0_CURRENCYCODE=EUR&PAYMENTINFO_0_PAYMENTSTATUS=Completed&PAYMENTINFO_0_PENDINGREASON=None&PAYMENTINFO_0_REASONCODE=None&PAYMENTINFO_0_PROTECTIONELIGIBILITY=Eligible&PAYMENTINFO_0_PROTECTIONELIGIBILITYTYPE=ItemNotReceivedEligible%2cUnauthorizedPaymentEligible&PAYMENTINFO_0_SECUREMERCHANTACCOUNTID=XXXXXXXXXXXXX&PAYMENTINFO_0_ERRORCODE=0&PAYMENTINFO_0_ACK=Success";
    }

    public function testSetExpressPurchase()
    {
        $options = array(
            'return_url' => 'http://example.com',
            'cancel_return_url' => 'http://example.com',
        );
        $options = array_merge($this->options, $options);

        $this->mock_request($this->successful_setup_purchase_response());

        $response = $this->gateway->setupPurchase($this->amount, $options);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_setup_purchase_request(),
            $request_body
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());

        $token = $response->token();
        $this->assertFalse(empty($token));
    }

    private function successful_setup_purchase_request()
    {
        return "PAYMENTREQUEST_0_SHIPTOSTREET=1234+Penny+Lane&PAYMENTREQUEST_0_SHIPTOCITY=Jonsetown&PAYMENTREQUEST_0_SHIPTOSTATE=NC&PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE=US&PAYMENTREQUEST_0_SHIPTOZIP=23456&METHOD=SetExpressCheckout&PAYMENTREQUEST_0_AMT=100.00&RETURNURL=http%3A%2F%2Fexample.com&CANCELURL=http%3A%2F%2Fexample.com&EMAIL=buyer%40email.com&USER=x&PWD=y&VERSION=94.0&SIGNATURE=z&PAYMENTREQUEST_0_CURRENCYCODE=EUR&PAYMENTREQUEST_0_PAYMENTACTION=Sale";
    }

    private function successful_setup_purchase_response()
    {
        return "TOKEN=EC%2d2KK82117LS1153937&TIMESTAMP=2012%2d10%2d04T00%3a33%3a10Z&CORRELATIONID=5d59521b7935a&ACK=Success&VERSION=63%2e0&BUILD=3881757";
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailAmount()
    {
       $this->gateway->amount('string');
    }

    public function testSuccessCapture()
    {
        $options = array(
            'complete_type' => 'Complete'
        );
        $amount = 46;
        $authorization = '2HR32227AR146560V';

        $this->mock_request($this->successful_capture_response());

        $response = $this->gateway->capture($amount, $authorization, $options);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_capture_request(),
            $request_body
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    private function successful_capture_request()
    {
        return "METHOD=DoCapture&AMT=46.00&AUTHORIZATIONID=2HR32227AR146560V&COMPLETETYPE=Complete&CURRENCYCODE=EUR&USER=x&PWD=y&VERSION=94.0&SIGNATURE=z&PAYMENTREQUEST_0_CURRENCYCODE=EUR";
    }

    private function successful_capture_response()
    {
        return "AUTHORIZATIONID=2HR32227AR146560V&TIMESTAMP=2012%2d12%2d30T00%3a10%3a28Z&CORRELATIONID=63805563cde4f&ACK=Success&VERSION=94%2e0&BUILD=4181146&TRANSACTIONID=36D84615R8185650E&PARENTTRANSACTIONID=2HR32227AR146560V&TRANSACTIONTYPE=expresscheckout&PAYMENTTYPE=instant&ORDERTIME=2012%2d12%2d30T00%3a10%3a26Z&AMT=46%2e00&FEEAMT=1%2e63&TAXAMT=0%2e00&CURRENCYCODE=EUR&PAYMENTSTATUS=Completed&PENDINGREASON=None&REASONCODE=None&PROTECTIONELIGIBILITY=Eligible&PROTECTIONELIGIBILITYTYPE=ItemNotReceivedEligible%2cUnauthorizedPaymentEligible";
    }

    public function testSuccessCredit()
    {
        $options = array(
            'refund_type' => 'Full'
        );
        $amount = 46;
        $identification = '36D84615R8185650E';

        $this->mock_request($this->successful_credit_response());

        $response = $this->gateway->credit($amount, $identification, $options);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_credit_request(),
            $request_body
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    private function successful_credit_request()
    {
        return "REFUNDTYPE=Full&TRANSACTIONID=36D84615R8185650E&METHOD=RefundTransaction&USER=x&PWD=y&VERSION=94.0&SIGNATURE=z&PAYMENTREQUEST_0_CURRENCYCODE=EUR";
    }

    private function successful_credit_response()
    {
        return "REFUNDTRANSACTIONID=98R99007WE150102S&FEEREFUNDAMT=1%2e33&GROSSREFUNDAMT=46%2e00&NETREFUNDAMT=44%2e67&CURRENCYCODE=EUR&TOTALREFUNDEDAMOUNT=46%2e00&TIMESTAMP=2012%2d12%2d30T00%3a13%3a21Z&CORRELATIONID=b906e26f283d&ACK=Success&VERSION=94%2e0&BUILD=4181146&REFUNDSTATUS=Instant&PENDINGREASON=None";
    }


    public function testSuccessVoid()
    {
        $authorization = '0NT65969AG744642L';

        $this->mock_request($this->successful_void_response());

        $response = $this->gateway->void($authorization);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_void_request(),
            $request_body
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    private function successful_void_request()
    {
        return "METHOD=DoVoid&AUTHORIZATIONID=0NT65969AG744642L&USER=x&PWD=y&VERSION=94.0&SIGNATURE=z&PAYMENTREQUEST_0_CURRENCYCODE=EUR";
    }

    private function successful_void_response()
    {
        return "AUTHORIZATIONID=0NT65969AG744642L&TIMESTAMP=2012%2d12%2d30T00%3a16%3a17Z&CORRELATIONID=931e3b8291997&ACK=Success&VERSION=94%2e0&BUILD=4181146";
    }
}
