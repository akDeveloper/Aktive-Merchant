<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

/**
 * Description of Cardlink gateway.
 *
 * @category Gateways
 * @package  Aktive-Merchant
 * @author   Your name <your@email.com>
 * @license  MIT License http://www.opensource.org/licenses/mit-license.php
 * @link     https://github.com/akDeveloper/Aktive-Merchant
 */
class Cardlink extends Modirum
{
    const TEST_URL = 'https://euro.test.modirum.com/vpos/xmlpayvpos';
    const LIVE_URL = 'https://example.com/live';

    const MPI_TEST_URL = 'https://euro.test.modirum.com/mdpaympi/MerchantServer';
    const MPI_LIVE_URL = 'https://euro.test.modirum.com/mdpaympi/MerchantServer';

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'http://www.cardlink.gr';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'Cardlink';
}
