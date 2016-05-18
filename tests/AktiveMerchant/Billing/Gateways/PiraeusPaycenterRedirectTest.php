<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\PiraeusPaycenterRedirect;
use AktiveMerchant\Billing\Base;

class PiraeusPaycenterRedirectTest extends \AktiveMerchant\TestCase
{
    /**
     * Setup
     */
    public function setUp()
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
        $this->mock_request($this->successTicketResponse());
        $response = $this->gateway->ticket($this->amount, $this->options);

        $this->assertNotNull($response->authorization());
        $this->assert_success($response);
        $this->assertTrue($response->test());
    }

    private function successTicketResponse()
    {
        return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><IssueNewTicketResponse xmlns="http://piraeusbank.gr/paycenter/redirection"><IssueNewTicketResult><ResultCode>0</ResultCode><ResultDescription /><TranTicket>b7239efc5fdc49569c5cc035aca4b3e3</TranTicket><Timestamp>2016-03-03T21:17:15.6354785+02:00</Timestamp><MinutesToExpiration>30</MinutesToExpiration></IssueNewTicketResult></IssueNewTicketResponse></soap:Body></soap:Envelope>';
    }
}
