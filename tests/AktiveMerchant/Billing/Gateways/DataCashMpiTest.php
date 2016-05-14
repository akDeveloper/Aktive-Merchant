<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\TestCase;

class DataCashMpiTest extends TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    public function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('datacash');

        $this->gateway = new DataCashMpi($login_info);

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
            'description' => 'Mpi DataCash Test Transaction',
            'accept_headers' => "*/*",
            'user_agent' => 'IE/6.0',
            'merchant_url' => $login_info['merchant_url'],
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
        );
    }

    public function testLookup()
    {
        $this->mock_request($this->successLookupResponse());
        $response = $this->gateway->lookup($this->amount, $this->creditcard, $this->options);

        $this->assert_success($response);
    }

    public function testNotEnroledLookup()
    {
        $this->mock_request($this->notEnrolledResponse());
        $response = $this->gateway->lookup($this->amount, $this->creditcard, $this->options);

        $this->assert_success($response);
    }

    public function testAuthenticate()
    {
        $this->mock_request($this->successAuthenticateResponse());
        $options = array(
            'pares' => "thePaReqWithBreaks",
            'reference' => "3300900013655290",
        );
        $response = $this->gateway->authenticate($options);

        $this->assert_success($response);
    }

    private function successLookupResponse()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Response version='2'>
  <CardTxn>
    <ThreeDSecure>
      <acs_url>https://accreditation.datacash.com/acs-acq_a</acs_url>
      <pareq_message>thePaReqWithBreaks
</pareq_message>
    </ThreeDSecure>
    <card_scheme>Mastercard</card_scheme>
    <country>Greece</country>
    <issuer>NATIONAL BANK OF GREECE, S.A.</issuer>
  </CardTxn>
  <acquirer>NBG s2a</acquirer>
  <datacash_reference>3300900013655290</datacash_reference>
  <merchantreference>REF4927444245</merchantreference>
  <mid>1234567</mid>
  <mode>LIVE</mode>
  <reason>3DS Payer Verification Required</reason>
  <status>150</status>
  <time>1452518728</time>
</Response>";
    }

    private function notEnrolledResponse()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<Response version='2'>
  <CardTxn>
    <card_scheme>Mastercard</card_scheme>
    <country>Greece</country>
    <issuer>NATIONAL BANK OF GREECE, S.A.</issuer>
  </CardTxn>
  <acquirer>NBG s2a</acquirer>
  <datacash_reference>3500900013657231</datacash_reference>
  <merchantreference>REF9391910155</merchantreference>
  <mid>1234567</mid>
  <mode>LIVE</mode>
  <reason>3DS Card not Enrolled</reason>
  <status>162</status>
  <time>1452611840</time>
</Response>";
    }

    private function successAuthenticateResponse()
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
    <ThreeDSecure>
      <aav>AAABAiUjEUUlIUUlICMQAAAAAAA=</aav>
      <cardholder_registered>yes</cardholder_registered>
      <cavvAlgorithm>2</cavvAlgorithm>
      <eci>02</eci>
      <xid>MDAwMDAwMDAwMDAwMTM2NTUyOTA=</xid>
    </ThreeDSecure>
    <authcode>685235</authcode>
    <card_scheme>Mastercard</card_scheme>
    <country>Greece</country>
    <issuer>NATIONAL BANK OF GREECE, S.A.</issuer>
    <response_code>00</response_code>
  </CardTxn>
  <acquirer>NBG s2a</acquirer>
  <aiic>001259</aiic>
  <datacash_reference>3300900013655290</datacash_reference>
  <merchantreference>3300900013655290</merchantreference>
  <mid>1234567</mid>
  <mode>LIVE</mode>
  <reason>ACCEPTED</reason>
  <rrn>601114004503</rrn>
  <stan>004503</stan>
  <status>1</status>
  <time>1452520889</time>
</Response>";
    }

    private function getMagicNumbers($index)
    {
        $numbers = include __DIR__ . '/../../../datacash_magic_numbers.php';

        if (array_key_exists($index, $numbers)) {
            return $numbers[$index];
        }

        throw new Exception(sprintf('Undefined index `%s`. Please check your datacash_magic_numbers.php file.', $index));
    }
}
