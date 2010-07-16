<?php
/**
 * Description of Merchant_Billing_Helper
 *
 * @author Andreas Kollaros
 */

class Merchant_Billing_Helper {

  private $fields = array();

  private $country_format = 'alpha2';

  protected  $mappings = array();

  public function __construct($order, $account, $options=array()){
    $this->order = $order;
    $this->account = $account;
    $this->amount = $options['amount'];
    $rhis->currency = $options['currency'];
  }

  public function mapping($attribute, $options = array()) {
    $this->mappings[$attribute] = $options;
  }

  public function add_field($name, $value) {
    if ( empty($name) || empty($value)) return;
    $this->fields[$name] = $value;
  }

  public function add_fields($subkey, $params=array()) {
    foreach ( $params as $k=>$v ) {
      $field = $this->mappings[$subkey][$k];
      if ( !empty($field) ) $this->add_field($field, $v);
    }
  }

  public function billing_address( $params=array()) {
   $this->add_address('billing_address', $params);
   return $this;
  }

  public function shipping_address( $params=array() ){
    $this->add_address('shipping_address', $params);
    return $this;
  }

  public function form_fields() {
    return $this->fields;
  }

  public function __call($name, $arguments){
    if ( !isset($this->mappings[$name] ) ) return;
    
    $mapping = $this->mappings[$name];
    var_dump($mapping);

    $args = current($arguments);
    if ( is_array($mapping) ) {
      foreach( $mapping as $key=>$field) {
        $this->add_field( $field, $args[$key]);
      }
    } else {
      $this->add_field($mapping, $args);
    }
    return $this;
  }

  /**
   * Private
   */

  private function add_address($key, $params) {
    if (!isset($this->mappings[$key]) ) return;

    $code = $this->lookup_country_code($params['country']);
    unset($params['country']);
    $this->add_field($this->mappings[$key]['country'], $code);
    $this->add_field($key, $params);
  }

  private function lookup_country_code($name_or_code) {
    $country = Merchant_Country::find($name_or_code);
    return (string) $country->code($this->country_format);
  }

}

?>
