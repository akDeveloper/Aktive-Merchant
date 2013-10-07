<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Common\Inflect;

/**
 * Description of Exact gateway
 *
 * @category Gateways
 * @package  Aktive-Merchant
 * @author   Andreas Kollaros <andreaskollaros@ymail.com>
 * @license  MIT License http://www.opensource.org/licenses/mit-license.php
 * @link     https://github.com/akDeveloper/Aktive-Merchant
 */
class Exact extends Gateway implements Interfaces\Charge
{
    const TEST_URL = 'https://api.e-xact.com/transaction';
    const LIVE_URL = 'https://api.e-xact.com/transaction';

    protected $namespaces = array(
        'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"',
        'xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/"',
        'xmlns:tns="http://secure2.e-xact.com/vplug-in/transaction/rpc-enc/"',
        'xmlns:types="http://secure2.e-xact.com/vplug-in/transaction/rpc-enc/encodedTypes"',
        'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"',
        'xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
    );

    protected $transactions = array(
        'sale'     => '00',
        'authonly' => '01',
        'capture'  => '32',
        'credit'   => '34'
    );

    protected $headers = array(
        'Content-Type: application/xml; charset=UTF-8',
        'Accept: application/xml'
    );

    /**
     * {@inheritdoc}
     */
    public static $money_format = 'dollars';

    /**
     * {@inheritdoc}
     */
    public static $supported_countries = array('CA', 'US');

    /**
     * {@inheritdoc}
     */
    public static $supported_cardtypes = array(
        'visa',
        'master',
        'american_express',
        'jcb',
        'discover'
    );

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'http://www.e-xact.com';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'E-xact';

    /**
     * {@inheritdoc}
     */
    public static $default_currency = 'USD';

    /**
     * Additional options needed by gateway
     *
     * @var array
     */
    private $options;

    /**
     * Contains the main body of the request.
     *
     * @var array
     */
    private $post;

    /**
     * creates gateway instance from given options.
     *
     * @param array $options An array contains:
     *                       login
     *                       password
     *                       currency (optional)
     *
     * @return Gateway The gateway instance.
     */
    public function __construct($options = array())
    {
        Options::required('login, password', $options);

        if (isset($options['currency']))
            self::$default_currency = $options['currency'];

        $this->options = new Options($options);
    }

    /**
     * {@inheritdoc}
     */
    public function authorize($money, CreditCard $creditcard, $options=array())
    {
        $options = new Options($options);

        $this->create_xml('authonly');

        $this->add_amount($money);
        $this->add_invoice($options);
        $this->add_creditcard($creditcard);
        $this->add_address($options);
        $this->add_customer_data($options);

        return $this->commit('authonly', $money);
    }

    /**
     * {@inheritdoc}
     */
    public function purchase($money, CreditCard $creditcard, $options=array())
    {
        $options = new Options($options);

        $this->create_xml('sale');

        $this->add_amount($money);
        $this->add_invoice($options);
        $this->add_creditcard($creditcard);
        $this->add_address($options);
        $this->add_customer_data($options);

        return $this->commit('sale', $money);
    }

    /**
     * {@inheritdoc}
     */
    public function capture($money, $authorization, $options = array())
    {
        $this->create_xml('capture');

        $this->add_authorization($authorization);
        $this->add_amount($money);

        return $this->commit('capture', $money);
    }

    /**
     * {@inheritdoc}
     */
    public function credit($money, $identification, $options = array())
    {
        $this->create_xml('credit');

        $this->add_authorization($identification);
        $this->add_amount($money);

        return $this->commit('credit', $money);
    }

    // Private methods

    private function add_amount($money)
    {
        $this->post->addChild('DollarAmount',$this->amount($money));
    }

    /**
     * Customer data like e-mail, ip, web browser used for transaction etc
     *
     * @param array $options
     */
    private function add_customer_data($options)
    {
        $this->post->addChild('Customer_Ref', $options->customer);
        $this->post->addChild('Client_IP', $options->ip);
        $this->post->addChild('Client_Email', $options->email);
    }

    /**
     * Options key can be 'shipping address' and 'billing_address' or 'address'
     *
     * Each of these keys must have an address array like:
     * <code>
     *      $address['name']
     *      $address['company']
     *      $address['address1']
     *      $address['address2']
     *      $address['city']
     *      $address['state']
     *      $address['country']
     *      $address['zip']
     *      $address['phone']
     * </code>
     * common pattern for address is
     * <code>
     * $billing_address = isset($options['billing_address'])
     *      ? $options['billing_address']
     *      : $options['address'];
     * $shipping_address = $options['shipping_address'];
     * </code>
     *
     * @param  array $options
     *
     * @return void
     */
    private function add_address($options)
    {
        if ($address = $options['billing_address'] ?: $options['address']) {
            $this->post->addChild('ZipCode', $address['zip']);
        }
    }

