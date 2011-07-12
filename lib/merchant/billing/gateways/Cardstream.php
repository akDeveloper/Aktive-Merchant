<?php

/**
 * Description of Merchant_Billing_ Cardstream
 *
 * @package Aktive Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Merchant_Billing_Cardstream extends Merchant_Billing_Gateway
{
    const TEST_URL = 'https://gateway.cardstream.com/process.ashx';
    const LIVE_URL = 'https://gateway.cardstream.com/process.ashx';

    # The countries the gateway supports merchants from as 2 digit ISO country codes

    public static $supported_countries = array('GB');

    # The card types supported by the payment gateway
    public static $supported_cardtypes = array('visa', 'master', 'american_express', 'diners_club', 'discover', 'jcb', 'maestro', 'solo', 'switch');

    # The homepage URL of the gateway
    public static $homepage_url = 'http://www.cardstream.com';

    # The display name of the gateway
    public static $display_name = 'CardStream';
    public static $default_currency = 'GBP';
    public static $money_format = 'cents';
    private $options;
    private $post;

    const APPROVED = '00';

    private $TRANSACTIONS = array(
        'purchase' => 'ESALE_KEYED',
        'credit' => 'EREFUND_KEYED',
        'authorization' => 'ESALE_KEYED',
        'capture' => 'ESALE_KEYED'
    );
    private $CVV_CODE = array(
        '0' => 'U',
        '1' => 'P',
        '2' => 'M',
        '4' => 'N'
    );

    # 0 - No additional information available.
    # 1 - Postcode not checked.
    # 2 - Postcode matched.
    # 4 - Postcode not matched.
    # 8 - Postcode partially matched.
    private $AVS_POSTAL_MATCH = array(
        "0" => null,
        "1" => null,
        "2" => "Y",
        "4" => "N",
        "8" => "N"
    );

    # 0 - No additional information available.
    # 1 - Address numeric not checked.
    # 2 - Address numeric matched.
    # 4 - Address numeric not matched.
    # 8 - Address numeric partially matched.
    private $AVS_STREET_MATCH = array(
        "0" => null,
        "1" => null,
        "2" => "Y",
        "4" => "N",
        "8" => "N"
    );

    /**
     * $options array includes login parameters of merchant and optional currency.
     *
     * @param array $options
     */
    public function __construct($options = array())
    {
        $this->required_options('login, password', $options);

        if (isset($options['currency']))
            self::$default_currency = $options['currency'];

        $this->options = $options;
    }

    /**
     *
     * @param number $money
     * @param Merchant_Billing_CreditCard $creditcard
     * @param array $options
     * @return Merchant_Billing_Response
     */
    public function authorize($money, Merchant_Billing_CreditCard $creditcard, $options=array())
    {
        $this->required_options('order_id', $options);

        $this->post['Amount'] = $this->amount($money);
        $this->add_invoice($money, $creditcard, $options);
        $this->add_creditcard($creditcard);
        $this->add_address($options);
        $this->add_customer_data($options);

        return $this->commit('authorization', $money);
    }

    /**
     *
     * @param number $money
     * @param Merchant_Billing_CreditCard $creditcard
     * @param array $options
     * @return Merchant_Billing_Response
     */
    public function purchase($money, Merchant_Billing_CreditCard $creditcard, $options=array())
    {
        $this->required_options('order_id', $options);

        $this->post['Amount'] = $this->amount($money);
        $this->add_invoice($money, $creditcard, $options);
        $this->add_creditcard($creditcard);
        $this->add_address($options);
        $this->add_customer_data($options);

        return $this->commit('purchase', $options);
    }

    /**
     *
     * @param number $money
     * @param string $authorization (unique value received from authorize action)
     * @param array $options
     * @return Merchant_Billing_Response
     */
    public function capture($money, $authorization, $options = array())
    {
        $this->post['Amount'] = $this->amount($money);
        $this->post = array('CrossReference' => $authorization);
        $this->add_customer_data($options);

        return $this->commit('capture', $money);
    }

    /**
     *
     * @param number $money
     * @param string $identification
     * @param array $options
     * @return Merchant_Billing_Response
     */
    public function credit($money, $identification, $options = array())
    {
        $this->post['Amount'] = $this->amount($money);
        $this->post = array('CrossReference' => $identification);
        return $this->commit('credit', $money);
    }

    /* Private */

    /**
     * Customer data like e-mail, ip, web browser used for transaction etc
     *
     * @param array $options
     */
    private function add_customer_data($options)
    {
        $this->post['BillingEmail'] = isset($options['email']) ? $options['email'] : null;
        $this->post['BillingPhoneNumber'] = isset($options['phone']) ? $options['phone'] : null;
    }

    /**
     *
     * Options key can be 'shipping address' and 'billing_address' or 'address'
     * Each of these keys must have an address array like:
     * $address['name']
     * $address['company']
     * $address['address1']
     * $address['address2']
     * $address['city']
     * $address['state']
     * $address['country']
     * $address['zip']
     * $address['phone']
     * common pattern for addres is
     * $billing_address = isset($options['billing_address']) ? $options['billing_address'] : $options['address']
     * $shipping_address = $options['shipping_address']
     *
     * @param array $options
     */
    private function add_address($options)
    {
        if (!isset($options['address']) || !isset($options['billing_address']))
            return false;

        $address = isset($options['billing_address']) ? $options['billing_address'] : $options['address'];

        $this->post['BillingStreet'] = isset($address['address1']) ? $address['address1'] : null;
        $this->post['BillingHouseNumber'] = isset($address['address2']) ? $address['address2'] : null;
        $this->post['BillingCity'] = isset($address['city']) ? $address['city'] : null;
        $this->post['BillingState'] = isset($address['state']) ? $address['state'] : null;
        $this->post['BillingPostCode'] = isset($address['zip']) ? $address['zip'] : null;
    }

    /**
     * @param number $money
     * @param Merchant_Billing_CreditCard $creditcard
     * @param array $options
     */
    private function add_invoice($money, Merchant_Billing_CreditCard $creditcard, $options)
    {
        $this->post['TransactionUnique'] = $options['order_id'];
        $this->post['OrderDesc'] = isset($options['descreption']) ? $options['descreption'] : $options['order_id'];

        if (in_array($this->card_brand($creditcard), array('american_express', 'diners_club'))) {
            $this->post['AEIT1Quantity'] = 1;
            $this->post['AEIT1Description'] = isset($options['descreption']) ? $options['descreption'] : $options['order_id'];
        } $this->post['AEIT1GrossValue'] = $this->amount($money);
    }

    /**
     *
     * @param Merchant_Billing_CreditCard $creditcard
     */
    private function add_creditcard(Merchant_Billing_CreditCard $creditcard)
    {
        $this->post['CardName'] = $creditcard->name();
        $this->post['CardNumber'] = $creditcard->number;
        $this->post['ExpiryDateMM'] = $this->cc_format($creditcard->month, 'two_digits');
        $this->post['ExpiryDateYY'] = $this->cc_format($creditcard->year, 'two_digits');

        if ($this->requires_start_date_or_issue_number($creditcard)) {
            $this->post['StartDateMM'] = $this->cc_format($creditcard->start_month, "two_digits");
            $this->post['StartDateYY'] = $this->cc_format($creditcard->start_year, "two_digits");
            $this->post['IssueNumber'] = $creditcard->issue_number;
            $this->post['CV2'] = $creditcard->verification_value;
        }
    }

    /**
     * Parse the raw data response from gateway
     *
     * Please note that cross reference transactions must come from a static IP
     * addressed that has been preregistered with Cardstream.
     * To register an IP address, please send details to solutions@cardstream.com
     * with the relevant Cardstream issued MerchantID and it will be added to yourS
     * account accordingly.
     *
     * @param string $body
     *
     * @return array
     */
    private function parse($body)
    {
        parse_str($body, $response_array);
        $response = array();
        foreach ($response_array as $k => $v) {
            $key = str_replace('VP', '', $k);
            $response[$key] = $v;
        }

        return $response;
    }

    /**
     *
     * @param string $action
     * @param number $money
     * @param array $parameters
     *
     * @return Merchant_Billing_Response
     */
    private function commit($action, $parameters)
    {
        $url = $this->is_test() ? self::TEST_URL : self::LIVE_URL;

        $data = $this->ssl_post($url, $this->post_data($action, $parameters));
        $response = $this->parse($data);

        $test_mode = $this->is_test();

        return new Merchant_Billing_Response($this->success_from($response), $this->message_from($response), $response, array(
            'test' => $test_mode,
            'authorization' => $response['CrossReference'],
            'avs_result' => $this->avs_result_from($response),
            'cvv_result' => $this->cvv_result_from($response)
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
        return $response['ResponseCode'] == self::APPROVED;
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
        return $response['Message'];
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
        if (!isset($response['AVSCV2ResponseCode']))
            return false;
        return array(
            'street_match' => $this->AVS_POSTAL_MATCH[$response['AVSCV2ResponseCode'][1]],
            'postal_match' => $this->AVS_STREET_MATCH[$response['AVSCV2ResponseCode'][2]]
        );
    }

    private function cvv_result_from($response)
    {
        if (!isset($response['AVSCV2ResponseCode']))
            return false;
        return $this->CVV_CODE[$response['AVSCV2ResponseCode'][0]];
    }

    /**
     *
     * Add final parameters to post data and
     * build $this->post to the format that your payment gateway understands
     *
     * @param string $action
     * @param array $parameters
     *
     * @return string
     */
    private function post_data($action, $parameters = array())
    {
        $this->post['MerchantPassword'] = $this->options['password'];
        $this->post['MerchantID'] = $this->options['login'];
        $this->post['MessageType'] = $this->TRANSACTIONS[$action];
        $this->post['CallBack'] = "disable";
        $this->post['DuplicateDelay'] = "0";
        $this->post['EchoCardType'] = "YES";
        $this->post['EchoAmount'] = "YES";
        if ($action == 'purchase' || $action == 'authorization') {
            $this->post['EchoAVSCV2ResponseCode'] = "YES";
            $this->post['ReturnAVSCV2Message'] = "YES";
        }
        $this->post['CurrencyCode'] = $this->currency_lookup(self::$default_currency);
        $this->post['CountryCode'] = $this->currency_lookup(self::$default_currency);
        $this->post['Dispatch'] = $action == 'authorization' ? 'LATER' : 'NOW';

        #Add VP prefix to all keys
        $request = '';
        foreach ($this->post as $k => $v) {
            $request .= 'VP' . $k . '=' . urlencode($v) . '&';
        }
        return rtrim($request, '& ');
    }

}

?>
