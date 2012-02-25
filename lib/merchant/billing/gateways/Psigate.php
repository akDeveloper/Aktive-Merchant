<?php
/**
 * Merchant driver for {link http://www.psigate.com/ PSiGate}.
 *
 * @package Aktive-Merchant
 * @author  Scott Gifford
 * @license http://www.opensource.org/licenses/mit-license.php
 * @see http://www.psigate.com/
 */

class Merchant_Billing_Psigate extends Merchant_Billing_Gateway implements Merchant_Billing_Gateway_Charge, Merchant_Billing_Gateway_Credit {

    const LIVE_URL = "https://secure.psigate.com:7934/Messenger/XMLMessenger";
    const TEST_URL = "https://dev.psigate.com:7989/Messenger/XMLMessenger";

    const SUCCESS_MESSAGE = "Success";
    const UNKNOWN_ERROR_MESSAGE = "The transaction was declined";
    const AUTHSTR_VERSION = 1;

    const TEST_MODE_ALWAYS_AUTH = 'A';
    const TEST_MODE_ALWAYS_DECLINE = 'D';
    const TEST_MODE_RANDOM = 'R';
    const TEST_MODE_FRAUD = 'F';

    public static $supported_countries = array('CA');
    public static $supported_cardtypes = array('visa', 'master', 'american_express');
    public static $homepage_url = 'http://www.psigate.com/';
    public static $display_name = 'Psigate';

    private $options = array();
    private $test_mode = 0;

