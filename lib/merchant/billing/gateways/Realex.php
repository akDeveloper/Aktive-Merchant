<?php

/**
 * Realex
 *
 * @package default
 * @author Simon Hamilton
 */
class Merchant_Billing_Realex extends Merchant_Billing_Gateway
{
    /*
     * For more information on the Realex Payment Gateway visit their site http://realexpayments.com.
     * Realex is the leading gateway in Ireland
     *
     * === Merchant ID and Password
     *
     * To be able to use this library you will need to obtain an account from Realex, you can find contact them
     * via their website.
     *
     * === Caveats
     *
     * Realex requires that you specify the account to which your transactions are made.
     *
     * If you wish to accept multiple currencies, you need to create an account per currency.
     * This you would need to handle within your application logic.
     * Again, contact Realex for more information.
     *
     * They also require accepting payment from a Diners card (Mastercard) go through a different account.
     *
     * Realex also requires that you send several (extra) required identifiers with credit and void methods
     *
     *  - order_id
     *  - pasref
     *  - authorization
     *
     * The pasref can be accessed from the response params. i.e.
     * $response->params['pasref']
     *
     * === Testing
     *
     * Realex provide test card numbers on a per-account basis, you will need to request these.
     */

    const URL = 'https://epage.payandshop.com/epage-remote.cgi';
    const RECURRING_URL = 'https://epage.payandshop.com/epage-remote-plugins.cgi';

    protected $messages = array(
        'SUCCESS' => 'Success',
        'DECLINED' => 'Declined',
        'BANK_ERROR' => 'Gateway is in maintenance. Please try again later.',
        'REALEX_ERROR' => 'Gateway is in maintenance. Please try again later.',
        'ERROR' => 'Gateway Error',
        'CLIENT_DEACTIVATED' => 'Gateway Error'
    );
    protected $card_mappings = array(
        'master' => 'MC',
        'visa' => 'VISA',
        'american_express' => 'AMEX',
        'diners_club' => 'DINERS',
        'switch' => 'SWITCH',
        'solo' => 'SWITCH',
        'laser' => 'LASER'
    );
    public static $supported_countries = array('IE', 'UK');
    public static $supported_cardtypes = array('visa', 'master', 'american_express', 'diners_club', 'switch', 'solo', 'laser');
    public static $homepage_url = 'http://www.realexpayments.com/';
    public static $display_name = 'Realex';
    public static $default_currency = 'USD';
    public static $money_format = 'cents'; # or cents
    private $options = array();
    private $xml;
    private $timestamp;

    /**
     * Contructor
     *
     * @param string $options
     * @author Simon Hamilton
     */
    public function __construct($options)
    {
        $this->required_options('login, password', $options);

        $this->timestamp = strftime("%Y%m%d%H%M%S");

        if (isset($options['currency']))
            self::$default_currency = $options['currency'];

        $this->options = $options;
    }

    /**
     * Performs an authorization, which reserves the funds on the customer's credit card, but does not
     * charge the card.
     *
     * @param string $money The amount to be authorized. Either an Integer value in cents or a Money object.
     * @param Merchant_Billing_CreditCard $creditcard The CreditCard details for the transaction.
     * @param array $options Optional parameters.
     * @return Merchant_Billing_Response
     * @author Simon Hamilton
     */
    public function authorize($money, Merchant_Billing_CreditCard $creditcard, $options)
    {
        $this->required_options('order_id', $options);
        $this->build_purchase_or_authorization_request('authorisation', $money, $creditcard, $options);
        return $this->commit();
    }

    /**
     * Perform a purchase, which is essentially an authorization and capture in a single operation.
     *
     * @param string $money The amount to be authorized. Either an Integer value in cents or a Money object.
     * @param Merchant_Billing_CreditCard $creditcard The CreditCard details for the transaction.
     * @param array $options Optional parameters.
     * @return Merchant_Billing_Response
     * @author Simon Hamilton
     */
    public function purchase($money, Merchant_Billing_CreditCard $creditcard, $options)
    {
        $this->required_options('order_id', $options);
        $this->build_purchase_or_authorization_request('purchase', $money, $creditcard, $options);
        return $this->commit();
    }

    /**
     * Captures the funds from an authorized transaction.
     *
     * @param string $money The amount to be authorized. Either an Integer value in cents or a Money object.
     * @param string $authorization The authorization returned from the previous authorize request.
     * @param array $options Optional parameters.
     * @return Merchant_Billing_Response
     * @author Simon Hamilton
     */
    public function capture($money, $authorization, $options)
    {
        $this->required_options('pasref, order_id', $options);
        $this->build_capture_request($authorization, $options);
        return $this->commit();
    }

