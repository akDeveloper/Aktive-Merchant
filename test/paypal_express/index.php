<?php
require_once('../../autoload.php');
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
            'return_url' => 'http://127.0.0.1/Aktive-Merchant/test/paypal_express/index.php',
            'cancel_return_url' => 'http://127.0.0.1/Aktive-Merchant/test/paypal_express/index.php?cancel=1',
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
            ),
            'billing_address' => array(
                'address1' => '1234 Penny Lane',
                'city' => 'Jonsetown',
                'state' => 'NC',
                'country' => 'US',
                'zip' => '23456'
            ),
            'ip' => '127.0.0.1',
        );
        $response = $gateway->setupAuthorize($_POST['amount'],$options);


        die ( header('Location: '. $gateway->urlForToken( $response->token() )) );

    } elseif (isset($_GET['cancel'])) {

        echo 'Transaction Canceled!<br />';

    } elseif ( isset($_GET['capture']) ) {

        $params = array('complete_type' => 'Complete');
        $response = $gateway->capture(46, $_POST['authorization'], $params);

        echo '<pre>';
        print_r($response);
        echo '</pre>';
    } elseif ( isset($_GET['credit']) ) {

        $params = array('refund_type' => 'Full');
        $response = $gateway->credit(46, $_POST['authorization'], $params);

        echo '<pre>';
        print_r($response);
        echo '</pre>';
    } elseif ( isset($_GET['void']) ) {

        $response = $gateway->void($_POST['authorization']);

        echo '<pre>';
        print_r($response);
        echo '</pre>';
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

        $response = $gateway->authorize($response->amount());
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
    <fieldset>
        <legend>Authorization</legend>
        <label for="amount">Amount</label>
        <input type="text" name="amount" value="46" id="amount" />
        <input type="submit" />
    </fieldset>
</form>

<form method="post" action="/Aktive-Merchant/test/paypal_express/index.php?capture=1">
    <fieldset>
        <legend>Capture</legend>
        <label for="authorization">Authorization</label>
        <input type="text" name="authorization" value="" id="authorization" />
        <input type="submit" />
    </fieldset>
</form>

<form method="post" action="/Aktive-Merchant/test/paypal_express/index.php?credit=1">
    <fieldset>
        <legend>Credit</legend>
        <label for="authorization">Authorization</label>
        <input type="text" name="authorization" value="" id="authorization" />
        <input type="submit" />
    </fieldset>
</form>

<form method="post" action="/Aktive-Merchant/test/paypal_express/index.php?void=1">
    <fieldset>
        <legend>Void</legend>
        <label for="authorization">Authorization</label>
        <input type="text" name="authorization" value="" id="authorization" />
        <input type="submit" />
    </fieldset>
</form>
