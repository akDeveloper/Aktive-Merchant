<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Common;

/**
 * Address class allows mapping user defined fields of an address array to
 * corresponding fields of a gateway.
 *
 * <code>
 *      $user_address = array(
 *          'city' => 'Athens',
 *          'country' => 'Greece',
 *      );
 *
 *      $address = new Address($user_address); // create new instance
 *
 *      $address->map('city', 'GATEWAY_CITY')    // maps the city field.
 *          ->map('country', 'GATEWAY_COUNTRY'); // maps the country field.
 *
 *      $fields = $address->getMappedFields();
 *      print_r($fields);
 *      // Output:
 *      // Array(
 *      //      'GATEWAY_CITY' => 'Athens',
 *      //      'GATEWAY_COUNTRY' => 'Greece'
 *      // )
 *
 *
 * </code>
 *
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://opensource.org/licenses/mit-license.php
 */
class Address
{

    /**
     * An array or Options class with user definded fields of an address.
     *
     * @var array|Options
     */
    protected $fields = array();

    /**
     * An array with mapped references from user defined address and gateway.
     *
     * @var array
     */
    protected $mappings = array();

    public function __construct($fields = array())
    {
        $this->fields = $fields;
    }

    /**
     * Maps an address field from user to the corresponding field from the
     * gateway.
     *
     * @param string $user_field
     * @param string $gateway_field
     *
     * @access public
     *
     * @return Address
     */
    public function map($user_field, $gateway_field)
    {
        $this->mappings[$user_field] = $gateway_field;

        return $this;
    }

    /**
     * Sets an array with address fields
     *
     * @param array $fields
     *
     * @access public
     *
     * @return void
     */
    public function setFields($fields = array())
    {
        $this->fields = $fields;
    }

    /**
     * Gets an array with address fields.
     *
     * @access public
     *
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Gets an array with mapped address fields.
     *
     * @access public
     *
     * @return array
     */
    public function getMappedFields()
    {
        $mapped = array();

        foreach ($this->fields as $key => $value) {
            if (array_key_exists($key, $this->mappings)) {
                $map = $this->mappings[$key];
                $mapped[$map] = $value;
            }
        }

        return $mapped;
    }
}
