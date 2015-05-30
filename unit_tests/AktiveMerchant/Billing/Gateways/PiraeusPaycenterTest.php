<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\PiraeusPaycenter;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Event\RequestEvents;

/**
 * Unit test PiraeusPaycenter
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
class PiraeusPaycenterTest extends \AktiveMerchant\TestCase
{

    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    /**
     * Setup
     */
    function setUp()
    {
        Base::mode('test');


        $options = $this->getFixtures()->offsetGet('piraeus_paycenter');

        $this->gateway = new PiraeusPaycenter($options);

        $this->amount = 1;
        $this->creditcard = new CreditCard(array(
            "first_name" => "John",
            "last_name" => "Doe",
            "number" => "4111111111111111",
            "month" => "01",
            "year" => date('Y') + 1,
            "verification_value" => "000"
        )
    );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'Test Transaction',
            'cavv' => '',
            'eci_flag' => '07',
            'xid' => '',
            'enrolled' => 'N',
            'pares_status' => 'U',
            'signature_verification' => 'U',
            'country' => 'US',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
        );
    }

    /**
     * Tests
     */

    public function testInitialization()
    {
        $this->assertNotNull($this->gateway);

        $this->assertNotNull($this->creditcard);

        $this->assertImplementation(
            array(
                'Charge',
                'Credit'
            )
        );
    }

    /**
     * @dataProvider casesProvider
     */
    public function testSuccessfulPurchase($options, $expected)
    {
        /*$this->gateway->addListener(RequestEvents::POST_SEND, function($event){
            var_dump($event->getRequest()->getAdapter()->getRequestBodyXml());
            var_dump($event->getRequest()->getAdapter()->getResponseBodyXml());
        });*/

        $method = "successful_test_case_".$options['case']."_visa_purchase_response";
        if (method_exists($this, $method)) {
            $this->mock_request($this->$method());
        }

        $this->creditcard->number = $options['card_number'];
        $this->creditcard->month  = $options['month'];
        $action = $options['action'];

        $response = $this->gateway->$action(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $assert_method = end($expected);
        $this->$assert_method($response);
        $this->assertTrue($response->test());
        $this->assertEquals($expected['ResultCode'], $response->result_code);
        $this->assertEquals($expected['ResponseCode'], $response->response_code);
        $this->assertEquals($expected['Message'], $response->message());
    }

    public function casesProvider()
    {
        return array(
            array(# TestCase01
                array('action' => 'purchase', 'card_number' => '4111111111111111', 'month' => '01', 'case' => '01'),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),
            array(# TestCase02
                array('action' => 'purchase', 'card_number' => '4111111111111111', 'month' => '02', 'case' => '02'),
                array('ResultCode' => '0', 'ResponseCode' => '12', 'Message' => 'Declined', 'assert_failure')
            ),
            array(# TestCase03
                array('action' => 'purchase', 'card_number' => '4111111111111111', 'month' => '03', 'case' => '03'),
                array('ResultCode' => '0', 'ResponseCode' => '11', 'Message' => 'Transaction already processed and completed', 'assert_success')
            ),
            array(# TestCase04
                array('action' => 'purchase', 'card_number' => '4111111111111111', 'month' => '04', 'case' => '04'),
                array('ResultCode' => '500', 'ResponseCode' => null, 'Message' => 'Communication error', 'assert_failure')
            ),
            array(# TestCase05
                array('action' => 'purchase', 'card_number' => '4111111111111111', 'month' => '05', 'case' => '05'),
                array('ResultCode' => '981', 'ResponseCode' => null, 'Message' => 'Invalid Card number/Exp Month/Exp Year', 'assert_failure')
            ),
            array(# TestCase06
                array('action' => 'purchase', 'card_number' => '4111111111111111', 'month' => '06', 'case' => '06'),
                array('ResultCode' => '1045', 'ResponseCode' => null, 'Message' => 'Duplicate transaction references are not allowed', 'assert_failure')
            ),
        );
    }

    public function testCase01VisaPurchase()
    {
        $this->mock_request($this->successful_test_case_01_visa_purchase_response());

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
        $this->assertEquals('0', $response->result_code);
        $this->assertEquals('00', $response->response_code);
        $this->assertEquals('Approved or completed successfully', $response->message());
    }

    public function testCase02VisaPurchase()
    {
        $this->mock_request($this->successful_test_case_02_visa_purchase_response());

        $this->creditcard->month = "02";

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_failure($response);
        $this->assertTrue($response->test());
        $this->assertEquals('0', $response->result_code);
        $this->assertEquals('12', $response->response_code);
        $this->assertEquals('Declined', $response->message());
    }

    public function testCase03VisaPurchase()
    {
        $this->mock_request($this->successful_test_case_03_visa_purchase_response());

        $this->creditcard->month = "03";

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
        $this->assertEquals('0', $response->result_code);
        $this->assertEquals('11', $response->response_code);
        $this->assertEquals('Transaction already processed and completed', $response->message());
    }

    public function testCase04VisaPurchase()
    {
        $this->mock_request($this->successful_test_case_04_visa_purchase_response());

        $this->creditcard->month = "04";

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_failure($response);
        $this->assertTrue($response->test());
        $this->assertEquals('500', $response->result_code);
        $this->assertNull($response->response_code);
        $this->assertEquals('Communication error', $response->message());
    }

    public function testCase05VisaPurchase()
    {
        $this->mock_request($this->successful_test_case_05_visa_purchase_response());

        $this->creditcard->month = "05";

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_failure($response);
        $this->assertTrue($response->test());
        $this->assertEquals('981', $response->result_code);
        $this->assertNull($response->response_code);
        $this->assertEquals('Invalid Card number/Exp Month/Exp Year', $response->message());
    }

    public function testCase06VisaPurchase()
    {
        $this->mock_request($this->successful_test_case_06_visa_purchase_response());

        $this->creditcard->month = "06";

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_failure($response);
        $this->assertTrue($response->test());
        $this->assertEquals('1045', $response->result_code);
        $this->assertNull($response->response_code);
        $this->assertEquals('Duplicate transaction references are not allowed', $response->message());
    }

    public function testAuthorize()
    {
        $this->mock_request($this->successful_authorize_response());

        $this->creditcard->number = '4000000000000002';

        $response = $this->gateway->authorize(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
        $this->assertEquals('0', $response->result_code);
        $this->assertEquals('00', $response->response_code);
        $this->assertEquals('Approved or completed successfully', $response->message());
    }

    public function testCapture()
    {
        $this->mock_request($this->successful_capture_response());

        $authorization = '37176857';
        $response = $this->gateway->capture(
            $this->amount,
            $authorization,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
        $this->assertEquals('0', $response->result_code);
        $this->assertEquals('00', $response->response_code);
        $this->assertEquals('Approved or completed successfully', $response->message());
    }

    public function testCredit()
    {
        $this->mock_request($this->successful_credit_response());

        $identification = '37176983';
        $response = $this->gateway->credit(
            $this->amount,
            $identification,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
        $this->assertEquals('0', $response->result_code);
        $this->assertEquals('00', $response->response_code);
        $this->assertEquals('Approved or completed successfully', $response->message());
    }

    public function testVoid()
    {
        $this->mock_request($this->successful_void_response());

        $authorization = '37176565';
        $options = array('money' => 1, 'order_id' => $this->gateway->generateUniqueId());
        $response = $this->gateway->void($authorization, $options);

        $this->assert_success($response);
        $this->assertTrue($response->test());
        $this->assertEquals('0', $response->result_code);
        $this->assertEquals('00', $response->response_code);
        $this->assertEquals('Approved or completed successfully', $response->message());
    }

    private function successful_test_case_01_visa_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:46946712;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":11:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:34:"Approved or completed successfully";s:13:"TransactionID";i:37095053;s:19:"TransactionDateTime";s:19:"2015-05-28T13:25:23";s:19:"TransactionTraceNum";i:5;s:17:"MerchantReference";s:13:"REF7903390935";s:12:"ApprovalCode";s:6:"713427";s:12:"RetrievalRef";s:12:"713427713427";s:9:"PackageNo";i:6;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_02_visa_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:46946947;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":11:{s:10:"StatusFlag";s:7:"Failure";s:12:"ResponseCode";s:2:"12";s:19:"ResponseDescription";s:8:"Declined";s:13:"TransactionID";i:37095234;s:19:"TransactionDateTime";s:19:"2015-05-28T13:30:30";s:19:"TransactionTraceNum";i:6;s:17:"MerchantReference";s:13:"REF8273302155";s:12:"ApprovalCode";N;s:12:"RetrievalRef";s:12:"870438870438";s:9:"PackageNo";i:6;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_03_visa_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:46955516;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":11:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"11";s:19:"ResponseDescription";s:43:"Transaction already processed and completed";s:13:"TransactionID";i:37101879;s:19:"TransactionDateTime";s:19:"2015-05-28T16:27:55";s:19:"TransactionTraceNum";i:11;s:17:"MerchantReference";s:13:"REF1843143923";s:12:"ApprovalCode";s:6:"381342";s:12:"RetrievalRef";s:12:"381342381342";s:9:"PackageNo";i:6;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_04_visa_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:500;s:17:"ResultDescription";s:19:"Communication error";s:18:"SupportReferenceID";i:46955672;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":9:{s:10:"StatusFlag";s:7:"Failure";s:13:"TransactionID";N;s:19:"TransactionDateTime";N;s:19:"TransactionTraceNum";N;s:17:"MerchantReference";s:13:"REF2364377175";s:12:"ApprovalCode";N;s:12:"RetrievalRef";N;s:9:"PackageNo";N;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_05_visa_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:981;s:17:"ResultDescription";s:38:"Invalid Card number/Exp Month/Exp Year";s:18:"SupportReferenceID";i:46955734;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":9:{s:10:"StatusFlag";s:7:"Failure";s:13:"TransactionID";N;s:19:"TransactionDateTime";N;s:19:"TransactionTraceNum";N;s:17:"MerchantReference";s:13:"REF1419591438";s:12:"ApprovalCode";N;s:12:"RetrievalRef";N;s:9:"PackageNo";N;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_06_visa_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:1045;s:17:"ResultDescription";s:48:"Duplicate transaction references are not allowed";s:18:"SupportReferenceID";i:46956561;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":9:{s:10:"StatusFlag";s:7:"Failure";s:13:"TransactionID";N;s:19:"TransactionDateTime";N;s:19:"TransactionTraceNum";N;s:17:"MerchantReference";s:13:"REF1165494255";s:12:"ApprovalCode";N;s:12:"RetrievalRef";N;s:9:"PackageNo";N;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_void_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:6:"REFUND";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:47060223;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":11:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:34:"Approved or completed successfully";s:13:"TransactionID";i:37176576;s:19:"TransactionDateTime";s:19:"2015-05-30T20:37:09";s:19:"TransactionTraceNum";i:3;s:17:"MerchantReference";s:13:"REF5936830445";s:12:"ApprovalCode";s:0:"";s:12:"RetrievalRef";s:12:"112629112629";s:9:"PackageNo";i:7;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_capture_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:6:"SETTLE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:47060822;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":11:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:34:"Approved or completed successfully";s:13:"TransactionID";i:37176940;s:19:"TransactionDateTime";s:19:"2015-05-30T20:51:55";s:19:"TransactionTraceNum";i:5;s:17:"MerchantReference";s:13:"REF1254604854";s:12:"ApprovalCode";s:6:"967550";s:12:"RetrievalRef";s:12:"048980048980";s:9:"PackageNo";i:7;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_credit_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:11:"VOIDREQUEST";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:47060877;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":11:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:34:"Approved or completed successfully";s:13:"TransactionID";i:37176983;s:19:"TransactionDateTime";N;s:19:"TransactionTraceNum";N;s:17:"MerchantReference";N;s:12:"ApprovalCode";N;s:12:"RetrievalRef";N;s:9:"PackageNo";N;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_authorize_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:9:"AUTHORIZE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:47060877;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":11:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:34:"Approved or completed successfully";s:13:"TransactionID";i:37176983;s:19:"TransactionDateTime";s:19:"2015-05-30T20:53:52";s:19:"TransactionTraceNum";i:6;s:17:"MerchantReference";s:13:"REF1637771035";s:12:"ApprovalCode";s:6:"005925";s:12:"RetrievalRef";s:12:"005925005925";s:9:"PackageNo";i:7;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }
}
