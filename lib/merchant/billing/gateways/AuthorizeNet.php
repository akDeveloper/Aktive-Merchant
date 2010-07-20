<?php
/**
 * Description of AuthorizeNet
 *
 * @author Andreas Kollaros
 */
class Merchant_Billing_AuthorizeNet extends Merchant_Billing_Gateway {
  const API_VERSION = "3.1";

  const TEST_URL = "https://test.authorize.net/gateway/transact.dll";
  const LIVE_URL = "https://secure.authorize.net/gateway/transact.dll";
  const ARB_TEST_URL = 'https://apitest.authorize.net/xml/v1/request.api';
  const ARB_LIVE_URL = 'https://api.authorize.net/xml/v1/request.api';

  public $duplicate_window;

  const APPROVED     = 1;
  const DECLINED     = 2;
  const ERROR        = 3;
  const FRAUD_REVIEW = 4;

  const RESPONSE_CODE           = 0;
  const RESPONSE_REASON_CODE    = 2;
  const RESPONSE_REASON_TEXT    = 3;
  const AVS_RESULT_CODE         = 5;
  const TRANSACTION_ID          = 6;
  const CARD_CODE_RESPONSE_CODE = 38;

  protected $supported_countries = array('US');
  protected $supported_cardtypes  = array('visa', 'master', 'american_express', 'discover');
  protected $homepage_url = 'http://www.authorize.net/';
  protected $display_name = 'Authorize.Net';

  private $post = array();
  private $options = array();
  private $CARD_CODE_ERRORS = array( 'N', 'S' );
  private $AVS_ERRORS = array( 'A', 'E', 'N', 'R', 'W', 'Z' );

  const AUTHORIZE_NET_ARB_NAMESPACE = 'AnetApi/xml/v1/schema/AnetApiSchema.xsd';

  private $RECURRING_ACTIONS = array(
    'create' => 'ARBCreateSubscription',
    'update' => 'ARBUpdateSubscription',
    'cancel' => 'ARBCancelSubscription'
  );

  public function  __construct($options) {
    $this->required_options('login, password', $options);

    $this->options = $options;
  }
  /**
   *
   * @param float $money
   * @param Merchant_Billing_CreditCard $creditcard
   * @param array $options
   * $return Merchant_Billing_Response 
   */
  public function authorize($money, Merchant_Billing_CreditCard $creditcard, $options = array()) {
    $post = array();
    $this->add_invoice($post, $options);
    $this->add_creditcard($post, $creditcard);
    $this->add_address($post, $options);
    $this->add_customer_data($post, $options);
    $this->add_duplicate_window($post);

    return $this->commit('AUTH_ONLY', $money, $post);
  }

  /**
   *
   * @param float $money
   * @param Merchant_Billing_CreditCard $creditcard
   * @param array $options
   * @return Merchant_Billing_Response 
   */
  public function purchase($money, Merchant_Billing_CreditCard $creditcard, $options = array()) {
    
    $this->add_invoice($options);
    $this->add_creditcard($creditcard);
    $this->add_address($options);
    $this->add_customer_data($options);
    $this->add_duplicate_window();

    return $this->commit('AUTH_CAPTURE', $money);
  }

  public function capture($money, $authorization, $options = array()) {
    $this->post = array('trans_id' => $authorization);
    $this->add_customer_data($options);
    return $this->commit('PRIOR_AUTH_CAPTURE', $money);
  }

  public function void($authorization, $options = array()) {
    $this->post = array('trans_id' => $authorization);
    return $this->commit('VOID', null);
  }


  /**
   *
   * @param float $money
   * @param string $identification
   * @param array $options
   * @return Merchant_Billing_Response
   */
  public function credit($money, $identification, $options = array()) {
     $this->required_options('card_number', $options);
     $this->post = array(
         'trans_id' => $identification,
         'card_num' => $options['card_number']
     );


     $this->add_invoice($options);
     return $this->commit('CREDIT', $money);
  }
  
/*
 * Private
 */

  /**
   *
   * @param string $action
   * @param float $money
   * @param array $parameters
   * @return Merchant_Billing_Response 
   */
  private function commit($action, $money, $parameters = array()) {
    if ($action != 'VOID')
      $parameters['amount'] = $this->amount($money);

    /*Request a test response*/
    # $parameters['test_request'] = $this->is_test() ? 'TRUE' : 'FALSE';

    $url = $this->is_test() ? self::TEST_URL : self::LIVE_URL;

    $data = $this->ssl_post($url, $this->post_data($action, $parameters));
    
    $response = $this->parse($data);

    $message = $this->message_from($response);
    
    $test_mode = $this->is_test();

    return new Merchant_Billing_Response($this->success_from($response), $message, $response, array(
        'test' => $test_mode,
        'authorization' => $response['transaction_id'],
        'fraud_review' => $this->fraud_review_from($response),
        'avs_result' => array( 'code' => $response['avs_result_code'] ),
        'cvv_result' => $response['card_code']
      )
    );
  }

