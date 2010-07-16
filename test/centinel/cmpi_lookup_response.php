<?php
require_once('../../lib/merchant.php');

require_once 'initialize_gateway.php';

try {
  if ( isset($_POST['submit-cmpi-lookup']) ) {

    $cc = new Merchant_Billing_CreditCard( array(
        "first_name" => $_POST['first_name'],
        "last_name" => $_POST['last_name'],
        "number" => $_POST['card_number'],
        "month" => $_POST['month'],
        "year" => $_POST['year'],
        "verification_value" => $_POST['verification_value']
      )
    );

    $options = array(
      'order_id' => 'REF' . $gateway->generate_unique_id()
    );

    $response = $gateway->lookup('1', $cc, $options);

    if ( $response->success() && $response->enrolled == 'Y' ) {    
      $_SESSION['transaction_id'] = $response->authorization();
      echo '<HTML><BODY onload="document.frmLaunch.submit(); return false;">
        <FORM name="frmLaunch" method="POST" action="'.$response->acs_url.'">
        <input type=hidden name="PaReq" value="'.$response->payload.'">
        <input type=hidden name="TermUrl" value="http://localhost/merchant/test/centinel/cmpi_authenticate.php">
        <input type=hidden name="MD" value="">
        </FORM>
        </BODY></HTML>
        ';
    } else {
      echo "<p>{$response->message()}</p>";
    }
  } else {
    if ( isset($_POST['PaReq']) &&  $_POST['PaReq'] == "" ) {
      echo '<p>Time Out Error</p>';
      echo '<p><a href="index.php">Return to test page</a></p>';
    }
  }

} catch (Exception $exc) {
  echo $exc->getMessage();
}
?>
