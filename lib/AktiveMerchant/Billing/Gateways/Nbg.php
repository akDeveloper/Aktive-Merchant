<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

/**
 * National Bank of Greece gateway implementation.
 *
 * @author  Andreas Kollaros <andreas@larium.net>
 */
class Nbg extends DataCash
{
    const TEST_URL = 'https://accreditation.datacash.com/Transaction/acq_a';
    const LIVE_URL = 'https://mars.transaction.datacash.com/Transaction';

    /**
     * {@inheritdoc}
     */
    public static $supported_countries = array('GR');

    /**
     * {@inheritdoc}
     */
    public static $supported_cardtypes = array(
        'visa',
        'master',
        'maestro'
    );

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'https://www.nbg.gr';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'DataCash for NBG';

    /**
     * {@inheritdoc}
     */
    public static $default_currency = 'EUR';
}
