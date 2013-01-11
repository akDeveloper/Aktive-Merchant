<?php
require_once dirname(__FILE__) . "/initialize_gateway.php";

use AktiveMerchant\Billing\CreditCard;

if (!empty($_POST)){
    try {

        # Create a creditcard object
        $cc = new CreditCard( array(
            "first_name" => $_POST['first_name'],
            "last_name" => $_POST['last_name'],
            "number" => $_POST['card_number'],
            "month" => $_POST['month'],
            "year" => $_POST['year'],
            "verification_value" => $_POST['verification_value']
        )
    );

        # Create an array of options that may needed for the transaction
        $options = array();
        if ( isset($_POST['address'])) {
            $options = array(
                'address' => array(
                    'name' => $_POST['address']['name'],
                    'address1' => $_POST['address']['address1'],
                    'zip' => $_POST['address']['zip'],
                    'state' => $_POST['address']['state'],
                    'country' => $_POST['address']['country'],
                    'city' => $_POST['address']['city']
                ),
                'shipping_address' => array(
                    'name' => $_POST['address']['name'],
                    'address1' => $_POST['address']['address1'],
                    'zip' => $_POST['address']['zip'],
                    'state' => $_POST['address']['state'],
                    'country' => $_POST['address']['country'],
                    'city' => $_POST['address']['city']
                ),
                'street_number' => $_POST['street_number'],
                'street_name' => $_POST['street_name']
            );
        }

        $options['order_id'] = $_POST['order_id'];

        $money = $_POST['amount'];
        $authorization = $_POST['txn_number'];

        # Payment actions for Moneris Gataway.
        switch ($_POST['pay-action']) {
        case 'PreAuth':
            $response = $gateway->authorize($money, $cc, $options);
            break;
        case 'Purchase':
            $response = $gateway->purchase($money, $cc, $options);
            break;
        case 'Capture':
            $response = $gateway->capture($money, $authorization, $options);
            break;
        case 'Void':
            $response = $gateway->void($authorization, $options);
            break;
        case 'Credit':
            $response = $gateway->credit($money, $authorization, $options);
            break;

        default:
            break;
        }


        if ( $response->success() ) {
            echo 'Success transaction <b>' . $_POST['pay-action'] ."</b>!";
            echo '<br />';
            echo '<b>Transaction Number:</b> ' . $response->authorization() . ' Needed for actions Capture, Void and Credit';
            echo '<br />';
            echo '<b>Reference Number:</b> ' . $response->reference_num . ' This information should be stored by the merchant (As eSelect Plus says at Appendix B on eSELECTplus_PHP_IG-US.pdf)';
            echo '<br />';
            echo '<b>Your Order Id:</b> ' . $response->receipt_id;
            echo '<br />';
        } else {
            echo $response->message();
        }
    } catch (Exception $exc) {
        echo $exc->getMessage();
    }
}
require_once dirname(__FILE__) . "/views/creditcard_form.php";
