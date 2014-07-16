<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Common;

/**
 * OptionsTest class.
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 */

class OptionsTest extends \PHPUnit_Framework_TestCase
{
    public $options = array();

    public function setUp()
    {
        $this->options = array(
            'login' => 'x',
            'password' => 'y',
            'billing_address' => array(
                'address1' => '1234 Test Street',
                'city' => 'MyCity',
                'state' => 'MyState',
                'country' => 'MyCountry',
                'zip' => 'Postal Code'
            )
        );
    }

    public function testArrayAccess()
    {
        $options = new Options($this->options);

        $login = $options['login'];
        $password= $options['password'];

        $this->assertEquals('x', $login);
        $this->assertEquals('y', $password);
    }

    public function testAccessArrayAsObject()
    {
        $options = new Options($this->options);

        $login = $options->login;
        $password= $options->password;

        $this->assertEquals('x', $login);
        $this->assertEquals('y', $password);
    }

    public function testAccessRecursiveArray()
    {
        $options = new Options($this->options);

        $this->assertInstanceOf(
            'AktiveMerchant\\Common\\Options',
            $options->billing_address
        );

        $address1 = $options->billing_address->address1;

        $this->assertEquals('1234 Test Street', $address1);
    }

    public function testRequired()
    {
        $options = new Options($this->options);

        $exists = Options::required('login, password', $options);

        $this->assertTrue($exists);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailRequired()
    {
        $options = new Options($this->options);

        $exists = Options::required('pass', $options);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailRecursiveRequired()
    {
        $options = new Options($this->options);

        $exists = Options::required('pass', $options->billing_address);
    }
}
