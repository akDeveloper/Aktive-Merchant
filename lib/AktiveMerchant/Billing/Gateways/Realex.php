<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

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
namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Exception;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Options;

/**
 * Integration of Realex gateway.
 *
 * @author Simon Hamilton
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Realex extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit,
    Interfaces\Recurring,
    Interfaces\Store
{
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

    protected $options = array();

    private $xml;

    private $timestamp;

    /**
     * Contructor
     *
     * @param array $options
     */
    public function __construct($options)
    {
        $this->required_options('login, password', $options);

        $this->timestamp = strftime("%Y%m%d%H%M%S");

        if (isset($options['currency'])) {
            self::$default_currency = $options['currency'];
        }

        $this->options = $options;
    }

    /**
     * Performs an authorization, which reserves the funds on the customer's credit card, but does not
     * charge the card.
     *
     * @param string $money The amount to be authorized. Either an Integer value in cents or a Money object.
     * @param CreditCard $creditcard The CreditCard details for the transaction.
     * @param array $options Optional parameters.
     *
     * @return Response
     */
    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $this->required_options('order_id', $options);
        $this->buildPurchaseOrAuthorizationRequest('authorization', $money, $creditcard, $options);

        return $this->commit();
    }

    /**
     * Perform a purchase, which is essentially an authorization and capture in a single operation.
     *
     * @param string $money The amount to be authorized. Either an Integer value in cents or a Money object.
     * @param CreditCard $creditcard The CreditCard details for the transaction.
     * @param array $options Optional parameters.
     *
     * @return Response
     */
    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $this->required_options('order_id', $options);
        $this->buildPurchaseOrAuthorizationRequest('purchase', $money, $creditcard, $options);

        return $this->commit();
    }

    /**
     * Captures the funds from an authorized transaction.
     *
     * @param string $money The amount to be authorized. Either an Integer value in cents or a Money object.
     * @param string $authorization The authorization returned from the previous authorize request.
     * @param array $options Optional parameters.
     *
     * @return Response
     */
    public function capture($money, $authorization, $options = array())
    {
        $this->required_options('pasref, order_id', $options);
        $this->buildCaptureRequest($authorization, $options);

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
     * `
     * @return Response
     */
    public function credit($money, $authorization, $options = array())
    {
        $this->required_options('pasref, order_id', $options);
        $this->buildCreditRequest($money, $authorization, $options);

        return $this->commit();
    }

    /**
     * Void a previous transaction
     *
     * @param string $authorization The authorization returned from the previous authorize request.
     * @param array $options Optional parameters.
     *
     * @return Response
     */
    public function void($authorization, $options = array())
    {
        $this->required_options('pasref, order_id', $options);
        $this->buildVoidRequest($authorization, $options);

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
     *
     * @return Response
     */
    public function recurring($money, CreditCard $creditcard, $options = array())
    {
        $this->required_options('order_id', $options);
        $this->buildReceiptInRequest($money, $options);

        return $this->commit('recurring');
    }

    public function updateRecurring($subscription_id, CreditCard $creditcard)
    {
    }

    public function cancelRecurring($subscription_id)
    {
    }

    /**
     * Store new card information in Realex RealVault
     *
     * @param CreditCard $creditcard The CreditCard details for the transaction.
     * @param array $options Optional parameters.
     *
     * @return Response
     */
    public function store(CreditCard $creditcard, $options = array())
    {
        $this->required_options('order_id', $options);
        $this->buildNewCardRequest($creditcard, $options);
        return $this->commit('recurring');
    }

    /**
     * Remove card information from Realex RealVault
     *
     * @param CreditCard $creditcard The CreditCard details for the transaction.
     * @param array $options Optional parameters.
     *
     * @return Response
     */
    public function unstore($reference, $options = array())
    {
        $this->required_options('order_id', $options);
        $this->buildCancelCardRequest($options);
        return $this->commit('recurring');
    }

    /**
     * Store User information in the Realex RealVault
     *
     * @param array $options Parameters.
     *
     * @return Response
     */
    public function storeUser($options)
    {
        $this->required_options('order_id', $options);
        $this->buildNewPayerRequest($options);
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
     *
     * @return Response
     */
    private function commit($endpoint = 'default')
    {
        $url = ($endpoint == 'recurring') ? self::RECURRING_URL : self::URL;
        $response = $this->parse($this->ssl_post($url, $this->xml->asXML()));

        return new Response(((string) $response->result == '00'), $this->messageFrom($response), $this->paramsFrom($response), $this->optionsFrom($response));
    }

    private function buildPurchaseOrAuthorizationRequest(
        $action,
        $money,
        $creditcard,
        $options
    ) {

        $options = new Options($options);

        // build the xml object
        $this->xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><request type="auth"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);

        if (isset($options['three_d_secure'])
            && isset($this->options['3dsaccount'])
        ) {
            $options['account'] = $this->options['3dsaccount'];
        } elseif (!isset($options['three_d_secure'])
            && isset($this->options['stdaccount'])
        ) {
            $options['account'] = $this->options['stdaccount'];
        }

        $this->addMerchantDetails($options);

        $this->xml->addChild('orderid', $options['order_id']);

        $this->addAmount($money, $options);

        $this->addCard($creditcard);

        // do we settle now or just authorise
        $autosettle = $this->xml->addChild('autosettle');
        $autosettle->addAttribute('flag', $this->autoSettleFlag($action));

        if (isset($options['three_d_secure'])) {
            $this->addThreeDSecure($options['three_d_secure']);
        }

        $currency = isset($options['currency'])
            ? $options['currency']
            : self::$default_currency;

        $digest = array(
            $this->timestamp,
            $this->options['login'],
            $options['order_id'],
            $this->amount($money),
            $currency,
            $creditcard->number
        );

        $this->addSignedDigest($digest, $options);
        $this->addComments($options);
        $this->addAddressAndCustomerInfo($options);
    }

    private function buildCaptureRequest($authorization, $options)
    {
        // build the xml object
        $this->xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><request type="settle"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);

        $this->addMerchantDetails($options);
        $this->addTransactionIdentifiers($authorization, $options);

        $digest = array(
            $this->timestamp,
            $this->options['login'],
            $options['order_id'],
            ".."
        );

        $this->addSignedDigest($digest, $options);
        $this->addComments($options);
    }

    private function buildCreditRequest($money, $authorization, $options)
    {
        // build the xml object
        $this->xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><request type="rebate"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);

        $this->addMerchantDetails($options);
        $this->addTransactionIdentifiers($authorization, $options);

        $this->addAmount($money, $options);

        $autosettle = $this->xml->addChild('autosettle');
        $autosettle->addAttribute('flag', 1);
        $this->xml->addChild('refundhash', $options['refund_hash']);

        $currency = (isset($options['currency'])) ? $options['currency'] : self::$default_currency;

        $digest = array(
            $this->timestamp,
            $this->options['login'],
            $options['order_id'],
            $this->amount($money),
            $currency
        );

        $this->addSignedDigest($digest, $options);
        $this->addComments($options);
    }

    private function buildVoidRequest($authorization, $options)
    {
        // build the xml object
        $this->xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><request type="void"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);

        $this->addMerchantDetails($options);
        $this->addTransactionIdentifiers($authorization, $options);

        $digest = array(
            $this->timestamp,
            $this->options['login'],
            $options['order_id']
        );

        $this->addSignedDigest($digest, $options);
        $this->addComments($options);
    }

    private function buildCancelCardRequest($options = array())
    {
        // build the xml object
        $this->xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><request type="card-cancel-card"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);

        $this->addMerchantDetails($options);
        $card = $this->xml->addChild('card');
        $card->addChild('ref', $options['payment_method']);
        $card->addChild('payerref', $options['user']['id']);

        $digest = array(
            $this->timestamp,
            $this->options['login'],
            $options['user']['id'],
            $options['payment_method']
        );

        $this->addSignedDigest($digest, $options);
        $this->addComments($options);
    }

    private function buildNewCardRequest($creditcard, $options = array())
    {
        // build the xml object
        $this->xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><request type="card-new"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);
        $this->addMerchantDetails($options);
        $this->xml->addChild('orderid', $options['order_id']);

        $this->addCard($creditcard);
        $this->xml->card->addChild('ref', $options['payment_method']);
        $this->xml->card->addChild('payerref', $options['user']['id']);

        $digest = array(
            $this->timestamp,
            $this->options['login'],
            $options['order_id'],
            '',
            '',
            $options['user']['id'],
            $creditcard->name(),
            $creditcard->number
        );

        $this->addSignedDigest($digest, $options);
        $this->addComments($options);
    }

    private function buildNewPayerRequest($options)
    {
        // build the xml object
        $this->xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><request type="payer-new"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);
        $this->addMerchantDetails($options);
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
            $this->options['login'],
            $options['order_id'],
            '',
            '',
            $options['user']['id']
        );

        $this->addSignedDigest($digest, $options);
    }

    private function buildReceiptInRequest($money, $options)
    {
        // build the xml object
        $this->xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><request type="receipt-in"></request>');
        $this->xml->addAttribute('timestamp', $this->timestamp);
        $this->addMerchantDetails($options);
        $this->xml->addChild('orderid', $options['order_id']);

        $this->addAmount($money, $options);

        $this->xml->addChild('paymentmethod', $options['payment_method']);
        $this->xml->addChild('payerref', $options['user']['id']);

        // do we settle now or just authorise
        $autosettle = $this->xml->addChild('autosettle');
        $autosettle->addAttribute('flag', 1);

        $currency = (isset($options['currency'])) ? $options['currency'] : self::$default_currency;

        $digest = array(
            $this->timestamp,
            $this->options['login'],
            $options['order_id'],
            $this->amount($money),
            $currency,
            $options['user']['id']
        );

        $this->addSignedDigest($digest, $options);
        $this->addComments($options);
        $this->addAddressAndCustomerInfo($options);
    }

    private function addAddressAndCustomerInfo($options)
    {
        $tssinfo = $this->xml->addChild('tssinfo');

        if (isset($options['customer'])) {
            $tssinfo->addChild('custnum', $options['customer']);
        }

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
            $billing->addChild('code', $this->avsInputCodeOrZip($billing_address, $options));
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

    private function addMerchantDetails($options)
    {
        $this->xml->addChild('merchantid', $this->options['login']);

        if (isset($this->options['account'])) {
            $this->xml->addChild('account', $this->options['account']);
        } elseif (isset($options['account'])) {
            $this->xml->addChild('account', $options['account']);
        }
    }

    private function addTransactionIdentifiers($authorization, $options)
    {
        $this->xml->addChild('orderid', $options['order_id']);
        $this->xml->addChild('pasref', $options['pasref']);
        $this->xml->addChild('authcode', $options['authcode']);
    }

    private function addThreeDSecure($options)
    {
        $mpi = $this->xml->addChild('mpi');
        $mpi->addChild('eci', $options['eci']);
        $mpi->addChild('xid', $options['xid']);
        $mpi->addChild('cavv', $options['cavv']);
    }

    private function addComments($options)
    {
        if (isset($options['description'])) {
            $comments = $this->xml->addChild('comments');
            $comment =  $comments->addChild('comment', substr($options['description'], 0, 255));
            $comment->addAttribute('id', 1);
        }
    }

    private function addAmount($money, $options)
    {
        $currency = (isset($options['currency'])) ? $options['currency'] : self::$default_currency;

        $amount = $this->xml->addChild('amount', $this->amount($money));
        $amount->addAttribute('currency', $currency);
    }

    private function addCard($creditcard)
    {
        $card = $this->xml->addChild('card');
        $card->addChild('number', $creditcard->number);
        $card->addChild('expdate', $this->expiryDate($creditcard));
        $card->addChild('type', $this->card_mappings[$creditcard->type]);
        $card->addChild('issueno', $creditcard->issue_number);
        $card->addChild('chname', $creditcard->name());

        $cvn = $card->addChild('cvn');
        $cvn->addChild('number', $creditcard->verification_value);
        $cvn->addChild('presind', (($creditcard->verification_value) ? 1 : null));
    }

    private function avsInputCodeOrZip($address, $options)
    {
        return (isset($options['skip_avs_check'])) ? $address['zip'] : $this->avsInputCode($address);
    }

    private function avsInputCode($address)
    {
        $string = $address['zip'] . $address['address1'];
        preg_match_all("/([\d]+)/", $string, $numbers);
        return implode('|', $numbers[0]);
    }

    private function stringifyValues($values)
    {
        return implode('.', $values);
    }

    private function addSignedDigest($values, $options)
    {
        $string = $this->stringifyValues($values);
        $this->xml->addChild('sha1hash', $this->sha1from($string, $options));
    }

    private function autoSettleFlag($action)
    {
        return ($action == 'authorization') ? '0' : '1';
    }

    private function expiryDate($creditcard)
    {
        $month = $this->cc_format($creditcard->month, 'two_digits');
        $year = $this->cc_format($creditcard->year, 'two_digits');

        return $month . $year;
    }

    private function sha1from($string, $options)
    {
        $sha1hash = sha1($string);
        $secret = $this->options['password'];
        $tmp = "$sha1hash.$secret";

        return sha1($tmp);
    }

    private function inTestMode($response)
    {
        return (strstr((string) $response->message, '[ test system ]'));
    }

    /**
     * Parse the message from the response
     *
     * @param string $response
     *
     * @return string
     */
    private function messageFrom($response)
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
                $message = ($this->inTestMode($response)) ? $response_message : $this->messages['DECLINED'];
                break;
            case (bool) preg_match("/^2[0-9][0-9]/", $result, $matches):
                $message = $this->messages['BANK_ERROR'];
                break;
            case (bool) preg_match("/^3[0-9][0-9]/", $result, $matches):
                $message = $this->messages['REALEX_ERROR'];
                break;
            case (bool) preg_match("/^5[0-9][0-9]/", $result, $matches):
                $message = $response_message;
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
     *
     * @return \SimpleXMLElement Object
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
     *
     * @return array
     */
    private function paramsFrom($response)
    {
        $attribs = $response->attributes();

        $params = array();
        $params['result'] = (string) $response->result;
        $params['pasref'] = (string) $response->pasref;
        $params['order_id'] = (string) $response->orderid;
        $params['batch_id'] = (string) $response->batchid;
        $params['account'] = (string) $response->account;
        $params['timestamp'] = (int) $attribs['timestamp'];

        return $params;
    }

    /**
     * get the options from the XML response
     *
     * @param SimpleXMLElement Object
     *
     * @return array
     */
    private function optionsFrom($response)
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