    public function __construct($options)
    {
        parent::__construct($options);
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
     *   <li>Merchant_Billing_Psigate::TEST_MODE_ALWAYS_AUTH - Authorize every request (default)
     *   <li>Merchant_Billing_Psigate::TEST_MODE_ALWAYS_DECLINE - Decline every request
     *   <li>Merchant_Billing_Psigate::TEST_MODE_RANDOM - Randomly authorize or decline requests
     *   <li>Merchant_Billing_Psigate::TEST_MODE_FRAUD - Treat every request as a fraud alert
     * </ul>
     * 
     * @param string $mode New value for test mode (unset or NULL to keep current value)
     * @return string Current value for test mode, or previous value if $mode is set
     */
    public function test_mode($mode = NULL)
    {
        $last_mode = $this->test_mode;
        if ($mode !== NULL) $this->test_mode = $mode;
        return $last_mode;
    }

    public function authorize($money, Merchant_Billing_CreditCard $creditcard, $options = array())
    {
        // Ruby code required order_id, but PSiGate doesn't require this, so we don't either
        $options['CardAction'] = 1;
        return $this->commit($money, $creditcard, $options);
    }

    public function purchase($money, Merchant_Billing_CreditCard $creditcard, $options = array())
    {
        // Ruby code required order_id, but PSiGate doesn't require this, so we don't either
        $options['CardAction'] = 0;
        return $this->commit($money, $creditcard, $options);
    }

    public function capture($money, $authorization, $options = array())
    {
        $options['CardAction'] = 2;
        $authdata = $this->unpack_authorization_string($authorization);
        $options['order_id'] = $authdata['order_id'];
        return $this->commit($money, NULL, $options);
    }

    public function credit($money, $authorization, $options = array())
    {
        $options['CardAction'] = 3;
        $authdata = $this->unpack_authorization_string($authorization);
        $options['order_id'] = $authdata['order_id'];
        return $this->commit($money, NULL, $options);
    }

    public function void($authorization, $options = array()) {
        $options['CardAction'] = 9;
        $authdata = $this->unpack_authorization_string($authorization);
        $options['transaction_id'] = $authdata['transaction_id'];
        $options['order_id'] = $authdata['order_id'];
        return $this->commit(NULL, NULL, $options);
    }

    /**
     * Create a string with required transaction data.
     * 
     * PSiGate always requires the order ID to complete or modify past transactions,
     * and sometimes requires the transaction ID.  This function packs them both into
     * one string, which can be used for any of these purposes.
     * 
     * @param unknown_type $orderid
     * @param unknown_type $transactionid
     */
    private function pack_authorization_values($orderid, $transactionid) {
        return join("&", array(self::AUTHSTR_VERSION,urlencode($orderid), urlencode($transactionid)));
    }

    private function unpack_authorization_string($authstr) {
        $split = split("&",$authstr);
        if ($split === FALSE) throw new Merchant_Billing_Exception("Invalid authorization string");
        if ($split[0] != self::AUTHSTR_VERSION) throw new Merchant_Billing_Exception("Invalid authorization string version");
        if (count($split) != 3) throw new Merchant_Billing_Exception("Error parsing authorization string");

        return array(
            'order_id'       => urldecode($split[1]),
            'transaction_id' => urldecode($split[2]),
        );
    }

    private function commit($money, Merchant_Billing_CreditCard $creditcard = NULL, $options = array()) {
        $url = $this->is_test() ? self::TEST_URL : self::LIVE_URL;

        // Log request, but mask real user information
        if ($creditcard === NULL) {
            $log_card = NULL;
        } else {
            $log_card = clone $creditcard;
            $log_card->number = $this->mask_cardnum($log_card->number);
            if ($log_card->verification_value) $log_card->verification_value = $this->mask_cvv($log_card->verification_value);
        }
        Merchant_Logger::log("Sending POST to $url:\n" . $this->post_data($money, $log_card, $options));

        // Make the request
        $data = $this->ssl_post($url, $this->post_data($money, $creditcard, $options));
        $response = $this->parse($data);

        // Make sure the response is valid and doesn't contain an error
        if (empty($response['approved'])) throw new Merchant_Billing_Exception("Error parsing merchant response: No status information");
        if ($response['approved'] == 'ERROR') throw new Merchant_Billing_Exception("Merchant error: " . (isset($response['errmsg']) ? $response['errmsg'] : 'Unknown error'));
        if ($response['approved'] != 'APPROVED' && $response['approved'] != 'DECLINED') throw new Merchant_Billing_Exception("Merchant error: Unknown status '$response[approved]'");

        return new Merchant_Billing_Response(
            $this->success_from($response),
            $this->message_from($response),
            $response,
            array(
                'test' => $this->is_test(),
                'authorization' => (isset($response['orderid']) && isset($response['transrefnumber'])) 
                ? $this->pack_authorization_values($response['orderid'],$response['transrefnumber']) 
                : NULL,
                'avs_result' => isset($response['avsresult'])
                ? array('code' => $response['avsresult'])
                : NULL,
                'cvv_result' => isset($response['cardidresult'])
                ? $response['cardidresult']
                : NULL,
            ));
    }

    private function message_from($response) {
        if ($this->success_from($response)) {
            return self::SUCCESS_MESSAGE;
        } else {
            if (isset($response['errmsg'])) {
                return $response['errmsg'];
            } else {
                return self::UNKNOWN_ERROR_MESSAGE;
            }
        }
    }

    private function success_from($response) {
        return $response['approved'] == "APPROVED";
    }

    private function parse($response_xml) {
        $response = array(
            'errmsg' => 'Unknown Error',
            'complete' => false,
        );

        try {
            // This will throw an exception in case of a severe error
            $xml = simplexml_load_string($response_xml);
            $results = $xml->xpath('//Result/*');
            if ($results === FALSE) {
                throw new Merchant_Billing_Exception("Xpath parsing failed");
            }
            foreach ($results as $elt) {
                $response[strtolower($elt->getName())] = (string) $elt;
            }
        } catch (Exception $ex) {
            throw new Merchant_Billing_Exception("Error parsing XML response from merchant", 0, $ex);
        }

        return $response;
    }

    protected function post_data($money, $creditcard, $options) {
        $params = $this->parameters($money, $creditcard, $options);
        $xml = new SimpleXMLElement("<Order />");
        foreach ($params as $k => $v) {
            if ($v !== NULL) $xml->addChild($k, $v);
        }
        return $xml->asXML();
    }

    private function parameters($money, Merchant_Billing_CreditCard $creditcard = NULL, $options = array()) {
        $params = array(
            'StoreID' => $this->options['login'],
            'Passphrase' => $this->options['password'],
            'TestResult' => isset($options['test_result']) ? $options['test_result'] : NULL,
            'OrderID' => isset($options['order_id']) ? $options['order_id'] : NULL,
            'UserID' => isset($options['user_id']) ? $options['user_id'] : NULL,
            'Phone' => isset($options['phone']) ? $options['phone'] : NULL,
            'Fax' => isset($options['fax']) ? $options['fax'] : NULL,
            'Email' => isset($options['email']) ? $options['email'] : NULL,

            'PaymentType' => 'CC',
            'CardAction' => isset($options['CardAction']) ? $options['CardAction'] : NULL,

            'CustomerIP' => isset($options['ip']) ? $options['ip'] : NULL,
            'SubTotal' => isset($money) ? $this->amount($money) : NULL,
            'Tax1' => isset($options['tax1']) ? $options['tax1'] : NULL,
            'Tax2' => isset($options['tax2']) ? $options['tax2'] : NULL,
            'ShippingTotal' => isset($options['shipping_total']) ? $options['shipping_total'] : NULL,
        );

        if (isset($creditcard)) {
            $params['CardNumber'] = $creditcard->number;
            if (isset($creditcard->month)) $params['CardExpMonth'] = sprintf("%02d", $creditcard->month);
            if (isset($creditcard->year))  $params['CardExpYear'] = substr($creditcard->year,-2);
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
            foreach($addrfields as $p => $o) {
                if (isset($options['billing_address'][$o])) $params['B'.$p] = $options['billing_address'][$o];
            }
        }
        if (isset($options['shipping_address'])) {
            foreach($addrfields as $o => $p) {
                if (isset($options['shipping_address'][$o])) $params['S'.$p] = $options['shipping_address'][$o];
            }
        }

        if (isset($options['transaction_id'])) {
            $params['TransRefNumber'] = $options['transaction_id'];
        }

        if ($this->is_test()) {
            if (empty($this->test_mode)) $this->test_mode = self::TEST_MODE_ALWAYS_AUTH;
            switch($this->test_mode) {
            case    self::TEST_MODE_ALWAYS_AUTH: $params['TestResult'] = 'A'; break;
            case self::TEST_MODE_ALWAYS_DECLINE: $params['TestResult'] = 'D'; break;
            case          self::TEST_MODE_FRAUD: $params['TestResult'] = 'F'; break;
            case         self::TEST_MODE_RANDOM: $params['TestResult'] = 'R'; break;
            default: throw new Merchant_Billing_Exception("Invalid test mode");
            }
        }

        return $params;    
    }
}

