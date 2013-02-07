<?php

namespace AktiveMerchant\Support;

class GatewaySupport
{

    private $supported_gateways = array();
    private $actions = array("purchase", "authorize", "capture", "void", "credit", "recurring");

    public function __construct()
    {
        $dir_path = realpath(__DIR__ . '/../Billing/Gateways');
        
        if ($handle = opendir($dir_path)) {
            while (false !== ($file = readdir($handle))) {
                if (is_dir($dir_path . "/" . $file) || $file === "." || $file === "..")
                    continue;
                $this->supported_gateways[] = str_replace(".php", "", $file);
            }
        }
        sort($this->supported_gateways);
    }

    public function supported_gateways()
    {
        return $this->supported_gateways;
    }

    public function features()
    {
        $max = array_map(function($a) {
            $gateway = "AktiveMerchant\\Billing\\Gateways\\".$a;
            return strlen($gateway::$display_name);
        }, $this->supported_gateways);
        
        $max = max($max) + 1;
        
        $print = "";
        $print .=  "Name" . str_repeat(' ', $max - 4);
        foreach ($this->actions as $action) {
            $print .=  $action . str_repeat(' ', $max - strlen($action));
        }
        $print .=  PHP_EOL;
        
        foreach ($this->supported_gateways as $gateway) {
            $methods = array();
            $ref = new \ReflectionClass('AktiveMerchant\\Billing\\Gateways\\' . $gateway);
            $display_name = $ref->getStaticPropertyValue('display_name');
            $length = $max - strlen($display_name);
            $print .=  $display_name . str_repeat(' ', $length > 0 ? $length : 0);
            foreach ($this->actions as $action) {
                if (method_exists($gateway, $action)) {
                    $print .=  "Y" . str_repeat(' ', $max - 1);
                } else {
                    $print .=  "N" . str_repeat(' ', $max - 1);
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
