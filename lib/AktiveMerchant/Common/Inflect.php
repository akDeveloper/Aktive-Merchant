<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace ActiveMerchant\Common;

class Inflect()
{
    public static function camelize($string)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    public function underscore($string)
    {
        return strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $string));
    }
}
