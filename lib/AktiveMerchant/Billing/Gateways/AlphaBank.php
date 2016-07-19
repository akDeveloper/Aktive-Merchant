<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

class AlphaBank extends Modirum
{
    const TEST_URL = 'https://alpha.test.modirum.com/vpos/xmlpayvpos';
    const LIVE_URL = 'https://www.alphaecommerce.gr/vpos/xmlpayvpos';

    const MPI_TEST_URL = 'https://alpha.test.modirum.com/mdpaympi/MerchantServer';
    const MPI_LIVE_URL = 'https://www.alphaecommerce.gr/mdpaympi/MerchantServer';

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'https://www.alpha.gr/e-services/gr/coorporate/alpha-e-commerce/';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'Alpha e-Commerce';
}
