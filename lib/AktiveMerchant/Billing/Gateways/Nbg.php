<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

class Nbg extends DataCash
{
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
