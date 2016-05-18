<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\Gateways\BridgePay;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Common\Options;
use AktiveMerchant\TestCase;

class BridgePaytTest extends TestCase
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

        $this->gateway = new BridgePay($options);
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4381258770269608",
                "month" => "01",
                "year" => date('Y') + 1,
                "verification_value" => "000"
            )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'email' => "buyer@email.com",
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

    public function testSuccessfulAuthorization()
    {
        $this->mock_request($this->successfulAuthorizeResponse());

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
    }

    public function testSuccesfulCapture()
    {
        $this->mock_request($this->successfulCaptureResponse());

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
        $this->mock_request($this->successfulPurchaseResponse());

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
    }

    public function testSuccessfulCredit()
    {
        $this->mock_request($this->successfulCreditResponse());

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
        $this->mock_request($this->successfulVoidResponse());
        $authorization = 'OK2755|860923';

        $response = $this->gateway->void(
            $authorization,
            $this->options
        );

        $this->assert_success($response);
    }

    public function testFailedPurchase()
    {
        $this->mock_request($this->failedPurchaseResponse());

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_failure($response);
    }

    private function successfulAuthorizeResponse()
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

    private function successfulCaptureResponse()
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


    private function successfulPurchaseResponse()
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

    private function successfulCreditResponse()
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


    private function successfulVoidResponse()
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

    private function failedPurchaseResponse()
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<Response xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://TPISoft.com/SmartPayments/">
  <Result>12</Result>
  <RespMSG>Decline</RespMSG>
  <Message>DECLINE</Message>
  <AuthCode>DECLINED</AuthCode>
  <PNRef>1424426</PNRef>
  <HostCode>1424426</HostCode>
  <GetAVSResult>D</GetAVSResult>
  <GetAVSResultTXT>Street Address Match and Postal Code Match</GetAVSResultTXT>
  <GetCommercialCard>False</GetCommercialCard>
  <ExtData>InvNum=REF1118183472,CardType=VISA</ExtData>
</Response>';
    }
}
