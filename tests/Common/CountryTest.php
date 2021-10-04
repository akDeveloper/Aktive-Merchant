<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use AktiveMerchant\Common\Country;

/**
 * CountryTest class.
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 */

class CountryTest extends TestCase
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

    public function testFailFindCountryEmptyValue()
    {
        $this->expectException(\InvalidArgumentException::class);
        $country = Country::find('');
    }

    public function testNotFoundCountry()
    {
        $this->expectException(\OutOfRangeException::class);
        $country = Country::find('Asgard');
    }
}
