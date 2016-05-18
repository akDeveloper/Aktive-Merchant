<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

/**
 * Support 3D Secure implementation for National Bank of Greece gateway.
 *
 * @author Andreas Kollaros <andreas@larium.net>
 * @license MIT License http://www.opensource.org/licenses/mit-license.php
 */
class NbgMpi extends DataCashMpi
{
    const TEST_URL = 'https://accreditation.datacash.com/Transaction/acq_a';
    const LIVE_URL = 'https://mars.transaction.datacash.com/Transaction';

    public static $default_currency = 'EUR';
}
