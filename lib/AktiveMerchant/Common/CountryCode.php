<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Common;

use AktiveMerchant\Billing\Exception;

class CountryCode
{
    /**
     * @var string|integer
     */
    protected $value;

    /**
     * The format of current CuntryCode.
     *
     * @var string Values are alpha2, alpha3 or numeric
     */
    protected $format;

    /**
     * @param string|integer The value of country code.Can be an alpha2, alpha3
     *                      or numeric value (ex. GR, GRC or 300)
     */
    public function __construct($value)
    {
        $this->value = strtoupper($value);
        $this->detectFormat();
    }

    /**
     * Getter for format value.
     *
     * @return string The format type of current CountryCode.
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @return string The current value of CountryCode according to format.
     */
    public function __toString()
    {
        return (string) $this->value;
    }

    /**
     * Detects the format of country name from the given value.
     *
     * @throws \Exception
     *
     * @return void
     */
    private function detectFormat()
    {
        if (preg_match('/^[[:alpha:]]{2}$/', $this->value)) {
            $this->format = 'alpha2';
        } elseif (preg_match('/^[[:alpha:]]{3}$/', $this->value)) {
            $this->format = 'alpha3';
        } elseif (preg_match('/^[[:digit:]]{3}$/', $this->value)) {
            $this->format = 'numeric';
        } else {
            throw new Exception("The country code is not formatted correctly {$this->value}");
        }
    }
}
