<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\Eway;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

/**
 * Unit tests for  Eway gateway.
 *
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
class EwayTest extends \AktiveMerchant\TestCase
{
    /**
     * Setup
     */
    public function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('eway');

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

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->purchase_request($this->options['order_id']),
            $request_body
        );

    }

    private function purchase_request($order_id)
    {
        return "<?xml version=\"1.0\"?>\n<ewaygateway><ewayCustomerInvoiceRef>{$order_id}</ewayCustomerInvoiceRef><ewayCustomerInvoiceDescription>Eway Test Transaction</ewayCustomerInvoiceDescription><ewayCardNumber>4444333322221111</ewayCardNumber><ewayCardExpiryMonth>01</ewayCardExpiryMonth><ewayCardExpiryYear>15</ewayCardExpiryYear><ewayCustomerFirstName>John</ewayCustomerFirstName><ewayCustomerLastName>Doe</ewayCustomerLastName><ewayCardHoldersName>John Doe</ewayCardHoldersName><ewayCVN>000</ewayCVN><ewayCustomerAddress>1234 Street, 98004, WA</ewayCustomerAddress><ewayCustomerPostcode>98004</ewayCustomerPostcode><ewayCustomerEmail>test@example.com</ewayCustomerEmail><ewayTrxnNumber/><ewayOption1/><ewayOption2/><ewayOption3/><ewayTotalAmount>10000</ewayTotalAmount><ewayCustomerID>87654321</ewayCustomerID></ewaygateway>\n";
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

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->authorize_request($this->options['order_id']),
            $request_body
        );
    }

    private function authorize_request($order_id)
    {
        return "<?xml version=\"1.0\"?>\n<ewaygateway><ewayCustomerInvoiceRef>{$order_id}</ewayCustomerInvoiceRef><ewayCustomerInvoiceDescription>Eway Test Transaction</ewayCustomerInvoiceDescription><ewayCardNumber>4444333322221111</ewayCardNumber><ewayCardExpiryMonth>01</ewayCardExpiryMonth><ewayCardExpiryYear>15</ewayCardExpiryYear><ewayCustomerFirstName>John</ewayCustomerFirstName><ewayCustomerLastName>Doe</ewayCustomerLastName><ewayCardHoldersName>John Doe</ewayCardHoldersName><ewayCVN>000</ewayCVN><ewayCustomerAddress>1234 Street, 98004, WA</ewayCustomerAddress><ewayCustomerPostcode>98004</ewayCustomerPostcode><ewayCustomerEmail>test@example.com</ewayCustomerEmail><ewayTrxnNumber/><ewayOption1/><ewayOption2/><ewayOption3/><ewayTotalAmount>10000</ewayTotalAmount><ewayCustomerID>87654321</ewayCustomerID></ewaygateway>\n";
    }

    private function successful_authorize_response()
    {
        return "<ewayResponse><ewayTrxnStatus>True</ewayTrxnStatus><ewayTrxnNumber>10170</ewayTrxnNumber><ewayTrxnReference/><ewayTrxnOption1/><ewayTrxnOption2/><ewayTrxnOption3/><ewayAuthCode>123456</ewayAuthCode><ewayReturnAmount>10000</ewayReturnAmount><ewayTrxnError>00,Transaction Approved(Test CVN Gateway)</ewayTrxnError></ewayResponse>";
    }

    public function testSuccessfulCapture()
    {
        $this->mock_request($this->successful_capture_response());

        $response = $this->gateway->capture(
            $this->amount,
            $this->options["order_id"],
            $this->options
        );

        $this->assert_success($response);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->capture_request($this->options['order_id']),
            trim($request_body)
        );
    }

    private function capture_request($order_id)
    {
        return "<?xml version=\"1.0\"?>\n<ewaygateway><ewayAuthTrxnNumber>{$order_id}</ewayAuthTrxnNumber><ewayTotalAmount>10000</ewayTotalAmount><ewayCardNumber/><ewayCardExpiryMonth/><ewayCardExpiryYear/><ewayCustomerFirstName/><ewayCustomerLastName/><ewayCardHoldersName> </ewayCardHoldersName><ewayTrxnNumber/><ewayOption1/><ewayOption2/><ewayOption3/><ewayCustomerID>87654321</ewayCustomerID></ewaygateway>";
    }

    private function successful_capture_response()
    {
        return "<ewayResponse><ewayTrxnError>00,Transaction Approved</ewayTrxnError><ewayTrxnStatus>True</ewayTrxnStatus><ewayTrxnNumber>9876543210</ewayTrxnNumber><ewayTrxnOption1>optional 1</ewayTrxnOption1><ewayTrxnOption2>optional 2</ewayTrxnOption2><ewayTrxnOption3>optional 3</ewayTrxnOption3><ewayReturnAmount>10</ewayReturnAmount><ewayAuthCode>012345</ewayAuthCode><ewayTrxnReference>12345678</ewayTrxnReference></ewayResponse>";
    }
}
