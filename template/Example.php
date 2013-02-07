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
class Example extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit,
    Interfaces\Store
{
    const TEST_URL = 'https://example.com/test';
    const LIVE_URL = 'https://example.com/live';

    /**
     * {@inheritdoc}
     */
    public static $money_format = 'dollars';

    /**
     * {@inheritdoc}
     */
    public static $supported_countries = array();

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public static $homepage_url = 'http://www.example.net';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'New Gateway';

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
     * @param array $options an array contains login parameters of merchant
     *                       and optional currency.
     *
     * @return Gateway The gateway instance.
     */
    public function __construct($options = array())
    {
        $this->required_options('login, password', $options);

        if (isset($options['currency']))
            self::$default_currency = $options['currency'];

        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function authorize($money, CreditCard $creditcard, $options=array())
    {
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
        $this->post = array('authorization_id' => $authorization);
        $this->add_customer_data($options);

        return $this->commit('capture', $money);
    }

    /**
     * {@inheritdoc}
     */
    public function void($authorization, $options = array())
    {
        $this->post = array('authorization' => $authorization);
        return $this->commit('void', null);
    }

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
        $this->post = array('authorization' => $identification);

        $this->add_invoice($options);
        return $this->commit('credit', $money);
    }

    // Private methods

    /**
     * Customer data like e-mail, ip, web browser used for transaction etc
     *
     * @param array $options
     */
    private function add_customer_data($options)
    {

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

    }

    /**
     * Adds invoice info if exists.
     *
     * @param array $options
     */
    private function add_invoice($options)
    {

    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     */
    private function add_creditcard(CreditCard $creditcard)
    {

    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    private function parse($body)
    {

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

        $data = $this->ssl_post($url, $this->post_data($action, $parameters));

        $response = $this->parse($data);

        $test_mode = $this->isTest();

        return new Response(
            $this->success_from($response),
            $this->message_from($response),
            $response,
            array(
                'test' => $test_mode,
                'authorization' => $response['authorization_id'],
                'fraud_review' => $this->fraud_review_from($response),
                'avs_result' => $this->avs_result_from($response),
                'cvv_result' => $response['card_code']
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
        return $response['success_code_from_gateway'];
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
        return $response['message_from_gateway'];
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
     * @param  string $action
     * @param  array  $parameters
     *
     * @return void
     */
    private function post_data($action, $parameters = array())
    {

    }

}
