<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing;

/**
 * Description of Date
 *
 * Usage:
 * <code>
 * $expire_date = new Merchant_Billing_ExpiryDate(5, 2010);
 * </code>
 * Public methods:
 * + is_expired() returns a boolean about expiration of given data
 * + expiration() returns expiration date as Unix timestamp
 *
 *
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class ExpiryDate
{

    private $year;
    private $month;

    public function __construct($month, $year)
    {
        $this->year = $year;
        $this->month = (int) $month;
    }

    public function isExpired()
    {
        return ( time() > $this->expiration() );
    }

    public function expiration()
    {
        $date = $this->year
            . "-"
            . $this->month
            . "-"
            . $this->monthDays()
            . " 23:59:59";
        return strtotime($date);
    }

    private function monthDays()
    {
        $mdays = array(null, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
        if ($this->isLeap()) {
            $mdays[2] = 29;
        }

        return $mdays[$this->month];
    }

    private function isLeap()
    {
        $time = strtotime($this->year . "-02-29");
        $time_array = localtime($time);
        if ($time_array[4] == 1) {
            return true;
        }

        return false;
    }
}