    /**
     * Adds invoice info if exists.
     *
     * @param array $options
     */
    private function add_invoice($options)
    {
        $this->post->addChild('Reference_No', $options->order_id);
        $this->post->addChild('Reference_3', $options->description);

    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     */
    private function add_creditcard(CreditCard $creditcard)
    {
        $this->post->addChild('Card_Number', $creditcard->number);
        $this->post->addChild('Expiry_Date', $this->expdate($creditcard));
        $this->post->addChild('CardHoldersName', $creditcard->name());
        if ($cvv = $creditcard->verification_value) {

            $this->post->addChild('CVD_Presence_Ind',1);
            $this->post->addChild('VerificationStr2', $cvv);
        }
    }

    private function expdate(CreditCard $creditcard)
    {
        return $this->cc_format($creditcard->month, 'two_digits')
            . $this->cc_format($creditcard->year, 'two_digits');
    }

    private function add_transaction_type($action)
    {
        $this->post->addChild('Transaction_Type', $this->transactions[$action]);
    }

    private function add_credentials()
    {
        $this->post->addChild('ExactID', $this->options->login);
        $this->post->addChild('Password', $this->options->password);
    }

    private function add_authorization($authorization)
    {
        list($num, $tag) = explode(';', $authorization);

        $this->post->addChild('Transaction_Tag', $tag);
        $this->post->addChild('Authorization_Num', $num);
    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    private function parse($body)
    {
        $reponse = array();

        try {
            $xml = new \SimpleXMLElement($body);
        } catch (\Exception $e) {
            return array(
                'EXact_Message' => $body
            );
        }

        $root = $xml->xpath('/TransactionResult');

        $this->parse_elements($response, $root[0]);

        return $response;
    }

    private function parse_elements(&$response, $root)
    {
        foreach($root as $name => $value) {
            $response[$name] = trim((string) $value);
        }
    }

    private function create_xml($action)
    {
        $this->post = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><Transaction></Transaction>');
        $this->add_credentials();
        $this->add_transaction_type($action);
    }

    /**
     * Adds final parameters to post data and
     * build $this->post to the format that your payment gateway understands
     *
     * @param  string $action
     * @param  array  $parameters
     *
     * @return void
     */
    public function post_data($action, $parameters = array())
    {
        return $this->post->asXML();
    }

    /**
     *
     * @param  string $action
     * @param  number $money
     * @param  array  $parameters
     *
     * @return Response
     */
    private function commit($action, $money, $parameters = array())
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        //$this->getAdapter()->setOption(CURLOPT_USERPWD, "{$this->options->login}:{$this->options->password}");
        $data = $this->ssl_post($url, $this->post_data($action, $parameters), array('headers'=>$this->headers));

        $response = $this->parse($data);

        $test_mode = $this->isTest();

        return new Response(
            $this->success_from($response),
            $this->message_from($response),
            $response,
            array(
                'test' => $test_mode,
                'authorization' => $this->authorization_from($response),
                'fraud_review' => $this->fraud_review_from($response),
                'avs_result' => $this->avs_result_from($response),
                'cvv_result' => $response['CVV2']
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
    private function success_from($response)
    {
        return $response['Transaction_Approved'] == 'true';
    }

    private function authorization_from($response)
    {
        if (   isset($response['authorization_num'])
            && isset($response['transaction_tag'])
        ) {
            return "{$response['authorization_num']};{$response['transaction_tag']}";
        } else  {
            return '';
        }
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
        if (isset($response['Fault_Code']) && isset($response['Fault_String'])) {
            return $response['Fault_String'];
        } elseif ($response['Error_Number'] != '0') {
            return $response['Error_Description'];
        } else {
            $result = isset($response['EXact_Message']) ? $response['EXact_Message'] : '';
            if (isset($response['Bank_Message']) && !empty($response['Bank_Message'])) {
                $result .= ' - ' . $response['Bank_Message'];
            }

            return $result;
        }
    }

    /**
     * Returns fraud review from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    private function fraud_review_from($response)
    {

    }

    /**
     *
     * Returns avs result from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    private function avs_result_from($response)
    {
        return array('code' => isset($response['AVS']) ? $response['AVS'] : '');
    }
}
