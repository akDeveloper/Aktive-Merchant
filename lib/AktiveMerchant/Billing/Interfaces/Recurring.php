<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Interfaces;

use AktiveMerchant\Billing\CreditCard;

/**
 * Recurring billing interface
 *
 * Using Recurring actions, merchant can creates a profile of customer so can
 * charge him for a certain period of time.
 * The payment gateway takes the reponsibility to charge the customer for the
 * given period of time.
 *
 * @package Aktive-Merchant
 * @author Andreas Kollaros
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
interface Recurring
{
    /**
     * Creates a profile using a valid credit card so can charge it in a given
     * period of time.
     * $options array must have following info:
     *  - 'start_date' The date the subscription begins
     *  - 'period'     Unit for billing during the subscription period. Varies
     *                 according gateway
     *  - 'frequency'  Number of billing periods that make up one billing cycle
     *
     * @param number     $money      The amount to to charge
     * @param Creditcard $creditcard A creditcard instance with a valid number.
     * @param array      $options    Additional options to use per payment
     *                               gateway.
     * @access public
     * @return \AktiveMerchant\Billing\Response
     */
    public function recurring($money, CreditCard $creditcard, $options = array());

    /**
     * Updates the credit cart data for a profile.
     *
     * @param string     $subscription_id The reference for the current profile as
     *                                    returned from recurring action.
     * @param Creditcard $creditcard      A creditcard instance with a valid number.
     * @access public
     * @return \AktiveMerchant\Billing\Response
     */
    public function updateRecurring($subscription_id, CreditCard $creditcard);

    /**
     * Cancels a profile.
     *
     * @param string $subscription_id The reference for the current profile as
     *                                returned from recurring action.
     * @access public
     * @return \AktiveMerchant\Billing\Response
     */
    public function cancelRecurring($subscription_id);
}
