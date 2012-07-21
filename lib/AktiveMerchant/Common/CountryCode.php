<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Common;

class CountryCode
{

    protected $value;
    protected $format;

    public function __construct($value)
    {
        $this->value = strtoupper($value);
        $this->detect_format();
    }

    private function detect_format()
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

    public function format()
    {
        return $this->format;
    }

    public function __toString()
    {
        return $this->value;
    }

}
