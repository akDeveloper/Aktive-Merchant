<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Interfaces;

use AktiveMerchant\Billing\CreditCard;

/**
 * Credit card storage interface
 *
 * Using Store actions, a merchant can store a reference of a creditcard.
 * Then can charge customers using this reference instead of real data of
 * a credit card.
 *
 * @package Aktive-Merchant
 * @author Andreas Kollaros
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
interface Store
{

    /**
     * Stores a reference of a credit card.
     *
     * @param CreditCard $creditcard
     * @param array      $options
     * @access public
     * @return \AktiveMerchant\Billing\Response
     */
    public function store(CreditCard $creditcard, $options = array());

    /**
     * Unstores a reference of a credit card.
     *
     * @param mixed $reference
     * @param array $options
     * @access public
     * @return \AktiveMerchant\Billing\Response
     */
    public function unstore($reference, $options = array());
}
