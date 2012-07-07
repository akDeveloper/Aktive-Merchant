<?php 

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Interfaces;

use AktiveMerchant\Billing\CreditCard;

/**
 * Interface for a merchant gateway that supports authorize, purchase, and capture.
 */
interface Charge 
{
    /**
     * Authorize a transaction without actually charging the card.
     *
     * The authorization can be charged with the {@link capture()} method.
     *
     * @param float $money Amount of money to authorize
     * @param \AktiveMerchant\Billing\CreditCard $creditcard Credit card to authorize
     * @param array $options Additional options to the driver.  Common options include:
     *                              <ul>
     *                              	<li>description - Description that should appear on the card owner's bill
     *                              	<li>invoice_num - Invoice number to reference
     *                              	<li>billing_address - Billing address entered by the user.  Includes the following fields:
     *                              		<ul>
     *                              			<li>address1 - First line of address
     *                              			<li>address2 - Second line of address
     *                              			<li>company - Company being billed
     *                              			<li>phone - Phone number
     *                              			<li>zip - Billing ZIP code
     *                              			<li>city - Billing city
     *                              			<li>country - Billing country
     *                              			<li>state - Billing state
     *                              		</ul>
     *                              	<li>email - Email address of customer
     *                              	<li>customer - Customer ID
     *                              	<li>ip - IP address of customer
     *                              </ul>
     * @return \AktiveMerchant\Billing\Response Response object
     * @throws \AktiveMerchant\Billing\Exception If the request fails
     * @package Aktive-Merchant
     */
    public function authorize($money, CreditCard $creditcard, $options = array());

    /**
     * Charge a credit card.
     *
     * @param float $money Amount of money to charge
     * @param \AktiveMerchant\Billing\CreditCard $creditcard Credit card to charge
     * @param array $options Additional options to the driver.  For details see {@link authorize()}.
     * @return \AktiveMerchant\Billing\Response Response object
     * @throws \AktiveMerchant\Billing\Exception If the request fails
     */
    public function purchase($money, CreditCard $creditcard, $options = array());

    /**
     * Charge a credit card after prior authorization.
     *
     * Charges a card after a prior authorization by {@link authorize()}.
     *
     * @param float $money Amount of money to charge
     * @param string $authorization Authorization transaction ID (from {@link \AktiveMerchant\Billing\Response::authorization()})
     * @param array $options Additional options to the driver.  For details see {@link authorize()}.
     * @return \AktiveMerchant\Billing\Response Response object
     * @throws \AktiveMerchant\Billing\Exception If the request fails
     * @package Aktive-Merchant
     */
    public function capture($money, $authorization, $options = array());
}
