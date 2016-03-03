<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways\Worldpay;

class XmlNormalizer extends \Thapp\XmlBuilder\Normalizer
{
    protected function normalizeString($string)
    {
        return $string;
    }
}
