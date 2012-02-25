<?php

class Merchant_Billing_PayflowResponse extends Merchant_Billing_Response
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
