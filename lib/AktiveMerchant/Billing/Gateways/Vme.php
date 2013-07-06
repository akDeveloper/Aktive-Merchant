<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Options;

/**
 * Description of Vme gateway
 *
 * @category Gateways
 * @package  Aktive-Merchant
 * @author   Andreas Kollaros andreaskollaros@ymail.com>
 * @license  MIT License http://www.opensource.org/licenses/mit-license.php
 * @link     https://github.com/akDeveloper/Aktive-Merchant
 */
class Vme extends Gateway
{
    const TEST_URL = 'https://sandbox-wapi.v.me/wallet/%s';
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
        'discover',
    );

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'http://v.me';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'Visa Me Gateway';

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
        Options::required('apikey', $options);

        if (isset($options['currency'])) {
            self::$default_currency = $options['currency'];
        }

        $this->options = new Options($options);
    }

    public function getCheckoutDetails($callid)
    { 
        $this->post['callid'] = $callid;

        return $this->commit('getcheckoutdetail', null);
    }
    
    /**
     * {@inheritdoc}
     */
    public function authorize($money, $callid, $options=array())
    {

        $this->post = array();

        $this->post['currency'] = static::$default_currency;
        $this->post['amount'] = $this->amount($money);
        $this->post['callid'] = $callid;

        return $this->commit('authorize', $money, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function purchase($money, $callid, $options=array())
    {
        Options::required('total', $options);
        $this->post = array();

        $this->post['currency'] = static::$default_currency;
        $this->post['amount'] = $this->amount($money);
        $this->post['total'] = $this->amount($options['total']);
        $this->post['callid'] = $callid;

        return $this->commit('confirmpurchase', $money, $options);
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

    private function get_hash($action)
    {
        $zone = new \DateTimeZone('UTC');
        $date = new \DateTime();
        $date->setTimezone($zone);
        $time = $date->getTimestamp();
        
        $body = $this->urlize($this->post);
        $sha = $this->options->secret . $time . "wallet/{$action}" ."apikey=".$this->options->apikey . $body;
        $hash = hash('sha256', $sha);

        return "x:" . $time . ":$hash";
    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    private function parse($body)
    {
        $data = json_decode($body);

        $response = array();
        
        $response['success'] = $data->status == '200';
        $response['message'] = '';
        $response['authorization_id'] = '';
        $response['card_code'] = '';

        $response['message'] = isset($data->detail) && isset($data->detail[0]->annotation)
            ? $data->detail[0]->annotation
            : '';

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
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $url = sprintf($url, $action);
        $url = $url . "?".$this->urlize(array("apikey"=>$this->options->apikey));

        $post = $this->post_data($action, $parameters);
        $options = array(
            'headers' => array(
                'Accept: application/json',
                "x-pay-token: " . $this->get_hash($action)
            )
        );
        
        $data = $this->ssl_post($url, $post, $options);

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
        return $response['success'];
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
        return $response['message'];
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
        return array('code' => '');
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
        $this->post['adminid'] = $this->generateUniqueId();
        
        $this->post = array_merge($this->post, $parameters);

        return $this->urlize($this->post);
    }

}
