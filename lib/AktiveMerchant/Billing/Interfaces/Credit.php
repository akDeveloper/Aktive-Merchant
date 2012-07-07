<?php 

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Interfaces;

/**
 * Interface for a merchant gateway that supports credit and void.
 * @package Aktive-Merchant
 */
interface Credit 
{
    /**
     * Credit a charge back to an account.
     *
     * @param float $money Amount of money to charge
     * @param string $identification Authorization transaction ID (from {@link \AktiveMerchant\Billing\Response::authorization()})
     * @param array $options Additional options to the driver.  For details see {@link authorize()}.
     * @return \AktiveMerchant\Billing\Response Response object
     * @throws \AktiveMerchant\Billing\Exception If the request fails
     * @package Aktive-Merchant
     */
    public function credit($money, $identification, $options = array());

    /**
     * Void an earlier transaction that has not yet been settled.
     *
     * @param string $authorization Authorization transaction ID (from {@link \AktiveMerchant\Billing\Response::authorization()})
     * @param array $options Additional options to the driver.  For details see {@link authorize()}.
     * @return \AktiveMerchant\Billing\Response Response object
     * @package Aktive-Merchant
     */
    public function void($authorization, $options = array());
}
