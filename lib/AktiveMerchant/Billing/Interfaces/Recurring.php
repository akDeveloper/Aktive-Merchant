<?php 

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Interfaces;

use AktiveMerchant\Billing\CreditCard;

/**
 * Recurring billing interface
 * 
 * @package Aktive-Merchant
 * @todo Needs documentation
 */
interface Recurring 
{
    public function recurring($money, CreditCard $creditcard, $options=array());

    public function updateRecurring($subscription_id, CreditCard $creditcard);

    public function cancelRecurring($subscription_id);
}
