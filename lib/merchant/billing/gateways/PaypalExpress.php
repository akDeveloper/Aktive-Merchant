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

require_once dirname(__FILE__) . "/paypal/PaypalExpressResponse.php";
class Merchant_Billing_PaypalExpress extends Merchant_Billing_Gateway {

  private $redirect_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=';

  private $urls = array(
  'test' => 'https://api-3t.sandbox.paypal.com/nvp',
  'live' => 'https://api-3t.paypal.com/nvp'
  );

  private $url;

  private $version  = '59.0';

  private $options = array();
  private $post_params = array();

  private $token;
  private $payer_id;

  protected $default_currency = 'EUR';
  protected $supported_countries = array('US');
  protected $homepage_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=xpt/merchant/ExpressCheckoutIntro-outside';
  protected $display_name = 'PayPal Express Checkout';

  const FAILURE = 'Failure';
  const PENDING = 'Pending';

  private $SUCCESS_CODES = array('Success', 'SuccessWithWarning');
  const FRAUD_REVIEW_CODE = "11610";

  public function __construct( $options = array() ) {

    $this->required_options('login, password, signature', $options);

    $this->options = $options;

    if( isset($options['version'])) $this->version = $options['version'];
    if( isset($options['currency'])) $this->default_currency = $options['currency'];

    $mode = $this->mode();
    $this->url = $this->urls[$mode];

    $this->post_params = array(
        'USER'          => $this->options['login'],
        'PWD'           => $this->options['password'],
        'VERSION'       => $this->version,
        'SIGNATURE'     => $this->options['signature'],
        'CURRENCYCODE'  => $this->default_currency);

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
   * @param number $amount  Total order amount
   * @param Array  $options
   *               currency           Valid currency code ex. 'EUR', 'USD'. See http://www.xe.com/iso4217.php for more
   *               return_url         Success url (url from  your site )
   *               cancel_return_url  Cancel url ( url from your site )
   */
  public function setup_authorize($amount, $options = array()) {
    return $this->setup($amount, 'Authorization', $options);
  }

  public function setup_purchase($amount, $options = array()) {
    return $this->setup($amount, 'Sale', $options);
  }

  private function setup( $amount, $action, $options = array() ) {

    $this->required_options('return_url, cancel_return_url', $options);

    $params = array('METHOD' => 'SetExpressCheckout',
        'PAYMENTACTION' => $action,
        'AMT'           => $this->amount($amount),
        'RETURNURL'     => $options['return_url'],
        'CANCELURL'     => $options['cancel_return_url']);

    $this->post_params = array_merge($this->post_params, $params);

    Merchant_Logger::log("Commit Payment Action: $action, Paypal Method: SetExpressCheckout");

    return $this->commit( $this->urlize( $this->post_params ) );
  }

  private function do_action ($amount, $action, $options = array() ) {
    if ( !isset($options['token']) ) $options['token'] = $this->token;
    if ( !isset($options['payer_id']) ) $options['payer_id'] = $this->payer_id;

    $this->required_options('token, payer_id', $options);

    $params = array('METHOD' => 'DoExpressCheckoutPayment',
        'PAYMENTACTION'  => $action,
        'AMT'            => number_format($amount, 2),
        'TOKEN'          => $options['token'],
        'PAYERID'        => $options['payer_id']);

    $this->post_params = array_merge($this->post_params, $params);

    Merchant_Logger::log("Commit Payment Action: $action, Paypal Method: DoExpressCheckoutPayment");

    return $this->commit($this->urlize( $this->post_params ) );

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
    $this->post_params = array_merge($this->post_params, $params);

    Merchant_Logger::log("Commit Paypal Method: GetExpressCheckoutDetails");
    return $this->commit($this->urlize( $this->post_params ) );

  }

  /**
   * PaypalCommonApi
   */
  private function parse($response) {
    parse_str( $response, $response_array );
    if ( $response_array['ACK'] == self::FAILURE ) {
      $error_message = "Error code (". $response_array['L_ERRORCODE0'] . ")\n ".$response_array['L_SHORTMESSAGE0']. ".\n Reason: ".$response_array['L_LONGMESSAGE0'];
      Logger::error_log($error_message);
    #throw new Exception ($error_message );
    }
    return $response_array;
  }

  private function commit($request) {
    $response = $this->parse( $this->ssl_post($this->url, $request) );
    #$response = $this->parse( 'TOKEN=EC%2d3Y885866912034356&TIMESTAMP=2010%2d06%2d19T14%3a45%3a14Z&CORRELATIONID=eec2593365b5&ACK=Success&VERSION=56%2e0&BUILD=1364064&EMAIL=whiteu_1252885474_per%40gmail%2ecom&PAYERID=26R5KV9FZ53RL&PAYERSTATUS=verified&FIRSTNAME=Test&LASTNAME=User&COUNTRYCODE=US&SHIPTONAME=Test%20User&SHIPTOSTREET=1%20Main%20St&SHIPTOCITY=San%20Jose&SHIPTOSTATE=CA&SHIPTOZIP=95131&SHIPTOCOUNTRYCODE=US&SHIPTOCOUNTRYNAME=United%20States&ADDRESSSTATUS=Confirmed&CURRENCYCODE=EUR&AMT=1%2e00&SHIPPINGAMT=0%2e00&HANDLINGAMT=0%2e00&TAXAMT=0%2e00&INSURANCEAMT=0%2e00&SHIPDISCAMT=0%2e00' );
    #$response = $this->parse( 'TIMESTAMP=2010%2d06%2d19T14%3a10%3a42Z&CORRELATIONID=5440b9f29a6f9&ACK=Failure&L_ERRORCODE0=10001&L_SHORTMESSAGE0=Internal%20Error&L_LONGMESSAGE0=Timeout%20processing%20request' );
    $options  = array();
    $options['test'] = $this->is_test();
    $options['authorization'] = $this->authorization_from($response);
    $options['fraud_review'] = $this->fraud_review($response);
    $options['avs_result'] = isset($response['AVSCODE']) ? array('code' => $response['AVSCODE']) : null;
    $options['cvv_result'] = isset($response['CVV2CODE']) ? $response['CVV2CODE'] : null;

    $return = $this->build_response( $this->successful($response), $this->message_from($response), $response, $options);
    return $return;
  }

  private function fraud_review($response) {
    if ( isset($response['L_ERRORCODE0']) )
      return ($response['L_ERRORCODE0'] == self::FRAUD_REVIEW_CODE);
    return false;
  }

  private function authorization_from($response) {
    if ( isset($response['TRANSACTIONID']) )
      return $response['TRANSACTIONID'];
    if ( isset($response['AUTHORIZATIONID']) )
      return $response['AUTHORIZATIONID'];
    if ( isset($response['REFUNDTRANSACTIONID']) )
      return $response['REFUNDTRANSACTIONID'];
    return false;
  }

  private function successful($response) {
    return ( in_array($response['ACK'], $this->SUCCESS_CODES) );
  }

  private function message_from($response) {
    return ( isset($response['L_LONGMESSAGE0']) ? $response['L_LONGMESSAGE0'] : $response['ACK'] );
  }

  private function build_response($success, $message, $response, $options = array()) {
    return new Merchant_Billing_PaypalExpressResponse($success, $message, $response, $options);
  }
}
?>
