<?php

namespace AktiveMerchant\Support;

use AktiveMerchant\Common\Inflect;
use AktiveMerchant\Billing\Gateway;

class GatewaySupport
{

    private $supported_gateways = array();
    private $actions = array("purchase", "authorize", "capture", "void", "credit", "recurring");

    public function __construct()
    {
        $dir_path = realpath(__DIR__ . '/../Billing/Gateways');

        if ($handle = opendir($dir_path)) {
            while (false !== ($file = readdir($handle))) {
                if (is_dir($dir_path . "/" . $file)
                    || $file === "."
                    || $file === ".."
                ) {
                    continue;
                }
                $this->supported_gateways[] = str_replace(".php", "", $file);
            }
        }
        sort($this->supported_gateways);
    }

    /**
     * Gets an array with Gateways.
     *
     * @access public
     * @return array
     */
    public function getGateways()
    {
        $gateways = array();
        foreach ($this->supported_gateways as $gateway) {
            $class = 'AktiveMerchant\\Billing\\Gateways\\' . $gateway;
            $gateways[Inflect::underscore($gateway)] = $class::$display_name;
        }

        return $gateways;
    }

    /**
     * Gets an array with supported actions for given gateway.
     *
     * @param string|Gateway $gateway
     * @access public
     * @return array
     */
    public function getSupportedActions($gateway)
    {
        if (!is_string($gateway)) {
            $gateway = get_class($gateway);
        }
        $gateway = Inflect::camelize($gateway);

        $class = new \ReflectionClass('AktiveMerchant\\Billing\\Gateways\\' . $gateway);
        $actions = array();

        foreach ($this->actions as $action) {
            if ($class->hasMethod($action)) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    public function supported_gateways()
    {
        return $this->supported_gateways;
    }

    public function features()
    {
        $max = array_map(function ($a) {
            $gateway = "AktiveMerchant\\Billing\\Gateways\\".$a;
            return strlen($gateway::$display_name);
        }, $this->supported_gateways);

        $max = max($max) + 1;
        $max_column = 15;

        $print = "";
        $print .=  "\033[1;36mName" . str_repeat(' ', $max - 4);
        foreach ($this->actions as $action) {
            $print .=  $action . str_repeat(' ', $max_column - strlen($action));
        }
        $print .= "\033[0m". PHP_EOL;


        foreach ($this->supported_gateways as $gateway) {
            $ref = new \ReflectionClass('AktiveMerchant\\Billing\\Gateways\\' . $gateway);
            $display_name = $ref->getStaticPropertyValue('display_name');
            $length = $max - strlen($display_name);
            $print .= "\033[1;35m".  $display_name ."\033[0m". str_repeat(' ', $length > 0 ? $length : 0);
            foreach ($this->actions as $action) {
                if ($ref->hasMethod($action)) {
                    $print .=  "\033[1;32m O\033[0m" . str_repeat(' ', $max_column-2);
                } else {
                    $print .=  "\033[1;31m X\033[0m" . str_repeat(' ', $max_column-2);
                }
            }
            $print .=  PHP_EOL;
        }

        echo $print;
    }

    public function __toString()
    {
        $to_string = "";
        foreach ($this->supported_gateways as $gateway) {
            $class = "\\AktiveMerchant\\Billing\\Gateways\\" . $gateway;
            /*$ref = new \ReflectionClass("\\AktiveMerchant\\Billing\\Gateways\\" . $gateway);
            $to_string .= $ref->getStaticPropertyValue('display_name') . " - " .
                $ref->getStaticPropertyValue('homepage_url') . " " .
                "[" . join(", ", $ref->getStaticPropertyValue('supported_countries')) .
                "]\n";*/
            $to_string .= $class::$display_name . " - " .
                $class::$homepage_url . " " .
                "[" . join(', ', $class::$supported_countries). "]" . PHP_EOL;
        }

        return $to_string;
    }
}
