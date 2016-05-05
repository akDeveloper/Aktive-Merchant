<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\TestCase;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Event\RequestEvents;

class AlphaBankTest extends TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    public function setUp()
    {
        Base::mode('test');

        $options = $this->getFixtures()->offsetGet('alphabank');

        $this->gateway = new AlphaBank($options);
        $this->amount = 0.09;
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4000000000000002",
                "month" => "01",
                "year" => date('Y') + 1,
                "verification_value" => "123"
            )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'Test Transaction',
            'cavv' => null,
            'eci' => null,
            'xid' => 'MDkzNjY1NzE3NzI4MzMxMjQyMDE=',
            'enrollment_status' => 'N',
            'authentication_status' => null,
            'country' => 'US',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
        );
    }

    public function testSuccessfulPurchase()
    {
        $this->mock_request($this->successPurchaseResponse());

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());

        return $response->order_id;
    }

    public function testPurchaseWithInstallments()
    {
        $this->mock_request($this->successPurchaseWithInstallmentsResponse());

        $this->options['installments'] = 6;

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    public function testAuthorize()
    {
        $this->mock_request($this->successAuthorizeResponse());

        $response = $this->gateway->authorize(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());

        return $response->order_id;
    }

    /**
     * @depends testAuthorize
     */
    public function testCapture($order_id)
    {
        $this->mock_request($this->successCaptureResponse());

        $this->options['payment_method'] = 'visa';
        $this->options['order_id'] = $order_id;
        $response = $this->gateway->capture(
            $this->amount,
            $this->creditcard->number,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());

        return $response->order_id;
    }

    /**
     * @depends testSuccessfulPurchase
     */
    public function testCredit($order_id)
    {
        $this->mock_request($this->successCreditResponse());

        $this->options['payment_method'] = 'visa';
        $this->options['order_id'] = $order_id;
        $response = $this->gateway->credit(
            $this->amount,
            $this->creditcard->number,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    /**
     * @depends testCapture
     */
    public function testVoid($order_id)
    {
        $this->mock_request($this->successVoidResponse());

        $this->options['payment_method'] = 'visa';
        $this->options['order_id'] = $order_id;
        $this->options['money'] = 0.09;
        $response = $this->gateway->void(
            $this->creditcard->number,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    public function testStatus()
    {
        $this->mock_request($this->successStatusResponse());

        $response = $this->gateway->status('24227111');

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    public function testErrorHandling()
    {
        $this->mock_request($this->errorResponse());
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );
        $this->assert_failure($response);
        $this->assertTrue($response->test());
    }

    public function testDuplicateOrder()
    {
        $this->mock_request($this->errorDuplicateOrderResponse());
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );
        $this->assert_failure($response);
        $this->assertTrue($response->test());
    }

    public function testUnsupportedCard()
    {
        $this->mock_request($this->errorSupportCardResponse());
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_failure($response);
        $this->assertTrue($response->test());
        $this->assertEquals('T3', array_search($response->message(), AlphaBank::$statusCode));
    }

    private function successPurchaseResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="00b64732ba90dd5aad0eda4568deae90"><SaleResponse><OrderId>REF9355783865</OrderId><OrderAmount>0.09</OrderAmount><Currency>EUR</Currency><PaymentTotal>0.09</PaymentTotal><Status>CAPTURED</Status><TxId>24227051</TxId><PaymentRef>133541</PaymentRef><RiskScore>0</RiskScore><Description>OK, CAPTURED response code 00</Description></SaleResponse></Message><Digest>rDWmTquVdqWv1vaAlucstv/IaOc=</Digest></VPOS>';
    }

    private function successPurchaseWithInstallmentsResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="bc53a032a53b02fbbe813fef9e1c9f6b"><SaleResponse><OrderId>REF4096019945</OrderId><OrderAmount>0.09</OrderAmount><Currency>EUR</Currency><PaymentTotal>0.09</PaymentTotal><Status>CAPTURED</Status><TxId>24228541</TxId><PaymentRef>101517</PaymentRef><RiskScore>0</RiskScore><Description>OK, CAPTURED response code 00</Description></SaleResponse></Message><Digest>wJrNheHA59yI6XUVvAjuffAGUNA=</Digest></VPOS>';
    }

    private function errorResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="1434105759548"><ErrorMessage><ErrorCode>SE</ErrorCode><Description>Unspecified Exception. Errror id: 1434105759548</Description></ErrorMessage></Message></VPOS>';
    }

    private function errorDuplicateOrderResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="aa145b481ad789d89cec4efb2c1bff52"><SaleResponse><OrderId>REF123</OrderId><TxId>0</TxId><ErrorCode>I0</ErrorCode><Description>[Invalid order id REF123 (duplicate)]</Description></SaleResponse></Message><Digest>9ys+S3YA+KrKnUEd8I7yMSxt878=</Digest></VPOS>';
    }

    private function errorSupportCardResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="a0ffa4eaede5d79c8011215b884642e8"><SaleResponse><OrderId>REF7947573095</OrderId><OrderAmount>1.25</OrderAmount><Currency>EUR</Currency><PaymentTotal>1.25</PaymentTotal><Status>REFUSED</Status><TxId>1540891</TxId><PaymentRef>406213</PaymentRef><RiskScore>0</RiskScore><Description>Refused, REFUSED response code T3</Description></SaleResponse></Message><Digest>YGYEQFKWkjMZrfFXkNQUG9SKSck=</Digest></VPOS>';
    }

    private function successAuthorizeResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="c3c7814c6a2896266ca9b5b92d73544b"><AuthorisationResponse><OrderId>REF6134229075</OrderId><OrderAmount>0.09</OrderAmount><Currency>EUR</Currency><PaymentTotal>0.09</PaymentTotal><Status>AUTHORIZED</Status><TxId>24228771</TxId><PaymentRef>104000</PaymentRef><RiskScore>0</RiskScore><Description>OK, AUTHORIZED response code 00</Description></AuthorisationResponse></Message><Digest>O5rqINjQymXE8O1fXb5NJaprKPY=</Digest></VPOS>';
    }

    private function successCaptureResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="10cc3d0c482163e8f285cd27471fd5c9"><CaptureResponse><OrderId>REF6134229075</OrderId><OrderAmount>0.09</OrderAmount><Currency>EUR</Currency><PaymentTotal>0.09</PaymentTotal><Status>CAPTURED</Status><TxId>24230001</TxId><PaymentRef>104000</PaymentRef><Description>OK, CAPTURED response code 00</Description></CaptureResponse></Message><Digest>4GylJuD2bUS9G8+wBBpvAa5b1FA=</Digest></VPOS>';
    }

    private function successCreditResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="b6721996c563e30e15514a65551402da"><RefundResponse><OrderId>REF4090800935</OrderId><OrderAmount>0.09</OrderAmount><Currency>EUR</Currency><PaymentTotal>0.09</PaymentTotal><Status>CAPTURED</Status><TxId>24230081</TxId><Description>OK, CAPTURED response code 00</Description></RefundResponse></Message><Digest>BGVY0C2DbMQHqUZPTSd6iBjJu+w=</Digest></VPOS>';
    }

    private function successVoidResponse()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="e168db59b2acf9c4d66a1db0c57b64e7"><CancelResponse><OrderId>REF9355783865</OrderId><OrderAmount>0.09</OrderAmount><Currency>EUR</Currency><PaymentTotal>0.09</PaymentTotal><Status>AUTHORIZED</Status><TxId>24227111</TxId><PaymentRef>133541</PaymentRef><Description>OK, AUTHORIZED response code 00</Description></CancelResponse></Message><Digest>zHmmXsy9sJOH7INceSNDNJKroi0=</Digest></VPOS>';
    }

    private function successStatusResponse()
    {
        return '<?xml version="1.0"?> <VPOS xmlns="http://www.modirum.com/schemas"> <Message messageId="7b00c7380e025722cf5b61b12feb49d0" timeStamp="2015-12-09T16:03:51.332+02:00" version="1.0"> <StatusResponse> <TransactionDetails> <OrderAmount>0.09</OrderAmount> <Currency>EUR</Currency> <PaymentTotal>0.09</PaymentTotal> <Status>AUTHORIZED</Status> <TxId>24227111</TxId> <PaymentRef>133541</PaymentRef> <Description>OK, AUTHORIZED response code 00</Description> <TxType>VOID</TxType> <TxDate>2015-12-09T15:51:50.206+02:00</TxDate> <TxStarted>2015-12-09T15:51:50.158+02:00</TxStarted> <TxCompleted>2015-12-09T15:51:50.721+02:00</TxCompleted> <PaymentMethod>visa</PaymentMethod> <Attribute name="MERCHANT NO">0022000230</Attribute> <Attribute name="USER IP">77.69.3.98</Attribute> <Attribute name="CHANNEL">XML API</Attribute> <Attribute name="SETTLEMENT STATUS">NA</Attribute> <Attribute name="BATCH NO">1</Attribute> <Attribute name="ISO response code">00</Attribute> <Attribute name="ORDER DESCRIPTION"></Attribute> <Attribute name="CARD MASK PAN">4000########0002</Attribute> <Attribute name="ECOM-FLG"> </Attribute> <Attribute name="BONUS PARTICIPATION">No</Attribute> </TransactionDetails> </StatusResponse> </Message> <Digest>cwDXoAgB9pPb4jRF1NJP8aEpCN0=</Digest> </VPOS>';
    }
}
