<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\Cardlink;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Event\RequestEvents;

class CardlinkTest extends \AktiveMerchant\TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    public function setUp()
    {
        Base::mode('test');

        $options = $this->getFixtures()->offsetGet('cardlink');

        $this->gateway = new Cardlink($options);
        $this->amount = 0.09;
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4792731080349610",
                "month" => "10",
                "year" => "17",
                "verification_value" => "655"
            )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'Test Transaction',
            'cavv' => 'CAACA0OBNoBmY2KFg4E2AAAAAAA=',
            'eci' => '06',
            'xid' => 'NDgyMzcxMTgxNzY4Mzg5NTU4MDY=',
            'enrollment_status' => 'Y',
            'authentication_status' => 'A',
            'country' => 'US',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
        );
    }

    public function testPurchase()
    {
        $this->mock_request($this->success_purchase_repsponse());

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
        $this->mock_request($this->success_authorize_response());

        $response = $this->gateway->authorize(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    public function testCredit()
    {
        $this->mock_request($this->success_credit_response());

        $this->options['payment_method'] = 'visa';
        $this->options['order_id'] = '1369981694782';
        $response = $this->gateway->credit(
            $this->amount,
            'xxxxxxxxxxxxxxxx',
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    public function testVoid()
    {
        $this->mock_request($this->success_void_response());

        $this->options['payment_method'] = 'mastercard';
        $this->options['order_id'] = 'REF1985947185';
        $this->options['money'] = 0.09;
        $response = $this->gateway->void(
            'xxxxxxxxxxxxxxxx',
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    public function testErrorHandling()
    {
        $this->mock_request($this->error_response());
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
        $this->mock_request($this->error_duplicate_order_response());
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
        $this->mock_request($this->error_support_card_response());
        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );
        $this->assert_failure($response);
        $this->assertTrue($response->test());
    }

    private function success_purchase_repsponse()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="8956f699f607a6bcf30e5018a96c8694"><SaleResponse><OrderId>REF1993816535</OrderId><OrderAmount>0.09</OrderAmount><Currency>EUR</Currency><PaymentTotal>0.09</PaymentTotal><Status>CAPTURED</Status><TxId>1540371</TxId><PaymentRef>750101</PaymentRef><RiskScore>0</RiskScore><Description>OK, CAPTURED response code 00</Description></SaleResponse></Message><Digest>qinPhnXrSsUkTm2d5iBNtv/9+Lg=</Digest></VPOS>';
    }

    private function error_response()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="1434105759548"><ErrorMessage><ErrorCode>SE</ErrorCode><Description>Unspecified Exception. Errror id: 1434105759548</Description></ErrorMessage></Message></VPOS>';
    }

    private function error_duplicate_order_response()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="aa145b481ad789d89cec4efb2c1bff52"><SaleResponse><OrderId>REF123</OrderId><TxId>0</TxId><ErrorCode>I0</ErrorCode><Description>[Invalid order id REF123 (duplicate)]</Description></SaleResponse></Message><Digest>9ys+S3YA+KrKnUEd8I7yMSxt878=</Digest></VPOS>';
    }

    private function error_support_card_response()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="a0ffa4eaede5d79c8011215b884642e8"><SaleResponse><OrderId>REF7947573095</OrderId><OrderAmount>1.25</OrderAmount><Currency>EUR</Currency><PaymentTotal>1.25</PaymentTotal><Status>REFUSED</Status><TxId>1540891</TxId><PaymentRef>406213</PaymentRef><RiskScore>0</RiskScore><Description>Refused, REFUSED response code T3</Description></SaleResponse></Message><Digest>YGYEQFKWkjMZrfFXkNQUG9SKSck=</Digest></VPOS>';
    }

    private function success_authorize_response()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="aabdc078302f8105fce4c3f30fbc6374"><AuthorisationResponse><OrderId>REF1985947185</OrderId><OrderAmount>0.09</OrderAmount><Currency>EUR</Currency><PaymentTotal>0.09</PaymentTotal><Status>AUTHORIZED</Status><TxId>1541201</TxId><PaymentRef>750140</PaymentRef><RiskScore>0</RiskScore><Description>OK, AUTHORIZED response code 00</Description></AuthorisationResponse></Message><Digest>6T3WzbFkeubZwC3ogtjmLufmau0=</Digest></VPOS>';
    }

    private function success_credit_response()
    {
       return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="7cf4522940c8f672a0f3499792c476e2"><RefundResponse><OrderId>1369981694782</OrderId><OrderAmount>0.09</OrderAmount><Currency>EUR</Currency><PaymentTotal>0.09</PaymentTotal><Status>CAPTURED</Status><TxId>1545651</TxId><Description>OK, CAPTURED response code 00</Description></RefundResponse></Message><Digest>HVpuSrccNqrcMSfuXTQwjetUjZ8=</Digest></VPOS>';
    }

    private function success_void_response()
    {
       return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas"><Message version="1.0" messageId="d553d3db39cd15c88b597def03071423"><CancelResponse><OrderId>REF1985947185</OrderId><OrderAmount>0.09</OrderAmount><Currency>EUR</Currency><PaymentTotal>0.09</PaymentTotal><Status>AUTHORIZED</Status><TxId>1553111</TxId><PaymentRef>750140</PaymentRef><Description>OK, AUTHORIZED response code 00</Description></CancelResponse></Message><Digest>moAueK4/iqBzw2X8/kSiQHxvjEs=</Digest></VPOS>';
    }
}
