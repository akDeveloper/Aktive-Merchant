<?php

namespace AktiveMerchant\Billing\Integrations;

/**
 * Description of Integration
 *
 * @author Andreas Kollaros
 */
class Integration
{

    private function __construct()
    {

    }

    private static function getInstance($order, $account, $options=array())
    {
        if (!isset($options['service']))
            throw new Exception("service parameter is required!");

        require_once dirname(__FILE__) . "/" . $options['service'] . '.php';

        $service_class = 'AktiveMerchant\\Billing\\Integrations\\' . $options['service'];
        unset($options['service']);

        return new $service_class($order, $account, $options);
    }

    public static function payment_service_for($order, $account, $options=array())
    {
        return self::getInstance($order, $account, $options);
    }

}

?>
