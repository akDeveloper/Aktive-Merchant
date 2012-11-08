<?php

namespace AktiveMerchant\Billing\Integrations;

/**
 * Description of Eurobank
 *
 * @author Andreas Kollaros
 */
class Eurobank extends Helper
{

    public function __construct($order, $account, $options=array())
    {
        parent::__construct($order, $account, $options);
        $this->mapping('billing_address', array(
            'country' => 'country'
        ));

        $this->mapping('customer', array(
            'first_name' => 'firstname',
            'last_name' => 'lastname'
        ));
        $this->mapping('currency', 'currency_code');
        
        $this->mapping('amount', 'amount');
    }

}

?>