    /**
     * Credit an account.
     *
     * This transaction is also referred to as a Refund (or Rebate) and indicates to the gateway that
     * money should flow from the merchant to the customer.
     *
     * @param string $money The amount to be rebated. Either an Integer value in cents or a Money object.
     * @param string $authorization The authorization returned from the previous authorize request.
     * @param array $options Optional parameters.
     * @return Merchant_Billing_Response
     * @author Simon Hamilton
     */
    public function credit($money, $authorization, $options)
    {
        $this->required_options('pasref, order_id', $options);
        $this->build_credit_request($money, $authorization, $options);
        return $this->commit();
    }

    /**
     * Void a previous transaction
     *
     * @param string $authorization The authorization returned from the previous authorize request.
     * @param array $options Optional parameters.
     * @return Merchant_Billing_Response
     * @author Simon Hamilton
     */
    public function void($authorization, $options)
    {
        $this->required_options('pasref, order_id', $options);
        $this->build_void_request($authorization, $options);
        return $this->commit();
    }

    /*
     * Recurring payments
     */

    /**
     * Process a recurring payment transaction
     *
     * @param string $money The amount to be authorized. Either an Integer value in cents or a Money object.
     * @param array $options Optional parameters.
     * @return Merchant_Billing_Response
     * @author Simon Hamilton
     */
    public function recurring($money, $options)
    {
        $this->required_options('order_id', $options);
        $this->build_receipt_in_request($money, $options);
        return $this->commit('recurring');
    }

    /**
     * Store new card information in Realex RealVault
     *
     * @param Merchant_Billing_CreditCard $creditcard The CreditCard details for the transaction.
     * @param array $options Optional parameters.
     * @return Merchant_Billing_Response
     * @author Simon Hamilton
     */
    public function store(Merchant_Billing_CreditCard $creditcard, $options)
    {
        $this->required_options('order_id', $options);
        $this->build_new_card_request($creditcard, $options);
        return $this->commit('recurring');
    }

    /**
     * Remove card information from Realex RealVault
     *
     * @param Merchant_Billing_CreditCard $creditcard The CreditCard details for the transaction.
     * @param array $options Optional parameters.
     * @return Merchant_Billing_Response
     * @author Simon Hamilton
     */
    public function unstore(Merchant_Billing_CreditCard $creditcard, $options)
    {
        $this->required_options('order_id', $options);
        $this->build_cancel_card_request($creditcard, $options);
        return $this->commit('recurring');
    }

    /**
     * Store User information in the Realex RealVault
     *
     * @param array $options Parameters.
     * @return Merchant_Billing_Response
     * @author Simon Hamilton
     */
    public function store_user($options)
    {
        $this->required_options('order_id', $options);
        $this->build_new_payer_request($options);
        return $this->commit('recurring');
    }

    /**
     * Commit the request and receive the response
     * Sample Response:
     *
     * <?xml version="1.0" encoding="UTF-8" ?>
     * <response timestamp="20100906204459">
     *   <merchantid>mymerchantid</merchantid>
     *   <account>myaccount</account>
     *   <orderid>20100906204458-793</orderid>
     *   <authcode>204459</authcode>
     *   <result>00</result>
     *   <cvnresult>U</cvnresult>
     *   <avspostcoderesponse>U</avspostcoderesponse>
     *   <avsaddressresponse>U</avsaddressresponse>
     *   <batchid>17583</batchid>
     *   <message>[ test system ] Authorised 204459</message>
     *   <pasref>12838022883116</pasref>
     *   <timetaken>0</timetaken>
     *   <authtimetaken>0</authtimetaken>
     *   <cardissuer>
     *     <bank>AIB BANK</bank>
     *     <country>IRELAND</country>
     *     <countrycode>IE</countrycode>
     *     <region>EUR</region>
     *   </cardissuer>
     *   <sha1hash>51be108aa300e9943b898530048826ad92710c86</sha1hash>
     * </response>
     *
     * @param string $endpoint
     * @return Merchant_Billing_Response
     * @author Simon Hamilton
     */
    private function commit($endpoint='default')
    {
        $url = ($endpoint == 'recurring') ? self::RECURRING_URL : self::URL;
        $response = $this->parse($this->ssl_post($url, $this->xml->asXML()));

        return new Merchant_Billing_Response(((string) $response->result == '00'), $this->message_from($response), $this->params_from($response), $this->options_from($response));
    }

