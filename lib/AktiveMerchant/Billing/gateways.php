<?php

/**
 * Description of gateways.php
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
if (false === spl_autoload_register('gateways_autoload')) {
    throw new Exception('Unable to register gateways_autoload as an autoloading method');
}

function gateways_autoload($class_name)
{
    $path = dirname(__FILE__) . "/";
    $filename = explode('_', $class_name);
    $class_filename = array_pop($filename);
    if (file_exists($path . 'gateways/' . $class_filename . ".php")) {
        require_once( $path . 'gateways/' . $class_filename . ".php");
    }
}

/* * ******************************
 * Retro-support of get_called_class()
 * Tested and works in PHP 5.2.4
 * http://www.sol1.com.au/
 * ****************************** */
if (!function_exists('get_called_class')) {

    function get_called_class($bt = false, $l = 1)
    {
        if (!$bt)
            $bt = debug_backtrace();
        if (!isset($bt[$l]))
            throw new Exception("Cannot find called class -> stack level too deep.");
        if (!isset($bt[$l]['type'])) {
            throw new Exception('type not set');
        }
        else
            switch ($bt[$l]['type']) {
                case '::':
                    $lines = file($bt[$l]['file']);
                    $i = 0;
                    $callerLine = '';
                    do {
                        $i++;
                        $callerLine = $lines[$bt[$l]['line'] - $i] . $callerLine;
                    } while (stripos($callerLine, $bt[$l]['function']) === false);
                    preg_match('/([a-zA-Z0-9\_]+)::' . $bt[$l]['function'] . '/', $callerLine, $matches);
                    if (!isset($matches[1])) {
                        // must be an edge case.
                        throw new Exception("Could not find caller class: originating method call is obscured.");
                    }
                    switch ($matches[1]) {
                        case 'self':
                        case 'parent':
                            return get_called_class($bt, $l + 1);
                        default:
                            return $matches[1];
                    }
                // won't get here.
                case '->': switch ($bt[$l]['function']) {
                        case '__get':
                            // edge case -> get class of calling object
                            if (!is_object($bt[$l]['object']))
                                throw new Exception("Edge case fail. __get called on non object.");
                            return get_class($bt[$l]['object']);
                        default: return $bt[$l]['class'];
                    }

                default: throw new Exception("Unknown backtrace method type");
            }
    }

}
?>
