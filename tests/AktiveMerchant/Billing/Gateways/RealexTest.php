<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\Realex;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

/**
 * Description of RealexTest
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 */
class RealexTest extends \AktiveMerchant\TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    public function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('realex');

        $this->gateway = new Realex($login_info);

        $this->amount = 100;

        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4263970000005262",
                "month" => "01",
                "year" => date('Y') + 1,
                "verification_value" => "000"
            )
        );

        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA',
                'country' => 'USA'
            )
        );
    }

    public function testSuccessfulPurchase()
    {
        $this->mock_request($this->successful_purchase_response());

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assertInstanceOf('AktiveMerchant\\Billing\\Response', $response);
        $this->assert_success($response);
    }

    private function successful_purchase_response()
    {
        return <<<XML
<response timestamp='20010427043422'>
  <merchantid>your merchant id</merchantid>
  <account>account to use</account>
  <orderid>order id from request</orderid>
  <authcode>authcode received</authcode>
  <result>00</result>
  <message>[ test system ] message returned from system</message>
  <pasref> realex payments reference</pasref>
  <cvnresult>M</cvnresult>
  <batchid>batch id for this transaction (if any)</batchid>
  <cardissuer>
    <bank>Issuing Bank Name</bank>
    <country>Issuing Bank Country</country>
    <countrycode>Issuing Bank Country Code</countrycode>
    <region>Issuing Bank Region</region>
  </cardissuer>
  <tss>
    <result>89</result>
    <check id="1000">9</check>
    <check id="1001">9</check>
  </tss>
  <sha1hash>7384ae67....ac7d7d</sha1hash>
  <md5hash>34e7....a77d</md5hash>
</response>
XML;
    }

    public function testUnsuccessfulPurchase()
    {
        $this->mock_request($this->unsuccessful_purchase_response());

        $response = $this->gateway->purchase(
            $this->amount, $this->creditcard, $this->options
        );

        $this->assertInstanceOf('AktiveMerchant\\Billing\\Response', $response);
        $this->assert_failure($response);
    }

    private function unsuccessful_purchase_response()
    {
        return <<<XML
<response timestamp='20010427043422'>
  <merchantid>your merchant id</merchantid>
  <account>account to use</account>
  <orderid>order id from request</orderid>
  <authcode>authcode received</authcode>
  <result>01</result>
  <message>[ test system ] message returned from system</message>
  <pasref> realex payments reference</pasref>
  <cvnresult>M</cvnresult>
  <batchid>batch id for this transaction (if any)</batchid>
  <cardissuer>
    <bank>Issuing Bank Name</bank>
    <country>Issuing Bank Country</country>
    <countrycode>Issuing Bank Country Code</countrycode>
    <region>Issuing Bank Region</region>
  </cardissuer>
  <tss>
    <result>89</result>
    <check id="1000">9</check>
    <check id="1001">9</check>
  </tss>
  <sha1hash>7384ae67....ac7d7d</sha1hash>
  <md5hash>34e7....a77d</md5hash>
</response>
XML;
    }
}
