<?php

namespace AktiveMerchant\Billing\Gateways;

class HsbcGlobalIris extends HsbcSecureEpayments
{
    const TEST_URL = 'https://apixml.globaliris.com';
    const LIVE_URL = 'https://apixml.globaliris.com';

    public static $display_name = 'HSBC Global Iris';

    protected function successFrom($action, $response)
    {
        if ($action == 'authorize' || $action == 'purchase' || $action == 'capture') {
            $transaction_status = $this->TRANSACTION_STATUS_MAPPINGS['accepted'];
        } elseif ($action == 'void') {
            $transaction_status = $this->TRANSACTION_STATUS_MAPPINGS['void'];
        } else {
            $transaction_status = null;
        }

        return ( ( isset($response['return_code']) && $response['return_code'] == self::APPROVED ) &&
        $response['transaction_id'] != null &&
        $response['transaction_status'] == $transaction_status);
    }
}
