<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Exception;
use AktiveMerchant\Billing\Response;

/**
 * Integration of {link http://www.psigate.com/ PSiGate}.
 *
 * @author Scott Gifford
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Psigate extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit
{

    const LIVE_URL = "https://secure.psigate.com:17989/Messenger/XMLMessenger";
    const TEST_URL = "https://dev.psigate.com:17989/Messenger/XMLMessenger";

    const SUCCESS_MESSAGE = "Success";
    const UNKNOWN_ERROR_MESSAGE = "The transaction was declined";
    const AUTHSTR_VERSION = 1;

    const TEST_MODE_ALWAYS_AUTH = 'A';
    const TEST_MODE_ALWAYS_DECLINE = 'D';
    const TEST_MODE_RANDOM = 'R';
    const TEST_MODE_FRAUD = 'F';

    public static $supported_countries = array('CA');
    public static $supported_cardtypes = array('visa', 'master', 'american_express');
    public static $homepage_url = 'https://www.psigate.com/';
    public static $display_name = 'Psigate';

    protected $options = array();
    private $test_mode = 0;

    public function __construct($options)
    {
        $this->required_options('login, password', $options);
        $this->options = $options;
    }

    /**
     * Configure how the gateway will respond to test requests.
     *
     * The gateway can be configured to always allow requests (the default), to always fail,
     * etc.  You can call this function to set the mode.  You must also call {@see mode()} to
     * set the mode to 'test'.
     *
     * Values are:
     * <ul>
     *   <li>Psigate::TEST_MODE_ALWAYS_AUTH - Authorize every request (default)
     *   <li>Psigate::TEST_MODE_ALWAYS_DECLINE - Decline every request
     *   <li>Psigate::TEST_MODE_RANDOM - Randomly authorize or decline requests
     *   <li>Psigate::TEST_MODE_FRAUD - Treat every request as a fraud alert
     * </ul>
     *
     * @param string $mode New value for test mode (unset or NULL to keep current value)
     * @return string Current value for test mode, or previous value if $mode is set
     */
    public function test_mode($mode = null)
    {
        $last_mode = $this->test_mode;
        if ($mode !== null) {
            $this->test_mode = $mode;
        }

        return $last_mode;
    }

    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $options['CardAction'] = 1;
        return $this->commit($money, $creditcard, $options);
    }

    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $options['CardAction'] = 0;
        return $this->commit($money, $creditcard, $options);
    }

    public function capture($money, $authorization, $options = array())
    {
        $options['CardAction'] = 2;
        $authdata = $this->unpackAuthorizationString($authorization);
        $options['order_id'] = $authdata['order_id'];
        return $this->commit($money, null, $options);
    }

    public function credit($money, $authorization, $options = array())
    {
        $options['CardAction'] = 3;
        $authdata = $this->unpackAuthorizationString($authorization);
        $options['order_id'] = $authdata['order_id'];
        return $this->commit($money, null, $options);
    }

    public function void($authorization, $options = array())
    {
        $options['CardAction'] = 9;
        $authdata = $this->unpackAuthorizationString($authorization);
        $options['transaction_id'] = $authdata['transaction_id'];
        $options['order_id'] = $authdata['order_id'];
        return $this->commit(null, null, $options);
    }

    /**
     * Create a string with required transaction data.
     *
     * PSiGate always requires the order ID to complete or modify past transactions,
     * and sometimes requires the transaction ID.  This function packs them both into
     * one string, which can be used for any of these purposes.
     *
     * @param string $orderid
     * @param string $transactionid
     */
    private function packAuthorizationValues($orderid, $transactionid)
    {
        return implode("&", array(self::AUTHSTR_VERSION, urlencode($orderid), urlencode($transactionid)));
    }

    private function unpackAuthorizationString($authstr)
    {
        $split = explode("&", $authstr);

        if ($split === false) {
            throw new Exception("Invalid authorization string");
        }

        if ($split[0] != self::AUTHSTR_VERSION) {
            throw new Exception("Invalid authorization string version");
        }

        if (count($split) != 3) {
            throw new Exception("Error parsing authorization string");
        }

        return array(
            'order_id'       => urldecode($split[1]),
            'transaction_id' => urldecode($split[2]),
        );
    }

    private function commit($money, CreditCard $creditcard = null, $options = array())
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        // Log request, but mask real user information
        if ($creditcard === null) {
            $log_card = null;
        } else {
            $log_card = clone $creditcard;
            $log_card->number = $this->maskCardnum($log_card->number);

            if ($log_card->verification_value) {
                $log_card->verification_value = $this->maskCvv($log_card->verification_value);
            }
        }

        // Make the request
        $data = $this->ssl_post($url, $this->postData($money, $creditcard, $options));
        $response = $this->parse($data);

        // Make sure the response is valid and doesn't contain an error
        if (empty($response['approved'])) {
            throw new Exception("Error parsing merchant response: No status information");
        }

        if ($response['approved'] == 'ERROR') {
            throw new Exception("Merchant error: " . (isset($response['errmsg']) ? $response['errmsg'] : 'Unknown error'));
        }

        if ($response['approved'] != 'APPROVED'
            && $response['approved'] != 'DECLINED'
        ) {
            throw new Exception("Merchant error: Unknown status '$response[approved]'");
        }

        return new Response(
            $this->successFrom($response),
            $this->messageFrom($response),
            $response,
            array(
                'test' => $this->isTest(),
                'authorization' => (isset($response['orderid']) && isset($response['transrefnumber']))
                ? $this->packAuthorizationValues($response['orderid'], $response['transrefnumber'])
                : null,
                'avs_result' => isset($response['avsresult'])
                ? array('code' => $response['avsresult'])
                : null,
                'cvv_result' => isset($response['cardidresult'])
                ? $response['cardidresult']
                : null,
            )
        );
    }

    private function messageFrom($response)
    {
        if ($this->successFrom($response)) {
            return self::SUCCESS_MESSAGE;
        } else {
            if (isset($response['errmsg'])) {
                return $response['errmsg'];
            } else {
                return self::UNKNOWN_ERROR_MESSAGE;
            }
        }
    }

    private function successFrom($response)
    {
        return $response['approved'] == "APPROVED";
    }

    private function parse($response_xml)
    {
        $response = array(
            'errmsg' => 'Unknown Error',
            'complete' => false,
        );

        try {
            // This will throw an exception in case of a severe error
            $xml = simplexml_load_string($response_xml);
            $results = $xml->xpath('//Result/*');
            if ($results === false) {
                throw new Exception("Xpath parsing failed");
            }
            foreach ($results as $elt) {
                $response[strtolower($elt->getName())] = (string) $elt;
            }
        } catch (\Exception $ex) {
            throw new Exception("Error parsing XML response from merchant", 0, $ex);
        }

        return $response;
    }

    protected function postData($money, $creditcard, $options)
    {
        $params = $this->parameters($money, $creditcard, $options);
        $xml = new \SimpleXMLElement("<Order />");
        foreach ($params as $k => $v) {
            if ($v !== null) {
                $xml->addChild($k, $v);
            }
        }
        return $xml->asXML();
    }

    private function parameters($money, CreditCard $creditcard = null, $options = array())
    {
        $params = array(
            'StoreID' => $this->options['login'],
            'Passphrase' => $this->options['password'],
            'TestResult' => isset($options['test_result']) ? $options['test_result'] : null,
            'OrderID' => isset($options['order_id']) ? $options['order_id'] : null,
            'UserID' => isset($options['user_id']) ? $options['user_id'] : null,
            'Phone' => isset($options['phone']) ? $options['phone'] : null,
            'Fax' => isset($options['fax']) ? $options['fax'] : null,
            'Email' => isset($options['email']) ? $options['email'] : null,

            'PaymentType' => 'CC',
            'CardAction' => isset($options['CardAction']) ? $options['CardAction'] : null,

            'CustomerIP' => isset($options['ip']) ? $options['ip'] : null,
            'SubTotal' => isset($money) ? $this->amount($money) : null,
            'Tax1' => isset($options['tax1']) ? $options['tax1'] : null,
            'Tax2' => isset($options['tax2']) ? $options['tax2'] : null,
            'ShippingTotal' => isset($options['shipping_total']) ? $options['shipping_total'] : null,
        );

        if (isset($creditcard)) {
            $params['CardNumber'] = $creditcard->number;
            if (isset($creditcard->month)) {
                $params['CardExpMonth'] = sprintf("%02d", $creditcard->month);
            }
            if (isset($creditcard->year)) {
                $params['CardExpYear'] = substr($creditcard->year, -2);
            }
            if (isset($creditcard->verification_value)) {
                $params['CardIDNumber'] = $creditcard->verification_value;
                $params['CardIDCode'] = '1';
            }
        }

        // Copy address information into request
        // psigate_name => options_name
        $addrfields = array(
            'name' => 'name',
            'address1' => 'address1',
            'address2' => 'address2',
            'city' => 'city',
            'province' => 'state',
            'postalcode' => 'zip',
            'country' => 'country',
            'company' => 'company',
        );

        if (isset($options['billing_address'])) {
            foreach ($addrfields as $p => $o) {
                if (isset($options['billing_address'][$o])) {
                    $params['B'.$p] = $options['billing_address'][$o];
                }
            }
        }

        if (isset($options['shipping_address'])) {
            foreach ($addrfields as $o => $p) {
                if (isset($options['shipping_address'][$o])) {
                    $params['S'.$p] = $options['shipping_address'][$o];
                }
            }
        }

        if (isset($options['transaction_id'])) {
            $params['TransRefNumber'] = $options['transaction_id'];
        }

        if ($this->isTest()) {
            if (empty($this->test_mode)) {
                $this->test_mode = self::TEST_MODE_ALWAYS_AUTH;
            }

            switch ($this->test_mode) {
                case self::TEST_MODE_ALWAYS_AUTH:
                    $params['TestResult'] = 'A';
                    break;
                case self::TEST_MODE_ALWAYS_DECLINE:
                    $params['TestResult'] = 'D';
                    break;
                case self::TEST_MODE_FRAUD:
                    $params['TestResult'] = 'F';
                    break;
                case self::TEST_MODE_RANDOM:
                    $params['TestResult'] = 'R';
                    break;
                default:
                    throw new Exception("Invalid test mode");
            }
        }

        return $params;
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
    protected function maskCardnum($cardnum)
    {
        return substr($cardnum, 0, 2)
            . preg_replace('/./', 'x', substr($cardnum, 2, -4))
            . substr($cardnum, -4, 4);
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
    protected function maskCvv($cvv)
    {
        return preg_replace('/./', 'x', $cvv);
    }
}
