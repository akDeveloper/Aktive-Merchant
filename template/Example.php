<?php

/**
 * Description of Example
 *
 * @package Aktive Merchant
 * @author  <your name>
 * @license http://www.opensource.org/licenses/mit-license.php
 */

class Merchant_Billing_Example extends Merchant_Billing_Gateway {
  const TEST_URL = 'https://example.com/test';
  const LIVE_URL = 'https://example.com/live';

  # The countries the gateway supports merchants from as 2 digit ISO country codes
  public static $supported_countries = array('US', 'GR');

  # The card types supported by the payment gateway
  public static $supported_cardtypes = array('visa', 'master', 'american_express', 'switch', 'solo', 'maestro');

  # The homepage URL of the gateway
  public static $homepage_url = 'http://www.example.net';

  # The display name of the gateway
  public static $display_name = 'New Gateway';

  private $options;
  private $post;

  /**
   * $options array includes login parameters of merchant and optional currency.
   *
   * @param array $options
   */
  public function __construct($options = array()) {
    $this->required_options('login, password', $options);

    if ( isset( $options['currency'] ) )
      $this->default_currency = $options['currency'];
      
    $this->options = $options;
  }

  /**
   *
   * @param float                       $money
   * @param Merchant_Billing_CreditCard $creditcard
   * @param array                       $options
   *
   * @return Merchant_Billing_Response
   */
  public function authorize($money, Merchant_Billing_CreditCard $creditcard, $options=array()) {
    $this->add_invoice($options);
    $this->add_creditcard($creditcard);
    $this->add_address($options);
    $this->add_customer_data($options);

    return $this->commit('authonly', $money);
  }

  /**
   *
   * @param number                      $money
   * @param Merchant_Billing_CreditCard $creditcard
   * @param array                       $options
   * 
   * @return Merchant_Billing_Response
   */
  public function purchase($money, Merchant_Billing_CreditCard $creditcard, $options=array()) {
    $this->add_invoice($options);
    $this->add_creditcard($creditcard);
    $this->add_address( $options);
    $this->add_customer_data($options);

    return $this->commit('sale', $money);
  }

  /**
   *
   * @param number $money
   * @param string $authorization (unique value received from authorize action)
   * @param array  $options
   * 
   * @return Merchant_Billing_Response
   */
  public function capture($money, $authorization, $options = array()) {
    $this->post = array('authorization_id' => $authorization);
    $this->add_customer_data($options);

    return $this->commit('capture', $money);
  }

  /**
   *
   * @param string $authorization
   * @param array  $options
   * 
   * @return Merchant_Billing_Response
   */
  public function void($authorization, $options = array()) {
    $this->post = array('authorization' => $authorization);
    return $this->commit('void', null);
  }

  /**
   *
   * @param number $money
   * @param string $identification
   * @param array  $options
   * 
   * @return Merchant_Billing_Response
   */
  public function credit($money, $identification, $options = array()) {
     $this->post = array('authorization' => $identification);

     $this->add_invoice($options);
     return $this->commit('credit', $money);
  }
  /* Private */

  /**
   * Customer data like e-mail, ip, web browser used for transaction etc
   *
   * @param array $options
   */
  private function add_customer_data($options) {

  }

  /**
   *
   * Options key can be 'shipping address' and 'billing_address' or 'address'
   * Each of these keys must have an address array like:
   * <code>
   * $address['name']
   * $address['company']
   * $address['address1']
   * $address['address2']
   * $address['city']
   * $address['state']
   * $address['country']
   * $address['zip']
   * $address['phone']
   * </code>
   * common pattern for address is
   * <code>
   * $billing_address = isset($options['billing_address']) ? $options['billing_address'] : $options['address']
   * $shipping_address = $options['shipping_address']
   * </code>
   *
   * @param array $options
   */
  private function add_address($options) {
  }

  /**
   *
   * @param array $options
   */
  private function add_invoice($options) {

  }

  /**
   *
   * @param Merchant_Billing_CreditCard $creditcard
   */
  
  private function add_creditcard(Merchant_Billing_CreditCard $creditcard) {

  }

  /**
   * Parse the raw data response from gateway
   *
   * @param string $body
   */
  private function parse($body) {
  }

  /**
   *
   * @param string $action
   * @param number $money
   * @param array  $parameters
   * 
   * @return Merchant_Billing_Response
   */
  private function commit($action, $money, $parameters) {
    $url = $this->is_test() ? self::TEST_URL : self::LIVE_URL;

    $data = $this->ssl_post($url, $this->post_data($action, $parameters));

    $response = $this->parse($data);

    $test_mode = $this->is_test();

    return new Merchant_Billing_Response($this->success_from($response), $this->message_from($response), $response, array(
        'test' => $test_mode,
        'authorization' => $response['authorization_id'],
        'fraud_review' => $this->fraud_review_from($response),
        'avs_result' => $this->avs_result_from($response),
        'cvv_result' => $response['card_code']
      )
    );
  }

  /**
   * Returns success flag from gateway response
   *
   * @param array $response
   * 
   * @return string
   */
  private function success_from($response) {
    return $response['success_code_from_gateway'];
  }

  /**
   * Returns message (error explanation  or success) from gateway response
   *
   * @param array $response
   * 
   * @return string
   */
  private function message_from($response) {
    return $response['message_from_gateway'];
  }


  /**
   * Returns fraud review from gateway response
   *
   * @param array $response
   *
   * @return string
   */
  private function fraud_review_from($response) {
    
  }

  /**
   *
   * Returns avs result from gateway response
   *
   * @param array $response
   *
   * @return string
   */
  private function avs_result_from($response) {
    return array( 'code' => $response['avs_result_code'] );
  }

  /**
   *
   * Add final parameters to post data and
   * build $this->post to the format that your payment gateway understands
   *
   * @param string $action
   * @param array  $parameters
   */
  private function post_data($action, $parameters = array()) {
  
  }

}
?>
