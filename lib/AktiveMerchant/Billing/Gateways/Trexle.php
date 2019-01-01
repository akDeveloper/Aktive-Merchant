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
 * Integration of Trexle gateway.
 *
 * Basic usage:
 *
 *  use AktiveMerchant\Billing\CreditCard;
 *
 *  $options = ['secret_key' => <yoursecretkey>];
 *  $gateway = AktiveMerchant\Billing\Base::gateway('trexle', $options);
 *  // Setup customer token or card token or card data.
 *  $card = new CreditCard(['token' => 'token_xxxxxxx']); // Customer token
 *  $card = new CreditCard(['token' => 'token_xxxxxxx']); // Card token
 *  $card = new CreditCard([
 *        "first_name" => "John",
 *        "last_name" => "Milwood",
 *        "number" => "4242424242424242",
 *        "month" => "01",
 *        "year" => "17",
 *        "verification_value" => "123"
 *  ]); // Card data
 *
 *  // Complete purchase
 *  $response = $gateway->purchase(1000, $card);
 *
 *  // Get unique reference from transaction for future use.
 *  $authorization = $response->authorization();
 *
 * @author Hossam Hossny <hossamhossnyar@gmail.com>
 * @license MIT License http://www.opensource.org/licenses/mit-license.php
 */
class Trexle extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit,
    Interfaces\Store
{
    const TEST_URL = 'https://core.trexle.com/api/v1';
    const LIVE_URL = 'https://core.trexle.com/api/v1';

    const CHARGE = '/charges';
    const CAPTURE = '/charges/%s/capture';
    const REFUND = '/charges/%s/refunds';
    const STORE = '/customers';
    const UNSTORE = '/customers/%s/cards/%s';

    /**
     * {@inheritdoc}
     */
    public static $money_format = 'cents';

    /**
     * {@inheritdoc}
     */
    public static $supported_countries = array("AD",
 "AE",
 "AT",
 "AU",
 "BD",
 "BE",
 "BG",
 "BN",
 "CA",
 "CH",
 "CY",
 "CZ",
 "DE",
 "DK",
 "EE",
 "EG",
 "ES",
 "FI",
 "FR",
 "GB",
 "GI",
 "GR",
 "HK",
 "HU",
 "ID",
 "IE",
 "IL",
 "IM",
 "IN",
 "IS",
 "IT",
 "JO",
 "KW",
 "LB",
 "LI",
 "LK",
 "LT",
 "LU",
 "LV",
 "MC",
 "MT",
 "MU",
 "MV",
 "MX",
 "MY",
 "NL",
 "NO",
 "NZ",
 "OM",
 "PH",
 "PL",
 "PT",
 "QA",
 "RO",
 "SA",
 "SE",
 "SG",
 "SI",
 "SK",
 "SM",
 "TR",
 "TT",
 "UM",
 "US",
 "VA",
 "VN",
 "ZA");

    /**
     * {@inheritdoc}
     */
    public static $supported_cardtypes = array(
        'visa',
        'master',
        'american_express',
    );

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'https://trexle.com/';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'Trexle';

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
        Options::required('secret_key', $options);

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $this->post = array();
        $options = new Options($options);

        $this->addInvoice($money, $options);
        $this->addCreditcard($creditcard);
        if (null === $creditcard->token) {
            $this->addAddress($options);
        }
        $this->addCustomerData($options);

        $this->post['capture'] = 'false';

        return $this->commit(self::CHARGE);
    }

    /**
     * {@inheritdoc}
     */
    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $this->post = array();
        $options = new Options($options);

        $this->addInvoice($money, $options);
        $this->addCreditcard($creditcard);
        if (null === $creditcard->token) {
            $this->addAddress($options);
        }
        $this->addCustomerData($options);

        return $this->commit(self::CHARGE);
    }

    /**
     * {@inheritdoc}
     */
    public function capture($money, $authorization, $options = array())
    {
        $this->post = array('amount' => $this->amount($money / 100));

        $action = sprintf(self::CAPTURE, $authorization);

        return $this->commit($action, RequestInterface::METHOD_PUT);
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
     * response `$response->card->token`
     * Then pass it as option in unstore method below.
     */
    public function store(CreditCard $creditcard, $options = array())
    {
        Options::required('email', $options);

        $this->post = array();
        $options = new Options($options);
        $this->addCreditcard($creditcard);
        $this->addAddress($options);
        $this->post['email'] = $options['email'];

        return $this->commit(self::STORE);
    }

    /**
     * {@inheritdoc}
     *
     * Options require the card token retrieved from store method.
     * $options = array('card_token' => 'token_xxxxxxxxx');
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
        $this->post['email'] = $options['email'];
        $this->post['ip_address'] = $options['ip'];
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

        $this->post['card']['address_line1'] = $address['address1'];
        $this->post['card']['address_line2'] = $address['address2'];
        $this->post['card']['address_city'] = $address['city'];
        $this->post['card']['address_postcode'] = $address['zip'];
        $this->post['card']['address_state'] = $address['state'];
        $this->post['card']['address_country'] = $address['country'];
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
            $this->post['card']['number'] = $creditcard->number;
            $this->post['card']['expiry_month'] = $creditcard->month;
            $this->post['card']['expiry_year'] = $creditcard->year;
            $this->post['card']['cvc'] = $creditcard->verification_value;
            $this->post['card']['name'] = $creditcard->name();

            return;
        }

        if (strpos($creditcard->token, 'card_') === 0) {
            return $this->post['card_token'] = $creditcard->token;
        }

        if (strpos($creditcard->token, 'cus_') === 0) {
            return $this->post['customer_token'] = $creditcard->token;
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

        if (isset($body['response'])) {
            return new Options($body['response']);
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

        $options = array('connect_timeout' => 30);

        $data = $this->ssl_request(
            $method,
            $url,
            $this->postData(),
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
                'authorization' => $response['token'],
                'fraud_review' => $this->fraudReviewFrom($response),
                'avs_result' => $this->avsResultFrom($response),
                'cvv_result' => null
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
        return $response['success'] == 1
            || $response['token'] != null;
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
        return $response['status_message'] ?: $response['error_description'];
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
        $post = array_filter($this->post);

        return http_build_query($post);
    }
}
