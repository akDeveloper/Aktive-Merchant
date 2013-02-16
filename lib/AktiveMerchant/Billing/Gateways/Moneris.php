<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Exception;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Options;

/**
 * Description of Moneris gateway
 *
 * Supported Methods
 * - authorize ( Verifies and locks funds on the customer' s credit card )
 * - purchase  ( Verifies funds on the customer’s card, removes the funds and
 *               readies them for deposit into the merchant’s account. )
 * - capture   ( Once an authorize is obtained the funds that are locked need to
 *               be retrieved from the customer’s credit card.)
 * - void      ( Can be performed against purchase or capture transactions. )
 * - credit    ( Can be performed against purchase or capture transactions. )
 *
 * NOTICE:
 * If MonerisMpi used for creditcard verification, $options array that passed
 * to *authorize* and *purchase* methods, should contain:
 * - $options['cavv'] element with value of cavv returned from MonerisMpi.
 * - $options['crypt_type'] element with value according to MonerisMpi response.
 *
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
class Moneris extends Gateway 
{
    const US_TEST_URL = 'https://esplusqa.moneris.com/gateway_us/servlet/MpgRequest';
    const US_LIVE_URL = 'https://esplus.moneris.com/gateway_us/servlet/MpgRequest';

    const CA_TEST_URL = 'https://esqa.moneris.com/gateway2/servlet/MpgRequest';
    const CA_LIVE_URL = 'https://www3.moneris.com/gateway2/servlet/MpgRequest';

    #Urls
    private $TEST_URL;
    private $LIVE_URL;
    private $API_VERSION;

    #Actions
    private $authorize;
    private $cavv_authorize;
    private $purchase;
    private $cavv_purchase;
    private $capture;
    private $void;
    private $credit;


    # The countries the gateway supports merchants from as 2 digit ISO country codes
    public static $supported_countries = array('US','CA');

    # The card types supported by the payment gateway
    public static $supported_cardtypes = array( 'visa',  'master','american_express', 'discover');

    # The homepage URL of the gateway
    public static $homepage_url = 'http://www.moneris.com';

    # The display name of the gateway
    public static $display_name = 'Moneris Payment Gateway (eSelect Plus)';

    public static $default_currency;

    private $options;
    private $post;

    private $crypt_type = 7;

    private $credit_card_types = array(
        'V'  => 'visa',
        'M'  => 'master',
        'AX' => 'american_express',
        'NO' => 'discover'
    );

    const US_API_VERSION = 'US PHP Api v.1.1.2';
    const CA_API_VERSION = 'MpgApi Version 2.03(php)';
    const FRAUD_REVIEW = 1;

    /**
     * $options array includes login parameters of merchant, optional currency 
     * and region (US or CA).
     *
     * @param array $options 
     */
    public function __construct(array $options = array())
    {
        Options::required('store_id, api_token, region', $options);

        $this->options = new Options($options);

        if (isset( $options['currency'])) {
            self::$default_currency = $options['currency'];
        }

        if ($options['region'] == 'US') {
            $this->TEST_URL = self::US_TEST_URL;
            $this->LIVE_URL = self::US_LIVE_URL;
            $this->API_VERSION = self::US_API_VERSION;

            $this->authorize      = 'us_preauth';
            $this->cavv_authorize = 'us_cavv_preauth';
            $this->purchase       = 'us_purchase';
            $this->cavv_purchase  = 'us_cavv_purchase';
            $this->capture        = 'us_completion';
            $this->void           = 'us_purchasecorrection';
            $this->credit         = 'us_refund';

            static::$default_currency = 'USD';
        } elseif($options['region'] == 'CA')  {
            $this->TEST_URL = self::CA_TEST_URL;
            $this->LIVE_URL = self::CA_LIVE_URL;
            $this->API_VERSION = self::CA_API_VERSION;

            $this->authorize      = 'preauth';
            $this->cavv_authorize = 'cavv_preauth';
            $this->purchase       = 'purchase';
            $this->cavv_purchase  = 'cavv_purchase';
            $this->capture        = 'completion';
            $this->void           = 'purchasecorrection';
            $this->credit         = 'refund';

            static::$default_currency = 'CAD';
        }
    }

    /**
     *
     * @param number     $money
     * @param CreditCard $creditcard
     * @param array      $options
     *
     * @return Response
     */
    public function authorize($money, CreditCard $creditcard, $options=array())
    {

        $this->add_invoice($options);
        $this->add_creditcard($creditcard);
        $this->add_address($options);
        $this->post .= "<amount>{$this->amount($money)}</amount>";
        $action = $this->authorize;

        if ( isset($options['cavv']) ) {
            $this->post .= "<cavv>{$this->amount($money)}</cavv>";
            $action = $this->cavv_authorize;
        }
        if ( isset( $options['crypt_type'] ) )
            $this->crypt_type = $options['crypt_type'];

        return $this->commit($action, $money);
    }

    /**
     *
     * @param number     $money
     * @param CreditCard $creditcard
     * @param array      $options
     *
     * @return Response
     */
    public function purchase($money, CreditCard $creditcard, $options=array())
    {

        $this->add_invoice($options);
        $this->add_creditcard($creditcard);
        $this->add_address($options);
        $this->post .= "<amount>{$this->amount($money)}</amount>";

        $action = $this->purchase;
        if ( isset($options['cavv']) ) {
            $this->post .= "<cavv>{$this->amount($money)}</cavv>";
            $action = $this->cavv_purchase;
        }
        if ( isset( $options['crypt_type'] ) )
            $this->crypt_type = $options['crypt_type'];

        return $this->commit($action, $money, $options);
    }

    /**
     *
     * @param float  $money
     * @param string $authorization unique value received from authorize action
     * @param array  $options
     *
     * @return Response
     */
    public function capture($money, $authorization, $options = array())
    {
        Options::required('order_id', $options);
     
        $this->add_invoice($options);
        $this->post .= "<comp_amount>{$this->amount($money)}</comp_amount>";
        $this->post .= "<txn_number>$authorization</txn_number>";

        return $this->commit($this->capture, $money);
    }

    /**
     *
     * @param string $authorization
     * @param array  $options
     *
     * @return Response
     */
    public function void($authorization, $options = array())
    {
        Options::required('order_id', $options);
        
        $this->add_invoice($options);
        $this->post .= "<txn_number>$authorization</txn_number>";
        
        return $this->commit($this->void, null);
    }

    /**
     *
     * @param float  $money
     * @param string $identification
     * @param array  $options
     *
     * @return Response
     */
    public function credit($money, $identification, $options = array())
    {
        Options::required('order_id', $options);

        $this->add_invoice($options);
        $this->post .= "<amount>{$this->amount($money)}</amount>";
        $this->post .= "<txn_number>$identification</txn_number>";

        return $this->commit($this->credit, $money);
    }

    /* Private */

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
    private function add_address($options)
    {
        $options = new Options($options);

        $billing_address = $options['billing_address'] ?: $options['address'];
        $shipping_address = $options['shipping_address'];

        if (null == $billing_address && null == $shipping_address) {
            return false;
        }

        $name = explode(' ',$billing_address['name']);
        $first_name = $name[0];
        $last_name = $name[1];

        $this->post .= "<billing><first_name>$first_name</first_name><last_name>$last_name</last_name>";
        $this->post .= $this->parse_address($billing_address)."</billing>";
        $this->post .= "<shipping><first_name>$first_name</first_name><last_name>$last_name</last_name>";
        $this->post .= $this->parse_address($shipping_address)."</shipping>";

        if (isset($options['street_number']) && isset($options['street_name'])) {
            $this->post .= "<avs_info><avs_street_number>{$options['street_number']}</avs_street_number><avs_street_name>{$options['street_name']}</avs_street_name><avs_zipcode>{$shipping_address['zip']}</avs_zipcode></avs_info>";
        }
    }

    /**
     * @param array $address an array of address information to parse.
     */
    private function parse_address($address)
    {
        $options = array(
            'company'=>'company_name',
            'address1'=>'address',
            'address2'=>'address',
            'city'=>'city',
            'state'=>'province',
            'country'=>'country',
            'zip'=>'postal_code',
            'phone'=>'phone_number'
        );

        $return = "";
        foreach ($options as $k=>$v) {
            if ( $address->offsetExists($k) ) {
                $return .= "<{$v}>{$address[$k]}</$v>";
            }
        }

        return $return;
    }

    /**
     *
     * @param array $options
     */
    private function add_invoice($options)
    {
        Options::required('order_id', $options);

        $this->post .= "<order_id>{$options['order_id']}</order_id>";

        if (isset($options['commcard_invoice'])) {
            $this->post .= "<commcard_invoice>{$options['commcard_invoice']}</commcard_invoice>";
        }

        if (isset($options['commcard_tax_amount'])) {
            $this->post .= "<commcard_tax_amount>{$this->amount($options['commcard_tax_amount'])}</commcard_tax_amount>";
        }

        if (isset($options['customer_id'])) {
            $this->post .= "<cust_id>{$this->amount($options['customer_id'])}</cust_id>";
        }
    }

    /**
     *
     * @param CreditCard $creditcard
     */

    private function add_creditcard(CreditCard $creditcard)
    {
        $exp_date = $this->cc_format($creditcard->year, 'two_digits') 
            . $this->cc_format($creditcard->month, 'two_digits');

        $this->post .= "<pan>{$creditcard->number}</pan><expdate>{$exp_date}</expdate><cvd_info><cvd_indicator>1</cvd_indicator><cvd_value>{$creditcard->verification_value}</cvd_value></cvd_info>";
    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    private function parse($body)
    {
        $xml = simplexml_load_string($body);
        $response = array();

        $response['receipt_id']     = (string) $xml->receipt->ReceiptId;
        $response['reference_num']  = (string) $xml->receipt->ReferenceNum;
        $response['response_code']  = (string) $xml->receipt->ResponseCode;
        $response['iso']            = (string) $xml->receipt->ISO;
        $response['auth_code']      = (string) $xml->receipt->AuthCode;
        $response['trans_time']     = (string) $xml->receipt->TransTime;
        $response['trans_date']     = (string) $xml->receipt->TransDate;
        $response['trans_type']     = (string) $xml->receipt->TransType;
        $response['complete']       = (string) $xml->receipt->Complete;
        $response['message']        = (string) $xml->receipt->Message;
        $response['trans_amount']   = (string) $xml->receipt->TransAmount;
        $cardtype                   = (string) $xml->receipt->CardType;
        $response['card_type']      = isset($this->credit_card_types[$cardtype]) 
            ? $this->credit_card_types[$cardtype] 
            : null;
        $response['transaction_id'] = (string) $xml->receipt->TransID;
        $response['timed_out']      = (string) $xml->receipt->TimedOut;
        $response['bank_totals']    = (string) $xml->receipt->BankTotals;
        $response['ticket']         = (string) $xml->receipt->Ticket;
        $response['avs_result_code']= (string) $xml->receipt->AvsResultCode;
        $response['cvd_result_code']= (string) $xml->receipt->CvdResultCode;
        
        if ($response['avs_result_code'] == 'null') {
            $response['avs_result_code'] = null;
        }

        return $response;
    }

    /**
     *
     * @param string $action
     * @param number $money
     * @param array  $parameters
     *
     * @return Response
     */
    private function commit($action, $money, $parameters=array())
    {
        $url = $this->isTest() ? $this->TEST_URL : $this->LIVE_URL;

        $data = $this->ssl_post(
            $url, 
            $this->post_data($action, $parameters),
            array(
                'user_agent' =>  $this->API_VERSION,
                'timeout'=> 60
            )
        );

        $response = $this->parse($data);

        $test_mode = $this->isTest();

        return new Response(
            $this->success_from($response),
            $this->message_from($response),
            $response,
            array(
                'test' => $test_mode,
                'authorization' => $response['transaction_id'],
                'fraud_review' => $this->fraud_review_from($response),
                'avs_result' => $this->avs_result_from($response),
                'cvv_result' => false
            )
        );
    }

    /**
     * Returns success flag from gateway response
     *
     * @param array $response
     *
     * @return bool
     */
    private function success_from($response)
    {
        return ($response['response_code'] < 50) && ($response['response_code'] != 'null');
    }

    /**
     * Returns message (error explanation  or success) from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    private function message_from($response)
    {
        return $response['message'];
    }


    /**
     * Returns fraud review from gateway response
     *
     * @param array $response
     *
     * @return boolean
     */
    private function fraud_review_from($response)
    {
        return $response['cvd_result_code'] == self::FRAUD_REVIEW;
    }

    /**
     *
     * Returns avs result from gateway response
     *
     * @param array $response
     *
     * @return array
     */
    private function avs_result_from($response)
    {
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
    private function post_data($action, $parameters = array()) 
    {
        $xml = '<?xml version="1.0"?><request>';
        $xml .= "<store_id>{$this->options['store_id']}</store_id><api_token>{$this->options['api_token']}</api_token>";
        $xml .= "<$action>$this->post<crypt_type>{$this->crypt_type}</crypt_type></$action></request>";
        
        return $xml;
    }

}
