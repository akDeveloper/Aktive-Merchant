<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways\Paypal;

use AktiveMerchant\Billing\Response;
/**
 * Description of PaypalExpressResponse
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class PaypalExpressResponse extends Response
{

    public function email()
    {
        return $this->paramOrNull('EMAIL');
    }

    public function name()
    {
        $first_name = $this->paramOrNull('FIRSTNAME');
        $middle_name = $this->paramOrNull('MIDDLENAME');
        $last_name = $this->paramOrNull('LASTNAME');
        return implode(' ', array_filter(array($first_name, $middle_name, $last_name)));
    }

    public function token()
    {
        return $this->paramOrNull('TOKEN');
    }

    public function payer_id()
    {
        return $this->paramOrNull('PAYERID');
    }

    public function payer_country()
    {
        return $this->paramOrNull('SHIPTOCOUNTRYNAME');
    }

    public function amount()
    {
        return $this->paramOrNull('AMT');
    }

    public function address()
    {
        return array(
            'name' => $this->paramOrNull('SHIPTONAME'),
            'address1' => $this->paramOrNull('SHIPTOSTREET'),
            'city' => $this->paramOrNull('SHIPTOCITY'),
            'state' => $this->paramOrNull('SHIPTOSTATE'),
            'zip' => $this->paramOrNull('SHIPTOZIP'),
            'country_code' => $this->paramOrNull('SHIPTOCOUNTRYCODE'),
            'country' => $this->paramOrNull('SHIPTOCOUNTRYNAME'),
            'address_status' => $this->paramOrNull('ADDRESSSTATUS')
        );
    }

    public function note()
    {
        return $this->param('NOTE');
    }

    private function paramOrNull($name)
    {
        return isset($this->params[$name]) ? $this->params[$name] : null;
    }

}

?>
