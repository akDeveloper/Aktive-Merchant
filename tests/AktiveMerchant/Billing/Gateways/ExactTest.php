<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\TestCase;

/**
 * Unit tests for Exact gateway.
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
class ExactTest extends TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    public function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('exact');

        $this->gateway = new Exact($login_info);
        $this->amount = 100;
        $this->creditcard = new CreditCard(
            array(
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
            'description' => 'Exact Test Transaction',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
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
        $this->assertRegExp(
            '/Approved/',
            $response->message()
        );

        $this->assertTrue(!is_null($response->authorization()));

    }

    public function testSuccessfulPurchase()
    {
        $this->mock_request($this->successfulPurchaseResponse());

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertRegExp(
            '/Approved/',
            $response->message()
        );

        $this->assertTrue(!is_null($response->authorization()));

    }

    public function testSuccessfulCapture()
    {
        $this->mock_request($this->successfulCaptureResponse());

        $authorization = 'ET5150;1255958813';
        $response = $this->gateway->capture($this->amount, $authorization);

        $this->assert_success($response);
        $this->assertEquals(
            'Transaction Normal - Approved by E-xact',
            $response->message()
        );

        $this->assertTrue(!is_null($response->authorization()));

        $request_body = $this->request->getBody();
        $login = $this->getFixtures()->offsetGet('exact');
        $this->assertEquals(
            $this->successfulCaptureRequest($login['login'], $login['password']),
            $request_body
        );
    }

    public function testSuccessfulCredit()
    {
        $this->mock_request($this->successfulCreditResponse());

        $identification = 'ET0205;1255961063';
        $response = $this->gateway->credit($this->amount, $identification);

        $this->assert_success($response);

        $this->assertEquals(
            'Transaction Normal - Approved by E-xact',
            $response->message()
        );

        $request_body = $this->request->getBody();
        $login = $this->getFixtures()->offsetGet('exact');
        $this->assertEquals(
            $this->successfulCreditRequest($login['login'], $login['password']),
            $request_body
        );
    }

    private function successfulAuthorizeResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<TransactionResult>
  <ExactID>A00049-01</ExactID>
  <Password></Password>
  <Transaction_Type>01</Transaction_Type>
  <DollarAmount>100.0</DollarAmount>
  <SurchargeAmount></SurchargeAmount>
  <Card_Number>############1111</Card_Number>
  <Transaction_Tag>1255958813</Transaction_Tag>
  <Track1></Track1>
  <Track2></Track2>
  <PAN></PAN>
  <Authorization_Num>ET5150</Authorization_Num>
  <Expiry_Date>0115</Expiry_Date>
  <CardHoldersName>John Doe</CardHoldersName>
  <VerificationStr1></VerificationStr1>
  <VerificationStr2>000</VerificationStr2>
  <CVD_Presence_Ind>1</CVD_Presence_Ind>
  <ZipCode>98004</ZipCode>
  <Tax1Amount></Tax1Amount>
  <Tax1Number></Tax1Number>
  <Tax2Amount></Tax2Amount>
  <Tax2Number></Tax2Number>
  <Secure_AuthRequired></Secure_AuthRequired>
  <Secure_AuthResult></Secure_AuthResult>
  <Ecommerce_Flag>0</Ecommerce_Flag>
  <XID></XID>
  <CAVV></CAVV>
  <CAVV_Algorithm></CAVV_Algorithm>
  <Reference_No>REF3585448745</Reference_No>
  <Customer_Ref></Customer_Ref>
  <Reference_3>Exact Test Transaction</Reference_3>
  <Language></Language>
  <Client_IP>94.64.32.67</Client_IP>
  <Client_Email></Client_Email>
  <LogonMessage></LogonMessage>
  <Error_Number>0</Error_Number>
  <Error_Description> </Error_Description>
  <Transaction_Error>false</Transaction_Error>
  <Transaction_Approved>true</Transaction_Approved>
  <EXact_Resp_Code>00</EXact_Resp_Code>
  <EXact_Message>Transaction Normal</EXact_Message>
  <Bank_Resp_Code>028</Bank_Resp_Code>
  <Bank_Message>Approved by E-xact</Bank_Message>
  <Bank_Resp_Code_2>00</Bank_Resp_Code_2>
  <SequenceNo>0010010130</SequenceNo>
  <AVS></AVS>
  <CVV2></CVV2>
  <Retrieval_Ref_No></Retrieval_Ref_No>
  <CAVV_Response></CAVV_Response>
  <MerchantName>E-xact ConnectionShop CAD Account</MerchantName>
  <MerchantAddress>12634 Evergreen Place</MerchantAddress>
  <MerchantCity>Vancouver</MerchantCity>
  <MerchantProvince>British Columbia</MerchantProvince>
  <MerchantCountry>Canada</MerchantCountry>
  <MerchantPostal>V6X 0A6</MerchantPostal>
  <MerchantURL>www.e-xact.com</MerchantURL>
  <ExactIssName></ExactIssName>
  <ExactIssConf></ExactIssConf>
  <CTR>=========== TRANSACTION RECORD ==========
E-xact ConnectionShop
12634 Evergreen Place
Vancouver, BC V6X 0A6
Canada
www.e-xact.com

TYPE: Pre-Authorization

ACCT: Visa  $ 100.00 CAD

CARD NUMBER : ############1111
DATE/TIME   : 06 Oct 13 15:51:49
REFERENCE # : 66001047 0010010130 M
AUTHOR. #   : ET5150
TRANS. REF. : REF3585448745

    00 Approved - Thank You 028


Please retain this copy for your records.

Cardholder will pay above amount to card
issuer pursuant to cardholder agreement.
=========================================</CTR>
</TransactionResult>';
    }

    private function successfulPurchaseResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<TransactionResult>
  <ExactID>A00049-01</ExactID>
  <Password></Password>
  <Transaction_Type>00</Transaction_Type>
  <DollarAmount>100.0</DollarAmount>
  <SurchargeAmount></SurchargeAmount>
  <Card_Number>############1111</Card_Number>
  <Transaction_Tag>1255961063</Transaction_Tag>
  <Track1></Track1>
  <Track2></Track2>
  <PAN></PAN>
  <Authorization_Num>ET0205</Authorization_Num>
  <Expiry_Date>0115</Expiry_Date>
  <CardHoldersName>John Doe</CardHoldersName>
  <VerificationStr1></VerificationStr1>
  <VerificationStr2>000</VerificationStr2>
  <CVD_Presence_Ind>1</CVD_Presence_Ind>
  <ZipCode>98004</ZipCode>
  <Tax1Amount></Tax1Amount>
  <Tax1Number></Tax1Number>
  <Tax2Amount></Tax2Amount>
  <Tax2Number></Tax2Number>
  <Secure_AuthRequired></Secure_AuthRequired>
  <Secure_AuthResult></Secure_AuthResult>
  <Ecommerce_Flag>0</Ecommerce_Flag>
  <XID></XID>
  <CAVV></CAVV>
  <CAVV_Algorithm></CAVV_Algorithm>
  <Reference_No>REF1038266108</Reference_No>
  <Customer_Ref></Customer_Ref>
  <Reference_3>Exact Test Transaction</Reference_3>
  <Language></Language>
  <Client_IP>94.64.32.67</Client_IP>
  <Client_Email></Client_Email>
  <LogonMessage></LogonMessage>
  <Error_Number>0</Error_Number>
  <Error_Description> </Error_Description>
  <Transaction_Error>false</Transaction_Error>
  <Transaction_Approved>true</Transaction_Approved>
  <EXact_Resp_Code>00</EXact_Resp_Code>
  <EXact_Message>Transaction Normal</EXact_Message>
  <Bank_Resp_Code>028</Bank_Resp_Code>
  <Bank_Message>Approved by E-xact</Bank_Message>
  <Bank_Resp_Code_2>00</Bank_Resp_Code_2>
  <SequenceNo>0010010140</SequenceNo>
  <AVS></AVS>
  <CVV2></CVV2>
  <Retrieval_Ref_No></Retrieval_Ref_No>
  <CAVV_Response></CAVV_Response>
  <MerchantName>E-xact ConnectionShop CAD Account</MerchantName>
  <MerchantAddress>12634 Evergreen Place</MerchantAddress>
  <MerchantCity>Vancouver</MerchantCity>
  <MerchantProvince>British Columbia</MerchantProvince>
  <MerchantCountry>Canada</MerchantCountry>
  <MerchantPostal>V6X 0A6</MerchantPostal>
  <MerchantURL>www.e-xact.com</MerchantURL>
  <ExactIssName></ExactIssName>
  <ExactIssConf></ExactIssConf>
  <CTR>=========== TRANSACTION RECORD ==========
E-xact ConnectionShop
12634 Evergreen Place
Vancouver, BC V6X 0A6
Canada
www.e-xact.com

TYPE: Purchase

ACCT: Visa  $ 100.00 CAD

CARD NUMBER : ############1111
DATE/TIME   : 06 Oct 13 16:02:04
REFERENCE # : 66001047 0010010140 M
AUTHOR. #   : ET0205
TRANS. REF. : REF1038266108

    00 Approved - Thank You 028


Please retain this copy for your records.

Cardholder will pay above amount to card
issuer pursuant to cardholder agreement.
=========================================</CTR>
</TransactionResult>';
    }

    private function successfulCaptureRequest($login, $password)
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<Transaction><ExactID>'.$login.'</ExactID><Password>'.$password.'</Password><Transaction_Type>32</Transaction_Type><Transaction_Tag>1255958813</Transaction_Tag><Authorization_Num>ET5150</Authorization_Num><DollarAmount>100.00</DollarAmount></Transaction>
';
    }

    private function successfulCaptureResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<TransactionResult>
  <ExactID>A00049-01</ExactID>
  <Password></Password>
  <Transaction_Type>32</Transaction_Type>
  <DollarAmount>100.0</DollarAmount>
  <SurchargeAmount></SurchargeAmount>
  <Card_Number>############1111</Card_Number>
  <Transaction_Tag>1255963010</Transaction_Tag>
  <Track1></Track1>
  <Track2></Track2>
  <PAN></PAN>
  <Authorization_Num>ET5150</Authorization_Num>
  <Expiry_Date>0115</Expiry_Date>
  <CardHoldersName>John Doe</CardHoldersName>
  <VerificationStr1></VerificationStr1>
  <VerificationStr2></VerificationStr2>
  <CVD_Presence_Ind>1</CVD_Presence_Ind>
  <ZipCode></ZipCode>
  <Tax1Amount></Tax1Amount>
  <Tax1Number></Tax1Number>
  <Tax2Amount></Tax2Amount>
  <Tax2Number></Tax2Number>
  <Secure_AuthRequired></Secure_AuthRequired>
  <Secure_AuthResult></Secure_AuthResult>
  <Ecommerce_Flag>0</Ecommerce_Flag>
  <XID></XID>
  <CAVV></CAVV>
  <CAVV_Algorithm></CAVV_Algorithm>
  <Reference_No>REF3585448745</Reference_No>
  <Customer_Ref></Customer_Ref>
  <Reference_3></Reference_3>
  <Language></Language>
  <Client_IP>94.64.32.67</Client_IP>
  <Client_Email></Client_Email>
  <LogonMessage></LogonMessage>
  <Error_Number>0</Error_Number>
  <Error_Description> </Error_Description>
  <Transaction_Error>false</Transaction_Error>
  <Transaction_Approved>true</Transaction_Approved>
  <EXact_Resp_Code>00</EXact_Resp_Code>
  <EXact_Message>Transaction Normal</EXact_Message>
  <Bank_Resp_Code>028</Bank_Resp_Code>
  <Bank_Message>Approved by E-xact</Bank_Message>
  <Bank_Resp_Code_2>00</Bank_Resp_Code_2>
  <SequenceNo>0010010150</SequenceNo>
  <AVS></AVS>
  <CVV2></CVV2>
  <Retrieval_Ref_No></Retrieval_Ref_No>
  <CAVV_Response></CAVV_Response>
  <MerchantName>E-xact ConnectionShop CAD Account</MerchantName>
  <MerchantAddress>12634 Evergreen Place</MerchantAddress>
  <MerchantCity>Vancouver</MerchantCity>
  <MerchantProvince>British Columbia</MerchantProvince>
  <MerchantCountry>Canada</MerchantCountry>
  <MerchantPostal>V6X 0A6</MerchantPostal>
  <MerchantURL>www.e-xact.com</MerchantURL>
  <ExactIssName></ExactIssName>
  <ExactIssConf></ExactIssConf>
  <CTR>=========== TRANSACTION RECORD ==========
E-xact ConnectionShop
12634 Evergreen Place
Vancouver, BC V6X 0A6
Canada
www.e-xact.com

TYPE: Pre-Auth Completion

ACCT: Visa  $ 100.00 CAD

CARD NUMBER : ############1111
DATE/TIME   : 06 Oct 13 16:10:40
REFERENCE # : 66001047 0010010150 M
AUTHOR. #   : ET5150
TRANS. REF. : REF3585448745

    00 Approved - Thank You 028


Please retain this copy for your records.

Cardholder will pay above amount to card
issuer pursuant to cardholder agreement.
=========================================</CTR>
</TransactionResult>';
    }
    private function successfulCreditRequest($login, $password)
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<Transaction><ExactID>'.$login.'</ExactID><Password>'.$password.'</Password><Transaction_Type>34</Transaction_Type><Transaction_Tag>1255961063</Transaction_Tag><Authorization_Num>ET0205</Authorization_Num><DollarAmount>100.00</DollarAmount></Transaction>
';
    }

    private function successfulCreditResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<TransactionResult>
  <ExactID>A00049-01</ExactID>
  <Password></Password>
  <Transaction_Type>34</Transaction_Type>
  <DollarAmount>100.0</DollarAmount>
  <SurchargeAmount></SurchargeAmount>
  <Card_Number>############1111</Card_Number>
  <Transaction_Tag>1255967870</Transaction_Tag>
  <Track1></Track1>
  <Track2></Track2>
  <PAN></PAN>
  <Authorization_Num>ET3440</Authorization_Num>
  <Expiry_Date>0115</Expiry_Date>
  <CardHoldersName>John Doe</CardHoldersName>
  <VerificationStr1></VerificationStr1>
  <VerificationStr2></VerificationStr2>
  <CVD_Presence_Ind>0</CVD_Presence_Ind>
  <ZipCode></ZipCode>
  <Tax1Amount></Tax1Amount>
  <Tax1Number></Tax1Number>
  <Tax2Amount></Tax2Amount>
  <Tax2Number></Tax2Number>
  <Secure_AuthRequired></Secure_AuthRequired>
  <Secure_AuthResult></Secure_AuthResult>
  <Ecommerce_Flag>0</Ecommerce_Flag>
  <XID></XID>
  <CAVV></CAVV>
  <CAVV_Algorithm></CAVV_Algorithm>
  <Reference_No>REF1038266108</Reference_No>
  <Customer_Ref></Customer_Ref>
  <Reference_3></Reference_3>
  <Language></Language>
  <Client_IP>94.64.32.67</Client_IP>
  <Client_Email></Client_Email>
  <LogonMessage></LogonMessage>
  <Error_Number>0</Error_Number>
  <Error_Description> </Error_Description>
  <Transaction_Error>false</Transaction_Error>
  <Transaction_Approved>true</Transaction_Approved>
  <EXact_Resp_Code>00</EXact_Resp_Code>
  <EXact_Message>Transaction Normal</EXact_Message>
  <Bank_Resp_Code>028</Bank_Resp_Code>
  <Bank_Message>Approved by E-xact</Bank_Message>
  <Bank_Resp_Code_2>00</Bank_Resp_Code_2>
  <SequenceNo>0010010170</SequenceNo>
  <AVS></AVS>
  <CVV2></CVV2>
  <Retrieval_Ref_No></Retrieval_Ref_No>
  <CAVV_Response></CAVV_Response>
  <MerchantName>E-xact ConnectionShop CAD Account</MerchantName>
  <MerchantAddress>12634 Evergreen Place</MerchantAddress>
  <MerchantCity>Vancouver</MerchantCity>
  <MerchantProvince>British Columbia</MerchantProvince>
  <MerchantCountry>Canada</MerchantCountry>
  <MerchantPostal>V6X 0A6</MerchantPostal>
  <MerchantURL>www.e-xact.com</MerchantURL>
  <ExactIssName></ExactIssName>
  <ExactIssConf></ExactIssConf>
  <CTR>=========== TRANSACTION RECORD ==========
E-xact ConnectionShop
12634 Evergreen Place
Vancouver, BC V6X 0A6
Canada
www.e-xact.com

TYPE: Refund

ACCT: Visa  $ 100.00 CAD

CARD NUMBER : ############1111
DATE/TIME   : 06 Oct 13 16:34:39
REFERENCE # : 66001047 0010010170 M
AUTHOR. #   : ET3440
TRANS. REF. : REF1038266108

    00 Approved - Thank You 028


Please retain this copy for your records.

=========================================</CTR>
</TransactionResult>';
    }
}
