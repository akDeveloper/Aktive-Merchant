<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Billing\Response;
use Thapp\XmlBuilder\XmlBuilder;
use AktiveMerchant\Common\Address;
use AktiveMerchant\Billing\Gateways\Worldpay\XmlNormalizer;
use AktiveMerchant\Common\Country;

/**
 * Integration of Iridium gateway.
 *
 * @author Dimitris Giannakakis <Dim.Giannakakis@yahoo.com>
 * @license MIT License http://www.opensource.org/licenses/mit-license.php
 */
class Iridium extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit
{

    const TEST_URL = 'https://gw1.iridiumcorp.net/';
    const LIVE_URL = 'https://gw1.iridiumcorp.net/';
    const DISPLAY_NAME = 'Iridium';

    public static $money_format = 'cents';

    /**
     * {@inheritdoc}
     */
    public static $supported_countries = array(
        'GB',
        'ES'
    );

    /**
     * {@inheritdoc}
     */
    public static $supported_cardtypes = array(
        'visa',
        'master',
        'american_express',
        'discover',
        'solo',
        'maestro',
        'jcb',
        'diners_club'
    );

    protected $soap;

    protected $reply = array();

    protected $success;

    protected $message;

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'http://www.iridiumcorp.co.uk/';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'Iridium';

    /**
     * {@inheritdoc}
     */
    public static $default_currency = 'EUR';


    protected $SOAP_ATTRIBUTES = array (

        'xmlns:soap' => 'http://schemas.xmlsoap.org/soap/envelope/',
        'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
        'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema'
    );

    public function __construct($options = array())
    {
        $this->required_options('merchant_id, password', $options);

        if (isset($options['currency'])) {
            self::$default_currency = $options['currency'];
        }

        $this->options = $options;

    }

    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $options = new Options($options);

        $this->setupAddressHash($options);

