<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\HsbcSecureEpayments;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

/**
 * Description of HsbcSecureEpaymentsTest
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */

class HsbcSecureEpaymentsTest extends \AktiveMerchant\TestCase
{

    public $gateway;
    public $amount;
    public $options;
    public $creditcard;
    public $request;

    /**
     * Setup
     */
    function setUp()
    {
        Base::mode('test');

        $options = $this->getFixtures()->offsetGet('hsbc');

        $options['currency'] = 'EUR';

        $this->gateway = new HsbcSecureEpayments($options);

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
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'Test Transaction',
            'country' => 'US',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
        );
        $this->authorization = '483e6382-7d13-3001-002b-0003bac00fc9';
    }

    public function testInitialization()
    {

        $this->assertNotNull($this->gateway);

        $this->assertNotNull($this->creditcard);

        $this->assertImplementation(array('Charge'));
    }

    // Tests

    public function testSuccessfulAuthorization()
    {
        $this->mock_request($this->successful_authorize_response());

        $response = $this->gateway->authorize(
            $this->amount,
            $this->creditcard,
            $this->options
        );
        $this->assert_success($response);
        $this->assertEquals('Approved.', $response->message());
    }

    public function testUnsuccessfulAuthorization()
    {
        $this->mock_request($this->failed_authorize_response());

        $response = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
        $this->assert_failure($response);
    }

    public function testSuccessfulCapture()
    {
        $this->mock_request($this->successful_capture_response());

        $capture = $this->gateway->capture($this->amount, $this->authorization, $this->options);
        $this->assert_success($capture);
        $this->assertEquals('Approved.', $capture->message());
        $this->assertEquals('483e6382-7d13-3001-002b-0003bac00fc9', $capture->authorization());
        $this->assertEquals('A', $capture->transaction_status);
        $this->assertEquals('1', $capture->return_code);
        $this->assertEquals('797220', $capture->auth_code);
    }

    public function testUnsuccessfulCapture()
    {
        $this->mock_request($this->failed_capture_response());

        $capture = $this->gateway->capture($this->amount, $this->authorization, $this->options);
        $this->assert_failure($capture);
        $this->assertEquals('Denied.', $capture->message());
        $this->assertEquals('483e6382-7d13-3001-002b-0003bac00fc9', $capture->authorization());
        $this->assertEquals('E', $capture->transaction_status);
        $this->assertEquals('1067', $capture->return_code);
        $this->assertNull($capture->auth_code);
    }

    public function testInvalidCredentialsRejected()
    {
        $this->mock_request($this->auth_fail_response());

        $response = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
        $this->assert_failure($response);
        $this->assertEquals('Insufficient permissions to perform requested operation.', $response->message());
    }

    public function testFraudulentTransactionAvs()
    {
        $this->mock_request($this->avs_result("NN", "500"));

        $response = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
        $this->assert_failure($response);
        $this->assertTrue(null !== $response->fraud_review());

        $this->mock_request($this->avs_result("NN", "501"));

        $response = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
        $this->assert_failure($response);
        $this->assertTrue(null !== $response->fraud_review());

        $this->mock_request($this->avs_result("NN", "502"));

        $response = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
        $this->assert_failure($response);
        $this->assertTrue(null !== $response->fraud_review());
    }

    public function testFraudulentTransactionCvv()
    {
        $this->mock_request($this->cvv_result("NN", "1055"));

        $response = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);
        $this->assert_failure($response);
        $this->assertTrue(null !== $response->fraud_review());
    }

    // Private methods

    private function successful_authorize_response()
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
    <EngineDocList>
     <DocVersion DataType="String">1.0</DocVersion>
     <EngineDoc>
      <Overview>
       <AuthCode DataType="String">889350</AuthCode>
       <CcErrCode DataType="S32">1</CcErrCode>
       <CcReturnMsg DataType="String">Approved.</CcReturnMsg>
       <DateTime DataType="DateTime">1212066788586</DateTime>
       <Mode DataType="String">Y</Mode>
       <OrderId DataType="String">483e6382-7d12-3001-002b-0003bac00fc9</OrderId>
       <TransactionId DataType="String">483e6382-7d13-3001-002b-0003bac00fc9</TransactionId>
       <TransactionStatus DataType="String">A</TransactionStatus>
      </Overview>
     </EngineDoc>
    </EngineDocList>
XML;
        return $xml;
    }

    private function failed_authorize_response()
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
    <EngineDocList>
     <DocVersion DataType="String">1.0</DocVersion>
     <EngineDoc>
      <OrderFormDoc>
       <Id DataType="String">48b7024c-0322-3002-002a-0003ba9a87ff</Id>
       <Mode DataType="String">Y</Mode>
       <Transaction>
        <Id DataType="String">48b7024c-0323-3002-002a-0003ba9a87ff</Id>
        <Type DataType="String">PreAuth</Type>
       </Transaction>
      </OrderFormDoc>
      <Overview>
       <CcErrCode DataType="S32">1067</CcErrCode>
       <CcReturnMsg DataType="String">System error.</CcReturnMsg>
       <DateTime DataType="DateTime">1219953701297</DateTime>
       <Mode DataType="String">Y</Mode>
       <Notice DataType="String">Unable to determine card type. (&apos;length&apos; is &apos;16&apos;)</Notice>
       <TransactionId DataType="String">48b7024c-0323-3002-002a-0003ba9a87ff</TransactionId>
       <TransactionStatus DataType="String">E</TransactionStatus>
      </Overview>
     </EngineDoc>
    </EngineDocList>
