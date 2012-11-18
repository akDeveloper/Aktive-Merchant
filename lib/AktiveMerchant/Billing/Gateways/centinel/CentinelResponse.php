<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Response;

/**
 * Description of CentinelResponse
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class CentinelResponse extends Response
{
    public function message()
    {
        if (isset($this->params['enrolled']) && $this->params['enrolled'] == 'N')
            return 'Cardholder not enrolled! ';
        
        return $this->params['error_no'] . ": " . $this->message;
    }

    public function isLiabilityShifted()
    {
        if(!$this->isCmpiLookupResponse())
            return false;

        return ($this->params['enrolled'] == 'N') ||
            ($this->params['pares_status'] == 'A' && empty($this->params['cavv']));
    }

    private function isCmpiLookupResponse()
    {
        return isset($this->params['enrolled']);
    }
}