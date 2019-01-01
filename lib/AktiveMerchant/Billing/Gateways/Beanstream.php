<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Common\Options;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Interfaces as Interfaces;

/**
 * Integration of Example gateway
 *
 * @author   Your name <your@email.com>
 * @license  MIT License http://www.opensource.org/licenses/mit-license.php
 */
class Beanstream extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit
{
    const TEST_URL = 'https://www.beanstream.com/api/v1/';
    const LIVE_URL = 'https://www.beanstream.com/api/v1/';

    const PURCHASE = 'payments';
    const AUTHORIZE = 'payments';
    const CREDIT = 'payments/%s/returns';
    const VOID = 'payments/%s/void';
    const CAPTURE = 'payments/%s/completions';

    const APPROVED = "1";

    /**
     * {@inheritdoc}
     */
    public static $money_format = 'dollars';

    /**
     * {@inheritdoc}
     */
    public static $supported_countries = array('CA', 'US');

    /**
     * {@inheritdoc}
     */
    public static $supported_cardtypes = array(
        'visa',
        'master',
        'american_express',
        'discover',
        'diners_club',
        'jcb'
    );

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'http://www.beanstream.com';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'Beanstream';

    /**
     * {@inheritdoc}
     */
    public static $default_currency = 'CAD';

    /**
     * Additional options needed by gateway
     *
     * @var array
     */
    protected $options;

    /**
     * Contains the main body of the request.
     *
     * @var array
     */
    private $post;

    /**
     * Creates gateway instance from given options.
     *
     * @param array $options An array contains login parameters of merchant
     *                       and optional currency.
     */
    public function __construct($options = array())
    {
        Options::required('merchant_id, passcode', $options);

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $this->post = array(
            'payment_method' => 'card',
            'amount' => $this->amount($money),
        );
        $options = new Options($options);

        $this->addInvoice($options);
        $this->addCreditcard($creditcard, false);
        $this->addAddress($options);
        $this->addCustomerData($options);

        return $this->commit(self::AUTHORIZE);
    }

    /**
     * {@inheritdoc}
     */
    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $this->post = array(
            'payment_method' => 'card',
            'amount' => $this->amount($money),
        );
        $options = new Options($options);

        $this->addInvoice($options);
        $this->addCreditcard($creditcard);
        $this->addAddress($options);
        $this->addCustomerData($options);

        return $this->commit(self::PURCHASE);
    }

    /**
     * {@inheritdoc}
     */
    public function capture($money, $authorization, $options = array())
    {
        $this->post = array(
            'card' => array(
                'complete' => true,
            ),
            'amount' => $this->amount($money),
        );

        $action = sprintf(self::CAPTURE, $authorization);

        return $this->commit($action);
    }

    /**
     * {@inheritdoc}
     */
    public function void($authorization, $options = array())
    {
        $this->post = array(
            'card' => array(
                'complete' => true,
            ),
            'amount' => $this->amount(0),
        );

        $action = sprintf(self::CAPTURE, $authorization);

        return $this->commit($action);
    }

    /**
     * {@inheritdoc}
     */
    public function credit($money, $identification, $options = array())
    {
        $options = new Options($options);
        $this->post = array(
            'order_number' => $options['order_id'],
            'amount' => $this->amount($money),
        );

        $action = sprintf(self::CREDIT, $identification);

        return $this->commit($action);
    }

    /**
     * Customer data like e-mail, ip, web browser used for transaction etc
     *
     * @param array $options
     */
    private function addCustomerData($options)
    {
        $this->post['cutomer_ip'] = $options['ip'];
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
     * $billingAddress = $options['billing_address']
     *      ?: $options['address'];
     * $shippingAddress = $options['shipping_address'];
     * </code>
     *
     * @param AktiveMerchant\Common\Options $options
     *
     * @return void
     */
    private function addAddress(Options $options)
    {
        $billingAddress = $options['billing_address']
            ?: $options['address'];
        $shippingAddress  = $options['shipping_address'];

        if ($billingAddress) {
            $this->setAddress($billingAddress, 'billing', $options);
        }

        if ($shippingAddress) {
            $this->setAddress($shippingAddress, 'shipping', $options);
        }
    }

    private function setAddress($address, $tag, $options)
    {
        $post[$tag] = array(
            'name' => $address['name'],
            'address_line1' => $address['address1'],
            'address_line2' => $address['address2'],
            'city' => $address['city'],
            'province' => $address['state'],
            'country' => $address['country'],
            'postal_code' => $address['zip'],
            'phone_number' => $address['phone'],
            'email_address' => $options['email']
        );
    }

    /**
     * Adds invoice info if exist.
     *
     * @param array $options
     */
    private function addInvoice($options)
    {
        $this->post['comments'] = $options['description'];
        $this->post['order_number'] = $options['order_id'];
    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     */
    private function addCreditcard(CreditCard $creditcard, $complete = true)
    {
        $card = array(
            'number' => $creditcard->number,
            'name' => $creditcard->name(),
            'expiry_month' => $this->cc_format($creditcard->month, 'two_digits'),
            'expiry_year' => $this->cc_format($creditcard->year, 'two_digits'),
            'cvd' => $creditcard->verification_value,
            'complete' => $complete,
        );

        $this->post['card'] = $card;
    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     *
     * @return Options The parsed response data.
     */
    private function parse($body)
    {
        return new Options(json_decode($body, true) ?: []);
    }

    /**
     *
     * @param string $action
     * @param number $money
     * @param array  $parameters
     *
     * @return Response
     */
    private function commit($action)
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $url = $url . $action;

        $options = array(
            'headers' => array(
                'Authorization: Passcode ' . $this->getPasscode(),
                'Content-Type: application/json',
            )
        );

        $postData = $this->postData();

        $data = $this->ssl_post($url, $postData, $options);

        $response = $this->parse($data);

        return new Response(
            $this->successFrom($response),
            $this->messageFrom($response),
            $response->getArrayCopy(),
            array(
                'test' => $this->isTest(),
                'authorization' => $response['id'],
                'fraud_review' => null,
                'avs_result' => $this->avsResultFrom($response),
                'cvv_result' => $response['card']['cvd_match']
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
    private function successFrom($response)
    {
        return $response['approved'] == self::APPROVED;
    }

    /**
     * Returns message (error explanation or success) from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    private function messageFrom($response)
    {
        return $response['message'];
    }

    /**
     *
     * Returns avs result from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    private function avsResultFrom($response)
    {
        return array('code' => $response['card']['postal_result']);
    }

    /**
     * Adds final parameters to post data and
     * build $this->post to the format that your payment gateway understands
     *
     * @return array
     */
    private function postData()
    {
        $data = array_filter($this->post, function ($v) {
            if (is_array($v)) {
                return array_filter($v, 'strlen');
            }

            return strlen($v);
        });

        return json_encode($data);
    }

    private function getPasscode()
    {
        return base64_encode(
            $this->options['merchant_id']
            .':'
            .$this->options['passcode']
        );
    }
}
