<?php 
require_once "../merchant.php";

class GatewaySupport {
  private $supported_geteways = array();
  private $actions = array("purchase", "authorize", "capture", "void", "credit", "recurring");

  public function __construct(){
    $dir_path = realpath('../merchant/billing/gateways');
    if ($handle = opendir($dir_path)) {
      while (false !== ($file = readdir($handle))) {
        if (is_dir($dir_path ."/". $file) || $file === "." || $file === ".." ) continue;
        $this->supported_gateways[] = "Merchant_Billing_" . str_replace(".php","",$file);
      }    
    }
    sort($this->supported_gateways);
  }

  public function features(){
    $tabs = "\t\t\t";
    print "Name$tabs";
    foreach ( $this->actions as $action ){
      print $action . $tabs;
    }
    print "\n";
    foreach( $this->supported_gateways as $gateway ){
      $methods = array();
      $ref = new ReflectionClass($gateway);
      print $ref->getStaticPropertyValue('display_name').$tabs;
      foreach ( $this->actions as $action ){
        if (method_exists($gateway, $action ) ){
          print "Y".$tabs;
        } else {
          print "N".$tabs;
        }
      }
      print "\n";
    }
  }
  
  public function __toString(){
    $to_string = "";
    foreach( $this->supported_gateways as $gateway ){
      $ref = new ReflectionClass($gateway);
      $to_string .= $ref->getStaticPropertyValue('display_name') ." - ".
        $ref->getStaticPropertyValue('homepage_url') ." ". 
        "[" . join(", ", $ref->getStaticPropertyValue('supported_countries')) .
        "]\n";
    }
    return $to_string;
  }
}
?>