        if (isset($creditcard->number)) {
            return $this->commit($this->buildPurchaseRequest('PREAUTH', $money, $creditcard, $options), $options);
        } else {
            return $this->commit($this->buildReferenceRequest('PREAUTH', $money, $creditcard, $options), $options);
        }
    }

    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $options = new Options($options);

        $this->setupAddressHash($options);

        if (isset($creditcard->number)) {
            return $this->commit($this->buildPurchaseRequest('SALE', $money, $creditcard, $options), $options);
        } else {
            return $this->commit($this->buildReferenceRequest('SALE', $money, $creditcard, $options), $options);
        }
    }

    public function capture($money, $authorization, $options = array())
    {
        $options = new Options($options);

        return $this->commit($this->buildReferenceRequest('COLLECTION', $money, $authorization, $options), $options);
    }

    public function credit($money, $authorization, $options = array())
    {
        $options = new Options($options);

        return $this->commit($this->buildReferenceRequest('REFUND', $money, $authorization, $options), $options);
    }

    public function void($authorization, $options = array())
    {
        $options = new Options($options);

        return $this->commit($this->buildReferenceRequest('VOID', null, $authorization, $options), $options);
    }

    private function commit($request, $options)
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $headers = array(
            'headers' => array(
                'Content-Type: text/xml; charset=utf-8;',
                'SOAPAction: https://www.thepaymentgateway.net/'.$options->action
            ),
            'request_timeout' => 10
        );

        $data = $this->ssl_post($url, $request, $headers);

        $this->parse($data);

        $test_mode = $this->isTest();
        $this->success = $this->reply['transaction_result']['StatusCode'] == "0";
        $this->message = $this->reply['transaction_result']['Message'];

        return new Response(
            $this->success,
            $this->message,
            $this->reply,
            array(

                'test' => $test_mode,
                'authorization' => $this->authorizationFrom($options),
            )
        );
    }

    private function setupAddressHash(Options $options)
    {
        if ($options->billing_address) {
            $adr = $options->billing_address;
        } elseif ($options->address) {
            $adr = $options->address;
        } else {
            $adr = null;
        }

        $options->billing_address = $adr;
        $options->shipping_address = ($options->shipping_address)? $options->shipping_address : null;
    }

    private function buildPurchaseRequest(
        $type,
        $money,
        CreditCard $creditcard,
        $options
    ) {

        $options->action ='CardDetailsTransaction';
        $this->buildRequest($options, $money, $type, $creditcard);

        return $this->builderSoapEnvelope($this->soap);
    }

    private function buildReferenceRequest(
        $type,
        $money,
        $authorization,
        $options
    ) {

        $options->action ='CrossReferenceTransaction';

        $this->buildRequest($options, $money, $type, $authorization);

        return $this->builderSoapEnvelope($this->soap);
    }

    private function buildRequest(
        $options,
        $money,
        $type,
        $payment_source
    ) {

        $this->required_options('action', $options);

        $merchant_data = $this->addMerchant($options);
        $purchase_data = isset($payment_source->number) ? $this->addPurchaseData($type, $money, $options) : array();
        $credit_data = isset($payment_source->number) ? $this->addCreditcard($payment_source, $options) : array();
        $customer_details = isset($payment_source->number) ? $this->addCustomerDetails($payment_source, $options) : array();

        if (!$purchase_data) {
            $reference = $this->referenceDetails($options, $money, $type, $payment_source);
        } else {
            $reference  = array();
        }

        $this->soap = array(
            'soapBody' => array(
                $options->action => array (
                    '@attributes'=> array(
                        'xmlns' => "https://www.thepaymentgateway.net/"

                    ),
                    'PaymentMessage'=> array(
                        array_merge($merchant_data, $purchase_data, $credit_data, $customer_details, $reference) ,
                    )
                )
            )
        );
    }

    private function addMerchant($options)
    {
        $merchant = array(
            'MerchantAuthentication' => array(
                '@attributes' => array(
                    'MerchantID' => $this->options['merchant_id'],
                    'Password' => $this->options['password']
                )
            )
        );

        return $merchant;
    }

    private function builderSoapEnvelope(&$data)
    {
        $xml = new XmlBuilder('soap:Envelope', new XmlNormalizer());
        $data['@attributes'] = $this->SOAP_ATTRIBUTES;
        $xml->load($this->soap);
        $xml->setRenderTypeAttributes(false);

        $request = $xml->createXML(true);

        $request = str_replace("soapBody", "soap:Body", $request);

        return $request;
    }

    private function addPurchaseData($type, $money, $options)
    {
        $this->required_options('order_id', $options);

        $purchase_data = array(
            'TransactionDetails' => array(
                '@attributes' => array(
                    'Amount' => $this->amount($money),
                    'CurrencyCode' => ($options->currency)? $this->currency_lookup($options->currency) :$this->currency_lookup(self::$default_currency)
                ),
                'MessageDetails' => array(
                    '@attributes' => array(
                        'TransactionType' => $type
                    )
                ),
                'OrderID' => $options->order_id,
                'TransactionControl' => array(
                    'ThreeDSecureOverridePolicy' => 'FALSE',
                    'EchoAVSCheckResult' => 'TRUE',
                    'EchoCV2CheckResult' => 'TRUE'
                )
            )
        );

        return $purchase_data;
    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     * @param array reference $post
     */
    private function addCreditcard(CreditCard $creditcard, $options)
    {
        $credit = array(
            'CardDetails' => array(
                'CardName' => $creditcard->name(),
                'CV2' => $creditcard->verification_value,
                'CardNumber' => $creditcard->number,
                'ExpiryDate' => array(
                    '@attributes'=> array(
                        'Month' =>  $this->cc_format($creditcard->month, 'two_digits'),
                        'Year' => $this->cc_format($creditcard->year, 'two_digits')
                    )
                )
            )
        );

        return $credit;
    }

    private function addCustomerDetails($creditcard, $options, $shipTo = false)
    {
        $customer = array(
            'CustomerDetails' => array(
                'BillingAddress'=> array(
                    'Address1' => $options->billing_address->address1,
                    'Address2' => $options->billing_address->address2,
                    'City' => $options->billing_address->city,
                    'State' => $options->billing_address->state,
                    'PostCode' => $options->billing_address->zip,
                    'CountryCode' => ($options->billing_address->country) ? Country::find($options->billing_address->country)->getCode('numeric')->__toString() : null
                ),
                'PhoneNumber' => $options->billing_address->phone,
                'EmailAddress' => $options->email,
                'CustomerIPAddress' => ($options->ip) ? $options->ip : "127.0.0.1"
            ),

        );

        return $customer;
    }

    private function splitAuthorization($authorization)
    {
        list($transaction_id, $amount, $last_four) = explode(';', $authorization);

        $array = array(
            'order_id' => $transaction_id,
            'cross_reference' => $amount,
            'auth_id' => $last_four
        );

        return $array;
    }

    private function referenceDetails($options, $money, $type, $authorization)
    {
        $author = $this->splitAuthorization($authorization);

        if ($money) {
            $details = array(
                'TransactionDetails' => array(
                    '@attributes' => array(
                        'Amount' => $this->amount($money),
                        'CurrencyCode' => ($options->currency)? $this->currency_lookup($options->currency) :$this->currency_lookup(self::$default_currency)
                    ),
                    'MessageDetails' => array(
                        '@attributes' => array(
                            'TransactionType' => $type,
                            'CrossReference' => $author['cross_reference']
                        )
                    ),
                    'OrderID' => ($options->order_id) ? $options->order_id : $author['order_id'],
                )
            );
        } else {
            $details =array(
                'TransactionDetails' => array(
                    '@attributes' => array(
                        'Amount' =>'0',
                        'CurrencyCode' => $this->currency_lookup(self::$default_currency)
                    ),
                    'MessageDetails' => array(
                        '@attributes' => array(
                            'TransactionType' => $type,
                            'CrossReference' =>$author['cross_reference']
                        )
                    ),
                    'OrderID' => ($options->order_id) ? $options->order_id :$author['order_id']
                )
            );

        }

        return $details;
    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    private function parse($body)
    {
        $body = $this->substringBetween($body, '<soap:Body>', '</soap:Body>');

        $xml = new \SimpleXMLElement($body);

        foreach ($xml as $child => $value) {
            $this->parseElement($child, $value);
        }
    }

    private function parseElement($child, $value)
    {
        if (($child == 'CardDetailsTransactionResult'  )
            || ($child == 'CrossReferenceTransactionResult')
        ) {
            $attributes = $value->attributes();

            if (isset($attributes)) {
                foreach ($attributes as $key => $att) {
                    $this->reply['transaction_result'][$key] = (string) $att;
                }
            }

            foreach ($value as $key => $value) {
                $this->reply['transaction_result'][$key] = (string) $value;
            }

        } elseif ($child == 'TransactionOutputData') {
            $attributes = $value->attributes();

            if (isset($attributes)) {
                foreach ($attributes as $key => $att) {
                    $this->reply['transaction_output_data'][$key] = (string) $att;
                }
            }

            foreach ($value as $child_node => $child_value) {
                if ($child_node == 'GatewayEntryPoints') {
                    $index = 0;

                    foreach ($child_value as $key => $att) {
                        $attributes = $att->attributes();

                        if (isset($attributes)) {
                            foreach ($attributes as $att_key => $att_value) {
                                $this->reply['transaction_output_data']['gateway_entry_points'][$index][$att_key] = (string) $att_value;

                            }
                        }
                        $index++;
                    }
                }

                $this->reply['transaction_output_data'][$child_node] = (string) $child_value;
            }
        }
    }

    private function substringBetween($haystack, $start, $end)
    {
        if (strpos($haystack, $start) === false
            || strpos($haystack, $end) === false
        ) {
            return false;
        } else {
            $start_position = strpos($haystack, $start) + strlen($start);

            $end_position = strpos($haystack, $end);

            return substr($haystack, $start_position, $end_position - $start_position);
        }
    }

    private function authorizationFrom(Options $options)
    {
        if ($this->success) {
            $auth_code = isset($this->reply['transaction_output_data']['AuthCode'])
                ? $this->reply['transaction_output_data']['AuthCode']
                : null ;
            $auth =  $options->order_id
                .';'
                .$this->reply['transaction_output_data']['CrossReference']
                .';'
                .$auth_code;

        } else {
            $auth = null;
        }

        return $auth;
    }
}
