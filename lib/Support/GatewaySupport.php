<?php

require_once "../merchant.php";

class GatewaySupport
{

    private $supported_gateways = array();
    private $actions = array("purchase", "authorize", "capture", "void", "credit", "recurring");

    public function __construct()
    {
        $dir_path = realpath('../merchant/billing/gateways');
        if ($handle = opendir($dir_path)) {
            while (false !== ($file = readdir($handle))) {
                if (is_dir($dir_path . "/" . $file) || $file === "." || $file === "..")
                    continue;
                $this->supported_gateways[] = "Merchant_Billing_" . str_replace(".php", "", $file);
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
        print "Name" . str_repeat(' ', 26);
        foreach ($this->actions as $action) {
            print $action . str_repeat(' ', 30 - strlen($action));
        }
        print "\r";
        foreach ($this->supported_gateways as $gateway) {
            $methods = array();
            $ref = new ReflectionClass($gateway);
            $display_name = $ref->getStaticPropertyValue('display_name');
            print $display_name . str_repeat(' ', 30 - strlen($display_name));
            foreach ($this->actions as $action) {
                if (method_exists($gateway, $action)) {
                    print "Y" . str_repeat(' ', 29);
                } else {
                    print "N" . str_repeat(' ', 29);
                }
            }
            print "\r";
        }
    }

    public function __toString()
    {
        $to_string = "";
        foreach ($this->supported_gateways as $gateway) {
            $ref = new ReflectionClass($gateway);
            $to_string .= $ref->getStaticPropertyValue('display_name') . " - " .
                $ref->getStaticPropertyValue('homepage_url') . " " .
                "[" . join(", ", $ref->getStaticPropertyValue('supported_countries')) .
                "]\n";
        }
        return $to_string;
    }

}

?>