  private function success_from($response) {
    return $response['response_code'] == self::APPROVED;
  }

  private function fraud_review_from($response) {
    return $response['response_code'] == self::FRAUD_REVIEW;
  }

  private function message_from($results) {
    if ( $results['response_code'] == self::DECLINED ) {
      if ( in_array( $results['card_code'], self::$CARD_CODE_ERRORS ) ) {
        $cvv_messages = Merchant_Billing_CvvResult::messages();
        return $cvv_messages[$results['card_code']];
      }
      if ( in_array( $results['avs_result_code'], self::$AVS_ERRORS ) ) {
        $avs_messages = Merchant_Billing_AvsResult::messages();
        return $avs_messages[$results['avs_result_code']];
      }
    }

    return $results['response_reason_text'] === null ? '' : $results['response_reason_text'];
  }

  private function parse($body) {
    $fields = explode('|', $body);
    $results = array(
      'response_code' => $fields[self::RESPONSE_CODE],
      'response_reason_code' => $fields[self::RESPONSE_REASON_CODE],
      'response_reason_text' => $fields[self::RESPONSE_REASON_TEXT],
      'avs_result_code' => $fields[self::AVS_RESULT_CODE],
      'transaction_id' => $fields[self::TRANSACTION_ID],
      'card_code' => $fields[self::CARD_CODE_RESPONSE_CODE]
    );

    return $results;
  }

  private function post_data($action, $parameters = array()) {

    $this->post['version']        = self::API_VERSION;
    $this->post['login']          = $this->options['login'];
    $this->post['tran_key']       = $this->options['password'];
    $this->post['relay_response'] = 'FALSE';
    $this->post['type']           = $action;
    $this->post['delim_data']     = 'TRUE';
    $this->post['delim_char']     = '|';

    $this->post = array_merge($this->post, $parameters);
    $request = "";

    #Add x_ prefix on all keys
    foreach ( $this->post as $k=>$v ) {
      $request .= 'x_' . $k . '=' . urlencode($v).'&';
    }
    return rtrim($request,'& ');
  }

  private function add_invoice($options) {
    $this->post['invoice_num'] = isset($options['order_id']) ? $options['order_id'] : null;
    $this->post['description'] = isset($options['description']) ? $options['description'] : null;
  }

  private function add_creditcard(Merchant_Billing_CreditCard $creditcard) {
    $this->post['method']     = 'CC';
    $this->post['card_num']   = $creditcard->number;
    if ( $creditcard->require_verification_value )
      $this->post['card_code']  = $creditcard->verification_value;
    $this->post['exp_date']   = $this->expdate($creditcard);
    $this->post['first_name'] = $creditcard->first_name;
    $this->post['last_name']  = $creditcard->last_name;
  }

  private function expdate(Merchant_Billing_CreditCard $creditcard) {
    $year  = $this->cc_format($creditcard->year, 'two_digits');
    $month = $this->cc_format($creditcard->month, 'two_digits');
    return  $month . $year;
  }

  private function add_address($options) {
    $address = isset($options['billing_address']) ? $options['billing_address'] : $options['address'];
    $this->post['address'] = isset($address['address1'])? $address['address1'] : null;
    $this->post['company'] = isset($address['company']) ? $address['company'] : null;
    $this->post['phone']   = isset($address['phone'])   ? $address['phone']   : null;
    $this->post['zip']     = isset($address['zip'])     ? $address['zip']     : null;
    $this->post['city']    = isset($address['city'])    ? $address['city']    : null;
    $this->post['country'] = isset($address['country']) ? $address['country'] : null;
    $this->post['state']   = isset($address['state'])   ? $address['state']   : 'n/a';
  }

  private function add_customer_data($options) {
    if ( isset($options['email']) ) {
      $this->post['email'] = isset( $options['email'] ) ? $options['email'] : null;
      $this->post['email_customer'] = false;
    }

    if ( isset($options['customer']) ) {
      $this->post['cust_id'] = $options['customer'];
    }

    if ( isset($options['ip']) ) {
      $this->post['customer_ip'] = $options['ip'];
    }
  }

  private function add_duplicate_window() {
    if ( $this->duplicate_window != null ) {
      $this->post['duplicate_window'] = $this->duplicate_window;
    }
  }

  
}
?>
