<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Common\Options;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Http\RequestInterface;
use AktiveMerchant\Billing\Interfaces as Interfaces;

/**
 * Integration of Everypay payment gateway.
 *
 * @author Andreas Kollaros <andreas@larium.net>
 * @license  MIT License http://www.opensource.org/licenses/mit-license.php
 */
class Everypay extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit
{
    const TEST_URL = 'https://sandbox-api.everypay.gr';
    const LIVE_URL = 'https://api.everypay.gr';

    const PURCHASE = '/payments';
    const AUTHORIZE = '/payments';
    const CAPTURE = '/payments/capture/%s';
    const CREDIT = '/payments/refund/%s';
    const VOID = '/payments/refund/%s';
    const STORE = '/customers';

    /**
     * {@inheritdoc}
     */
    public static $money_format = 'cents';

    /**
     * {@inheritdoc}
     */
    public static $supported_countries = array('GR');

    /**
     * {@inheritdoc}
     */
    public static $supported_cardtypes = array(
        'visa',
        'master',
        'maestro',
        'diners_club',
        'american_express',
    );

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'http://www.example.net';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'EveryPay';

    /**
     * {@inheritdoc}
     */
    public static $default_currency = 'EUR';

    /**
     * Additional options needed by gateway
     *
     * @var AktiveMerchant\Common\Options
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
        $options = new Options($options);
        $this->post = array('capture' => '0');
        $this->post['amount'] = $this->amount($money);

        $this->addCreditCard($creditcard);
        $this->addCustomerData($options);

        return $this->commit(self::AUTHORIZE);
    }

    /**
     * {@inheritdoc}
     */
    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $options = new Options($options);
        $this->post = array();
        $this->post['amount'] = $this->amount($money);

        $this->addCreditcard($creditcard);
        $this->addCustomerData($options);

        return $this->commit(self::PURCHASE);
    }

    /**
     * {@inheritdoc}
     */
    public function capture($money, $authorization, $options = array())
    {
        $options = new Options($options);
        $this->post = array('authorization_id' => $authorization);
        $this->addCustomerData($options);

        $path = sprintf(self::CAPTURE, $authorization);

        return $this->commit($path, RequestInterface::METHOD_PUT);
    }

    /**
     * {@inheritdoc}
     */
    public function void($authorization, $options = array())
    {
        $path = sprintf(self::VOID, $authorization);

        return $this->commit($path, RequestInterface::METHOD_PUT);
    }

    /**
     * {@inheritdoc}
     */
    public function credit($money, $identification, $options = array())
    {
        $options = new Options($options);
        $this->post = array('amount' => $this->amount($money));

        $path = sprintf(self::CREDIT, $identification);

        return $this->commit($path, RequestInterface::METHOD_PUT);
    }

    /**
     * Customer data like e-mail, ip, web browser used for transaction etc
     *
     * @param array $options
     */
    private function addCustomerData($options)
    {
        $this->post['description'] = $options->description;
        $this->post['payee_email'] = $options->email;
        $this->post['payee_phone'] = $options->phone;
    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     */
    private function addCreditcard(CreditCard $creditcard)
    {
        $this->post['card_number'] = $creditcard->number;
        $this->post['holder_name'] = $creditcard->name();
        $this->post['expiration_year'] = $creditcard->year;
        $this->post['expiration_month'] = $this->cc_format($creditcard->month, 'two_digits');
        $this->post['cvv'] = $creditcard->verification_value;
    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     *
     * @return array|stdClass The parsed response data.
     */
    private function parse($body)
    {
        $body = json_decode($body, true);

        return new Options($body);
    }

    /**
     *
     * @param string $action
     * @param number $money
     * @param array  $parameters
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
        $options = array('connect_timeout' => 60);

        $postData = $this->postData();
        print_r($postData);
        $data = $this->ssl_request(
            $method,
            $url,
            $postData,
            $options
        );
        $response = $this->parse($data);

        $test_mode = $this->isTest();

        return new Response(
            $this->successFrom($response),
            $this->messageFrom($response),
            $response->getArrayCopy(),
            array(
                'test' => $test_mode,
                'authorization' => $response->token ?: null,
                'fraud_review' => $this->fraudReviewFrom($response),
                'avs_result' => $this->avsResultFrom($response),
                'cvv_result' => '',
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
        return isset($response['token']);
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
        return $response->error
            ? $response->error->message
            : $response->status;
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
        return array('code' => 'U');
    }

    /**
     * Adds final parameters to post data and
     * build $this->post to the format that your payment gateway understands
     *
     * @return array
     */
    private function postData()
    {
        return array_filter($this->post, 'strlen');
    }
}
