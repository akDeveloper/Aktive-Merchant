<?php
//if ( false === spl_autoload_register('merchant_autoload') ){
//  throw new Exception('Unable to register merchant_autoload as an autoloading method');
//}
require_once dirname(__FILE__) .  '/merchant/billing.php';
require_once dirname(__FILE__) .  '/merchant/common.php';

//function merchant_autoload($class_name) {
//  $path = dirname(__FILE__) . "/";
//
//  $path_from_class = str_replace('_', ':', $class_name);
//  $path_from_class = explode(':',underscore($path_from_class));
//  array_pop($path_from_class);
//  $path_from_class = implode('/',$path_from_class);
//
//  $lib_path = $path . "common/";
//  $billing_path = $path . "billing/";
//  $gateway_path = $billing_path . "gateways/";
//
//  $paths = array($path_from_class);
//
//  foreach ( $paths as $p ) {
//    if ( file_exists( $p . $class_name . ".php" ) ) {
//      require_once( $p . $class_name . ".php");
//      break;
//    }
//  }
//}
?>
