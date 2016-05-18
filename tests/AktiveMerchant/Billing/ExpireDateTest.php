<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\ExpiryDate;

/**
 * ExpireDateTest class.
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 */
class ExpireDateTest extends PHPUnit_Framework_TestCase
{

    private $given;

    public function setUp()
    {

        $this->given['Date']['Expired'] = array(
            'Year'  => date('Y', strtotime('-1 year')),
            'Month' => date('m'),
        );

        $this->given['Date']['Valid'] = array(
            'Year'  => date('Y', strtotime('+5 years')),
            'Month' => date('m'),
        );

        $expired = new ExpiryDate(
            $this->given['Date']['Expired']['Month'],
            $this->given['Date']['Expired']['Year']
        );
        $valid = new ExpiryDate(
            $this->given['Date']['Valid']['Month'],
            $this->given['Date']['Valid']['Year']
        );

        $this->given['MerchantDate']['Expired'] = $expired;
        $this->given['MerchantDate']['Valid']   = $valid;
    }

    public function testSuccessfulExpireDate()
    {
        $this->assertTrue($this->given['MerchantDate']['Expired']->isExpired());
    }

    public function testFailedExpireDate()
    {
        $this->assertFalse($this->given['MerchantDate']['Valid']->isExpired());
    }

    public function testSuccessfulReturnExpirationTime()
    {
        $this->assertEquals(
            $this->given['Date']['Expired']['Year']
            . "-" . $this->given['Date']['Expired']['Month'],
            date('Y-m', $this->given['MerchantDate']['Expired']->expiration())
        );
    }

}
