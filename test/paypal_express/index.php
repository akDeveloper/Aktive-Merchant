<?php
require_once('../../lib/merchant.php');
require_once('../login.php');

Merchant_Billing_Base::mode('test');

$gateway = new Merchant_Billing_PaypalExpress( array(
  'login' => PAYPAL_LOGIN,
  'password' => PAYPAL_PASS,
  'signature' => PAYPAL_SIG,
  'currency' => 'EUR'
  )
);

try {

  if ( isset($_GET['pay']) ) {

    $response = $gateway->setup_purchase($_POST['amount'], array(
      'return_url' => 'http://localhost/merchant/test/paypal_express/index.php',
      'cancel_return_url' => 'http://localhost/merchant/test/paypal_express/index.php?cancel=1',
      )
    );


    die ( header('Location: '. $gateway->url_for_token( $response->token() )) );

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
<form method="post" action="/merchant/test/paypal_express/index.php?pay=1">
  <label for="amount">Amount</label>
  <input type="text" name="amount" value="1" id="amount" />
  <input type="submit" />
</form>
