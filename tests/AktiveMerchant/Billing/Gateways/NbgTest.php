<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\Nbg;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

class NbgTest extends \AktiveMerchant\TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;


    public function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('nbg');

        $this->gateway = new Nbg($login_info);

        $this->amount = 20.30;

        $magic = $this->getMagicNumbers(0);

        $this->creditcard = new CreditCard(
            array_merge(array(
                "first_name" => "John",
                "last_name" => "Doe",
            ), $magic)
        );

        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'NbgDataCash Test Transaction',
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
        $this->options['moto'] = true;
        $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    /*
    public function test3DSecurePurchase()
    {
        $this->mock_request($this->success3DSecurePurchaseResponse());
        $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }*/

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

    public function testDeclinedResponse()
    {
        $this->mock_request($this->declinedResponse());
        $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);

        $this->assert_failure($response);
    }

    public function testSpeedLimitResponse()
    {
        $this->mock_request($this->speedLimitResponse());
        $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);

        $this->assert_failure($response);
    }

    public function testInvalidArgumentResponse()
    {
        $this->mock_request($this->invalidArgumentResponse());
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

        throw new Exception(sprintf('Undefined index `%s`. Please check your datacash_magic_numbers.php file.', $index));
    }

    private function successPurchaseResponse()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Response version='2'>
  <CardTxn>
    <Cv2Avs>
      <address_policy matched='accept' notchecked='accept' notmatched='accept' notprovided='accept' partialmatch='accept'></address_policy>
      <address_result numeric='0'>notprovided</address_result>
      <cv2_policy matched='accept' notchecked='accept' notmatched='reject' notprovided='reject' partialmatch='reject'></cv2_policy>
      <cv2_result numeric='1'>notchecked</cv2_result>
      <cv2avs_status>ACCEPTED</cv2avs_status>
      <postcode_policy matched='accept' notchecked='accept' notmatched='accept' notprovided='accept' partialmatch='accept'></postcode_policy>
      <postcode_result numeric='0'>notprovided</postcode_result>
    </Cv2Avs>
    <authcode>545787</authcode>
    <card_scheme>Mastercard</card_scheme>
    <country>Greece</country>
    <issuer>NATIONAL BANK OF GREECE, S.A.</issuer>
    <response_code>00</response_code>
  </CardTxn>
  <acquirer>NBG s2a</acquirer>
  <aiic>001259</aiic>
  <datacash_reference>3100900013650378</datacash_reference>
  <merchantreference>REF1281608176</merchantreference>
  <mid>1234567</mid>
  <mode>LIVE</mode>
  <reason>ACCEPTED</reason>
  <rrn>600812004476</rrn>
  <stan>004476</stan>
  <status>1</status>
  <time>1452257678</time>
</Response>
";
    }

    private function declinedResponse()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Response version='2'>
  <CardTxn>
    <Cv2Avs>
      <address_policy matched='accept' notchecked='accept' notmatched='accept' notprovided='accept' partialmatch='accept'></address_policy>
      <address_result numeric='0'>notprovided</address_result>
      <cv2_policy matched='accept' notchecked='accept' notmatched='reject' notprovided='reject' partialmatch='reject'></cv2_policy>
      <cv2_result numeric='1'>notchecked</cv2_result>
      <cv2avs_status>ACCEPTED</cv2avs_status>
      <postcode_policy matched='accept' notchecked='accept' notmatched='accept' notprovided='accept' partialmatch='accept'></postcode_policy>
      <postcode_result numeric='0'>notprovided</postcode_result>
    </Cv2Avs>
    <card_scheme>Mastercard</card_scheme>
    <country>Greece</country>
    <issuer>NATIONAL BANK OF GREECE, S.A.</issuer>
    <response_code>70</response_code>
  </CardTxn>
  <acquirer>NBG s2a</acquirer>
  <aiic>001259</aiic>
  <datacash_reference>3200900013650405</datacash_reference>
  <information>DECLINED</information>
  <merchantreference>REF1756248983</merchantreference>
  <mid>1234567</mid>
  <mode>LIVE</mode>
  <reason>DECLINED</reason>
  <rrn>600812004479</rrn>
  <stan>004479</stan>
  <status>7</status>
  <time>1452257777</time>
</Response>";
    }

    private function speedLimitResponse()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Response version='2'>
  <CardTxn>
    <card_scheme>Mastercard</card_scheme>
    <country>Greece</country>
    <issuer>NATIONAL BANK OF GREECE, S.A.</issuer>
  </CardTxn>
  <datacash_reference>3600900013650390</datacash_reference>
  <merchantreference>REF1105401622</merchantreference>
  <mode>LIVE</mode>
  <reason>speed limit (57)</reason>
  <status>56</status>
  <time>1452257737</time>
</Response>";
    }

    private function invalidArgumentResponse()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Response version='2'>
  <CardTxn>
    <card_scheme>Mastercard</card_scheme>
  </CardTxn>
  <datacash_reference>3500900013649972</datacash_reference>
  <information>Extended Policy missing address_policy, postcode_policy element(s)</information>
  <merchantreference>REF9352519175</merchantreference>
  <mode>LIVE</mode>
  <reason>Invalid ExtendedPolicy definition</reason>
  <status>131</status>
  <time>1452255413</time>
</Response>";
    }

    private function successAuthorizeResponse()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Response version='2'>
  <CardTxn>
    <Cv2Avs>
      <address_policy matched='accept' notchecked='accept' notmatched='accept' notprovided='accept' partialmatch='accept'></address_policy>
      <address_result numeric='0'>notprovided</address_result>
      <cv2_policy matched='accept' notchecked='accept' notmatched='reject' notprovided='reject' partialmatch='reject'></cv2_policy>
      <cv2_result numeric='2'>matched</cv2_result>
      <cv2avs_status>ACCEPTED</cv2avs_status>
      <postcode_policy matched='accept' notchecked='accept' notmatched='accept' notprovided='accept' partialmatch='accept'></postcode_policy>
      <postcode_result numeric='0'>notprovided</postcode_result>
    </Cv2Avs>
    <authcode>013648</authcode>
    <card_scheme>VISA</card_scheme>
    <country>Greece</country>
    <issuer>National Bank of Greece S.A.</issuer>
    <response_code>00</response_code>
  </CardTxn>
  <acquirer>NBG s2a</acquirer>
  <aiic>001259</aiic>
  <datacash_reference>3400900013651606</datacash_reference>
  <merchantreference>REF1551428783</merchantreference>
  <mid>1234567</mid>
  <mode>LIVE</mode>
  <reason>ACCEPTED</reason>
  <rrn>600815004486</rrn>
  <stan>004486</stan>
  <status>1</status>
  <time>1452267927</time>
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
