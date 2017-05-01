<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

/**
 * Integration of Cardlink gateway.
 *
 * @author Andreas Kollaros <andreas@larium.net>
 * @license  MIT License http://www.opensource.org/licenses/mit-license.php
 */
class Cardlink extends Modirum
{
    const TEST_URL = 'https://euro.test.modirum.com/vpos/xmlpayvpos';
    const LIVE_URL = 'https://ep.eurocommerce.gr/vpos/xmlpayvpos';

    const MPI_TEST_URL = 'https://euro.test.modirum.com/mdpaympi/MerchantServer';
    const MPI_LIVE_URL = 'https://ep.eurocommerce.gr/mdpaympi/MerchantServer';

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'http://www.cardlink.gr';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'Cardlink';
}
