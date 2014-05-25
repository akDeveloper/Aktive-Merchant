<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'config.php';

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\Gateways\BridgePay;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Billing\Gateways\Eway;

class BridgePaytTest extends AktiveMerchant\TestCase
{

    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    public function setUp()
    {
        Base::mode('test');


        $options = $this->getFixtures()->offsetGet('bridge_pay');

        $this->amount = 100;
        $authorization = 'OK9757|837495';

        $this->gateway = new BridgePay($options);
        $this->creditcard = new CreditCard(
            array(
                "type" => 'master',
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4381258770269608",
                "month" => "01",
                "year" => "2015",
                "verification_value" => "000"
            )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'email' => "buyer@email.com",
            // 'description' => 'Paypal Pro Test Transaction',
            'billing_address' => array(
                'address1' => '1234 Penny Lane',
                'city' => 'Jonsetown',
                'state' => 'NC',
                'country' => 'US',
                'zip' => '23456'
            ),
            'ip' => '10.0.0.1'
        );

        //$b->authorize($amount, $creditcard, $options);
        //echo '========================================='. "\n";
        //$b->purchase($amount, $creditcard, $options);
        //echo '========================================='. "\n";
        //$b->capture( $amount, $authorization, $options);
        //echo '========================================='. "\n";
        //$b->refund( $amount, $authorization, $options);
        //echo '========================================='. "\n";
        //$b->void($authorization, $options);


    }

    public function testSuccessfulAuthorization()
    {
        $this->mock_request($this->successful_authorize_response());

        $response = $this->gateway->authorize(
            $this->amount,
            $this->creditcard,
            $this->options
        );
        $this->assert_success($response);
        $this->assertEquals(
            'OK2755|860592',
            $response->authorization()
        );
        $request_body = $this->request->getBody();

        //$this->assertTrue(!is_null($response->authorization()));

    }

    public function testSuccesfulCapture ()
    {

        $this->mock_request($this->successful_capture_response());

        $authorization = 'OK2755|860592';
        $response = $this->gateway->capture(
            $this->amount,
            $authorization,
            $this->options
        );

        $this->assert_success($response);
        $this->assertRegExp('/OK2755/', $response->authorization());

    }

    public function testSuccesfulPurchase()
    {
        $this->mock_request($this->successful_purchase_response());

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);

    }

    public function testSuccessfulCredit()
    {
        $this->mock_request($this->successful_credit_response());

        $authorization = 'OK2755|860923';
        $response = $this->gateway->credit(
            $this->amount,
            $authorization,
            $this->options
        );


        $this->assert_success($response);

    }

    public function testSuccessfulVoid()
    {
        $this->mock_request($this->successful_void_response());
        $authorization = 'OK2755|860923';

        $response = $this->gateway->void(
            $authorization,
            $this->options
        );


        $this->assert_success($response);
    }

    private function successful_authorize_response()
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<Response xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://TPISoft.com/SmartPayments/">
  <Result>0</Result>
  <RespMSG>Approved</RespMSG>
  <Message>APPROVAL</Message>
  <AuthCode>OK2755</AuthCode>
  <PNRef>860592</PNRef>
  <HostCode>860592</HostCode>
  <GetAVSResult>Z</GetAVSResult>
  <GetAVSResultTXT>5 Zip Match No Address Match</GetAVSResultTXT>
  <GetStreetMatchTXT>No Match</GetStreetMatchTXT>
  <GetZipMatchTXT>Match</GetZipMatchTXT>
  <GetCVResult>P</GetCVResult>
  <GetCVResultTXT>Service Not Available</GetCVResultTXT>
  <GetCommercialCard>False</GetCommercialCard>
  <ExtData>InvNum=REF1279470267,CardType=VISA</ExtData>
  </Response>';

    }

    private function successful_capture_response()
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<Response xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://TPISoft.com/SmartPayments/">
  <Result>0</Result>
  <RespMSG>Approved</RespMSG>
  <Message>APPROVAL</Message>
  <AuthCode>OK2755</AuthCode>
  <PNRef>860923</PNRef>
  <GetCommercialCard>False</GetCommercialCard>
  <ExtData>InvNum=REF9866437935</ExtData>
</Response>';
    }


    private function successful_purchase_response()
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<Response xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://TPISoft.com/SmartPayments/">
  <Result>0</Result>
  <RespMSG>Approved</RespMSG>
  <Message>APPROVAL</Message>
  <AuthCode>OK1223</AuthCode>
  <PNRef>861038</PNRef>
  <HostCode>861038</HostCode>
  <GetAVSResult>Z</GetAVSResult>
  <GetAVSResultTXT>5 Zip Match No Address Match</GetAVSResultTXT>
  <GetStreetMatchTXT>No Match</GetStreetMatchTXT>
  <GetZipMatchTXT>Match</GetZipMatchTXT>
  <GetCVResult>P</GetCVResult>
  <GetCVResultTXT>Service Not Available</GetCVResultTXT>
  <GetCommercialCard>False</GetCommercialCard>
  <ExtData>InvNum=REF1102160392,CardType=VISA</ExtData>
  </Response>';

    }

    private function successful_credit_response()
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<Response xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://TPISoft.com/SmartPayments/">
  <Result>0</Result>
  <RespMSG>Approved</RespMSG>
  <Message>APPROVAL</Message>
  <AuthCode>OK2755</AuthCode>
  <PNRef>861099</PNRef>
  <GetCommercialCard>False</GetCommercialCard>
  <ExtData>InvNum=REF8462995545</ExtData>
</Response>';

    }


    private function successful_void_response()
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<Response xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://TPISoft.com/SmartPayments/">
  <Result>0</Result>
  <RespMSG>Approved</RespMSG>
  <Message>APPROVAL</Message>
  <AuthCode>OK2755</AuthCode>
  <PNRef>861134</PNRef>
  <GetCommercialCard>False</GetCommercialCard>
</Response>';

    }

}
