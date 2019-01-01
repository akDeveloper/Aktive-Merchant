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
 * Integration of Exact gateway, using REST API with XML message format.
 *
 * @link https://hostedcheckout.zendesk.com/entries/231362-Transaction-Processing-API-Reference-Guide
 *
 * @author Andreas Kollaros <andreas@larium.net>
 * @license MIT License http://www.opensource.org/licenses/mit-license.php
 */
class Exact extends Gateway implements Interfaces\Charge
{
    const TEST_URL = 'https://api.demo.e-xact.com/transaction';
    const LIVE_URL = 'https://api.e-xact.com/transaction';

    protected $transactions = array(
        'sale'     => '00',
        'authonly' => '01',
        'capture'  => '32',
        'credit'   => '34',
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
    protected $options;

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

        if (isset($options['currency'])) {
            unset($options['currency']);
        }

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $options = new Options($options);

        $this->createXml('authonly');

        $this->addAmount($money);
        $this->addInvoice($options);
        $this->addCreditcard($creditcard);
        $this->addAddress($options);
        $this->addCustomerData($options);

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $options = new Options($options);

        $this->createXml('sale');

        $this->addAmount($money);
        $this->addInvoice($options);
        $this->addCreditcard($creditcard);
        $this->addAddress($options);
        $this->addCustomerData($options);

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function capture($money, $authorization, $options = array())
    {
        $this->createXml('capture');

        $this->addAuthorization($authorization);
        $this->addAmount($money);

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function credit($money, $identification, $options = array())
    {
        $this->createXml('credit');

        $this->addAuthorization($identification);
        $this->addAmount($money);

        return $this->commit();
    }

    public function amount($money)
    {
        $money = parent::amount($money);

        return number_format($money, 2, '.', ',');
    }

    private function addAmount($money)
    {
        $this->post->addChild('DollarAmount', $this->amount($money));
    }

    /**
     * Customer data like e-mail, ip, web browser used for transaction etc
     *
     * @param array $options
     */
    private function addCustomerData($options)
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
    private function addAddress($options)
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
    private function addInvoice($options)
    {
        $this->post->addChild('Reference_No', $options->order_id);
        $this->post->addChild('Reference_3', $options->description);

    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     */
    private function addCreditcard(CreditCard $creditcard)
    {
        $this->post->addChild('Card_Number', $creditcard->number);
        $this->post->addChild('Expiry_Date', $this->expdate($creditcard));
        $this->post->addChild('CardHoldersName', $creditcard->name());
        if ($cvv = $creditcard->verification_value) {
            $this->post->addChild('CVD_Presence_Ind', 1);
            $this->post->addChild('VerificationStr2', $cvv);
        }
    }

    private function expdate(CreditCard $creditcard)
    {
        return $this->cc_format($creditcard->month, 'two_digits')
            . $this->cc_format($creditcard->year, 'two_digits');
    }

    private function addTransactionType($action)
    {
        $this->post->addChild('Transaction_Type', $this->transactions[$action]);
    }

    private function addCredentials()
    {
        $this->post->addChild('ExactID', $this->options->login);
        $this->post->addChild('Password', $this->options->password);
    }

    private function addAuthorization($authorization)
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
        try {
            $xml = new \SimpleXMLElement($body);
        } catch (\Exception $e) {
            return array(
                'EXact_Message' => $body
            );
        }

        $root = $xml->xpath('/TransactionResult');

        $response = $this->parseElements($root[0]);

        return $response;
    }

    private function parseElements($root)
    {
        $response = array();
        foreach ($root as $name => $value) {
            $response[$name] = trim((string) $value);
        }

        return $response;
    }

    private function createXml($action)
    {
        $this->post = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><Transaction></Transaction>');
        $this->addCredentials();
        $this->addTransactionType($action);
    }

    /**
     * Adds final parameters to post data and
     * build $this->post to the format that your payment gateway understands
     *
     * @return string
     */
    private function postData()
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
    private function commit()
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $postData = $this->postData();
        $options = array('headers' => $this->headers);
        $data = $this->ssl_post($url, $postData, $options);

        $response = $this->parse($data);

        return new Response(
            $this->successFrom($response),
            $this->messageFrom($response),
            $response,
            array(
                'test' => $this->isTest(),
                'authorization' => $this->authorizationFrom($response),
                'fraud_review' => null,
                'avs_result' => $this->avsResultFrom($response),
                'cvv_result' => $this->cvvResultFrom($response),
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
    private function successFrom($response)
    {
        return isset($response['Transaction_Approved'])
            && $response['Transaction_Approved'] == 'true';
    }

    private function authorizationFrom($response)
    {
        if (isset($response['Authorization_Num'])
            && isset($response['Transaction_Tag'])
        ) {
            return "{$response['Authorization_Num']};{$response['Transaction_Tag']}";
        } else {
            return null;
        }
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
        if (isset($response['Fault_Code']) && isset($response['Fault_String'])) {
            return $response['Fault_String'];
        } elseif (isset($response['Error_Number']) && $response['Error_Number'] != '0') {
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
     *
     * Returns avs result from gateway response.
     *
     * @param array $response
     *
     * @return array
     */
    private function avsResultFrom($response)
    {
        $code = isset($response['AVS']) ? $response['AVS'] : 'U';

        return array('code' => $code);
    }

    /**
     * Returns cvv result from gateway response.
     *
     * @param array $response
     *
     * @return string
     */
    private function cvvResultFrom($response)
    {
        return isset($response['CVV2'])
            ? $response['CVV2']
            : 'P';
    }
}
