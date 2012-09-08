<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing;

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Http\Request;
use AktiveMerchant\Billing\Exception;

/**
 * AktiveMerchant\Billing\Gateway
 * 
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
abstract class Gateway
{

    public static $money_format = 'dollars'; // or cents
    
    public static $default_currency;
    
    public static $supported_countries = array();
    
    public static $supported_cardtypes = array(
        'visa', 
        'master', 
        'american_express', 
        'switch', 
        'solo', 
        'maestro'
    );
    
    public static $homepage_url;
    
    public static $display_name;
    
    private $debit_cards = array('switch', 'solo');
    
    protected $gateway_mode;
    
    protected $request;

    public function setRequest($request)
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
        $class = str_replace('Merchant_Billing_', '', get_class($this));
        return $this->underscore($class);
    }

    private function underscore($string)
    {
        return strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $string));
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
     * @throws Merchant_Billing_Exception If the request fails at the HTTP layer
     *
     * @param string $method Method to use ('post' or 'get')
     * @param string $endpoint URL of remote endpoint to connect to
     * @param string $data Body to include with the request 
     * @param array $options Additional options for the request (see {@link Merchant_Connection::request()})
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
     
    public function generate_unique_id()
    {
        return substr(uniqid(rand(), true), 0, 10);
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
                throw new \AktiveMerchant\Billing\Exception($r . " parameter is required!");
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
     * Mask a credit card number.
     * 
     * Makes the card safe for logging and storing, by replacing all but the
     * first 2 and last 4 digits with x's.
     * 
     * @param string $cardnum Card number to mask
     * @return string Masked card number
     */
    protected function mask_cardnum($cardnum) {
      return substr($cardnum,0,2) . preg_replace('/./','x',substr($cardnum,2,-4)) . substr($cardnum,-4,4);
    }
    
    /**
     * Mask a card verification value;
     * 
     * Makes a card verification value safe for logging and storing, by replacing all
     * characters with x's.
     *
     * @param string $cardverifier Card verification value to mask
     * @return string Masked card verification value
     */
    protected function mask_cvv($cvv) {
      return preg_replace('/./','x',$cvv);
    }
    
    /**
     * Numeric Currency Codes
     *
     * Return numeric represantation of currency codes
     */
    protected function currency_lookup($code)
    {
        if (!array_key_exists($code, $this->CURRENCY_CODES))
            return;
        return $this->CURRENCY_CODES[$code];
    }
            
    private $CURRENCY_CODES = array(
        "XPT" => "962",
        "SAR" => "682",
        "RUB" => "643",
        "NIO" => "558",
        "LAK" => "418",
        "NOK" => "578",
        "USD" => "840",
        "XCD" => "951",
        "OMR" => "512",
        "AMD" => "051",
        "CDF" => "976",
        "KPW" => "408",
        "CNY" => "156",
        "KES" => "404",
        "PLN" => "985",
        "KHR" => "116",
        "MVR" => "462",
        "GTQ" => "320",
        "CLP" => "152",
        "INR" => "356",
        "BZD" => "084",
        "MYR" => "458",
        "GWP" => "624",
        "HKD" => "344",
        "SEK" => "752",
        "COP" => "170",
        "DKK" => "208",
        "BYR" => "974",
        "LYD" => "434",
        "UYI" => "940",
        "RON" => "946",
        "DZD" => "012",
        "BIF" => "108",
        "ARS" => "032",
        "GIP" => "292",
        "BOB" => "068",
        "USN" => "997",
        "AED" => "784",
        "STD" => "678",
        "PGK" => "598",
        "NGN" => "566",
        "XOF" => "952",
        "ERN" => "232",
        "MWK" => "454",
        "CUP" => "192",
        "GMD" => "270",
        "ZWL" => "932",
        "TZS" => "834",
        "CVE" => "132",
        "COU" => "970",
        "BTN" => "064",
        "UGX" => "800",
        "SYP" => "760",
        "MNT" => "496",
        "MAD" => "504",
        "LSL" => "426",
        "XAF" => "950",
        "XTS" => "963",
        "XAG" => "961",
        "TOP" => "776",
        "RSD" => "941",
        "SHP" => "654",
        "HTG" => "332",
        "MGA" => "969",
        "USS" => "998",
        "MZN" => "943",
        "LVL" => "428",
        "FKP" => "238",
        "CHE" => "947",
        "BWP" => "072",
        "HNL" => "340",
        "EUR" => "978",
        "PYG" => "600",
        "EGP" => "818",
        "CHF" => "756",
        "ILS" => "376",
        "LBP" => "422",
        "ANG" => "532",
        "KZT" => "398",
        "WST" => "882",
        "GYD" => "328",
        "THB" => "764",
        "NPR" => "524",
        "KMF" => "174",
        "IRR" => "364",
        "XPD" => "964",
        "XBA" => "955",
        "UYU" => "858",
        "SRD" => "968",
        "JPY" => "392",
        "BRL" => "986",
        "XBB" => "956",
        "SZL" => "748",
        "MOP" => "446",
        "BMD" => "060",
        "XBC" => "957",
        "ETB" => "230",
        "JOD" => "400",
        "IDR" => "360",
        "EEK" => "233",
        "MDL" => "498",
        "XPF" => "953",
        "MRO" => "478",
        "XBD" => "958",
        "YER" => "886",
        "PEN" => "604",
        "BAM" => "977",
        "AWG" => "533",
        "NZD" => "554",
        "VEF" => "937",
        "TRY" => "949",
        "SLL" => "694",
        "KYD" => "136",
        "AOA" => "973",
        "TND" => "788",
        "TJS" => "972",
        "LKR" => "144",
        "SGD" => "702",
        "SCR" => "690",
        "MXN" => "484",
        "LTL" => "440",
        "HUF" => "348",
        "DJF" => "262",
        "BSD" => "044",
        "GNF" => "324",
        "ISK" => "352",
        "VUV" => "548",
        "SDG" => "938",
        "GEL" => "981",
        "FJD" => "242",
        "DOP" => "214",
        "XDR" => "960",
        "PHP" => "608",
        "MUR" => "480",
        "MMK" => "104",
        "KRW" => "410",
        "LRD" => "430",
        "BBD" => "052",
        "XAU" => "959",
        "ZMK" => "894",
        "VND" => "704",
        "UAH" => "980",
        "TMT" => "934",
        "IQD" => "368",
        "BGN" => "975",
        "GBP" => "826",
        "KGS" => "417",
        "ZAR" => "710",
        "TTD" => "780",
        "HRK" => "191",
        "BOV" => "984",
        "RWF" => "646",
        "CLF" => "990",
        "BHD" => "048",
        "UZS" => "860",
        "TWD" => "901",
        "PKR" => "586",
        "CRC" => "188",
        "AUD" => "036",
        "MKD" => "807",
        "AFN" => "971",
        "NAD" => "516",
        "BDT" => "050",
        "AZN" => "944",
        "CZK" => "203",
        "XXX" => "999",
        "CHW" => "948",
        "SOS" => "706",
        "QAR" => "634",
        "PAB" => "590",
        "CUC" => "931",
        "MXV" => "979",
        "SBD" => "090",
        "SVC" => "222",
        "ALL" => "008",
        "BND" => "096",
        "JMD" => "388",
        "CAD" => "124",
        "KWD" => "414",
        "GHS" => "936"
    );

}
?>
