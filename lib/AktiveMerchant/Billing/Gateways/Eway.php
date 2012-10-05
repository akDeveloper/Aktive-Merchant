<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;

/**
 * Description of Example
 *
 * @category Gateways
 * @package  Aktive-Merchant
 * @author   Your name <your@email.com>
 * @license  MIT License http://www.opensource.org/licenses/mit-license.php
 * @link     https://github.com/akDeveloper/Aktive-Merchant
 */
class Eway extends Gateway implements 
    Interfaces\Charge,
    Interfaces\Credit
{
    const TEST_URL = 'https://www.eway.com.au/gateway/xmltest/testpage.asp';
    const LIVE_URL = "https://www.eway.com.au/gateway/%s.asp";

    const TEST_CVN_URL = 'https://www.eway.com.au/gateway_cvn/xmltest/testpage.asp';
    const LIVE_CVN_URL = "https://www.eway.com.au/gateway_cvn/%s.asp";

    /**
     * Money format supported by this gateway.
     * Can be 'dollars' or 'cents'
     *
     * @var string Money format 'dollars' | 'cents'
     */
    public static $money_format = 'cents';

    /**
     * The countries supported by the gateway as 2 digit ISO country codes.
     *
     * @var array
     */
    public static $supported_countries = array('AU');

    /**
     * The card types supported by the payment gateway
     *
     * @var array
     */
    public static $supported_cardtypes = array(
        'visa',
        'master',
        'american_express',
        'diners_club',
    );

    /**
     * The homepage URL of the gateway
     *
     * @var string
     */
    public static $homepage_url = 'http://www.eway.com.au/';

    /**
     * The display name of the gateway
     *
     * @var string
     */
    public static $display_name = 'eWay';

    /**
     * The currency supported by the gateway as ISO 4217 currency code.
     *
     * @var string The ISO 4217 currency code
     */
    public static $default_currency = 'EUR';

    /**
     * Additional options needed by gateway
     *
     * @var array
     */
    private $options;

    /**
     *
     *
     * @var array
     */
    private $post;

    /**
     * creates gateway instance from given options.
     *
     * @param array $options an array contains login parameters of merchant
     *                       and optional currency.
     *
     * @return Gateway The gateway instance.
     */
    public function __construct($options = array())
    {
        $this->required_options('login', $options);

        if (isset($options['currency'])) {
            self::$default_currency = $options['currency'];
        }

        $this->options = $options;
    }

    /**
     * Binds the given amount to customer creditcard
     *
     * creditcard is not charged yet. a capture action required for charging the
     * creditcard.
     *
     * @param number     $money
     * @param CreditCard $creditcard
     * @param array      $options
     *
     * @return Response
     */
    public function authorize($money, CreditCard $creditcard, $options=array())
    {
        $this->required_options('address', $options);

        $this->post = array();

        $this->add_invoice($options);
        $this->add_creditcard($creditcard);
        $this->add_address($options);
        $this->add_customer_data($options);
        $this->add_optional_data();

        $this->post['TotalAmount'] = $this->amount($money);
        
        return $this->commit('xmlauth', $money, $options);
    }

    /**
     *
     * @param  number     $money
     * @param  CreditCard $creditcard
     * @param  array      $options
     *
     * @return Response
     */
    public function purchase($money, CreditCard $creditcard, $options=array())
    {
        $this->required_options('address', $options);

        $this->post = array();

        $this->add_invoice($options);
        $this->add_creditcard($creditcard);
        $this->add_address($options);
        $this->add_customer_data($options);
        $this->add_optional_data();

        $this->post['TotalAmount'] = $this->amount($money);
        
        return $this->commit('xmlpayment', $money, $options);
    }

    public function capture($money, $authorization, $options = array()){}

    /**
     *
     * @param  number $money
     * @param  string $identification
     * @param  array  $options
     *
     * @return Response
     */
    public function credit($money, $identification, $options = array())
    {
        $this->required_options('password', $options);

        $this->post = array();

        $this->post['OriginalTrxnNumber'] = $identification;
        
        $this->post['TotalAmount'] = $this->amount($money);

        $this->post['RefundPassword'] = $options['password'];

        return $this->commit('xmlpaymentrefund', $money);
    }

    public function void($authorization, $options = array()){}

    // Private methods

    /**
     * Customer data like e-mail, ip, web browser used for transaction etc
     *
     * @param array $options
     */
    private function add_customer_data($options)
    {
        $this->post['CustomerEmail'] = $options['email'];
    }

    /**
     * Options key can be 'shipping address' and 'billing_address' or 'address'
     *
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
        $billing_address = isset($options['billing_address'])
            ? $options['billing_address']
            : $options['address'];
        $this->post['CustomerAddress'] = join(', ', $billing_address);

        $this->post['CustomerPostcode'] = $billing_address['zip'];
    }

    /**
     * Adds invoice info if exists.
     *
     * @param array $options
     */
    private function add_invoice($options)
    {
        $this->post['CustomerInvoiceRef'] = $options['order_id'];
        $this->post['CustomerInvoiceDescription'] = $options['description'];
    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     */
    private function add_creditcard(CreditCard $creditcard)
    {
        $this->post['CardNumber'] = $creditcard->number;
        $this->post['CardExpiryMonth'] = $this->cc_format($creditcard->month, 'two_digits');
        $this->post['CardExpiryYear'] = $this->cc_format($creditcard->year, 'two_digits');
        $this->post['CustomerFirstName'] = $creditcard->first_name;
        $this->post['CustomerLastName'] = $creditcard->last_name;
        $this->post['CardHoldersName'] = $creditcard->name();

        if ($creditcard->verification_value) {
            $this->post['CVN'] = $creditcard->verification_value;
        }
    }

    private function add_optional_data()
    {
        $this->post['TrxnNumber'] = null;
        $this->post['Option1'] = null;
        $this->post['Option2'] = null;
        $this->post['Option3'] = null;
    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    private function parse($body)
    {
        $response = array();
        $xml = new \SimpleXMLElement($body);

        foreach ($xml as $name=>$value) {
            $k = str_replace('eway', '',$name);
            $response[$k] = (string) $value;
        }

        #print_r($response);
        return $response;
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
        $cvn = isset($this->post['CVN']) ? $this->post['CVN'] : false;

        $url = $this->get_gateway_url($action, $cvn, $this->isTest());
        
        $data = $this->ssl_post($url, $this->post_data($parameters));

        $response = $this->parse($data);

        $test_mode = $this->isTest();

        return new Response(
            $this->success_from($response),
            $this->message_from($response),
            $response,
            array(
                'test' => $test_mode,
                'authorization' => $response['AuthCode'],
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
        return $response['TrxnStatus'] == 'True';
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
        return $response['TrxnError'];
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
        return array('code' => $response['avs_result_code']);
    }

    /**
     * Adds final parameters to post data and
     * build $this->post to the format that your payment gateway understands
     *
     * @param  array  $parameters
     *
     * @return string Request xml as string
     */
    private function post_data($parameters = array())
    {
        $this->post['CustomerID'] = $this->options['login'];

        $xml = new \SimpleXMLElement('<ewaygateway></ewaygateway>');

        foreach ($this->post as $name => $value) {

            $xml->addChild("eway$name", $value);
        }

        return $xml->asXML(); 
    }

    private function get_gateway_url($action, $cvn, $test)
    {
        if ($cvn) {
            if ($test) {
                $url = self::TEST_CVN_URL;
            } else {
                $url = sprintf(self::LIVE_CVN_URL, $action);
            }
        } else {
            if ($test) {
                $url = self::TEST_URL;
            } else {
                $url = sprintf(self::LIVE_URL, $action);
            } 
        }

        return $url;
    }



}
