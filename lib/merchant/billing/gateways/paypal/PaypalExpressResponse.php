<?php

/**
 * Description of Merchant_Billing_PaypalExpressResponse
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Merchant_Billing_PaypalExpressResponse extends Merchant_Billing_Response
{

    public function email()
    {
        return $this->params['EMAIL'];
    }

    public function name()
    {
        $first_name = $this->params['FIRSTNAME'];
        $middle_name = isset($this->params['MIDDLENAME']) ? $this->params['MIDDLENAME'] : null;
        $last_name = $this->params['LASTNAME'];
        return implode(' ', array_filter(array($first_name, $middle_name, $last_name)));
    }

    public function token()
    {
        return $this->params['TOKEN'];
    }

    public function payer_id()
    {
        return $this->params['PAYERID'];
    }

    public function payer_country()
    {
        return $this->params['SHIPTOCOUNTRYNAME'];
    }

    public function amount()
    {
        return $this->params['AMT'];
    }

    public function address()
    {
        return array(
            'name' => $this->params['SHIPTONAME'],
            'address1' => $this->params['SHIPTOSTREET'],
            'city' => $this->params['SHIPTOCITY'],
            'state' => $this->params['SHIPTOSTATE'],
            'zip' => $this->params['SHIPTOZIP'],
            'country_code' => $this->params['SHIPTOCOUNTRYCODE'],
            'country' => $this->params['SHIPTOCOUNTRYNAME'],
            'address_status' => $this->params['ADDRESSSTATUS']
        );
    }

    public function note()
    {
        return isset($this->params['NOTE']) ? $this->params['NOTE'] : null;
    }

}

?>
