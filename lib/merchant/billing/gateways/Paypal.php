<?php

/**
 * Description of Merchant_Billing_Paypal
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
require_once dirname(__FILE__) . "/paypal/PaypalCommon.php";

class Merchant_Billing_Paypal extends Merchant_Billing_PaypalCommon implements Merchant_Billing_Gateway_Charge, Merchant_Billing_Gateway_Credit
{

    public static $money_format = 'dollars'; # or cents
    # The countries the gateway supports merchants from as 2 digit ISO country codes
    public static $supported_countries = array('US', 'UK');

    # The card types supported by the payment gateway
    public static $supported_cardtypes = array('visa', 'master', 'american_express', 'discover');

    # The homepage URL of the gateway
    public static $homepage_url = 'https://merchant.paypal.com/cgi-bin/marketingweb?cmd=_render-content&content_ID=merchant/wp_pro';

    # The display name of the gateway
    public static $display_name = 'PayPal Website Payments Pro';
    private $options;
    private $post = array();
    private $version = '59.0';
    public static $default_currency = 'USD';
    private $credit_card_types = array(
        'visa' => 'Visa',
        'master' => 'MasterCard',
        'discover' => 'Discover',
        'american_express' => 'Amex',
        'switch' => 'Switch',
        'solo' => 'Solo'
    );

    /**
     * $options array includes login parameters of merchant and optional currency.
     *
     * @param array $options
     */
    public function __construct($options = array())
    {
      parent::__construct($options);
      $this->required_options('login, password, signature', $options);

        if (isset($options['currency']))
            self::$default_currency = $options['currency'];

        $this->options = $options;
    }

    /**
     *
     * @param number                      $money
     * @param Merchant_Billing_CreditCard $creditcard
     * @param array                       $options
     *
     * @return Merchant_Billing_Response
     */
    public function authorize($money, Merchant_Billing_CreditCard $creditcard, $options=array())
    {
        $this->add_creditcard($creditcard);
        $this->add_address($options);
        $this->post['PAYMENTACTION'] = 'Authorization';
        $this->post['AMT'] = $this->amount($money);
        $this->post['IPADDRESS'] = isset($options['ip']) ? $options['ip'] : $_SERVER['REMOTE_ADDR'];
        return $this->commit('DoDirectPayment');
    }

    /**
     *
     * @param number                      $money
     * @param Merchant_Billing_CreditCard $creditcard
     * @param array                       $options
     *
     * @return Merchant_Billing_Response
     */
    public function purchase($money, Merchant_Billing_CreditCard $creditcard, $options=array())
    {

        $this->add_creditcard($creditcard);
        $this->add_address($options);
        $this->post['PAYMENTACTION'] = 'Sale';
        $this->post['AMT'] = $this->amount($money);
        $this->post['IPADDRESS'] = isset($options['ip']) ? $options['ip'] : $_SERVER['REMOTE_ADDR'];
        return $this->commit('DoDirectPayment');
    }

    /**
     *
     * @param number $money
     * @param string $authorization (unique value received from authorize action)
     * @param array $options
     *
     * @return Merchant_Billing_Response
     */
    public function capture($money, $authorization, $options = array())
    {
        $this->required_options('complete_type', $options);

        $this->post['AUTHORIZATIONID'] = $authorization;
        $this->post['AMT'] = $this->amount($money);
        $this->post['COMPLETETYPE'] = $options['complete_type']; # Complete or NotComplete
        $this->add_invoice($options);

        return $this->commit('DoCapture');
    }

    /**
     *
     * @param string $authorization
     * @param array  $options
     *
     * @return Merchant_Billing_Response
     */
    public function void($authorization, $options = array())
    {
        $this->post['AUTHORIZATIONID'] = $authorization;
        $this->post['NOTE'] = isset($options['note']) ? $options['note'] : null;
        return $this->commit('DoVoid');
    }

    /**
     *
     * @param number $money
     * @param string $identification
     * @param array  $options
     *
     * @return Merchant_Billing_Response
     */
    public function credit($money, $identification, $options = array())
    {
        $this->required_options('refund_type', $options);

        $this->post['REFUNDTYPE'] = $options['refund_type']; //Must be Other, Full or Partial
        if ($this->post['REFUNDTYPE'] != 'Full')
            $this->post['AMT'] = $this->amount($money);

        $this->post['TRANSACTIONID'] = $identification;

        $this->add_invoice($options);
        return $this->commit('RefundTransaction');
    }

    /* Private */

    /**
     *
     * Options key can be 'shipping address' and 'billing_address' or 'address'
     * Each of these keys must have an address array like:
     * $address['name']
     * $address['company']
     * $address['address1']
     * $address['address2']
     * $address['city']
     * $address['state']
     * $address['country']
     * $address['zip']
     * $address['phone']
     * common pattern for addres is
     * $billing_address = isset($options['billing_address']) ? $options['billing_address'] : $options['address']
     * $shipping_address = $options['shipping_address']
     *
     * @param array $options
     */
    private function add_address($options)
    {
        $billing_address = isset($options['billing_address']) ? $options['billing_address'] : $options['address'];
        $this->post['STREET'] = isset($billing_address['address1']) ? $billing_address['address1'] : null;
        $this->post['CITY'] = isset($billing_address['city']) ? $billing_address['city'] : null;
        $this->post['STATE'] = isset($billing_address['state']) ? $billing_address['state'] : null;
        $this->post['ZIP'] = isset($billing_address['zip']) ? $billing_address['zip'] : null;
        $this->post['COUNTRYCODE'] = isset($billing_address['country']) ? Merchant_Country::find($billing_address['country'])->code('alpha2') : null;
    }

    /**
     *
     * @param Merchant_Billing_CreditCard $creditcard
     */
    private function add_creditcard(Merchant_Billing_CreditCard $creditcard)
    {

        $this->post['CREDITCARDTYPE'] = $this->credit_card_types[$creditcard->type];
        $this->post['ACCT'] = $creditcard->number;
        $this->post['EXPDATE'] = $this->cc_format($creditcard->month, 'two_digits') . $this->cc_format($creditcard->year, 'four_digits');
        $this->post['CVV2'] = $creditcard->verification_value;
        $this->post['FIRSTNAME'] = $creditcard->first_name;
        $this->post['LASTNAME'] = $creditcard->last_name;
        $this->post['CURRENCYCODE'] = self::$default_currency;

        if ($this->requires_start_date_or_issue_number($creditcard)) {
            if (!is_null($creditcard->start_month)) {
                $startMonth = $this->cc_format($creditcard->start_month, 'two_digits');
                $startYear = $this->cc_format($creditcard->start_year, 'four_digits');
                $this->post['STARTDATE'] = $startYear . $startMonth;
            }

            if (!is_null($creditcard->issue_number))
                $this->post['ISSUENUMBER'] = $this->cc_format($creditcard->issue_number, 'two_digits');
        }
    }

    /**
     *
     * @param array $options
     */
    private function add_invoice($options)
    {
        $this->post['INVNUM'] = isset($options['order_id']) ? $options['order_id'] : null;
        $this->post['NOTE'] = isset($options['note']) ? $options['note'] : null;
    }

    /**
     *
     * Add final parameters to post data and
     * build $this->post to the format that your payment gateway understands
     *
     * @param string $action
     * @param array  $parameters
     */
    protected function post_data($action)
    {
        $this->post['METHOD'] = $action;
        $this->post['VERSION'] = $this->version;
        $this->post['PWD'] = $this->options['password'];
        $this->post['USER'] = $this->options['login'];
        $this->post['SIGNATURE'] = $this->options['signature'];

        return $this->urlize($this->post);
    }

    /**
     *
     * @param Boolean $success
     * @param string  $message
     * @param array   $response
     * @param array   $options
     *
     * @return Merchant_Billing_Response
     */
    protected function build_response($success, $message, $response, $options=array())
    {
        return new Merchant_Billing_Response($success, $message, $response, $options);
    }

}

?>
