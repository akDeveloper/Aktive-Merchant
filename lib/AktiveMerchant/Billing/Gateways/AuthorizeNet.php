<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Exception;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Options;

/**
 * Integration of  {link http://authorize.net/ Authorize.net}.
 *
 * @author Andreas Kollaros <andreas@larium.net>
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class AuthorizeNet extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit,
    Interfaces\Recurring
{
    const API_VERSION = "3.1";

    const LIVE_URL = "https://secure.authorize.net/gateway/transact.dll";
    const LIVE_ARB_URL = 'https://api.authorize.net/xml/v1/request.api';

    const TEST_URL = "https://test.authorize.net/gateway/transact.dll";
    const TEST_ARB_URL = 'https://apitest.authorize.net/xml/v1/request.api';

    const APPROVED = 1;
    const DECLINED = 2;
    const ERROR = 3;
    const FRAUD_REVIEW = 4;

    const RESPONSE_CODE = 0;
    const RESPONSE_REASON_CODE = 2;
    const RESPONSE_REASON_TEXT = 3;
    const AVS_RESULT_CODE = 5;
    const TRANSACTION_ID = 6;
    const CARD_CODE_RESPONSE_CODE = 38;

    const RESPONSE_REASON_CARD_INVALID = 6;
    const RESPONSE_REASON_CARD_EXPIRATION_INVALID = 7;
    const RESPONSE_REASON_CARD_EXPIRED = 8;
    const RESPONSE_REASON_ABA_INVALID = 9;
    const RESPONSE_REASON_ACCOUNT_INVALID = 10;
    const RESPONSE_REASON_DUPLICATE = 11;
    const RESPONSE_REASON_AUTHCODE_REQUIRED = 12;

    public static $supported_countries = array('AD', 'AT', 'AU', 'BE', 'BG', 'CA', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GB', 'GI', 'GR', 'HU', 'IE', 'IS', 'IT', 'LI', 'LT', 'LU', 'LV', 'MC', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'SM', 'TR', 'US', 'VA');

    public static $supported_cardtypes = array('visa', 'master', 'american_express', 'discover');

    public static $homepage_url = 'http://www.authorize.net/';

    public static $display_name = 'Authorize.Net';

    public $duplicate_window;

    private $post = array();

    private $xml;

    protected $options = array();

    private $CARD_CODE_ERRORS = array('N', 'S');

    private $AVS_ERRORS = array('A', 'E', 'N', 'R', 'W', 'Z');

    private $AUTHORIZE_NET_ARB_NAMESPACE = 'AnetApi/xml/v1/schema/AnetApiSchema.xsd';

    private $RECURRING_ACTIONS = array(
        'create' => 'ARBCreateSubscriptionRequest',
        'update' => 'ARBUpdateSubscriptionRequest',
        'cancel' => 'ARBCancelSubscriptionRequest'
    );

    public function __construct($options)
    {
        Options::required('login, password', $options);

        $this->options = new Options($options);
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
        $this->post = array();

        $options = new Options($options);

        $this->addInvoice($options);
        $this->addCreditcard($creditcard);
        $this->addAddress($options);
        $this->addCustomerData($options);
        $this->addDuplicateWindow();

        return $this->commit('AUTH_ONLY', $money);
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
        $this->post = array();

        $options = new Options($options);

        $this->addInvoice($options);
        $this->addCreditcard($creditcard);
        $this->addAddress($options);
        $this->addCustomerData($options);
        $this->addDuplicateWindow();

        return $this->commit('AUTH_CAPTURE', $money);
    }

    /**
     *
     * @param number $money
     * @param string $authorization
     * @param array  $options
     *
     * @return Response
     */
    public function capture($money, $authorization, $options = array())
    {
        $options = new Options($options);

        $this->post = array('trans_id' => $authorization);
        $this->addCustomerData($options);
        return $this->commit('PRIOR_AUTH_CAPTURE', $money);
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
        $this->post = array('trans_id' => $authorization);
        return $this->commit('VOID', null);
    }

    /**
     *
     * @param number $money
     * @param string $identification
     * @param array  $options
     *
     * @return Response
     */
    public function credit($money, $identification, $options = array())
    {
        Options::required('card_number', $options);
        $this->post = array(
            'trans_id' => $identification,
            'card_num' => $options['card_number']
        );


        $this->addInvoice($options);
        return $this->commit('CREDIT', $money);
    }

    /**
     * {@inheritdoc}
     * Optional $options are:
     *  - 'occurrences' Number of billing occurrences or payments for the
     *                  subscription. Default is 9999 for a no end date
     *                  (an ongoing subscription).
     */
    public function recurring($money, CreditCard $creditcard, $options = array())
    {
        $options = new Options($options);

        Options::required(
            'frequency, period, start_date, billing_address',
            $options
        );

        Options::required(
            'first_name, last_name',
            $options['billing_address']
        );

        if (null == $options->occurrences) {
            $options->occurrences = '9999';
        }

        if (null == $options->trial_occurrences) {
            $options->trial_occurrences = 0;
        }

        $amount = $this->amount($money);

        $ref_id = $options['order_id'];

        $this->xml = "<refId>$ref_id</refId>";
        $this->xml .= "<subscription>";
        $this->arbAddSubscription($amount, $options);
        $this->arbAddCreditcard($creditcard);
        $this->arbAddAddress($options['billing_address']);
        $this->xml .= "</subscription>";

        return $this->recurringCommit('create');
    }

    /**
     *
     * @param string     $subscription_id subscription id returned from
     *                                    recurring method
     * @param CreditCard $creditcard
     *
     * @return Response
     */
    public function updateRecurring($subscription_id, CreditCard $creditcard)
    {
        $this->xml = <<<XML
            <subscriptionId>$subscription_id</subscriptionId>
              <subscription>
XML;
        $this->arbAddCreditcard($creditcard);
        $this->xml .= "</subscription>";

        return $this->recurringCommit('update');
    }

    /**
     *
     * @param string $subscription_id subscription id return from recurring
     *                                method
     *
     * @return Response
     */
    public function cancelRecurring($subscription_id)
    {
        $this->xml = "<subscriptionId>$subscription_id</subscriptionId>";

        return $this->recurringCommit('cancel');
    }

    /* Private */

    /**
     *
     * @param string $action
     * @param number $money
     * @param array  $parameters
     *
     * @return Response
     */
    private function commit($action, $money, $parameters = array())
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        if ($action != 'VOID') {
            $parameters['amount'] = $this->amount($money);
        }

        /* Request a test response */
        if ($this->isTest()) {
            #$parameters['test_request'] = 'TRUE';
        }

        $data = $this->ssl_post(
            $url,
            $this->postData($action, $parameters, $this->post)
        );

        $response = $this->parse($data);

        // Check the response code, and throw an exception if necessary
        if (empty($response['response_code'])) {
            throw new Exception("Error parsing merchant response: No status information");
        }

        switch ($response['response_code']) {
            case self::ERROR:
                switch ($response['response_reason_code']) {
                    case self::RESPONSE_REASON_CARD_INVALID:
                    case self::RESPONSE_REASON_CARD_EXPIRATION_INVALID:
                    case self::RESPONSE_REASON_CARD_EXPIRED:
                    case self::RESPONSE_REASON_ABA_INVALID:
                    case self::RESPONSE_REASON_ACCOUNT_INVALID:
                    case self::RESPONSE_REASON_DUPLICATE:
                    case self::RESPONSE_REASON_AUTHCODE_REQUIRED:
                        // These should be treated like a decline
                        break;
                    default:
                        throw new Exception("Merchant error: $response[response_reason_text] (code $response[response_code]/$response[response_reason_code])");
                }
                break;
            case self::APPROVED:
            case self::DECLINED:
            case self::FRAUD_REVIEW:
                // These are OK
                break;
            default:
                throw new Exception("Merchant error: Unknown status '$response[response_code]'");
        }

        $message = $this->messageFrom($response);

        $test_mode = $this->isTest();

        return new Response(
            $this->successFrom($response),
            $message,
            $response,
            array(
                'test' => $test_mode,
                'authorization' => $response['transaction_id'],
                'fraud_review' => $this->fraudReviewFrom($response),
                'avs_result' => array('code' => $response['avs_result_code']),
                'cvv_result' => $response['card_code']
            )
        );
    }

    /**
     *
     * @param string $response
     *
     * @return bool
     */
    private function successFrom($response)
    {
        return $response['response_code'] == self::APPROVED;
    }

    /**
     *
     * @param string $response
     *
     * @return bool
     */
    private function fraudReviewFrom($response)
    {
        return $response['response_code'] == self::FRAUD_REVIEW;
    }

    /**
     *
     * @param string $response
     *
     * @return string
     */
    private function messageFrom($response)
    {
        if ($response['response_code'] == self::DECLINED) {
            if (in_array($response['card_code'], $this->CARD_CODE_ERRORS)) {
                $cvv_messages = \AktiveMerchant\Billing\CvvResult::messages();
                return $cvv_messages[$response['card_code']];
            }
            if (in_array($response['avs_result_code'], $this->AVS_ERRORS)) {
                $avs_messages = \AktiveMerchant\Billing\AvsResult::messages();
                return $avs_messages[$response['avs_result_code']];
            }
        }

        return $response['response_reason_text'] === null
            ? ''
            : $response['response_reason_text'];
    }

    /**
     * Parse raw gateway body response.
     *
     * @param string $body raw gateway response
     *
     * @return array gateway response in array format.
     */
    private function parse($body)
    {
        if (empty($body)) {
            throw new Exception('Error parsing credit card response: Empty response');
        }

        $fields = explode('|', $body);

        if (count($fields) < 39) {
            throw new Exception('Error parsing credit card response: Too few fields');
        }

        $response = array(
            'response_code' => $fields[self::RESPONSE_CODE],
            'response_reason_code' => $fields[self::RESPONSE_REASON_CODE],
            'response_reason_text' => $fields[self::RESPONSE_REASON_TEXT],
            'avs_result_code' => $fields[self::AVS_RESULT_CODE],
            'transaction_id' => $fields[self::TRANSACTION_ID],
            'card_code' => $fields[self::CARD_CODE_RESPONSE_CODE]
        );

        return $response;
    }

    private function postData($action, $parameters = array(), $post = null)
    {
        if ($post === null) {
            $post = $this->post;
        }
        $post['version'] = self::API_VERSION;
        $post['login'] = $this->options['login'];
        $post['tran_key'] = $this->options['password'];
        $post['relay_response'] = 'FALSE';
        $post['type'] = $action;
        $post['delim_data'] = 'TRUE';
        $post['delim_char'] = '|';

        $post = array_merge($post, $parameters);
        $request = "";

        #Add x_ prefix to all keys
        foreach ($post as $k => $v) {
            $request .= 'x_' . $k . '=' . urlencode($v) . '&';
        }
        return rtrim($request, '& ');
    }

    private function addInvoice($options)
    {
        $this->post['invoice_num'] = $options['order_id'];
        $this->post['description'] = $options['description'];
    }

    private function addCreditcard(CreditCard $creditcard)
    {
        $this->post['method'] = 'CC';
        $this->post['card_num'] = $creditcard->number;
        if ($creditcard->require_verification_value) {
            $this->post['card_code'] = $creditcard->verification_value;
        }
        $this->post['exp_date'] = $this->expdate($creditcard);
        $this->post['first_name'] = $creditcard->first_name;
        $this->post['last_name'] = $creditcard->last_name;
    }

    private function expdate(CreditCard $creditcard)
    {
        $year = $this->cc_format($creditcard->year, 'two_digits');
        $month = $this->cc_format($creditcard->month, 'two_digits');
        return $month . $year;
    }

    private function addAddress($options)
    {
        $address = isset($options['billing_address'])
            ? $options['billing_address']
            : $options['address'];

        $this->post['address']  = $address['address1'];
        $this->post['company']  = $address['company'];
        $this->post['phone']    = $address['phone'];
        $this->post['zip']      = $address['zip'];
        $this->post['city']     = $address['city'];
        $this->post['country']  = $address['country'];
        $this->post['state']    = $address['state'];
    }

    private function addCustomerData($options)
    {
        $this->post['email'] = $options['email'];
        $this->post['email_customer'] = false;
        $this->post['cust_id'] = $options['customer'];
        $this->post['customer_ip'] = $options['ip'];
    }

    private function addDuplicateWindow()
    {
        if ($this->duplicate_window != null) {
            $this->post['duplicate_window'] = $this->duplicate_window;
        }
    }

    /* ARB */

    private function recurringCommit($action, $parameters = array())
    {
        $url = $this->isTest() ? self::TEST_ARB_URL : self::LIVE_ARB_URL;

        $headers = array("Content-Type: text/xml");

        $data = $this->ssl_post(
            $url,
            $this->arbPostData($action),
            array('headers' => $headers)
        );

        $response = $this->arbParse($data);

        $message = $this->arbMessageFrom($response);

        $test_mode = $this->isTest();

        return new Response(
            $this->arbSuccessFrom($response),
            $message,
            $response,
            array(
                'test' => $test_mode,
                'authorization' => $response['subscription_id'],
            )
        );
    }

    private function arbParse($body)
    {

        $response = array();

        /*
         * SimpleXML returns some warnings about arb namespace, althought it parse
         * the xml correctly.
          $xml = simplexml_load_string($body);
          $response['ref_id'] = (string) $xml->refId;
          $response['result_code'] = (string) $xml->messages->resultCode;
          $response['code'] = (string) $xml->messages->message->code;
          $response['text'] = (string) $xml->messages->message->text;
          $response['subscription_id'] = (string) $xml->subscriptionId;
         */

        /*
         * Used parsing method from authorize.net example
         */
        $response['ref_id']          = $this->substringBetween($body, '<refId>', '</refId>');
        $response['result_code']     = $this->substringBetween($body, '<resultCode>', '</resultCode>');
        $response['code']            = $this->substringBetween($body, '<code>', '</code>');
        $response['text']            = $this->substringBetween($body, '<text>', '</text>');
        $response['subscription_id'] = $this->substringBetween($body, '<subscriptionId>', '</subscriptionId>');

        return $response;
    }

    private function arbMessageFrom($response)
    {
        return $response['text'];
    }

    private function arbSuccessFrom($response)
    {
        return $response['result_code'] == 'Ok';
    }

    private function arbAddCreditcard(CreditCard $creditcard)
    {
        $expiration_date = $this->cc_format($creditcard->year, 'four_digits') . "-" .
            $this->cc_format($creditcard->month, 'two_Digits');

        $this->xml .= <<< XML
        <payment>
          <creditCard>
            <cardNumber>{$creditcard->number}</cardNumber>
            <expirationDate>{$expiration_date}</expirationDate>
          </creditCard>
        </payment>
XML;
    }

    private function arbAddAddress($address)
    {
        $this->xml .= <<< XML
        <billTo>
          <firstName>{$address['first_name']}</firstName>
          <lastName>{$address['last_name']}</lastName>
        </billTo>
XML;
    }

    private function arbAddSubscription($amount, $options)
    {
        $this->xml .= <<< XML
      <name>Subscription of {$options['billing_address']['first_name']} {$options['billing_address']['last_name']}</name>
      <paymentSchedule>
        <interval>
          <length>{$options['frequency']}</length>
          <unit>{$options['period']}</unit>
        </interval>
        <startDate>{$options['start_date']}</startDate>
        <totalOccurrences>{$options['occurrences']}</totalOccurrences>
        <trialOccurrences>{$options['trial_occurrences']}</trialOccurrences>
      </paymentSchedule>
      <amount>$amount</amount>
      <trialAmount>0</trialAmount>
XML;
    }

    private function arbPostData($action)
    {
        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
      <{$this->RECURRING_ACTIONS[$action]} xmlns="{$this->AUTHORIZE_NET_ARB_NAMESPACE}">
        <merchantAuthentication>
          <name>{$this->options['login']}</name>
          <transactionKey>{$this->options['password']}</transactionKey>
        </merchantAuthentication>
          {$this->xml}
      </{$this->RECURRING_ACTIONS[$action]}>
XML;

        return $xml;
    }

    /*
     * ARB parsing xml
     */

    private function substringBetween($haystack, $start, $end)
    {
        if (strpos($haystack, $start) === false || strpos($haystack, $end) === false) {
            return false;
        } else {
            $start_position = strpos($haystack, $start) + strlen($start);
            $end_position = strpos($haystack, $end);
            return substr($haystack, $start_position, $end_position - $start_position);
        }
    }
}
