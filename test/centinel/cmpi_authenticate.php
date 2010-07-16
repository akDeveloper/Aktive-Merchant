<?php 
require_once('../../lib/merchant.php');

require_once('initialize_gateway.php');

try {

  $response = $gateway->authenticate( array(
      'transaction_id' => $_SESSION['transaction_id'],
      'payload' => $_POST['PaRes']
    ));
  if ( $response->success() ) {
?>
  <table border="1" cellspacing="4" cellpadding="4">
    <thead>
      <tr>
        <th>PAResStatus</th>
        <th>SignatureVerification</th>
        <th>EciFlag</th>
        <th>Xid</th>
        <th>Cavv</th>
        <th>ErrorNo</th>
        <th>ErrorDesc</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><?php echo $response->pares_status ?></td>
        <td><?php echo $response->signature_verification ?></td>
        <td><?php echo $response->eci_flag ?></td> 
        <td><?php echo $response->xid ?></td>
        <td><?php echo $response->Cavv ?></td>
        <td><?php echo $response->ErrorNo ?></td>
        <td<?php echo $response->ErrorDesc ?></td>
      </tr>
    </tbody>
  </table>

<?php
  } else {
    echo "<p>{$response->message()}</p>";
  }

} catch (Exception $exc) {
  echo $exc->getMessage();
}
?>
  <p><a href="index.php">Return to test page</a></p>
