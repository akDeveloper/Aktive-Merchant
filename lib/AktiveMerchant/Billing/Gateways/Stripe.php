<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Http\RequestInterface;

/**
 * Integration of Stripe gateway
 *
 * @author   Andreas Kollaros <andreas@gmail.com>
 * @license  MIT License http://www.opensource.org/licenses/mit-license.php
 */
class Stripe extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit,
    Interfaces\Store
{
    const TEST_URL = 'https://api.stripe.com/v1';
    const LIVE_URL = 'https://api.stripe.com/v1';

    const CHARGE = '/charges';
    const CAPTURE = '/charges/%s/capture';
    const REFUND = '/charges/%s/refunds';
    const STORE = '/customers';
    const UNSTORE = '/customers/%s/sources/%s';

    /**
     * {@inheritdoc}
     */
    public static $money_format = 'cents';

    /**
     * {@inheritdoc}
     */
    public static $supported_countries = array('AT', 'AU', 'BE', 'CA', 'CH', 'DE', 'DK', 'ES', 'FI', 'FR', 'GB', 'IE', 'IT', 'LU', 'NL', 'NO', 'SE', 'US');

    /**
     * {@inheritdoc}
     */
    public static $supported_cardtypes = array(
        'visa',
        'master',
        'american_express',
        'discover',
        'jcb',
        'diners_club',
        'maestro',
    );

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'https://stripe.com';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'Stripe';

    /**
     * {@inheritdoc}
     */
    public static $default_currency = 'USD';

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
        $this->required_options('secret_key', $options);

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $this->createCharge($money, $creditcard, $options);

        $this->post['capture'] = 'false';

        return $this->commit(self::CHARGE);
    }

    /**
     * {@inheritdoc}
     */
    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $this->createCharge($money, $creditcard, $options);

        return $this->commit(self::CHARGE);
    }

    private function createCharge($money, CreditCard $creditcard, $options = array())
    {
        $this->post = array();
        $options = new Options($options);

        $this->addInvoice($money, $options);
        $this->addCreditcard($creditcard);
        if (null === $creditcard->token) {
            $this->addAddress($options);
        }
        $this->addCustomerData($options);
    }

    /**
     * {@inheritdoc}
     */
    public function capture($money, $authorization, $options = array())
    {
        $this->post = array('amount' => $this->amount($money / 100));

        $action = sprintf(self::CAPTURE, $authorization);

        return $this->commit($action);
    }

    /**
     * {@inheritdoc}
     */
    public function void($authorization, $options = array())
    {
        $this->post = array();
        $action = sprintf(self::REFUND, $authorization);

        return $this->commit($action);
    }

    /**
     * {@inheritdoc}
     */
    public function credit($money, $identification, $options = array())
    {
        $this->post = array('amount' => $this->amount($money / 100));

        $action = sprintf(self::REFUND, $identification);

        return $this->commit($action);
    }

    /**
     * {@inheritdoc}
     *
     * If you want to unstore a credit card you should also keep card token from
     * response `$response->sources->data[0]->id`
     * Then pass it as option in unstore method below.
     */
    public function store(CreditCard $creditcard, $options = array())
    {
        $this->post = array();
        $options = new Options($options);
        $this->addCreditcard($creditcard);
        $this->post['source']['object'] = 'card';
        $this->addAddress($options);
        $this->post['email'] = $options['email'];

        return $this->commit(self::STORE);
    }

    /**
     * {@inheritdoc}
     *
     * Options require the card token retrieved from store method.
     * $options = array('card_token' => 'card_xxxxxxxxx');
     */
    public function unstore($reference, $options = array())
    {
        Options::required('card_token', $options);

        $action = sprintf(self::UNSTORE, $reference, $options['card_token']);

        return $this->commit($action, RequestInterface::METHOD_DELETE);
    }

    /**
     * Customer data like e-mail, ip, web browser used for transaction etc
     *
     * @param array $options
     */
    private function addCustomerData($options)
    {
        $this->post['receipt_email'] = $options['email'];
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
     * @param array $options
     *
     * @return void
     */
    private function addAddress($options)
    {
        $address = $options['billing_address']
            ?: $options['address'];

        $this->post['source']['address_line1'] = $address['address1'];
        $this->post['source']['address_line2'] = $address['address2'];
        $this->post['source']['address_city'] = $address['city'];
        $this->post['source']['address_zip'] = $address['zip'];
        $this->post['source']['address_state'] = $address['state'];
        $this->post['source']['address_country'] = $address['country'];
    }

    /**
     * Adds invoice info if exist.
     *
     * @param array $options
     */
    private function addInvoice($money, $options)
    {
        $this->post['amount'] = $this->amount($money / 100);
        $this->post['currency'] = self::$default_currency;
        $this->post['description'] = $options['description'];
    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     */
    private function addCreditcard(CreditCard $creditcard)
    {
        if (null === $creditcard->token) {
            # No token available.
            $this->post['source']['number'] = $creditcard->number;
            $this->post['source']['exp_month'] = $creditcard->month;
            $this->post['source']['exp_year'] = $creditcard->year;
            $this->post['source']['cvc'] = $creditcard->verification_value;
            $this->post['source']['name'] = $creditcard->name();

            return;
        }

        if (strpos($creditcard->token, 'tok_') === 0) {
            return $this->post['source'] = $creditcard->token;
        }

        if (strpos($creditcard->token, 'cus_') === 0) {
            return $this->post['customer'] = $creditcard->token;
        }
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
        $body = json_decode($body, true);

        if (empty($body)) {
            $body = array();
        }
        return new Options($body);
    }

    /**
     *
     * @param string $action
     * @param string $method Http request method [GET, POST, PUT, DELETE].
     *
     * @return Response
     */
    private function commit($action, $method = RequestInterface::METHOD_POST)
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $url .= $action;

        $this->getAdapter()->setOption(
            CURLOPT_USERPWD,
            "{$this->options->secret_key}:"
        );

        $data = $this->ssl_request(
            $method,
            $url,
            $this->postData()
        );

        $response = $this->parse($data);

        $test_mode = $this->isTest();

        return new Response(
            $this->successFrom($response),
            $this->messageFrom($response),
            $response->getArrayCopy(),
            array(
                'test' => $test_mode,
                'authorization' => $response['id'],
                'fraud_review' => $this->fraudReviewFrom($response),
                'avs_result' => $this->avsResultFrom($response),
                'cvv_result' => null
            )
        );
    }

    /**
     * Returns success flag from gateway response
     *
     * @param Options $response
     *
     * @return string
     */
    private function successFrom(Options $response)
    {
        return !$response->offsetExists('error');
    }

    /**
     * Returns message (error explanation  or success) from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    private function messageFrom($response)
    {
        return $this->successFrom($response)
            ? $response['status']
            : $response['error']['message'];
    }

    /**
     * Returns fraud review from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    private function fraudReviewFrom($response)
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
    private function avsResultFrom($response)
    {
        return array('code' => null);
    }

    /**
     * Adds final parameters to post data and
     * build $this->post to the format that your payment gateway understands
     *
     * @return string
     */
    private function postData()
    {
        if (empty($this->post)) {
            return;
        }

        $post = array_filter($this->post);

        return http_build_query($post);
    }
}