XML;
    }

    private function successful_capture_response()
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
    <EngineDocList>
     <DocVersion DataType="String">1.0</DocVersion>
     <EngineDoc>
      <OrderFormDoc>
       <DateTime DataType="DateTime">1219956808155</DateTime>
       <Id DataType="String">483e6382-7d13-3001-002b-0003bac00fc9</Id>
       <Mode DataType="String">Y</Mode>
       <Transaction>
        <AuthCode DataType="String">797220</AuthCode>
        <CardProcResp>
         <CcErrCode DataType="S32">1</CcErrCode>
         <CcReturnMsg DataType="String">Approved.</CcReturnMsg>
         <Status DataType="String">1</Status>
        </CardProcResp>
        <Id DataType="String">483e6382-7d13-3001-002b-0003bac00fc9</Id>
        <Type DataType="String">PostAuth</Type>
       </Transaction>
      </OrderFormDoc>
      <Overview>
       <AuthCode DataType="String">797220</AuthCode>
       <CcErrCode DataType="S32">1</CcErrCode>
       <CcReturnMsg DataType="String">Approved.</CcReturnMsg>
       <DateTime DataType="DateTime">1219956808155</DateTime>
       <Mode DataType="String">Y</Mode>
       <TransactionId DataType="String">483e6382-7d13-3001-002b-0003bac00fc9</TransactionId>
       <TransactionStatus DataType="String">A</TransactionStatus>
      </Overview>
     </EngineDoc>
    </EngineDocList>
XML;
    }

    private function failed_capture_response()
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
    <EngineDocList>
     <DocVersion DataType="String">1.0</DocVersion>
     <EngineDoc>
      <OrderFormDoc>
       <Id DataType="String">483e6382-7d13-3001-002b-0003bac00fc9</Id>
       <Mode DataType="String">Y</Mode>
       <Transaction>
        <CardProcResp>
         <CcErrCode DataType="S32">1067</CcErrCode>
         <CcReturnMsg DataType="String">Denied.</CcReturnMsg>
         <Status DataType="String">1</Status>
        </CardProcResp>
        <Id DataType="String">483e6382-7d13-3001-002b-0003bac00fc9</Id>
        <Type DataType="String">PostAuth</Type>
       </Transaction>
      </OrderFormDoc>
      <Overview>
       <CcErrCode DataType="S32">1067</CcErrCode>
       <CcReturnMsg DataType="String">Denied.</CcReturnMsg>
       <DateTime DataType="DateTime">1219956808155</DateTime>
       <Mode DataType="String">Y</Mode>
       <TransactionId DataType="String">483e6382-7d13-3001-002b-0003bac00fc9</TransactionId>
       <TransactionStatus DataType="String">E</TransactionStatus>
      </Overview>
     </EngineDoc>
    </EngineDocList>
XML;
    }

    private function avs_result($avs_display, $cc_err_code = '1')
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
    <EngineDocList>
      <DocVersion DataType="String">1.0</DocVersion>
      <EngineDoc>
        <OrderFormDoc>
          <Transaction>
            <CardProcResp>
              <AvsDisplay>{$avs_display}</AvsDisplay>
            </CardProcResp>
          </Transaction>
        </OrderFormDoc>
        <Overview>
          <CcErrCode DataType="S32">{$cc_err_code}</CcErrCode>
          <CcReturnMsg DataType="String">Approved.</CcReturnMsg>
          <Mode DataType="String">Y</Mode>
          <TransactionStatus DataType="String">A</TransactionStatus>
        </Overview>
      </EngineDoc>
    </EngineDocList>
XML;
    }

    private function cvv_result($cvv2_resp, $cc_err_code = '1')
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
    <EngineDocList>
      <DocVersion DataType="String">1.0</DocVersion>
      <EngineDoc>
        <OrderFormDoc>
          <Transaction>
            <CardProcResp>
              <Cvv2Resp>{$cvv2_resp}</Cvv2Resp>
            </CardProcResp>
          </Transaction>
        </OrderFormDoc>
        <Overview>
          <CcErrCode DataType="S32">{$cc_err_code}</CcErrCode>
          <CcReturnMsg DataType="String">Approved.</CcReturnMsg>
          <Mode DataType="String">Y</Mode>
          <TransactionStatus DataType="String">A</TransactionStatus>
        </Overview>
      </EngineDoc>
    </EngineDocList>
XML;
    }

    private function auth_fail_response()
    {
        return <<<XML
<?xml version='1.0' encoding='UTF-8'?>
    <EngineDocList>
     <DocVersion DataType='String'>1.0</DocVersion>
     <EngineDoc>
      <MessageList>
       <MaxSev DataType='S32'>6</MaxSev>
       <Message>
        <AdvisedAction DataType='S32'>16</AdvisedAction>
        <Audience DataType='String'>Merchant</Audience>
        <ResourceId DataType='S32'>7</ResourceId>
        <Sev DataType='S32'>6</Sev>
        <Text DataType='String'>Insufficient permissions to perform requested operation.</Text>
       </Message>
      </MessageList>
     </EngineDoc>
    </EngineDocList>
XML;
    }

}

?>
