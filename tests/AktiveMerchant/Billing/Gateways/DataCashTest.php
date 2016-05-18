<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\TestCase;

class DatacashTest extends TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;


    public function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('datacash');

        $this->gateway = new DataCash($login_info);

        $this->amount = 100.00;

        $magic = $this->getMagicNumbers(1);

        $this->creditcard = new CreditCard(
            array_merge(array(
                "first_name" => "John",
                "last_name" => "Doe",
            ), $magic)
        );

        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'DataCash Test Transaction',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
        );
    }

    public function testPurchase()
    {
        $this->mock_request($this->successPurchaseResponse());
        $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    public function testMotoPurchase()
    {
        $this->mock_request($this->successPurchaseResponse());
        $this->options['moto'] = true;
        $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    public function testTokenPurchase()
    {
        $this->options['token'] = true;
        $this->creditcard->number = 'XXXXXXXXXXXXXXXXXXXXXXXXXXX';
        $this->mock_request($this->successTokenPurchaseResponse());
        $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    public function testAuthorize()
    {
        $this->mock_request($this->successAuthorizeResponse());
        $response = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);

        $this->assert_success($response);
    }

    public function testCapture()
    {
        $this->mock_request($this->successCaptureResponse());
        $authorization = '3400900013651606;013648';
        $response = $this->gateway->capture($this->amount, $authorization, $this->options);

        $this->assert_success($response);
    }

    public function testVoid()
    {
        $this->mock_request($this->successVoidResponse());
        $authorization = "3200900013655530;732727";
        $response = $this->gateway->void($authorization, $this->options);

        $this->assert_success($response);
    }

    public function testCredit()
    {
        $this->mock_request($this->successCreditResponse());
        $identification = "3700900013657174;529021";
        $response = $this->gateway->credit($this->amount, $identification, $this->options);

        $this->assert_success($response);
    }

    public function testStore()
    {
        $this->mock_request($this->successTokenPurchaseResponse());
        $response = $this->gateway->store($this->creditcard, $this->options);

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    public function testSpeedLimitResponse()
    {
        $this->mock_request($this->speedLimitResponse());
        $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);

        $this->assert_failure($response);
    }

    public function testQuery()
    {
        $authorization = "3100900013661334";
        $this->mock_request($this->successPurchaseResponse());
        $response = $this->gateway->query($authorization);

        $this->assert_success($response);
    }

    private function getMagicNumbers($index)
    {
        $numbers = include __DIR__ . '/../../../datacash_magic_numbers.php';

        if (array_key_exists($index, $numbers)) {
            return $numbers[$index];
        }

        throw new \InvalidArgumentException(sprintf('Undefined index `%s`. Please check your datacash_magic_numbers.php file.', $index));
    }

    private function successPurchaseResponse()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Response version='2'>
  <CardTxn>
    <Cv2Avs>
      <cv2avs_status>ALL MATCH</cv2avs_status>
    </Cv2Avs>
    <authcode>100000</authcode>
    <card_scheme>VISA</card_scheme>
  </CardTxn>
  <acquirer>RBS</acquirer>
  <datacash_reference>4400204473646246</datacash_reference>
  <merchantreference>REF5194395215</merchantreference>
  <mid>12345678</mid>
  <mode>TEST</mode>
  <reason>ACCEPTED</reason>
  <status>1</status>
  <time>1463431327</time>
</Response>
";
    }

    private function speedLimitResponse()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Response version='2'>
  <CardTxn>
    <card_scheme>VISA</card_scheme>
  </CardTxn>
  <datacash_reference>4000204473646272</datacash_reference>
  <merchantreference>REF2135056169</merchantreference>
  <mode>TEST</mode>
  <reason>speed limit (84)</reason>
  <status>56</status>
  <time>1463431411</time>
</Response>";
    }

    private function successAuthorizeResponse()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Response version='2'>
  <CardTxn>
    <Cv2Avs>
      <cv2avs_status>SECURITY CODE MATCH ONLY</cv2avs_status>
    </Cv2Avs>
    <authcode>596295</authcode>
    <card_scheme>Mastercard</card_scheme>
    <country>Japan</country>
    <response_code>00</response_code>
    <response_code_text>Approved or completed successfully</response_code_text>
  </CardTxn>
  <acquirer>RBS</acquirer>
  <datacash_reference>4600204473646542</datacash_reference>
  <merchantreference>REF2112450153</merchantreference>
  <mid>12345678</mid>
  <mode>TEST</mode>
  <reason>ACCEPTED</reason>
  <status>1</status>
  <time>1463431874</time>
</Response>";
    }

    private function successCaptureResponse()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Response version='2'>
  <acquirer>NBG s2a</acquirer>
  <datacash_reference>3400900013651606</datacash_reference>
  <merchantreference>3400900013651606</merchantreference>
  <mid>1234567</mid>
  <mode>LIVE</mode>
  <reason>FULFILLED OK</reason>
  <status>1</status>
  <time>1452268581</time>
</Response>";
    }

    private function successVoidResponse()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Response version='2'>
  <acquirer>NBG s2a</acquirer>
  <datacash_reference>3200900013655530</datacash_reference>
  <merchantreference>3200900013655530</merchantreference>
  <mid>7003706</mid>
  <mode>LIVE</mode>
  <reason>CANCELLED OK</reason>
  <status>1</status>
  <time>1452526486</time>
</Response>";
    }

    private function successCreditResponse()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Response version='2'>
  <HistoricTxn>
    <authcode>REFUND ACCEPTED</authcode>
  </HistoricTxn>
  <acquirer>NBG s2a</acquirer>
  <datacash_reference>3400900013657180</datacash_reference>
  <merchantreference>3700900013657174</merchantreference>
  <mid>1234567</mid>
  <mode>LIVE</mode>
  <reason>ACCEPTED</reason>
  <status>1</status>
  <time>1452609668</time>
</Response>";
    }

    private function successTokenPurchaseResponse()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Response version='2'>
  <CardTxn>
    <Cv2Avs>
      <cv2avs_status>NO DATA MATCHES</cv2avs_status>
      <policy>0</policy>
    </Cv2Avs>
    <authcode>160350</authcode>
    <card_scheme>Mastercard</card_scheme>
    <country>Greece</country>
    <issuer>NATIONAL BANK OF GREECE, S.A.</issuer>
    <response_code>00</response_code>
    <token>XXXXXXXXXXXXXXXXXXXXXXXXXXX</token>
  </CardTxn>
  <acquirer>NBG s2a</acquirer>
  <aiic>001259</aiic>
  <datacash_reference>3300900013661583</datacash_reference>
  <merchantreference>REF1538340954</merchantreference>
  <mid>123456</mid>
  <mode>LIVE</mode>
  <reason>ACCEPTED</reason>
  <rrn>601512004609</rrn>
  <stan>004609</stan>
  <status>1</status>
  <time>1452860086</time>
</Response>";
    }
}
