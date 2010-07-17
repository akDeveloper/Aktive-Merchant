<?php
require_once('../../lib/merchant.php');

require_once 'initialize_gateway.php';

try {
  if ( isset($_POST['submit-cmpi-lookup']) ) {
    $_SESSION['transaction_id'] = null;
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
      ?>
  <table border="1" cellspacing="4" cellpadding="4">
    <thead>
      <tr>
        <th>Enrolled</th>
        <th>EciFlag</th>
        <th>TransactionId</th>
        <th>ErrorNo</th>
        <th>ErrorDesc</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><?php echo $response->enrolled ?></td>
        <td><?php echo $response->eci_flag ?></td>
        <td><?php echo $response->transaction_id ?></td>
        <td><?php echo $response->error_no ?></td>
        <td<?php echo $response->error_desc ?></td>
      </tr>
    </tbody>
  </table>
  <p><a href="index.php">Return to test page</a></p>
      <?php
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