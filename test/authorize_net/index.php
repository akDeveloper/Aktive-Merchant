<?php
require_once('../../autoload.php');
require_once('../login.php');

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

Base::mode('test'); # Remove this on production mode

#Alternative way to get a gateway instanse.
$gateway = Base::gateway('authorize_net', array(
    'login' => AUTH_NET_LOGIN,
    'password' => AUTH_NET_PASS));

$cc = new CreditCard( array(
    "first_name" => "John",
    "last_name" => "Doe",
    "number" => "4111111111111111",
    "month" => "01",
    "year" => "2015",
    "verification_value" => "000"
)
);

$options = array(
    'order_id' => 'REF' . $gateway->generateUniqueId(),
    'description' => 'Autorize.net Test Transaction',
    'address' => array(
        'address1' => '1234 Street',
        'zip' => '98004',
        'state' => 'WA'
    )
);

try {
    if( false == $cc->isValid() ) {
        var_dump($cc->errors());
    } else {
        $response = $gateway->authorize("0.01", $cc, $options);
        echo $response->message()."\n";
    }

} catch (Exception $e) {
    echo $e->getMessage()."\n";
}
?>
