<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Common;

/**
 * AddressTest class.
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 */

class AddressTest extends \PHPUnit_Framework_TestCase
{
    public function testMapping()
    {
        $user_address = array(
            'city_name' => 'Athens',
            'country_name' => 'Greece',
            'state' => 'Attiki',
            'postal_code' => '23456',
            'address_line_1' => 'Address 7 Street',
            'phone_number' => '30210123456',
            'fullname' => 'Andreas Kollaros',
        );

        $address = new Address();

        $address->setFields($user_address);

        $address->map('fullname', 'SHIPTONAME')
            ->map('phone_number', 'PHONENUM')
            ->map('city_name', 'SHIPTOCITY')
            ->map('address_line_1', 'SHIPTOSTREET')
            ->map('state', 'SHIPTOSTATE')
            ->map('country_name', 'SHIPTOCOUNTRY')
            ->map('postal_code', 'SHIPTOZIP');

        $fields = $address->getMappedFields();

        $this->assertEquals('Attiki', $fields['SHIPTOSTATE']);
        $this->assertEquals('Athens', $fields['SHIPTOCITY']);
        $this->assertEquals('23456', $fields['SHIPTOZIP']);

    }
}
