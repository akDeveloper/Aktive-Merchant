<?php

namespace AktiveMerchant\Billing\Gateways\SecurePayAu;

class DateTime extends \DateTime {
    /**
     * Returns new DateTime object.  Adds microtime for "now" dates
     * @param string $sTime
     * @param DateTimeZone $oTimeZone 
     */
    public function __construct($sTime = 'now', \DateTimeZone $oTimeZone = NULL) {
        // check that constructor is called as current date/time
        if (strtotime($sTime) == time()) {
            $aMicrotime = explode(' ', microtime());
            $sTime = date('Y-m-d H:i:s.' . $aMicrotime[0] * 1000000, $aMicrotime[1]);
        }

        // DateTime throws an Exception with a null TimeZone
        if ($oTimeZone instanceof \DateTimeZone) {
            parent::__construct($sTime, $oTimeZone);
        } else {
            parent::__construct($sTime);
        }
    }
}
