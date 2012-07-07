<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Interfaces;

use AktiveMerchant\Billing\CreditCard;

/**
 * Credit card storage interface
 * 
 * @package Aktive-Merchant
 * @todo Needs documentation
 */
interface Store 
{

    public function store(CreditCard $creditcard, $options);

    public function unstore(CreditCard $creditcard, $options);
}
