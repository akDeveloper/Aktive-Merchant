<?php
if ( false === spl_autoload_register('gateways_autoload') ){
  throw new Exception('Unable to register gateways_autoload as an autoloading method');
}

function gateways_autoload($class_name) {
  $path = dirname(__FILE__) . "/";
  $class_name = explode('_',$class_name);
  $class_filename = array_pop($class_name);
  if ( file_exists( $path . 'gateways/' . $class_filename . ".php" ) ) {
    require_once( $path . 'gateways/' . $class_filename . ".php");
  }
}
?>
