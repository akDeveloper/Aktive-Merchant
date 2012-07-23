<?php
require_once('../../lib/autoload.php');
require_once('../login.php');

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\Gateways\PaypalExpress;

Base::mode('test');

$gateway = new PaypalExpress(
    array(
        'login' => PAYPAL_LOGIN,
        'password' => PAYPAL_PASS,
        'signature' => PAYPAL_SIG,
        'currency' => 'USD'
    )
);

try {

    if ( isset($_GET['pay']) ) {
        $options = array(
            'return_url' => 'http://localhost/Aktive-Merchant/test/paypal_express/index.php',
            'cancel_return_url' => 'http://localhost/Aktive-Merchant/test/paypal_express/index.php?cancel=1',
            'items' => array(
                array(
                    'name' => 'Shirt',
                    'description' => 'Blue shirt',
                    'unit_price' => 10,
                    'quantity' => 1,
                    'id' => '1245345'
                ),
                array(
                    'name' => 'Shirt',
                    'description' => 'White shirt',
                    'unit_price' => 12,
                    'quantity' => 3,
                    'id' => '124242345'
                ),
            )
        );
        $response = $gateway->setupPurchase($_POST['amount'],$options);


        die ( header('Location: '. $gateway->urlForToken( $response->token() )) );

    } elseif (isset($_GET['cancel'])) {

        echo 'Transaction Canceled!<br />';

    } elseif (isset( $_GET['token'] ) ) {

        $response = $gateway->get_details_for( $_GET['token'], $_GET['PayerID']);

        /**
         * You can modify transaction amount according to paypal ship address
         * or even render a form to allow customer choose shipping methods and
         * additional costs.
         * NOTE: if you execute $gateway->authorize() or $gateway->purchase() to a
         * different page than you executed $gateway->get_details_for()
         * make sure you have store somewhere token and payer_id values
         * ex. $_SESSION or Database
         */

        $response = $gateway->purchase($response->amount());
        if ( $response->success() ) {
            echo 'Success payment!';
        } else {
            echo $response->message();
        }
    }
} catch (Exception $exc) {
    echo $exc->getMessage();
}

?>
<form method="post" action="/Aktive-Merchant/test/paypal_express/index.php?pay=1">
    <label for="amount">Amount</label>
    <input type="text" name="amount" value="46" id="amount" />
    <input type="submit" />
</form>
