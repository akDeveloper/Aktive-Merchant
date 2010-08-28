<?php
/**
 * Description of PaypalExpress
 *
 * @author Andreas Kollaros
 *
 * PUBLIC METHODS
 * ============================
 *  setup_authorize()
 *  setup_purchase()
 *  authorize()
 *  purchase()
 *  get_details_for()
 *  url_for_token()
 * @return Response Object
 */

require_once dirname(__FILE__) . "/paypal/PaypalCommon.php";
require_once dirname(__FILE__) . "/paypal/PaypalExpressResponse.php";
class Merchant_Billing_PaypalExpress extends Merchant_Billing_PaypalCommon {

  private $redirect_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=';

  private $version  = '59.0';

  private $options = array();
  private $post = array();

  private $token;
  private $payer_id;

  protected $default_currency = 'EUR';
  protected $supported_countries = array('US');
  protected $homepage_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=xpt/merchant/ExpressCheckoutIntro-outside';
  protected $display_name = 'PayPal Express Checkout';


  public function __construct( $options = array() ) {

    $this->required_options('login, password, signature', $options);

    $this->options = $options;

    if( isset($options['version'])) $this->version = $options['version'];
    if( isset($options['currency'])) $this->default_currency = $options['currency'];
  }

  /**
   * Authorize and Purchase actions
   *
   * @param number $amount  Total order amount
   * @param Array  $options
   *               token    token param from setup action
   *               payer_id payer_id param from setup action
   */
  public function authorize($amount, $options = array() ) {
    return $this->do_action($amount, "Authorization", $options);
  }

  public function purchase($amount, $options = array()) {
    return $this->do_action($amount, "Sale", $options);
  }

  /**
   * Setup Authorize and Purchase actions
   *
   * @param number $money  Total order amount
   * @param Array  $options
   *               currency           Valid currency code ex. 'EUR', 'USD'. See http://www.xe.com/iso4217.php for more
   *               return_url         Success url (url from  your site )
   *               cancel_return_url  Cancel url ( url from your site )
   */
  public function setup_authorize($money, $options = array()) {
    return $this->setup($money, 'Authorization', $options);
  }

  public function setup_purchase($money, $options = array()) {
    return $this->setup($money, 'Sale', $options);
  }

  private function setup( $money, $action, $options = array() ) {

    $this->required_options('return_url, cancel_return_url', $options);

    $params = array(
        'METHOD' => 'SetExpressCheckout',
        'AMT'           => $this->amount($money),
        'RETURNURL'     => $options['return_url'],
        'CANCELURL'     => $options['cancel_return_url']);

    $this->post = array_merge($this->post, $params);

    Merchant_Logger::log("Commit Payment Action: $action, Paypal Method: SetExpressCheckout");

    return $this->commit( $action );
  }

  private function do_action ($money, $action, $options = array() ) {
    if ( !isset($options['token']) ) $options['token'] = $this->token;
    if ( !isset($options['payer_id']) ) $options['payer_id'] = $this->payer_id;

    $this->required_options('token, payer_id', $options);

    $params = array(
        'METHOD' => 'DoExpressCheckoutPayment',
        'AMT'            => $this->amount($money),
        'TOKEN'          => $options['token'],
        'PAYERID'        => $options['payer_id']);

    $this->post = array_merge($this->post, $params);

    Merchant_Logger::log("Commit Payment Action: $action, Paypal Method: DoExpressCheckoutPayment");

    return $this->commit($action );

  }

  public function url_for_token($token) {
    return $this->redirect_url . $token;
  }

  public function get_details_for($token, $payer_id) {

    $this->payer_id = urldecode($payer_id);
    $this->token    = urldecode($token);

    $params = array(
        'METHOD' => 'GetExpressCheckoutDetails',
        'TOKEN'  => $token
    );
    $this->post = array_merge($this->post, $params);

    Merchant_Logger::log("Commit Paypal Method: GetExpressCheckoutDetails");
    return $this->commit($this->urlize( $this->post ) );

  }

  /**
   *
   * Add final parameters to post data and
   * build $this->post to the format that your payment gateway understands
   *
   * @param string $action
   * @param array $parameters
   */
  protected function post_data($action) {
    $params = array(
        'PAYMENTACTION' => $action,
        'USER'          => $this->options['login'],
        'PWD'           => $this->options['password'],
        'VERSION'       => $this->version,
        'SIGNATURE'     => $this->options['signature'],
        'CURRENCYCODE'  => $this->default_currency);
    
    $this->post = array_merge($this->post, $params);

    return $this->urlize( $this->post );
  }

  protected function build_response($success, $message, $response, $options=array()){
    return new Merchant_Billing_PaypalExpressResponse($success, $message, $response,$options);
  }
  
}
?>
