<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Interfaces;

/**
 * Interface for a merchant gateway that supports credit and void.
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
interface Credit
{
    /**
     * Credit a charge back to an account.
     *
     * @param number $money          Amount of money to charge
     * @param string $identification Authorization transaction ID
     *                               (from {@link \AktiveMerchant\Billing\Response::authorization()})
     * @param array  $options        Additional options to the driver.
     *                               For details see {@link \AktiveMerchant\Billing\Interfaces\Charge::authorize()}.
     * @access public
     * @throws \AktiveMerchant\Billing\Exception If the request fails
     * @return \AktiveMerchant\Billing\Response  Response object
     */
    public function credit($money, $identification, $options = array());

    /**
     * Void an earlier transaction that has not yet been settled.
     *
     * @param string $authorization Authorization transaction ID
     *                              (from {@link \AktiveMerchant\Billing\Response::authorization()})
     * @param array  $options       Additional options to the driver.  For details
     *                              see {@link \AktiveMerchant\Billing\Interfaces\Charge::authorize()}.
     * @access public
     * @throws \AktiveMerchant\Billing\Exception If the request fails
     * @return \AktiveMerchant\Billing\Response  Response object
     */
    public function void($authorization, $options = array());
}
