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
    public function setUp()
    {
        Base::mode('test');

        $options = $this->getFixtures()->offsetGet('piraeus_paycenter');

        $this->gateway = new PiraeusPaycenter($options);

        $this->amount = 1;
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4111111111111111",
                "month" => "01",
                "year" => date('Y') + 1,
                "verification_value" => "123"
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
    public function testPaycenterTestCases($options, $expected)
    {
        $method = "successful_test_case_".$options['case']."_".$options['action']."_response";
        if (method_exists($this, $method)) {
            $this->mock_request($this->$method());
        } else {
            /*$this->gateway->addListener(RequestEvents::POST_SEND, function($event){
                var_dump($event->getRequest()->getAdapter()->getRequestBodyXml());
                var_dump($event->getRequest()->getAdapter()->getResponseBodyXml());
            });*/
        }

        $this->creditcard->number = $options['card_number'];
        $this->creditcard->month  = $options['month'];
        $this->creditcard->type   = CreditCard::type($this->creditcard->number);
        if (isset($options['cvv'])) {
            $this->creditcard->verification_value = $options['cvv'];
        }
        $action = $options['action'];
        if (isset($options['installments'])) {
            $this->options['installments'] = $options['installments'];
        }

        if(isset($options['currency'])) {
            $this->options['currency'] = $options['currency'];
        }
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
            array(# TestCase07
                array('action' => 'purchase', 'card_number' => '4111111111111111', 'month' => '07', 'case' => '07'),
                array('ResultCode' => '1072', 'ResponseCode' => null, 'Message' => 'Pack is still closing', 'assert_failure')
            ),
            array(# TestCase08
                array('action' => 'purchase', 'card_number' => '4111111111111111', 'month' => '08', 'case' => '08'),
                array('ResultCode' => '1', 'ResponseCode' => null, 'Message' => 'An error occured. Please check your data or else contact Winbank PayCenter administrator', 'assert_failure')
            ),
            array(# TestCase09
                array('action' => 'purchase', 'card_number' => '4908440000000003', 'month' => '08', 'case' => '09', 'installments' => 3),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),
            array(# TestCase10
                array('action' => 'purchase', 'card_number' => '5100150000000001', 'month' => '01', 'case' => '10'),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),
            array(# TestCase11
                array('action' => 'purchase', 'card_number' => '6773111111111115', 'month' => '01', 'case' => '11'),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),/*
            array(# TestCase12. [Terminal does not support given card type]
                array('action' => 'purchase', 'card_number' => '36131111111119', 'month' => '01', 'case' => '12'),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),
            array(# TestCase13. [Test Case Not Found]
                array('action' => 'purchase', 'card_number' => '6011111111111117', 'month' => '01', 'case' => '13'),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),
            array(# TestCase14. [Terminal does not support given card type]
                array('action' => 'purchase', 'card_number' => '375537111111116', 'month' => '01', 'case' => '14', 'cvv' => 1234),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),
            array(# TestCase15 [Terminal does not support given currency]
                array('action' => 'purchase', 'card_number' => '4001151111111110', 'month' => '01', 'case' => '15', 'currency' => 'GBP'),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),
            array(# TestCase16 [Terminal does not support given currency]
                array('action' => 'purchase', 'card_number' => '4408661111111117', 'month' => '01', 'case' => '16', 'currency' => 'USD'),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),*/
            array(# TestCase01
                array('action' => 'authorize', 'card_number' => '4000000000000002', 'month' => '01', 'case' => '01'),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),
            array(# TestCase02
                array('action' => 'authorize', 'card_number' => '4000000000000002', 'month' => '02', 'case' => '02'),
                array('ResultCode' => '0', 'ResponseCode' => '12', 'Message' => 'Declined', 'assert_failure')
            ),
            array(# TestCase03
                array('action' => 'authorize', 'card_number' => '4000000000000002', 'month' => '03', 'case' => '03'),
                array('ResultCode' => '0', 'ResponseCode' => '11', 'Message' => 'Transaction already processed and completed', 'assert_success')
            ),
            array(# TestCase04
                array('action' => 'authorize', 'card_number' => '4000000000000002', 'month' => '04', 'case' => '04'),
                array('ResultCode' => '500', 'ResponseCode' => null, 'Message' => 'Communication error', 'assert_failure')
            ),
            array(# TestCase05
                array('action' => 'authorize', 'card_number' => '4000000000000002', 'month' => '05', 'case' => '05'),
                array('ResultCode' => '981', 'ResponseCode' => null, 'Message' => 'Invalid Card number/Exp Month/Exp Year', 'assert_failure')
            ),
            array(# TestCase06
                array('action' => 'authorize', 'card_number' => '4000000000000002', 'month' => '06', 'case' => '06'),
                array('ResultCode' => '1045', 'ResponseCode' => null, 'Message' => 'Duplicate transaction references are not allowed', 'assert_failure')
            ),
            array(# TestCase07
                array('action' => 'authorize', 'card_number' => '4000000000000002', 'month' => '07', 'case' => '07'),
                array('ResultCode' => '1072', 'ResponseCode' => null, 'Message' => 'Pack is still closing', 'assert_failure')
            ),
            array(# TestCase08
                array('action' => 'authorize', 'card_number' => '4000000000000002', 'month' => '08', 'case' => '08'),
                array('ResultCode' => '1', 'ResponseCode' => null, 'Message' => 'An error occured. Please check your data or else contact Winbank PayCenter administrator', 'assert_failure')
            ),
            array(# TestCase09
                array('action' => 'authorize', 'card_number' => '4908460000000001', 'month' => '08', 'case' => '09', 'installments' => 3),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),
            array(# TestCase10
                array('action' => 'authorize', 'card_number' => '5100160000000000', 'month' => '01', 'case' => '10'),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),
            array(# TestCase11
                array('action' => 'authorize', 'card_number' => '6773110000000009', 'month' => '01', 'case' => '11'),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),/*
            array(# TestCase12. [Terminal does not support given card type]
                array('action' => 'authorize', 'card_number' => '36131111111119', 'month' => '01', 'case' => '12'),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),
            array(# TestCase13. [Test Case Not Found]
                array('action' => 'authorize', 'card_number' => '6011111111111117', 'month' => '01', 'case' => '13'),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),
            array(# TestCase14. [Terminal does not support given card type]
                array('action' => 'authorize', 'card_number' => '375537111111116', 'month' => '01', 'case' => '14', 'cvv' => 1234),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),
            array(# TestCase15 [Terminal does not support given currency]
                array('action' => 'authorize', 'card_number' => '4001151111111110', 'month' => '01', 'case' => '15', 'currency' => 'GBP'),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),
            array(# TestCase16 [Terminal does not support given currency]
                array('action' => 'authorize', 'card_number' => '4408661111111117', 'month' => '01', 'case' => '16', 'currency' => 'USD'),
                array('ResultCode' => '0', 'ResponseCode' => '00', 'Message' => 'Approved or completed successfully', 'assert_success')
            ),*/
        );
    }

    public function testPurchase()
    {
        $this->mock_request($this->successful_test_case_01_purchase_response());

        $this->creditcard->number = '4111111111111111';

        $response = $this->gateway->purchase(
            2000.15,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
        $this->assertEquals('0', $response->result_code);
        $this->assertEquals('00', $response->response_code);
        $this->assertEquals('Approved or completed successfully', $response->message());
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

    public function testTokenization()
    {
        $options = $this->getFixtures()->offsetGet('piraeus_paycenter_tokenization');
        $this->gateway = new PiraeusPaycenter($options);

        $this->mock_request($this->successful_store_response());

        $response = $this->gateway->store($this->creditcard, $this->options);

        $this->assert_success($response);
    }

    public function testInstallmentsSupport()
    {
        $this->mock_request($this->successfull_installments_support_response());

        $this->creditcard->number = '4908440000000003';
        $amount = 120;
        $response = $this->gateway->supportsInstallment($amount, $this->creditcard);

        $this->assert_success($response);
    }



    public function testCharge()
    {
        $options = $this->getFixtures()->offsetGet('piraeus_paycenter_tokenization');
        $this->gateway = new PiraeusPaycenter($options);

        $this->mock_request($this->successful_charge_response());

        $this->creditcard->first_name = null;
        $this->creditcard->last_name = null;
        $this->creditcard->number = '8888889256045945';
        $this->creditcard->verification_value = null;
        $this->creditcard->month = null;
        $this->creditcard->year = null;
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
    }

    public function testFollowUp()
    {
        $this->mock_request($this->successful_followup_response());

        $merchantReference = 'REF2961958575';
        $response = $this->gateway->followUp($merchantReference);

        $this->assert_success($response);
        $this->assertTrue($response->test());
        $this->assertEquals('0', $response->result_code);
        $this->assertEquals('00', $response->response_code);
        $this->assertEquals('Approved or completed successfully', $response->message());
    }

    public function testIsAvailable()
    {
        $this->mock_request($this->successfull_is_available_response());

        $response = $this->gateway->isAvailable();

        $this->assert_success($response);
        $this->assertTrue($response->test());
        $this->assertEquals('0', $response->result_code);
        $this->assertEquals('00', $response->response_code);
    }

    private function successful_test_case_01_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:46946712;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":11:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:34:"Approved or completed successfully";s:13:"TransactionID";i:37095053;s:19:"TransactionDateTime";s:19:"2015-05-28T13:25:23";s:19:"TransactionTraceNum";i:5;s:17:"MerchantReference";s:13:"REF7903390935";s:12:"ApprovalCode";s:6:"713427";s:12:"RetrievalRef";s:12:"713427713427";s:9:"PackageNo";i:6;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_02_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:46946947;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":11:{s:10:"StatusFlag";s:7:"Failure";s:12:"ResponseCode";s:2:"12";s:19:"ResponseDescription";s:8:"Declined";s:13:"TransactionID";i:37095234;s:19:"TransactionDateTime";s:19:"2015-05-28T13:30:30";s:19:"TransactionTraceNum";i:6;s:17:"MerchantReference";s:13:"REF8273302155";s:12:"ApprovalCode";N;s:12:"RetrievalRef";s:12:"870438870438";s:9:"PackageNo";i:6;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_03_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:46955516;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":11:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"11";s:19:"ResponseDescription";s:43:"Transaction already processed and completed";s:13:"TransactionID";i:37101879;s:19:"TransactionDateTime";s:19:"2015-05-28T16:27:55";s:19:"TransactionTraceNum";i:11;s:17:"MerchantReference";s:13:"REF1843143923";s:12:"ApprovalCode";s:6:"381342";s:12:"RetrievalRef";s:12:"381342381342";s:9:"PackageNo";i:6;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_04_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:500;s:17:"ResultDescription";s:19:"Communication error";s:18:"SupportReferenceID";i:46955672;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":9:{s:10:"StatusFlag";s:7:"Failure";s:13:"TransactionID";N;s:19:"TransactionDateTime";N;s:19:"TransactionTraceNum";N;s:17:"MerchantReference";s:13:"REF2364377175";s:12:"ApprovalCode";N;s:12:"RetrievalRef";N;s:9:"PackageNo";N;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_05_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:981;s:17:"ResultDescription";s:38:"Invalid Card number/Exp Month/Exp Year";s:18:"SupportReferenceID";i:46955734;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":9:{s:10:"StatusFlag";s:7:"Failure";s:13:"TransactionID";N;s:19:"TransactionDateTime";N;s:19:"TransactionTraceNum";N;s:17:"MerchantReference";s:13:"REF1419591438";s:12:"ApprovalCode";N;s:12:"RetrievalRef";N;s:9:"PackageNo";N;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_06_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:1045;s:17:"ResultDescription";s:48:"Duplicate transaction references are not allowed";s:18:"SupportReferenceID";i:46956561;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":9:{s:10:"StatusFlag";s:7:"Failure";s:13:"TransactionID";N;s:19:"TransactionDateTime";N;s:19:"TransactionTraceNum";N;s:17:"MerchantReference";s:13:"REF1165494255";s:12:"ApprovalCode";N;s:12:"RetrievalRef";N;s:9:"PackageNo";N;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_07_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:1072;s:17:"ResultDescription";s:21:"Pack is still closing";s:18:"SupportReferenceID";i:48323674;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":9:{s:10:"StatusFlag";s:7:"Failure";s:13:"TransactionID";N;s:19:"TransactionDateTime";N;s:19:"TransactionTraceNum";N;s:17:"MerchantReference";s:13:"REF5496273385";s:12:"ApprovalCode";N;s:12:"RetrievalRef";N;s:9:"PackageNo";N;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_08_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:1;s:17:"ResultDescription";s:88:"An error occured. Please check your data or else contact Winbank PayCenter administrator";s:18:"SupportReferenceID";i:48323766;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":9:{s:10:"StatusFlag";s:7:"Failure";s:13:"TransactionID";N;s:19:"TransactionDateTime";N;s:19:"TransactionTraceNum";N;s:17:"MerchantReference";s:13:"REF1390241569";s:12:"ApprovalCode";N;s:12:"RetrievalRef";N;s:9:"PackageNo";N;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_09_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:48325481;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":12:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:34:"Approved or completed successfully";s:13:"TransactionID";i:38055603;s:19:"TransactionDateTime";s:19:"2015-06-25T12:15:13";s:19:"TransactionTraceNum";i:12;s:17:"MerchantReference";s:13:"REF7736856415";s:12:"ApprovalCode";s:6:"889903";s:12:"RetrievalRef";s:12:"889903889903";s:9:"PackageNo";i:61;s:10:"SessionKey";N;s:5:"Token";s:16:"8888885995495110";}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_10_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:48324647;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":12:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:34:"Approved or completed successfully";s:13:"TransactionID";i:38054968;s:19:"TransactionDateTime";s:19:"2015-06-25T11:58:19";s:19:"TransactionTraceNum";i:9;s:17:"MerchantReference";s:13:"REF1770580602";s:12:"ApprovalCode";s:6:"683892";s:12:"RetrievalRef";s:12:"683892683892";s:9:"PackageNo";i:61;s:10:"SessionKey";N;s:5:"Token";s:16:"8888881404604475";}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_11_purchase_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:48325597;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":12:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:34:"Approved or completed successfully";s:13:"TransactionID";i:38055683;s:19:"TransactionDateTime";s:19:"2015-06-25T12:17:23";s:19:"TransactionTraceNum";i:14;s:17:"MerchantReference";s:13:"REF3426314355";s:12:"ApprovalCode";s:6:"491079";s:12:"RetrievalRef";s:12:"491079491079";s:9:"PackageNo";i:61;s:10:"SessionKey";N;s:5:"Token";s:16:"8888882500835898";}}}}';

        return unserialize($serialized);
    }

    private function successful_test_case_01_authorize_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:9:"AUTHORIZE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:48327765;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":12:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:34:"Approved or completed successfully";s:13:"TransactionID";i:38057251;s:19:"TransactionDateTime";s:19:"2015-06-25T13:01:32";s:19:"TransactionTraceNum";i:38;s:17:"MerchantReference";s:13:"REF5691670995";s:12:"ApprovalCode";s:6:"541806";s:12:"RetrievalRef";s:12:"541806541806";s:9:"PackageNo";i:61;s:10:"SessionKey";N;s:5:"Token";s:16:"8888886940550462";}}}}';
        return unserialize($serialized);
    }

    private function successful_test_case_02_authorize_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:9:"AUTHORIZE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:48327768;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":11:{s:10:"StatusFlag";s:7:"Failure";s:12:"ResponseCode";s:2:"12";s:19:"ResponseDescription";s:8:"Declined";s:13:"TransactionID";i:38057253;s:19:"TransactionDateTime";s:19:"2015-06-25T13:01:33";s:19:"TransactionTraceNum";i:39;s:17:"MerchantReference";s:13:"REF1710401275";s:12:"ApprovalCode";s:0:"";s:12:"RetrievalRef";s:12:"321273321273";s:9:"PackageNo";i:61;s:10:"SessionKey";N;}}}}';
        return unserialize($serialized);
    }

    private function successful_test_case_03_authorize_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:9:"AUTHORIZE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:48327769;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":11:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"11";s:19:"ResponseDescription";s:43:"Transaction already processed and completed";s:13:"TransactionID";i:38057255;s:19:"TransactionDateTime";s:19:"2015-06-25T13:01:34";s:19:"TransactionTraceNum";i:40;s:17:"MerchantReference";s:13:"REF1395187950";s:12:"ApprovalCode";s:6:"949726";s:12:"RetrievalRef";s:12:"949726949726";s:9:"PackageNo";i:61;s:10:"SessionKey";N;}}}}';
        return unserialize($serialized);
    }

    private function successful_test_case_04_authorize_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:9:"AUTHORIZE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:500;s:17:"ResultDescription";s:19:"Communication error";s:18:"SupportReferenceID";i:48327771;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":9:{s:10:"StatusFlag";s:7:"Failure";s:13:"TransactionID";N;s:19:"TransactionDateTime";N;s:19:"TransactionTraceNum";N;s:17:"MerchantReference";s:13:"REF6509767255";s:12:"ApprovalCode";N;s:12:"RetrievalRef";N;s:9:"PackageNo";N;s:10:"SessionKey";N;}}}}';
        return unserialize($serialized);
    }

    private function successful_test_case_05_authorize_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:9:"AUTHORIZE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:981;s:17:"ResultDescription";s:38:"Invalid Card number/Exp Month/Exp Year";s:18:"SupportReferenceID";i:48327772;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":9:{s:10:"StatusFlag";s:7:"Failure";s:13:"TransactionID";N;s:19:"TransactionDateTime";N;s:19:"TransactionTraceNum";N;s:17:"MerchantReference";s:13:"REF1482897762";s:12:"ApprovalCode";N;s:12:"RetrievalRef";N;s:9:"PackageNo";N;s:10:"SessionKey";N;}}}}';
        return unserialize($serialized);
    }

    private function successful_test_case_06_authorize_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:9:"AUTHORIZE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:1045;s:17:"ResultDescription";s:48:"Duplicate transaction references are not allowed";s:18:"SupportReferenceID";i:48327774;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":9:{s:10:"StatusFlag";s:7:"Failure";s:13:"TransactionID";N;s:19:"TransactionDateTime";N;s:19:"TransactionTraceNum";N;s:17:"MerchantReference";s:13:"REF1465392104";s:12:"ApprovalCode";N;s:12:"RetrievalRef";N;s:9:"PackageNo";N;s:10:"SessionKey";N;}}}}';
        return unserialize($serialized);
    }

    private function successful_test_case_07_authorize_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:9:"AUTHORIZE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:1072;s:17:"ResultDescription";s:21:"Pack is still closing";s:18:"SupportReferenceID";i:48327775;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":9:{s:10:"StatusFlag";s:7:"Failure";s:13:"TransactionID";N;s:19:"TransactionDateTime";N;s:19:"TransactionTraceNum";N;s:17:"MerchantReference";s:13:"REF1499399490";s:12:"ApprovalCode";N;s:12:"RetrievalRef";N;s:9:"PackageNo";N;s:10:"SessionKey";N;}}}}';
        return unserialize($serialized);
    }

    private function successful_test_case_08_authorize_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:9:"AUTHORIZE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:1;s:17:"ResultDescription";s:88:"An error occured. Please check your data or else contact Winbank PayCenter administrator";s:18:"SupportReferenceID";i:48327776;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":9:{s:10:"StatusFlag";s:7:"Failure";s:13:"TransactionID";N;s:19:"TransactionDateTime";N;s:19:"TransactionTraceNum";N;s:17:"MerchantReference";s:13:"REF1654944517";s:12:"ApprovalCode";N;s:12:"RetrievalRef";N;s:9:"PackageNo";N;s:10:"SessionKey";N;}}}}';
        return unserialize($serialized);
    }

    private function successful_test_case_09_authorize_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:9:"AUTHORIZE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:48327779;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":12:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:34:"Approved or completed successfully";s:13:"TransactionID";i:38057264;s:19:"TransactionDateTime";s:19:"2015-06-25T13:01:39";s:19:"TransactionTraceNum";i:46;s:17:"MerchantReference";s:13:"REF4458717085";s:12:"ApprovalCode";s:6:"462921";s:12:"RetrievalRef";s:12:"462921462921";s:9:"PackageNo";i:61;s:10:"SessionKey";N;s:5:"Token";s:16:"8888884802784618";}}}}';
        return unserialize($serialized);
    }

    private function successful_test_case_10_authorize_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:9:"AUTHORIZE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:48327781;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":12:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:34:"Approved or completed successfully";s:13:"TransactionID";i:38057266;s:19:"TransactionDateTime";s:19:"2015-06-25T13:01:40";s:19:"TransactionTraceNum";i:47;s:17:"MerchantReference";s:13:"REF6441345995";s:12:"ApprovalCode";s:6:"947622";s:12:"RetrievalRef";s:12:"947622947622";s:9:"PackageNo";i:61;s:10:"SessionKey";N;s:5:"Token";s:16:"8888889070124389";}}}}';
        return unserialize($serialized);
    }

    private function successful_test_case_11_authorize_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:9:"AUTHORIZE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:48327784;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":12:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:34:"Approved or completed successfully";s:13:"TransactionID";i:38057267;s:19:"TransactionDateTime";s:19:"2015-06-25T13:01:41";s:19:"TransactionTraceNum";i:48;s:17:"MerchantReference";s:13:"REF1128311090";s:12:"ApprovalCode";s:6:"264957";s:12:"RetrievalRef";s:12:"264957264957";s:9:"PackageNo";i:61;s:10:"SessionKey";N;s:5:"Token";s:16:"8888883622159274";}}}}';
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

    private function successful_store_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:18:"RequestTokenResult";O:8:"stdClass":6:{s:17:"MerchantReference";s:13:"REF2052753086";s:18:"SupportReferenceID";i:48764761;s:10:"StatusFlag";s:7:"Success";s:10:"ResultCode";s:1:"0";s:17:"ResultDescription";s:0:"";s:5:"Token";s:16:"8888889256045945";}}';

        return unserialize($serialized);
    }

    private function successful_charge_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:4:"SALE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:9:"eCommerce";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:48695150;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":11:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:34:"Approved or completed successfully";s:13:"TransactionID";i:38313565;s:19:"TransactionDateTime";s:19:"2015-07-03T17:41:06";s:19:"TransactionTraceNum";i:3;s:17:"MerchantReference";s:13:"REF7182310275";s:12:"ApprovalCode";s:6:"845556";s:12:"RetrievalRef";s:12:"845556845556";s:9:"PackageNo";i:4;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }

    private function successful_followup_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:9:"FOLLOW_UP";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"TR222222";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:49877382;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":12:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:34:"Approved or completed successfully";s:13:"TransactionID";i:39055402;s:19:"TransactionDateTime";s:19:"2015-07-31T14:27:51";s:19:"TransactionTraceNum";i:16;s:17:"MerchantReference";s:13:"REF2961958575";s:12:"ApprovalCode";s:6:"203858";s:12:"RetrievalRef";s:12:"203858203858";s:9:"PackageNo";i:78;s:10:"SessionKey";N;s:5:"Token";s:16:"8888889256045945";}}}}';

        return unserialize($serialized);
    }

    private function successfull_installments_support_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:30:"GetInstallmentsSupportedResult";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":2:{s:8:"Merchant";O:8:"stdClass":3:{s:10:"MerchantID";i:2222222222;s:10:"AcquirerID";s:5:"GR014";s:4:"User";s:8:"EV777777";}s:18:"SupportReferenceID";i:51697033;}s:4:"Body";O:8:"stdClass":4:{s:20:"SupportsInstallments";s:3:"Yes";s:12:"Installments";i:3;s:10:"ResultCode";i:0;s:17:"ResultDescription";s:0:"";}}}';

        return unserialize($serialized);
    }

    private function successfull_is_available_response()
    {
        $serialized = 'O:8:"stdClass":1:{s:19:"TransactionResponse";O:8:"stdClass":2:{s:6:"Header";O:8:"stdClass":5:{s:11:"RequestType";s:11:"ISAVAILABLE";s:12:"MerchantInfo";O:8:"stdClass":4:{s:10:"MerchantID";i:2222222222;s:5:"PosID";i:2222222222;s:11:"ChannelType";s:8:"3DSecure";s:4:"User";s:8:"EV777777";}s:10:"ResultCode";i:0;s:17:"ResultDescription";s:8:"No Error";s:18:"SupportReferenceID";i:56966842;}s:4:"Body";O:8:"stdClass":1:{s:15:"TransactionInfo";O:8:"stdClass":11:{s:10:"StatusFlag";s:7:"Success";s:12:"ResponseCode";s:2:"00";s:19:"ResponseDescription";s:9:"Available";s:13:"TransactionID";N;s:19:"TransactionDateTime";N;s:19:"TransactionTraceNum";N;s:17:"MerchantReference";N;s:12:"ApprovalCode";N;s:12:"RetrievalRef";N;s:9:"PackageNo";N;s:10:"SessionKey";N;}}}}';

        return unserialize($serialized);
    }
}