    private function build_purchase_or_authorization_request($action, $money, $creditcard, $options)
    {

        // build the xml object
        $this->xml = new SimpleXMLElement('<request type="auth"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);

        $this->add_merchant_details($options);

        $this->xml->addChild('orderid', $options['order_id']);

        $this->add_amount($money, $options);

        $this->add_card($creditcard);

        // do we settle now or just authorise
        $autosettle = $this->xml->addChild('autosettle');
        $autosettle->addAttribute('flag', $this->auto_settle_flag($action));

        $currency = (isset($options['currency'])) ? $options['currency'] : self::$default_currency;

        $digest = array(
            $this->timestamp,
            $options['merchant']['login'],
            $options['order_id'],
            $this->amount($money),
            $currency,
            $creditcard->number
        );

        $this->add_signed_digest($digest, $options);
        $this->add_comments($options);
        $this->add_address_and_customer_info($options);
    }

    private function build_capture_request($authorization, $options)
    {
        // build the xml object
        $this->xml = new SimpleXMLElement('<request type="settle"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);

        $this->add_merchant_details($options);
        $this->add_transaction_identifiers($authorization, $options);

        $digest = array(
            $this->timestamp,
            $options['merchant']['login'],
            $options['order_id']
        );

        $this->add_signed_digest($digest, $options);
        $this->add_comments($options);
    }

    private function build_credit_request($money, $authorization, $options)
    {
        // build the xml object
        $this->xml = new SimpleXMLElement('<request type="rebate"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);

        $this->add_merchant_details($options);
        $this->add_transaction_identifiers($authorization, $options);

        $this->add_amount($money, $options);

        $autosettle = $this->xml->addChild('autosettle');
        $autosettle->addAttribute('flag', 1);
        $this->xml->addChild('refundhash', $options['refund_hash']);

        $currency = (isset($options['currency'])) ? $options['currency'] : self::$default_currency;

        $digest = array(
            $this->timestamp,
            $options['merchant']['login'],
            $options['order_id'],
            $this->amount($money),
            $currency
        );

        $this->add_signed_digest($digest, $options);
        $this->add_comments($options);
    }

    private function build_void_request($authorization, $options)
    {
        // build the xml object
        $this->xml = new SimpleXMLElement('<request type="void"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);

        $this->add_merchant_details($options);
        $this->add_transaction_identifiers($authorization, $options);

        $digest = array(
            $this->timestamp,
            $options['merchant']['login'],
            $options['order_id']
        );

        $this->add_signed_digest($digest, $options);
        $this->add_comments($options);
    }

    private function build_cancel_card_request($creditcard, $options = array())
    {
        // build the xml object
        $this->xml = new SimpleXMLElement('<request type="card-cancel-card"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);

        $this->add_merchant_details($options);
        $card = $this->xml->addChild('card');
        $card->addChild('ref', $options['payment_method']);
        $card->addChild('payerref', $options['user']['id']);

        $digest = array(
            $this->timestamp,
            $options['merchant']['login'],
            $options['user']['id'],
            $options['payment_method']
        );

        $this->add_signed_digest($digest, $options);
        $this->add_comments($options);
    }

    private function build_new_card_request($creditcard, $options = array())
    {
        // build the xml object
        $this->xml = new SimpleXMLElement('<request type="card-new"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);
        $this->add_merchant_details($options);
        $this->xml->addChild('orderid', $options['order_id']);

        $this->add_card($creditcard);
        $this->xml->card->addChild('ref', $options['payment_method']);
        $this->xml->card->addChild('payerref', $options['user']['id']);

        $digest = array(
            $this->timestamp,
            $options['merchant']['login'],
            $options['order_id'],
            '',
            '',
            $options['user']['id'],
            $creditcard->name(),
            $creditcard->number
        );

        $this->add_signed_digest($digest, $options);
    }

    private function build_new_payer_request($options)
    {
        // build the xml object
        $this->xml = new SimpleXMLElement('<request type="payer-new"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);
        $this->add_merchant_details($options);
        $this->xml->addChild('orderid', $options['order_id']);

        $payer = $this->xml->addChild('payer');
        $payer->addAttribute('type', 'Business');
        $payer->addAttribute('ref', $options['user']['id']);
        $payer->addChild('firstname', $options['user']['first_name']);
        $payer->addChild('surname', $options['user']['last_name']);

        if (isset($options['company'])) {
            $payer->addChild('company', $options['company']);
        }

        if (isset($options['email'])) {
            $payer->addChild('email', $options['email']);
        }

        $digest = array(
            $this->timestamp,
            $options['merchant']['login'],
            $options['order_id'],
            '',
            '',
            $options['user']['id']
        );

        $this->add_signed_digest($digest, $options);
    }

    private function build_receipt_in_request($money, $options)
    {
        // build the xml object
        $this->xml = new SimpleXMLElement('<request type="receipt-in"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);
        $this->add_merchant_details($options);
        $this->xml->addChild('orderid', $options['order_id']);

        $this->add_amount($money, $options);

        $this->xml->addChild('paymentmethod', $options['payment_method']);
        $this->xml->addChild('payerref', $options['user']['id']);

        // do we settle now or just authorise
        $autosettle = $this->xml->addChild('autosettle');
        $autosettle->addAttribute('flag', 1);

        $currency = (isset($options['currency'])) ? $options['currency'] : self::$default_currency;

        $digest = array(
            $this->timestamp,
            $options['merchant']['login'],
            $options['order_id'],
            $this->amount($money),
            $currency,
            $options['user']['id']
        );

        $this->add_signed_digest($digest, $options);
        $this->add_comments($options);
        $this->add_address_and_customer_info($options);
    }

    private function add_address_and_customer_info($options)
    {
        $tssinfo = $this->xml->addChild('tssinfo');
        $tssinfo->addChild('custnum', $options['customer']);

        if (isset($options['invoice'])) {
            $tssinfo->addChild('prodid', $options['invoice']);
        }

        if (isset($options['varref'])) {
            $tssinfo->addChild('varref', $options['varref']);
        }

        if (isset($options['ip'])) {
            $tssinfo->addChild('custipaddress', $options['ip']);
        }

        if (isset($options['billing_address'])) {
            $billing_address = $options['billing_address'];
        } else {
            if (isset($options['address'])) {
                $billing_address = $options['address'];
            }
        }

        if (isset($billing_address)) {
            $billing = $tssinfo->addChild('address');
            $billing->addAttribute('type', 'billing');
            $billing->addChild('code', $this->avs_input_code_or_zip($billing_address, $options));
            $billing->addChild('country', $billing_address['country']);

            $shipping_address = (isset($options['shipping_address'])) ? $options['shipping_address'] : $billing_address;
        }

        if (isset($shipping_address)) {
            $shipping = $tssinfo->addChild('address');
            $shipping->addAttribute('type', 'shipping');
            $shipping->addChild('code', $shipping_address['zip']);
            $shipping->addChild('country', $shipping_address['country']);
        }
    }

    private function add_merchant_details($options)
    {
        $merchant = $options['merchant'];

        $this->xml->addChild('merchantid', $merchant['login']);
        $this->xml->addChild('account', $merchant['account']);
    }

    private function add_transaction_identifiers($authorization, $options)
    {
        $this->xml->addChild('orderid', $options['order_id']);
        $this->xml->addChild('pasref', $options['pasref']);
        $this->xml->addChild('authcode', $options['authcode']);
    }

    private function add_comments($options)
    {
        if (isset($options['description'])) {
            $comments = $this->xml->addChild('comments');
            $comment = $comments->addChild('comment');
            $comment->addAttribute('id', 1);
        }
    }

    private function add_amount($money, $options)
    {
        $currency = (isset($options['currency'])) ? $options['currency'] : self::$default_currency;

        $amount = $this->xml->addChild('amount', $this->amount($money));
        $amount->addAttribute('currency', $currency);
    }

    private function add_card($creditcard)
    {
        $card = $this->xml->addChild('card');
        $card->addChild('number', $creditcard->number);
        $card->addChild('expdate', $this->expiry_date($creditcard));
        $card->addChild('type', $this->card_mappings[$creditcard->type]);
        $card->addChild('issueno', $creditcard->issue_number);
        $card->addChild('chname', $creditcard->name());

        $cvn = $card->addChild('cvn');
        $cvn->addChild('number', $creditcard->verification_value);
        $cvn->addChild('presind', (($creditcard->verification_value) ? 1 : null));
    }

    private function avs_input_code_or_zip($address, $options)
    {
        return (isset($options['skip_avs_check'])) ? $address['zip'] : $this->avs_input_code($address);
    }

    private function avs_input_code($address)
    {
        $string = $address['zip'] . $address['address1'];
        preg_match_all("/([\d]+)/", $string, $numbers);
        return implode('|', $numbers[0]);
    }

    private function stringify_values($values)
    {
        return implode('.', $values);
    }

    private function add_signed_digest($values, $options)
    {
        $string = $this->stringify_values($values);
        $this->xml->addChild('sha1hash', $this->sha1from($string, $options));
    }

    private function auto_settle_flag($action)
    {
        return ($action == 'authorization') ? '0' : '1';
    }

    private function expiry_date($creditcard)
    {
        $month = $this->cc_format($creditcard->month, 'two_digits');
        $year = $this->cc_format($creditcard->year, 'two_digits');

        return $month . $year;
    }

    private function sha1from($string, $options)
    {
        $sha1hash = sha1($string);
        $secret = $options['merchant']['password'];
        $tmp = "$sha1hash.$secret";

        return sha1($tmp);
    }

    private function in_test_mode($response)
    {
        return (strstr((string) $response->message, '[ test system ]'));
    }

    /**
     * Parse the message from the response
     *
     * @param string $response
     * @return void
     * @author Simon Hamilton
     */
    private function message_from($response)
    {
        $result = (string) $response->result;
        $response_message = (string) $response->message;

        switch ($result) {
            case '00':
                $message = $this->messages['SUCCESS'];
                break;
            case '101':
            case '102':
            case '103':
                $message = ($this->in_test_mode($response)) ? $response_message : $this->messages['DECLINED'];
                break;
            case preg_match("/^2[0-9][0-9]/", $result, $matches):
                $message = $this->messages['BANK_ERROR'];
                break;
            case preg_match("/^3[0-9][0-9]/", $result, $matches):
                $message = $this->messages['REALEX_ERROR'];
                break;
            case preg_match("/^5[0-9][0-9]/", $result, $matches):
                $message = $reponse_message;
                break;
            case '600':
            case '601':
            case '602':
                $message = $this->messages['ERROR'];
                break;
            case '666':
                $message = $this->messages['CLIENT_DEACTIVATED'];
                break;
            default:
                $message = $this->messages['DECLINED'];
                break;
        }

        return $message;
    }

    /**
     * parse the response xml and create a SimpleXMLElement object
     *
     * Example parsed reponse:
     *
     * SimpleXMLElement Object
     * (
     *     [@attributes] => Array
     *         (
     *             [timestamp] => 20100906204810
     *         )
     *
     *     [merchantid] => mymerchantid
     *     [account] => myaccount
     *     [orderid] => 20100906204808-292
     *     [authcode] => 204809
     *     [result] => 00
     *     [cvnresult] => U
     *     [avspostcoderesponse] => U
     *     [avsaddressresponse] => U
     *     [batchid] => 17583
     *     [message] => [ test system ] Authorised 204809
     *     [pasref] => 12838024982560
     *     [timetaken] => 1
     *     [authtimetaken] => 1
     *     [cardissuer] => SimpleXMLElement Object
     *         (
     *             [bank] => AIB BANK
     *             [country] => IRELAND
     *             [countrycode] => IE
     *             [region] => EUR
     *         )
     *
     *     [sha1hash] => 10e8129a430c29e07c1eace81dbe0049d31a7f05
     * )
     *
     * @param string $response_xml
     * @return SimpleXMLElement Object
     * @author Simon Hamilton
     */
    private function parse($response_xml)
    {
        $xml = simplexml_load_string($response_xml);
        return $xml;
    }

    /**
     * get the params from the XML response
     *
     * @param SimpleXMLElement Object
     * @return array
     * @author Simon Hamilton
     */
    private function params_from($response)
    {
        $attribs = $response->attributes();

        $params = array();
        $params['result'] = (string) $response->result;
        $params['pasref'] = (string) $response->pasref;
        $params['order_id'] = (string) $response->orderid;
        $params['batch_id'] = (string) $response->batchid;
        $params['timestamp'] = (int) $attribs['timestamp'];

        return $params;
    }

    /**
     * get the options from the XML response
     *
     * @param SimpleXMLElement Object
     * @return array
     * @author Simon Hamilton
     */
    private function options_from($response)
    {
        $options = array();
        $options['test'] = (strstr((string) $response->message, '[ test system ]')) ? true : false;
        $options['authorization'] = (string) $response->authcode;
        $options['cvv_result'] = (string) $response->cvnresult;
        $options['avs_result'] = array(
            'street_match' => (string) $response->avspostcoderesponse,
            'postal_match' => (string) $response->avspostcoderesponse
        );

        return $options;
    }

}

?>
