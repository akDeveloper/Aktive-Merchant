<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\PiraeusPaycenterRedirect;
use AktiveMerchant\Billing\Base;

class PiraeusPaycenterRedirectTest extends \AktiveMerchant\TestCase
{

    /**
     * Setup
     */
    function setUp()
    {
        Base::mode('test');


        $options = $this->getFixtures()->offsetGet('piraeus_paycenter_redirect');

        $this->gateway = new PiraeusPaycenterRedirect($options);

        $this->amount = 1;

        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId()
        );
    }
    public function testTicket()
    {
        $response = $this->gateway->ticket($this->amount, $this->options);

        $this->assert_success($response);
        $this->assertTrue($response->test());
    }
}
