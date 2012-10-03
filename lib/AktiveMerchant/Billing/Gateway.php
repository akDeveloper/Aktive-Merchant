<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing;

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Http\Request;
use AktiveMerchant\Billing\Exception;
use AktiveMerchant\Common\CurrencyCode;
use AktiveMerchant\Http\RequestInterface;

/**
 * Gateway abstract class
 * 
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
abstract class Gateway
{

    /**
     * Money format supported by this gateway.
     * Can be 'dollars' or 'cents'
     *
     * @var string Money format 'dollars' | 'cents'
     */
    public static $money_format = 'dollars';

    /**
     * The currency supported by the gateway as ISO 4217 currency code.
     * the format 
     *
     * @var string The ISO 4217 currency code
     */   
    public static $default_currency;

    /**
     * The countries supported by the gateway as 2 digit ISO country codes.
     *
     * @var array
     */   
    public static $supported_countries = array();

    /**
     * The card types supported by the payment gateway
     *
     * @var array
     */   
    public static $supported_cardtypes = array(
        'visa', 
        'master', 
        'american_express', 
        'switch', 
        'solo', 
        'maestro'
    );

    /**
     * The homepage URL of the gateway
     *
     * @var string
     */   
    public static $homepage_url;

    /**
     * The display name of the gateway
     *
     * @var string
     */   
    public static $display_name;
    
    private $debit_cards = array('switch', 'solo');
    
    protected $request;

    public function setRequest(RequestInterface $request)
    {
        $this->request = $request;
    }

    public function money_format()
    {
        $class = get_class($this);
        $ref = new \ReflectionClass($class);
        return $ref->getStaticPropertyValue('money_format');
    }

    public function supported_countries()
    {
        $class = get_class($this);
        $ref = new \ReflectionClass($class);
        return $ref->getStaticPropertyValue('supported_countries');
    }

    public function supported_cardtypes()
    {
        $class = get_class($this);
        $ref = new \ReflectionClass($class);
        return $ref->getStaticPropertyValue('supported_cardtypes');
    }

    public function homepage_url()
    {
        $class = get_class($this);
        $ref = new \ReflectionClass($class);
        return $ref->getStaticPropertyValue('homepage_url');
    }

    public function factory_name()
    {
        $class = str_replace('ActiveMerchant\\Billing\\Gateways\\', '', get_class($this));
        return $this->underscore($class);
    }

    public function display_name()
    {
        $class = get_class($this);
        $ref = new \ReflectionClass($class);
        return $ref->getStaticPropertyValue('display_name');
    }

    public function supports($card_type)
    {
        return in_array($card_type, $this->supported_cardtypes());
    }

    public function isTest()
    {
        return Base::$gateway_mode == 'test';
    }

    /**
     * @throws AktiveMerchant\Billing\Exception
     *
     * @access public 
     * @return integer|float
     */
    public function amount($money)
    {
        if (null === $money)
            return null;

        $cents = $money * 100;
        if (!is_numeric($money) || $money < 0) {
            throw new Exception('money amount must be a positive Integer in cents.');
        }
        
        return ($this->money_format() == 'cents') 
            ? number_format($cents, 0, '', '') 
            : number_format($money, 2);
    }

    protected function card_brand($source)
    {
        $result = isset($source->brand) ? $source->brand : $source->type;
        return strtolower($result);
    }

    public function requires_start_date_or_issue_number(CreditCard $creditcard)
    {
        $card_band = $this->card_brand($creditcard);
        if (empty($card_band))
            return false;
        return in_array($this->card_brand($creditcard), $this->debit_cards);
    }

    /**
     * Send an HTTPS GET request to a remote server, and return the response.
     * 
     * @param string $endpoint URL of remote endpoint to connect to
     * @param string $data Body to include with the request 
     * @param array $options Additional options for the request (see {@link Merchant_Connection::request()})
	 * @return string Response from server
     * @throws Merchant_Billing_Exception If the request fails at the HTTP layer
     */
    protected function ssl_get($endpoint, $data, $options = array())
    {
        return $this->ssl_request('GET', $endpoint, $data, $options);
    }

    /**
     * Send an HTTPS POST request to a remote server, and return the response.
     * 
     * @param string $endpoint URL of remote endpoint to connect to
     * @param string $data Body to include with the request 
     * @param array $options Additional options for the request (see {@link Merchant_Connection::request()})
	 * @return string Response from server
     * @throws Merchant_Billing_Exception If the request fails at the HTTP layer
     */
    protected function ssl_post($endpoint, $data, $options = array())
    {
        return $this->ssl_request('POST', $endpoint, $data, $options);
    }

    /**
     * Send a request to a remote server, and return the response.
     * 
     * @throws AktiveMerchant\Billing\Exception If the request fails at the HTTP layer
     *
     * @param string $method Method to use ('post' or 'get')
     * @param string $endpoint URL of remote endpoint to connect to
     * @param string $data Body to include with the request 
     * @param array $options Additional options for the request (see {@link Merchant_Connection::request()})
     *
	 * @return string Response from server
     */
    private function ssl_request($method, $endpoint, $data, $options = array())
    { 
        $request = $this->request ?: new Request(
            $endpoint, 
            $method, 
            $options
        );
        $request->setBody($data);
        if (true == $request->send()) {

            return $request->getResponseBody();
        }
    }

    
    // Utils

    public function generateUniqueId()
    {
        return substr(uniqid(rand(), true), 0, 10);
    }

    public function generate_unique_id()
    {
        trigger_error('generate_unique_id method is deprecated. Use generateUniqueId');
        return $this->generateUniqueId();
    }

    // PostData

    /**
     * Convert an associative array to url parameters
     *
     * @params array key/value hash of parameters
     * @return string
     */
    protected function urlize($params)
    {
        $string = "";
        foreach ($params as $key => $value) {
            $string .= $key . '=' . urlencode(trim($value)) . '&';
        }
        return rtrim($string, "& ");
    }

    /**
     * RequiresParameters
     *
     * @throws AktiveMerchant\Billing\Exception If a required parameter is missing
     *
     * @param string comma seperated parameters. Represent keys of $options array
     * @param array the key/value hash of options to compare with
     */
    protected function required_options($required, $options = array())
    {
        $required = explode(',', $required);
        foreach ($required as $r) {
            if (!array_key_exists(trim($r), $options)) {
                throw new Exception($r . " parameter is required!");
                break;
                return false;
            }
        }
        return true;
    }

    /**
     * CreditCardFormatting
     */
    protected function cc_format($number, $options)
    {
        if (empty($number))
            return '';

        switch ($options) {
            case 'two_digits':
                $number = sprintf("%02d", $number);
                return substr($number, -2);
                break;
            case 'four_digits':
                return sprintf("%04d", $number);
                break;
            default:
                return $number;
                break;
        }
    }
    
    /**
     * Numeric Currency Codes
     *
     * Return numeric represantation of currency codes
     */
    protected function currency_lookup($code)
    {
        $currency = new CurrencyCode();

        if (isset($currency[$code])) {
            return $currency[$code];
        }

        return false;
    }

    private function underscore($string)
    {
        return strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $string));
    }
            

}
