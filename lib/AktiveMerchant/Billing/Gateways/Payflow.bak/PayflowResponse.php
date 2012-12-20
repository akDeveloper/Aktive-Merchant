<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways\Payflow;

use AktiveMerchant\Billing\Response;

class PayflowResponse extends Response
{

    function profileId()
    {
        return $this->params['profileId'];
    }

    function paymentHistory()
    {
        if (is_array($this->params['rpPaymentResult']))
            return $this->params['rpPaymentResult'];
        else
            return array();
    }

}
