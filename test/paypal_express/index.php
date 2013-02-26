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
    } elseif ( isset($_GET['recurring']) ) {
        $options = array(
            'return_url' => 'http://127.0.0.1/Aktive-Merchant/test/paypal_express/index.php',
            'cancel_return_url' => 'http://127.0.0.1/Aktive-Merchant/test/paypal_express/index.php?cancel=1',
            'desc' =>'Golden plan subscription',
            'items' => array(
                array(
                    'name' => 'Golden Plan',
                    'description' => 'Golden plan subscription',
                    'unit_price' => 30,
                    'quantity' => 1,
                    'id' => '1245345'
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
        $amount = $_POST['amount'];
        $startdate = new \DateTime("{$_POST['year']}-{$_POST['month']}-{$_POST['day']}");

        $response = $gateway->setupRecurring($amount, $options);

        if ($response->success()) {
            die ( header('Location: '. $gateway->urlForToken( $response->token() )) );
        } else {
            echo $response->message();
        }

        echo '<pre>';
        //print_r($response);
        print_r($startdate);
        echo '</pre>';
    } elseif ( isset($_GET['details']) ) {

        $response = $gateway->getRecurringDetails('I-BGKDETHHBHU1');

        echo '<pre>';
        print_r($response);
        echo '</pre>';
    } elseif (isset( $_GET['token'] ) ) {

        //$response = $gateway->get_details_for( $_GET['token'], $_GET['PayerID']);
        
        /**
         * You can modify transaction amount according to paypal ship address
         * or even render a form to allow customer choose shipping methods and
         * additional costs.
         * NOTE: if you execute $gateway->authorize() or $gateway->purchase() to a
         * different page than you executed $gateway->get_details_for()
         * make sure you have store somewhere token and payer_id values
         * ex. $_SESSION or Database
         */
        $opt = array(
            'start_date' => '2013-2-16T0:0:0',
            'period' => 'Month',
            'frequency' => 1,
            'token' => $_GET['token'],
            'description' => 'Golden plan subscription',
            'items' => array(
                array(
                    'name' => 'Golden Plan',
                    'description' => 'Golden plan subscription',
                    'unit_price' => 30,
                    'quantity' => 1,
                    'id' => '1245345'
                ),
            ),
            'billing_address' => array(
                'address1' => '1234 Penny Lane',
                'city' => 'Jonsetown',
                'state' => 'NC',
                'country' => 'US',
                'zip' => '23456'
            ),
            'email' => 'john_doe@example.com'
        );
        
        $response = $gateway->recurring(30, $opt);
        
        if ( $response->success() ) {
            echo 'Success payment!';
        } else {
            echo $response->message();
        }
        echo '<pre>';
        print_r($response);
        echo '</pre>';
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

<form method="post" action="/Aktive-Merchant/test/paypal_express/index.php?recurring=1">
    <fieldset>
        <legend>Recurring</legend>
        <label for="amount">Amount</label>
        <input type="text" name="amount" value="30" id="amount" />
        <label for="startdate">Start Date</label>
        <select id="year" name="year">
            <?php $year = date("Y", time());?>
            <?php for ($i = $year; $i < $year+10; $i++): ?>
            <option value="<?php echo $i?>"><?php echo $i?></option>
            <?php endfor; ?>
        </select>
        <select id="month" name="month">
            <?php for ($i = 1; $i <= 12; $i++): ?>
            <option value="<?php echo $i?>"><?php echo $i?></option>
            <?php endfor; ?>
        </select>
        <select id="day" name="day">
            <?php for ($i = 1; $i <= 31 ; $i++): ?>
            <option value="<?php echo $i?>"><?php echo $i?></option>
            <?php endfor; ?>
        </select>
        <label for="period">Billing Period</label>
        <select id="period" name="period">
            <option value="Day">Day</option>
            <option value="Week">Week</option>
            <option value="SemiMonth">Semi Month</option>
            <option value="Month">Month</option>
            <option value="Year">Year</option>
        </select>
        <label for="frequency">Frequency</label>
        <input type="text" name="frequency" value="1" id="frequency" />
        <input type="submit" />
    </fieldset>
</form>
