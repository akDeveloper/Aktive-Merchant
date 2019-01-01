<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing;

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Http\Request;
use AktiveMerchant\Billing\Exception;
use AktiveMerchant\Common\CurrencyCode;
use AktiveMerchant\Http\RequestInterface;
use AktiveMerchant\Http\AdapterInterface;
use AktiveMerchant\Http\Adapter\cUrl;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Common\Inflect;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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

    /**
     * Request instance.
     *
     * @var RequestInterface
     */
    protected $request;

    /**
     * Adapter to use for request.
     *
     * @var AdapterInterface
     */
    protected $adapter;

    protected $options;

    private $debit_cards = array('switch', 'solo');

    private $dispatcher;

    public function __construct($options = array())
    {
        $options = new Options($options);

        static::$default_currency = $options['currency']
            ?: static::$default_currency;

        $this->options = $options;
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

        return Inflect::underscore($class);
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

    /**
     * Checks if gateway is in test mode.
     *
     * @return boolean
     */
    public function isTest()
    {
        return Base::$gateway_mode == 'test';
    }

    /**
     * Accepts the amount of money in base unit and returns cents or base unit
     * amount according to the @see $money_format propery.
     *
     * @param $money The amount of money in base unit, not in cents.
     *
     * @throws \InvalidArgumentException
     *
     * @return integer|float
     */
    public function amount($money)
    {
        if (null === $money) {
            return null;
        }

        $cents = $money * 100;
        if (!is_numeric($money) || $money < 0) {
            throw new \InvalidArgumentException('money amount must be a positive number.');
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

        if (empty($card_band)) {
            return false;
        }

        return in_array($this->card_brand($creditcard), $this->debit_cards);
    }

    /**
     * Sets the request instance.
     * Usefull for testing purposes.
     *
     * @param RequestInterface $request
     *
     * @return void
     */
    public function setRequest(RequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * Gets the adapter to execute the request.
     * Defaulr is cUrl.
     *
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        $this->adapter = $this->adapter ?: new cUrl();

        return $this->adapter;
    }

    /**
     * Sets a custom adapter to perform the request.
     * Adapter must implements AdapterInterface.
     *
     * @param  AdapterInterface $adapter
     *
     * @return void
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Send an HTTPS GET request to a remote server, and return the response.
     *
     * @param string $endpoint URL of remote endpoint to connect to
     * @param string $data Body to include with the request
     * @param array $options Additional options for the request (see {@link AktiveMerchant\Http\Request})
     *
     * @throws AktiveMerchant\Billing\Exception If the request fails at the HTTP layer
     *
     * @return string Response from server
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
     * @param array $options Additional options for the request (see {@link AktiveMerchant\Http\Request})
     *
     * @throws AktiveMerchant\Billing\Exception If the request fails at the HTTP layer
     *
     * @return string Response from server
     */
    protected function ssl_post($endpoint, $data, $options = array())
    {
        return $this->ssl_request('POST', $endpoint, $data, $options);
    }

    /**
     * Send a request to a remote server, and return the response.
     *
     * @param string $method Method to use ('post' or 'get')
     * @param string $endpoint URL of remote endpoint to connect to
     * @param string $data Body to include with the request
     * @param array $options Additional options for the request (see {@link AktiveMerchant\Http\Request})
     *
     * @throws AktiveMerchant\Billing\Exception If the request fails at the HTTP layer
     *
     * @return string Response from server
     */
    protected function ssl_request($method, $endpoint, $data, array $options = array())
    {
        $request = $this->request ?: new Request(
            $endpoint,
            $method,
            $options
        );

        $request->setMethod($method);
        $request->setUrl($endpoint);
        $request->setBody($data);
        $request->setDispatcher($this->getDispatcher());

        $request->setAdapter($this->getAdapter());

        if (true == $request->send()) {
            return $request->getResponseBody();
        }
    }


    /* -(  Utils  ) -------------------------------------------------------- */

    /**
     * Returns a unique identifier.
     *
     * @since Method available since Release 1.0.0
     *
     * @return string
     */
    public function generateUniqueId()
    {
        return substr(uniqid(rand(), true), 0, 10);
    }

    /**
     * Returns a unique identifier.
     *
     * @deprecated Method deprecated in Release 1.0.0
     *
     * @return string
     */
    public function generate_unique_id()
    {
        trigger_error('generate_unique_id method is deprecated. Use generateUniqueId');

        return $this->generateUniqueId();
    }

    // PostData

    /**
     * Convert an associative array to url parameters
     *
     * @param array key/value hash of parameters
     *
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
     * required_options
     *
     * @param string comma seperated parameters. Represent keys of $options array
     * @param array  the key/value hash of options to compare with
     *
     * @return boolean
     */
    protected function required_options($required, $options = array())
    {
        return Options::required($required, $options);
    }

    /**
     * Formats values from a credit card.
     *
     * Used to format mont or year values to 2 or 4 digit numbers.
     *
     * @param integer $number  The number to format
     * @param string  $options 'two_digits' or 'four_digits'
     *
     * @return string
     */
    protected function cc_format($number, $options)
    {
        if (empty($number)) {
            return '';
        }

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
     * Lookup for numeric currency codes and returns numeric represantation
     * of ISO 4217 currency code.
     *
     * @param string $code
     *
     * @return string|false
     */
    protected function currency_lookup($code)
    {
        $currency = new CurrencyCode();

        if (isset($currency[$code])) {
            return $currency[$code];
        }

        return false;
    }

    /**
     * Add a listener to gateway event.
     *
     * @param string $eventName
     * @param string $listener
     * @param int $priority
     *
     * @return void
     */
    public function addListener($eventName, $listener, $priority = 0)
    {
        $this->getDispatcher()->addListener($eventName, $listener, $priority);
    }

    /**
     * Gets dispatcher.
     *
     * @since Method available since Release 1.1.0
     *
     * @return EventDispatcherInterface
     */
    public function getDispatcher()
    {
        return $this->dispatcher ?: $this->dispatcher = new EventDispatcher();
    }

    /**
     * Sets dispatcher.
     *
     * @param EventDispatcherInterface $dispatcher
     *
     * @since Method available since Release 1.1.0
     *
     * @return void
     */
    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }
}
