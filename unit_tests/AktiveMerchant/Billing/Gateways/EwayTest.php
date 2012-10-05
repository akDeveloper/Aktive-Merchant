<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\Eway;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

require_once 'config.php';

/**
 * Unit tests for  Eway gateway.
 *
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
class EwayTest extends AktiveMerchant\TestCase
{
    /**
     * Setup
     */
    public function setUp()
    {
        Base::mode('test');

        $login_info = array(
            'login' => 'x',
        );
        $this->gateway = new Eway($login_info);

        $this->amount = 100;
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4444333322221111",
                "month" => "01",
                "year" => "2015",
                "verification_value" => "000"
            )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'Eway Test Transaction',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            ),
            'email' => 'test@example.com',
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

        $this->assert_success($response);
    }
    private function successful_purchase_response()
    {
        return "<ewayResponse><ewayTrxnStatus>True</ewayTrxnStatus><ewayTrxnNumber>1010222</ewayTrxnNumber><ewayTrxnReference/><ewayTrxnOption1/><ewayTrxnOption2/><ewayTrxnOption3/><ewayAuthCode>123456</ewayAuthCode><ewayReturnAmount>10000</ewayReturnAmount><ewayTrxnError>00,Transaction Approved(Test Gateway)</ewayTrxnError></ewayResponse>";
    }

    public function testSuccessfulAuthorize()
    {
        $this->mock_request($this->successful_authorize_response());
        
        $response = $this->gateway->authorize(
            $this->amount, 
            $this->creditcard, 
            $this->options
        );

        $this->assert_success($response);
    }

    private function successful_authorize_response()
    {
        return "<ewayResponse><ewayTrxnStatus>True</ewayTrxnStatus><ewayTrxnNumber>10170</ewayTrxnNumber><ewayTrxnReference/><ewayTrxnOption1/><ewayTrxnOption2/><ewayTrxnOption3/><ewayAuthCode>123456</ewayAuthCode><ewayReturnAmount>10000</ewayReturnAmount><ewayTrxnError>00,Transaction Approved(Test CVN Gateway)</ewayTrxnError></ewayResponse>";
    }
}
