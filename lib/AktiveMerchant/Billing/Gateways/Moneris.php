<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Exception;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Common\XmlBuilder;

/**
 * Integration of Moneris gateway
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
 * @author Andreas Kollaros <andreas@larium.net
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Moneris extends Gateway
{
    const TEST_URL = 'https://esqa.moneris.com/gateway2/servlet/MpgRequest';
    const LIVE_URL = 'https://www3.moneris.com/gateway2/servlet/MpgRequest';

    #Actions
    const AUTHORIZE = 'preauth';
    const CAVV_ATHORIZE = 'cavv_preauth';
    const PURCHASE = 'purchase';
    const CAVV_PURCHASE = 'cavv_purchase';
    const CAPTURE = 'completion';
    const VOID = 'purchasecorrection';
    const CREDIT = 'refund';

    # The countries the gateway supports merchants from as 2 digit ISO country codes
    public static $supported_countries = array('CA');

    # The card types supported by the payment gateway
    public static $supported_cardtypes = array( 'visa',  'master','american_express', 'discover');

    # The homepage URL of the gateway
    public static $homepage_url = 'http://www.moneris.com';

    # The display name of the gateway
    public static $display_name = 'Moneris Payment Gateway (eSelect Plus)';

    public static $default_currency = 'CAD';

    protected $options;

    private $post;

    private $xml;

    private $crypt_type = 7;

    private $credit_card_types = array(
        'V'  => 'visa',
        'M'  => 'master',
        'AX' => 'american_express',
        'NO' => 'discover'
    );

    const API_VERSION = 'MpgApi Version 2.03(php)';
    const FRAUD_REVIEW = 1;

    /**
     * $options array includes login parameters of merchant, optional currency
     * and region (US or CA).
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        Options::required('store_id, api_token', $options);

        parent::__construct($options);
    }

    /**
     *
     * @param number     $money
     * @param CreditCard $creditcard
     * @param array      $options
     *
     * @return Response
     */
    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $action = static::AUTHORIZE;

        if (isset($options['cavv'])) {
            $action = static::CAVV_ATHORIZE;
        }
        if (isset($options['crypt_type'])) {
            $this->crypt_type = $options['crypt_type'];
        }
        $this->buildXml($action, function ($xml) use ($money, $creditcard, $options) {
            if (isset($options['cavv'])) {
                $xml->cavv($this->amount($money));
            }
            $this->addInvoice($options);
            $this->addCreditcard($creditcard);
            $this->addAddress($options);
            $xml->amount($this->amount($money));
        });

        return $this->commit();
    }

    /**
     *
     * @param number     $money
     * @param CreditCard $creditcard
     * @param array      $options
     *
     * @return Response
     */
    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $action = static::PURCHASE;

        if (isset($options['cavv'])) {
            $action = static::CAVV_PURCHASE;
        }
        if (isset($options['crypt_type'])) {
            $this->crypt_type = $options['crypt_type'];
        }

        $this->buildXml($action, function ($xml) use ($money, $creditcard, $options) {
            if (isset($options['cavv'])) {
                $xml->cavv($this->amount($money));
            }
            $this->addInvoice($options);
            $this->addCreditcard($creditcard);
            $this->addAddress($options);
            $xml->amount($this->amount($money));
        });

        return $this->commit();
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

        $this->buildXml(static::CAPTURE, function ($xml) use ($authorization, $money, $options) {
            $this->addInvoice($options);
            $xml->comp_amount($this->amount($money));
            $xml->txn_number($authorization);
        });

        return $this->commit();
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

        $this->buildXml(static::VOID, function ($xml) use ($authorization, $options) {
            $this->addInvoice($options);
            $xml->txn_number($authorization);
        });

        return $this->commit();
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

        $this->buildXml(static::CREDIT, function ($xml) use ($identification, $money, $options) {
            $this->addInvoice($options);
            $xml->amount($this->amount($money));
            $xml->txn_number($identification);
        });

        return $this->commit();
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
    private function addAddress($options)
    {
        $options = new Options($options);

        $billing_address = $options['billing_address'] ?: $options['address'];
        $shipping_address = $options['shipping_address'];

        if (null == $billing_address && null == $shipping_address) {
            return false;
        }

        $name = explode(' ', $billing_address['name']);
        $first_name = $name[0];
        $last_name = $name[1];

        $this->xml->billing(function ($xml) use ($billing_address, $first_name, $last_name) {
            $xml->first_name($first_name);
            $xml->last_name($last_name);
            $this->parseAddress($billing_address);
        });

        $this->xml->shipping(function ($xml) use ($shipping_address, $first_name, $last_name) {
            $xml->first_name($first_name);
            $xml->last_name($last_name);
            $this->parseAddress($shipping_address);
        });

        if (isset($options['street_number']) && isset($options['street_name'])) {
            $this->xml->avs_info(function ($xml) use ($options, $billing_address) {
                $xml->avs_street_number($options['street_number']);
                $xml->avs_street_name($options['street_name']);
                $xml->avs_zipcode($billing_address['zip']);
            });
        }
    }

    /**
     * @param array $address an array of address information to parse.
     */
    private function parseAddress(Options $address)
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

        foreach ($options as $k => $v) {
            if ($address->offsetExists($k)) {
                $this->xml->$v($address[$k]);
            }
        }
    }

    /**
     *
     * @param array $options
     */
    private function addInvoice($options)
    {
        Options::required('order_id', $options);

        $this->xml->order_id($options['order_id']);

        if (isset($options['commcard_invoice'])) {
            $this->xml->commcard_invoice($options['commcard_invoice']);
        }

        if (isset($options['commcard_tax_amount'])) {
            $this->xml->commcard_tax_amount($this->amount($options['commmcard_tax_amount']));
        }

        if (isset($options['customer_id'])) {
            $this->xml->cust_id($this->options['customer_id']);
        }
    }

    /**
     *
     * @param CreditCard $creditcard
     */

    private function addCreditcard(CreditCard $creditcard)
    {
        $expDate = $this->cc_format($creditcard->year, 'two_digits')
            . $this->cc_format($creditcard->month, 'two_digits');

        $this->xml->pan($creditcard->number);
        $this->xml->expdate($expDate);
        $this->xml->cvd_info(function ($xml) use ($creditcard) {
            $xml->cvd_indicator(1);
            $xml->cvd_value($creditcard->verification_value);
        });
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
    private function commit()
    {
        $url = $this->isTest() ? static::TEST_URL : static::LIVE_URL;

        $data = $this->ssl_post(
            $url,
            $this->xml->__toString(),
            array(
                'user_agent' => static::API_VERSION,
                'timeout'=> 60
            )
        );

        $response = $this->parse($data);

        $test_mode = $this->isTest();

        return new Response(
            $this->successFrom($response),
            $this->messageFrom($response),
            $response,
            array(
                'test' => $test_mode,
                'authorization' => $response['transaction_id'],
                'fraud_review' => $this->fraudReviewFrom($response),
                'avs_result' => $this->avsResultFrom($response),
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
    private function successFrom($response)
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
    private function messageFrom($response)
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
    private function fraudReviewFrom($response)
    {
        return $response['cvd_result_code'] == static::FRAUD_REVIEW;
    }

    /**
     *
     * Returns avs result from gateway response
     *
     * @param array $response
     *
     * @return array
     */
    private function avsResultFrom($response)
    {
        return array('code' => $response['avs_result_code']);
    }

    private function buildXml($action, $block)
    {
        $this->xml = new XmlBuilder();
        $this->xml->instruct('1.0', null, false);

        $this->xml->request(function ($xml) use ($action, $block) {
            $this->authenticate();
            $xml->$action(function ($xml) use ($block) {
                $block($xml);
                $xml->crypt_type($this->crypt_type);
            });
        });
    }

    private function authenticate()
    {
        $this->xml->store_id($this->options['store_id']);
        $this->xml->api_token($this->options['api_token']);
    }
}
