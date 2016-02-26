<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\ePDQ;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

/**
 * Unit tests for ePDQ gateway.
 *
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
class ePDQTest extends \AktiveMerchant\TestCase
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

        $login_info = $this->getFixtures()->offsetGet('epdq');

        $this->gateway = new ePDQ($login_info);

        $this->amount = 100;
        $this->creditcard = new CreditCard(
            array(
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
            'description' => 'ePDQ Test Transaction',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
        );

        $this->markTestIncomplete('Uncomplete implementation.');
    }

    public function testSuccessfulPurchase()
    {
        $this->mock_request($this->successful_purchase_response());

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        print_r($response);

        $this->assert_success($response);
        $this->assertEquals(
            'This transaction has been approved.',
            $response->message()
        );
    }

    private function successful_purchase_response()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<EngineDocList>
 <DocVersion DataType="String">1.0</DocVersion>
 <EngineDoc>
  <ContentType DataType="String">OrderFormDoc</ContentType>
  <DocumentId DataType="String">4d45da6a-5e10-3000-002b-00144ff2e45c</DocumentId>
  <Instructions>
   <Pipeline DataType="String">Payment</Pipeline>

  </Instructions>
  <MessageList>
   <MaxSev DataType="S32">3</MaxSev>
   <Message>
    <AdvisedAction DataType="S32">32</AdvisedAction>
    <Audience DataType="String">Merchant</Audience>
    <Component DataType="String">CcxBarclaysGbpAuth</Component>
    <ContextId DataType="String">PaymentNormErrors</ContextId>
    <DataState DataType="S32">3</DataState>
    <FileLine DataType="S32">121</FileLine>
    <FileName DataType="String">CcxBarclaysAuthResponseRedirector.cpp</FileName>
    <FileTime DataType="String">10:41:43May 26 2009</FileTime>
    <ResourceId DataType="S32">1</ResourceId>
    <Sev DataType="S32">3</Sev>
    <Text DataType="String">Approved.</Text>

   </Message>

  </MessageList>
  <OrderFormDoc>
   <Consumer>
    <BillTo>
     <Location>
      <Address>
       <City DataType="String">Ottawa</City>
       <Country DataType="String"></Country>
       <PostalCode DataType="String">K1C2N6</PostalCode>
       <StateProv DataType="String">ON</StateProv>
       <Street1 DataType="String">1234 My Street</Street1>
       <Street2 DataType="String">Apt 1</Street2>

      </Address>
      <Id DataType="String">4d45da6a-5e12-3000-002b-00144ff2e45c</Id>

     </Location>

    </BillTo>
    <PaymentMech>
     <CreditCard>
      <Cvv2Indicator DataType="String">1</Cvv2Indicator>
      <Cvv2Val DataType="String">123</Cvv2Val>
      <Expires DataType="ExpirationDate">09/12</Expires>
      <Number DataType="String">4715320629000001</Number>
      <Type DataType="S32">1</Type>

     </CreditCard>
     <Type DataType="String">CreditCard</Type>

    </PaymentMech>

   </Consumer>
   <DateTime DataType="DateTime">1296599280954</DateTime>
   <FraudInfo>
    <FraudResult DataType="String">None</FraudResult>
    <FraudResultCode DataType="S32">0</FraudResultCode>
    <OrderScore DataType="Numeric" Precision="0">0</OrderScore>
    <StrategyList>
     <Strategy>
      <FraudAction DataType="String">None</FraudAction>
      <StrategyId DataType="S32">1</StrategyId>
      <StrategyName DataType="String">My Rules</StrategyName>
      <StrategyOwnerId DataType="S32">2974</StrategyOwnerId>
      <StrategyScore DataType="Numeric" Precision="0">0</StrategyScore>

     </Strategy>

    </StrategyList>
    <TotalScore DataType="Numeric" Precision="0">0</TotalScore>

   </FraudInfo>
   <GroupId DataType="String">150127237</GroupId>
   <Id DataType="String">150127237</Id>
   <Mode DataType="String">P</Mode>
   <Transaction>
    <AuthCode DataType="String">442130</AuthCode>
    <CardProcRequest>
     <TerminalId DataType="String">90003750</TerminalId>

    </CardProcRequest>
    <CardProcResp>
     <AvsDisplay DataType="String">YY</AvsDisplay>
     <AvsRespCode DataType="String">EX</AvsRespCode>
     <CcErrCode DataType="S32">1</CcErrCode>
     <CcReturnMsg DataType="String">Approved.</CcReturnMsg>
     <Cvv2Resp DataType="String">2</Cvv2Resp>
     <ProcAvsRespCode DataType="String">22</ProcAvsRespCode>
     <ProcReturnCode DataType="String">00</ProcReturnCode>
     <ProcReturnMsg DataType="String">AUTH CODE:442130</ProcReturnMsg>
     <Status DataType="String">1</Status>

    </CardProcResp>
    <CardholderPresentCode DataType="S32">7</CardholderPresentCode>
    <CurrentTotals>
     <Totals>
      <Total DataType="Money" Currency="826">3900</Total>

     </Totals>

    </CurrentTotals>
    <Id DataType="String">4d45da6a-5e11-3000-002b-00144ff2e45c</Id>
    <InputEnvironment DataType="S32">4</InputEnvironment>
    <SecurityIndicator DataType="S32">7</SecurityIndicator>
    <TerminalInputCapability DataType="S32">1</TerminalInputCapability>
    <Type DataType="String">Auth</Type>

   </Transaction>

  </OrderFormDoc>
  <User>
   <Alias DataType="String">2974</Alias>
   <ClientId DataType="S32">2974</ClientId>
   <EffectiveAlias DataType="String">2974</EffectiveAlias>
   <EffectiveClientId DataType="S32">2974</EffectiveClientId>
   <Name DataType="String">spreedlytesting</Name>
   <Password DataType="String">XXXXXXX</Password>

  </User>

 </EngineDoc>
 <TimeIn DataType="DateTime">1296599280948</TimeIn>
 <TimeOut DataType="DateTime">1296599283885</TimeOut>
 </EngineDocList>';
    }


    private function failed_purchase_response()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<EngineDocList>
 <DocVersion DataType="String">1.0</DocVersion>
 <EngineDoc>
  <ContentType DataType="String">OrderFormDoc</ContentType>
  <DocumentId DataType="String">4d45da6a-5d6b-3000-002b-00144ff2e45c</DocumentId>
  <Instructions>
   <Pipeline DataType="String">Payment</Pipeline>

  </Instructions>
  <MessageList>
   <MaxSev DataType="S32">3</MaxSev>
   <Message>
    <AdvisedAction DataType="S32">32</AdvisedAction>
    <Audience DataType="String">Merchant</Audience>
    <Component DataType="String">CcxBarclaysGbpAuth</Component>
    <ContextId DataType="String">PaymentNormErrors</ContextId>
    <DataState DataType="S32">3</DataState>
    <FileLine DataType="S32">121</FileLine>
    <FileName DataType="String">CcxBarclaysAuthResponseRedirector.cpp</FileName>
    <FileTime DataType="String">10:41:43May 26 2009</FileTime>
    <ResourceId DataType="S32">50</ResourceId>
    <Sev DataType="S32">3</Sev>
    <Text DataType="String">Declined (General).</Text>

   </Message>

  </MessageList>
  <OrderFormDoc>
   <Consumer>
    <BillTo>
     <Location>
      <Address>
       <City DataType="String">Ottawa</City>
       <Country DataType="String"></Country>
       <PostalCode DataType="String">K1C2N6</PostalCode>
       <StateProv DataType="String">ON</StateProv>
       <Street1 DataType="String">1234 My Street</Street1>
       <Street2 DataType="String">Apt 1</Street2>

      </Address>
      <Id DataType="String">4d45da6a-5d6d-3000-002b-00144ff2e45c</Id>

     </Location>

    </BillTo>
    <PaymentMech>
     <CreditCard>
      <Cvv2Indicator DataType="String">1</Cvv2Indicator>
      <Cvv2Val DataType="String">123</Cvv2Val>
      <Expires DataType="ExpirationDate">09/12</Expires>
      <Number DataType="String">4715320629000027</Number>
      <Type DataType="S32">1</Type>

     </CreditCard>
     <Type DataType="String">CreditCard</Type>

    </PaymentMech>

   </Consumer>
   <DateTime DataType="DateTime">1296598178436</DateTime>
   <FraudInfo>
    <FraudResult DataType="String">None</FraudResult>
    <FraudResultCode DataType="S32">0</FraudResultCode>
    <OrderScore DataType="Numeric" Precision="0">0</OrderScore>
    <StrategyList>
     <Strategy>
      <FraudAction DataType="String">None</FraudAction>
      <StrategyId DataType="S32">1</StrategyId>
      <StrategyName DataType="String">My Rules</StrategyName>
      <StrategyOwnerId DataType="S32">2974</StrategyOwnerId>
      <StrategyScore DataType="Numeric" Precision="0">0</StrategyScore>

     </Strategy>

    </StrategyList>
    <TotalScore DataType="Numeric" Precision="0">0</TotalScore>

   </FraudInfo>
   <GroupId DataType="String">22394792</GroupId>
   <Id DataType="String">22394792</Id>
   <Mode DataType="String">P</Mode>
   <Transaction>
    <CardProcRequest>
     <TerminalId DataType="String">90003745</TerminalId>

    </CardProcRequest>
    <CardProcResp>
     <AvsDisplay DataType="String">NY</AvsDisplay>
     <AvsRespCode DataType="String">B5</AvsRespCode>
     <CcErrCode DataType="S32">50</CcErrCode>
     <CcReturnMsg DataType="String">Declined (General).</CcReturnMsg>
     <Cvv2Resp DataType="String">2</Cvv2Resp>
     <ProcAvsRespCode DataType="String">24</ProcAvsRespCode>
     <ProcReturnCode DataType="String">05</ProcReturnCode>
     <ProcReturnMsg DataType="String">NOT AUTHORISED</ProcReturnMsg>
     <Status DataType="String">1</Status>

    </CardProcResp>
    <CardholderPresentCode DataType="S32">7</CardholderPresentCode>
    <CurrentTotals>
     <Totals>
      <Total DataType="Money" Currency="826">4205</Total>

     </Totals>

    </CurrentTotals>
    <Id DataType="String">4d45da6a-5d6c-3000-002b-00144ff2e45c</Id>
    <InputEnvironment DataType="S32">4</InputEnvironment>
    <SecurityIndicator DataType="S32">7</SecurityIndicator>
    <TerminalInputCapability DataType="S32">1</TerminalInputCapability>
    <Type DataType="String">Auth</Type>

   </Transaction>

  </OrderFormDoc>
  <User>
   <Alias DataType="String">2974</Alias>
   <ClientId DataType="S32">2974</ClientId>
   <EffectiveAlias DataType="String">2974</EffectiveAlias>
   <EffectiveClientId DataType="S32">2974</EffectiveClientId>
   <Name DataType="String">login</Name>
   <Password DataType="String">XXXXXXX</Password>

  </User>

 </EngineDoc>
 <TimeIn DataType="DateTime">1296598178430</TimeIn>
 <TimeOut DataType="DateTime">1296598179756</TimeOut>

</EngineDocList>';
    }


    private function successful_credit_response()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<EngineDocList>
 <DocVersion DataType="String">1.0</DocVersion>
 <EngineDoc>
  <ContentType DataType="String">OrderFormDoc</ContentType>
  <DocumentId DataType="String">4d45da6a-8bcd-3000-002b-00144ff2e45c</DocumentId>
  <Instructions>
   <Pipeline DataType="String">Payment</Pipeline>

  </Instructions>
  <MessageList>

  </MessageList>
  <OrderFormDoc>
   <Consumer>
    <BillTo>
     <Location>
      <Address>
       <City DataType="String">Ottawa</City>
       <PostalCode DataType="String">K1C2N6</PostalCode>
       <StateProv DataType="String">ON</StateProv>
       <Street1 DataType="String">1234 My Street</Street1>
       <Street2 DataType="String">Apt 1</Street2>

      </Address>
      <Id DataType="String">4d45da6a-8bcc-3000-002b-00144ff2e45c</Id>

     </Location>

    </BillTo>
    <PaymentMech>
     <CreditCard>
      <ExchangeType DataType="S32">1</ExchangeType>
      <Expires DataType="ExpirationDate">09/12</Expires>
      <Number DataType="String">4715320629000001</Number>

     </CreditCard>
     <Type DataType="String">CreditCard</Type>

    </PaymentMech>

   </Consumer>
   <DateTime DataType="DateTime">1296679499967</DateTime>
   <FraudInfo>
    <FraudResult DataType="String">None</FraudResult>
    <FraudResultCode DataType="S32">0</FraudResultCode>
    <OrderScore DataType="Numeric" Precision="0">0</OrderScore>
    <StrategyList>
     <Strategy>
      <FraudAction DataType="String">None</FraudAction>
      <StrategyId DataType="S32">1</StrategyId>
      <StrategyName DataType="String">My Rules</StrategyName>
      <StrategyOwnerId DataType="S32">2974</StrategyOwnerId>
      <StrategyScore DataType="Numeric" Precision="0">0</StrategyScore>

     </Strategy>

    </StrategyList>
    <TotalScore DataType="Numeric" Precision="0">0</TotalScore>

   </FraudInfo>
   <GroupId DataType="String">b92b5bff09d05d771c17e6b6b30531ed</GroupId>
   <Id DataType="String">b92b5bff09d05d771c17e6b6b30531ed</Id>
   <Mode DataType="String">P</Mode>
   <Transaction>
    <CardProcResp>
     <CcErrCode DataType="S32">1</CcErrCode>
     <CcReturnMsg DataType="String">Approved.</CcReturnMsg>
     <ProcReturnCode DataType="String">1</ProcReturnCode>
     <ProcReturnMsg DataType="String">Approved</ProcReturnMsg>
     <Status DataType="String">1</Status>

    </CardProcResp>
    <CardholderPresentCode DataType="S32">7</CardholderPresentCode>
    <ChargeTypeCode DataType="String">S</ChargeTypeCode>
    <CurrentTotals>
     <Totals>
      <Total DataType="Money" Currency="826">3900</Total>

     </Totals>

    </CurrentTotals>
    <Id DataType="String">4d45da6a-8bce-3000-002b-00144ff2e45c</Id>
    <InputEnvironment DataType="S32">4</InputEnvironment>
    <SecurityIndicator DataType="S32">7</SecurityIndicator>
    <TerminalInputCapability DataType="S32">1</TerminalInputCapability>
    <Type DataType="String">Credit</Type>

   </Transaction>

  </OrderFormDoc>
  <User>
   <Alias DataType="String">2974</Alias>
   <ClientId DataType="S32">2974</ClientId>
   <EffectiveAlias DataType="String">2974</EffectiveAlias>
   <EffectiveClientId DataType="S32">2974</EffectiveClientId>
   <Name DataType="String">spreedlytesting</Name>
   <Password DataType="String">XXXXXXX</Password>

  </User>

 </EngineDoc>
 <TimeIn DataType="DateTime">1296679499961</TimeIn>
 <TimeOut DataType="DateTime">1296679500312</TimeOut>

</EngineDocList>';
    }
}
