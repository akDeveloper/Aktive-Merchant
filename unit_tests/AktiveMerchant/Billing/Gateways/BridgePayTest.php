<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'config.php';

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\Gateways\BridgePay;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Billing\Gateways\Eway;

class BridgePaytTest extends AktiveMerchant\TestCase
{
    public function testXml()
    {
        Base::mode('test');

        $conf = array (
            'UserName' => 'Spre3676',
            'password' => 'H3392nc5'
        );

        $amount = 100;
        $authorization = 'OK9757|837495';

        $b = new BridgePay($conf);
        $creditcard = new CreditCard(
            array(
                "type" => 'master',
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4381258770269608",
                "month" => "01",
                "year" => "2015",
                "verification_value" => "000"
            )
        );
        $options = array(
            'order_id' => 'REF' . $b->generateUniqueId(),
            'email' => "buyer@email.com",
            // 'description' => 'Paypal Pro Test Transaction',
            'billing_address' => array(
                'address1' => '1234 Penny Lane',
                'city' => 'Jonsetown',
                'state' => 'NC',
                'country' => 'US',
                'zip' => '23456'
            ),
            'ip' => '10.0.0.1'
        );

        $b->authorize($amount, $creditcard, $options);
        //echo '========================================='. "\n";
        //$b->purchase($amount, $creditcard, $options);
        //echo '========================================='. "\n";
        //$b->capture( $amount, $authorization, $options);
        //echo '========================================='. "\n";
        //$b->refund( $amount, $authorization, $options);
        //echo '========================================='. "\n";
        //$b->void($authorization, $options);


    }

}
