<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\Cardstream;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

/**
 * Description of CardstreamTest
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 */

class CardstreamTest extends \AktiveMerchant\TestCase
{

    public $gateway;
    public $amount;
    public $options;
    public $creditcard;


    public function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('cardstream');

        $this->gateway = new Cardstream($login_info);

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
            'description' => 'Cardstream Test Transaction',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
        );
    }

    public function testInitialization() {

        $this->assertNotNull($this->gateway);

        $this->assertNotNull($this->creditcard);

        $this->assertImplementation(
            array(
                'Charge',
                'Credit'
            )
        );
    }

    public function testSuccessfulPurchase()
    {
        $this->mock_request($this->successful_purchase_response());

        $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);
        $this->assert_success($response);
        $this->assertEquals('08010706065208191057', $response->authorization());
    }

    public function testFailedPurchase()
    {
        $this->mock_request($this->failed_purchase_response());

        $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);
        $this->assert_failure($response);
    }

    public function testSuccessfulAvsResult()
    {
        $this->mock_request($this->failed_purchase_response());

        $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);
        $avs_result = $response->avs_result()->toArray();
        $this->assertEquals('Y', $avs_result['street_match']);
        $this->assertEquals('Y', $avs_result['postal_match']);
    }

    public function testFailedAvsResult()
    {
        $this->mock_request($this->successful_purchase_failed_avs_cvv_response());

        $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);
        $avs_result = $response->avs_result()->toArray();
        $cvv_result = $response->cvv_result()->toArray();

        $this->assertEquals('N', $cvv_result['code']);
        $this->assertEquals('N', $avs_result['street_match']);
        $this->assertEquals('N', $avs_result['postal_match']);
    }

    public function testCvvResult()
    {
        $this->mock_request($this->successful_purchase_response());

        $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);
        $cvv_result = $response->cvv_result()->toArray();
        $this->assertEquals('M', $cvv_result['code']);
    }

    public function testSupportedCountries()
    {
        $this->assertEquals(array('GB'), Cardstream::$supported_countries);
    }

    public function testSupportedCardTypes()
    {
        $supported_cards = array(
            'visa',
            'master',
            'american_express',
            'switch',
            'solo',
            'maestro'
        );
        $this->assertEquals($supported_cards, Cardstream::$supported_cardtypes);
    }

    // Private methods

    private function successful_purchase_response()
    {
        return 'VPResponseCode=00&VPCrossReference=08010706065208191057&VPMessage=AUTHCODE:08191&VPTransactionUnique=c3871e2d005b924bf81565537caba82d&VPOrderDesc=Store purchase&VPBillingCountry=826&VPCardName=Longbob Longsen&VPBillingPostCode=LE10 2RT&VPAmountRecieved=100&VPAVSCV2ResponseCode=222100&VPCV2ResultMessage=CV2 Matched&VPAVSResultMessage=Postcode Matched&VPAVSAddressMessage=Address Numeric Matched&VPCardType=MC&VPBillingAddress=25 The Larches, Narborough, Leicester&VPReturnPoint=0090';
    }

    private function successful_purchase_failed_avs_cvv_response()
    {
        return 'VPResponseCode=00&VPCrossReference=08010706065208191057&VPMessage=AUTHCODE:08191&VPTransactionUnique=c3871e2d005b924bf81565537caba82d&VPOrderDesc=Store purchase&VPBillingCountry=826&VPCardName=Longbob Longsen&VPBillingPostCode=LE10 2RT&VPAmountRecieved=100&VPAVSCV2ResponseCode=444100&VPCV2ResultMessage=CV2 Matched&VPAVSResultMessage=Postcode Matched&VPAVSAddressMessage=Address Numeric Matched&VPCardType=MC&VPBillingAddress=25 The Larches, Narborough, Leicester&VPReturnPoint=0090';
    }

    private function failed_purchase_response()
    {
        return 'VPResponseCode=05&VPCrossReference=NoCrossReference&VPMessage=CARD DECLINED&VPTransactionUnique=d966e18a2983faff3715a541983792e0&VPOrderDesc=Store purchase&VPBillingCountry=826&VPCardName=Longbob Longsen&VPBillingPostCode=LE10 2RT&VPAmountRecieved=NA&VPAVSCV2ResponseCode=222100&VPCV2ResultMessage=CV2 Matched&VPAVSResultMessage=Postcode Matched&VPAVSAddressMessage=Address Numeric Matched&VPCardType=MC&VPBillingAddress=25 The Larches, Narborough, Leicester&VPReturnPoint=0090';
    }

}
