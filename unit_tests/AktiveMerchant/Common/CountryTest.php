<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Common\Country;

/**
 * CountryTest class.
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 */

class CountryTest extends PHPUnit_Framework_TestCase
{

    public function testGetCountryFromNumeric()
    {
        $country = Country::find(300);

        $this->assertInstanceOf('AktiveMerchant\\Common\\Country', $country);

        $this->assertEquals($country->__toString(), 'Greece');

        $this->assertEquals($country->getCode('alpha2')->__toString(), 'GR');

        $this->assertEquals($country->getCode('alpha3')->__toString(), 'GRC');
    }

    public function testGetCountryFromAlpha2()
    {
        $country = Country::find('GR');

        $this->assertInstanceOf('AktiveMerchant\\Common\\Country', $country);

        $this->assertEquals($country->__toString(), 'Greece');

        $this->assertEquals($country->getCode('alpha3')->__toString(), 'GRC');

        $this->assertEquals($country->getCode('numeric')->__toString(), 300);
    }

    public function testGetCountryFromAlpha3()
    {
        $country = Country::find('GRC');

        $this->assertInstanceOf('AktiveMerchant\\Common\\Country', $country);

        $this->assertEquals($country->__toString(), 'Greece');

        $this->assertEquals($country->getCode('alpha2')->__toString(), 'GR');

        $this->assertEquals($country->getCode('numeric')->__toString(), 300);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailFindCountryEmptyValue()
    {
        $country = Country::find('');
    }

    /**
     * @expectedException \OutOfRangeException
     */
    public function testNotFoundCountry()
    {
        $country = Country::find('Asgard');
    }
}
