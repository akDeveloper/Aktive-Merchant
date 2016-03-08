<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

class NbgMpi extends DataCashMpi
{
    const TEST_URL = 'https://accreditation.datacash.com/Transaction/acq_a';
    const LIVE_URL = 'https://mars.transaction.datacash.com/Transaction';
}
