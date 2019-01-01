<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Common\Address;
use AktiveMerchant\Http\RequestInterface;

/**
 * Integration of Fat Zebra payment gateway.
 *
 * @author Andreas Kollaros <andreas@larium.net>
 * @license MIT License http://www.opensource.org/licenses/mit-license.php
 */
class FatZebra extends Gateway
{
    const TEST_URL = 'https://gateway.sandbox.fatzebra.com.au/v1.0/';
    const LIVE_URL = 'https://gateway.fatzebra.com.au/v1.0/';

    /**
     * {@inheritdoc}
     */
    public static $money_format = 'cents';

    /**
     * {@inheritdoc}
     */
    public static $supported_countries = array('AU');

    /**
     * {@inheritdoc}
     */
    public static $supported_cardtypes = array(
        'visa',
        'master',
        'american_express',
        'jcb',
    );

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'https://www.fatzebra.com.au/';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'Fat Zebra';

    /**
     * {@inheritdoc}
     */
    public static $default_currency = 'AUD';

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
     * creates gateway instance from given options.
     *
     * @param array $options An array contains login parameters of merchant
     *                       and optional currency. Login info keys are 'username'
     *                       and 'token'
     *
     * @return Gateway The gateway instance.
     */
    public function __construct($options = array())
    {
        Options::required('username, token', $options);

        if (isset($options['currency'])) {
            self::$default_currency = $options['currency'];
        }

        $this->options = new Options($options);
    }

    /**
     * Charge a credit card.
     *
     * @param number              $money      Amount of money to charge
     * @param CreditCard | string $creditcard Credit card to charge or a token
     *                                        from a stored credit card
     * @param array               $options    The order_id reference and coustomer ip.
     * @access public
     * @throws \AktiveMerchant\Billing\Exception If the request fails
     * @return \AktiveMerchant\Billing\Response Response object
     */
    public function purchase($money, $creditcard, $options = array())
    {

        Options::required('order_id, ip', $options);

        $this->post = array();

        $options = new Options($options);

        $this->addInvoice($options->order_id, $money);

        if (is_string($creditcard)) {
            Options::required('cvv', $options);
            $this->post['card_token'] = $creditcard;
        } else {
            $this->addCreditcard($creditcard);
        }

        $this->addCustomerData($options);

        return $this->commit('purchases');
    }

    /**
     *
     * @param  number $money
     * @param  string $identification The transaction_id returned from a
     *                                success purchase
     * @param  array  $options        The unique reference for THIS transaction.
     *                                NOTE. Not the reference from purchase
     *                                transction.
     *
     * @return Response
     */
    public function credit($money, $identification, $options = array())
    {
        Options::required('order_id', $options);

        $options = new Options($options);

        $this->post = array('transaction_id' => $identification);

        $this->addInvoice($options->order_id, $money);
        return $this->commit('refunds');
    }

    /**
     * Stores a reference of a credit card.
     *
     * @param CreditCard $creditcard
     * @access public
     * @return void
     */
    public function store(CreditCard $creditcard, $options = array())
    {
        $this->post = array();

        $this->addCreditcard($creditcard);

        return $this->commit('credit_cards');
    }

    /**
     * Creates a recurring billing subscription for a customer.
     *
     * Available options for period are: "Daily", "Weekly", "Fortnightly", "Monthly", "Quarterly", "Bi-Annually", "Annually"
     *
     * start_date can be a DateTime object a timestamp or a string the can be
     * parsed via strtime function.
     *
     * customer_id can be a string with id returned when you created a customer (XXX-C-XXXXXXXX).
     * if customer_id ommited then a new customer will be cteated.
     *
     * @param string     $plan The plan id returned when you created a plan (XXX-PL-XXXXXXXX).
     * @param CreditCard $creditcard
     * @param array      $options
     *
     * @access public
     * @return void
     */
    public function recurring($plan, CreditCard $creditcard, $options = array())
    {
        $this->post = array();

        Options::required('first_name, last_name, email, customer_id, start_date, period', $options);

        $options = new Options($options);

        if (null === $options->customer_id) {
            $response = $this->createCustomer($creditcard, $options);
            $customer_id = $response->params()->id;
        } else {
            $customer_id = $options->customer_id;
        }

        $this->post['customer'] = $customer_id;
        $start_date = $options->start_date instanceof \DateTime ? $options->start_date : new \DateTime(strtotime($options->start_date));
        $this->post['start_date'] = $start_date->format('Y-m-d');
        $this->post['plan'] = $plan;
        $this->post['frequency'] = $options->period;
        $this->post['is_active'] = true;
        $this->post['reference'] = $options->customer_id;

        return $this->commit('subscriptions');
    }

    public function cancelRecurring($subscription_id)
    {
        $this->post = array();

        $this->post['is_active'] = false;

        return $this->commit("subscriptions/{$subscription_id}", RequestInterface::METHOD_PUT);
    }


    public function getSubscription($reference)
    {
        $reference = urlencode(trim($reference));

        return $this->commit("subscriptions/{$reference}", RequestInterface::METHOD_GET);
    }

    public function createPlan($money, $options)
    {
        Options::required('name, order_id, description', $options);
        $options = new Options($options);

        $this->post = array();

        $this->addInvoice($options->order_id, $money);

        $this->post['name'] = $options->name;
        $this->post['description'] = $options['description'];

        return $this->commit('plans');

    }

    public function getPlan($plan_id)
    {
        $plan_id = urlencode(trim($plan_id));

        return $this->commit("plans/{$plan_id}", RequestInterface::METHOD_GET);
    }

    public function getPlans()
    {
        return $this->commit('plans', RequestInterface::METHOD_GET);
    }

    /**
     * Update attributes of a plan.
     *
     * Valid attributes are name and or description.
     *
     * <code>
     * $attrs = array(
     *      'name' => 'New Name',
     *      'description' => 'New description'
     * );
     * </code>
     * @param string $plan_id The plan id of a plant to update.
     * @param array  $attrs   An array holding the attributes to update
     * @return Response
     */
    public function updatePlan($plan_id, array $attrs = array())
    {
        $this->post = array();
        $attrs = new Options($attrs);
        $plan_id = urlencode(trim($plan_id));

        if ($attrs->name) {
            $this->post['name'] = $attrs->name;
        }

        if ($attrs->description) {
            $this->post['description'] = $attrs->description;
        }

        return $this->commit("plans/{$plan_id}", RequestInterface::METHOD_PUT);
    }

    public function createCustomer(CreditCard $creditcard, $options)
    {
        $this->post = array();

        Options::required('first_name, last_name, email, customer_id', $options);
        $options = new Options($options);

        $this->addCustomerData($options);
        $this->addAddress($options);
        $this->addRecurringCreditcard($creditcard);
        $this->post['reference'] = $options->customer_id;

        return $this->commit('customers');
    }

    /**
     * Updates customer information.
     *
     * @param string     $customer_id The id returned from gateway when the
     *                                customer record creted to the gateway.
     * @param CreditCard $creditcard
     * @param array      $options
     * @access public
     * @return void
     */
    public function updateCustomer($customer_id, CreditCard $creditcard = null, $options = array())
    {
        $this->post = array();

        $options = new Options($options);

        $this->addCustomerData($options);
        $this->addAddress($options);
        $address = $this->post['address'];
        unset($this->post['address']);
        $this->post = array_merge($this->post, $address);

        if (null !== $creditcard) {
            $this->addRecurringCreditcard($creditcard);
        }

        $customer_id = urlencode(trim($customer_id));

        return $this->commit("customers/{$customer_id}", RequestInterface::METHOD_PUT);
    }

    /* -(  Private methods  ) ---------------------------------------------- */

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
    private function addAddress($options)
    {
        $billing_address = $options->billing_address ?: $options->address;

        if (null == $billing_address) {
            return;
        }

        $address = new Address($billing_address);

        $address->map('city', 'city')
            ->map('address1', 'address')
            ->map('state', 'state')
            ->map('country', 'country')
            ->map('zip', 'postcode');

        $this->post['address'] = $address->getMappedFields();
    }

    /**
     * Customer data like e-mail, ip, web browser used for transaction etc
     *
     * @param Options $options Options must include the ip address of
     *                         customer.
     */
    private function addCustomerData($options)
    {
        $this->post['customer_ip'] = $options->ip;

        if ($options->first_name && $options->last_name) {
            $this->post['first_name'] = $options->first_name;
            $this->post['last_name'] = $options->last_name;
        }

        if ($options->email) {
            $this->post['email'] = $options->email;
        }
    }

    /**
     * Adds invoice info if exists.
     *
     * @param Options $order_id The unique order_id.
     * @param number  $money    The amount of money if needed.
     */
    private function addInvoice($order_id, $money = null)
    {
        $this->post['reference'] = $order_id;

        if ($money) {
            $this->post['amount'] = $this->amount($money);
        }

    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     */
    private function addCreditcard(CreditCard $creditcard)
    {

        $post['card_holder'] = $creditcard->name();
        $post['card_number'] = $creditcard->number;
        $post['card_expiry'] = $this->cc_format($creditcard->month, 'two_digits')
            . "/"
            . $this->cc_format($creditcard->year, 'four_digits');
        $post['cvv'] = $creditcard->verification_value;

        $this->post = array_merge($this->post, $post);
    }

    private function addRecurringCreditcard(CreditCard $creditcard)
    {
        $post['card_holder'] = $creditcard->name();
        $post['card_number'] = $creditcard->number;
        $post['expiry_date'] = $this->cc_format($creditcard->month, 'two_digits')
            . "/"
            . $this->cc_format($creditcard->year, 'four_digits');
        $post['cvv'] = $creditcard->verification_value;

        $this->post['card'] = $post;
    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    private function parse($body)
    {
        $result = array();
        $data = json_decode($body);

        $response = is_array($data->response) ? new \stdClass : $data->response;
        if (null == $response) {
            $response = $data;
        }
        $response->errors = $data->errors;
        $response->test = isset($data->test) ? $data->test : $response->test;

        if ($data->successful == true) {
            if ((isset($response->authorized)
                && $response->authorized == true)
                || (isset($response->id)
                && $response->id !== null)
            ) {
                $response->authorization_id = isset($response->id)
                    ? $response->id
                    : $response->token;
            } else {
                $response->authorization_id = null;
            }

            $response->success = true;
        } else {
            $response->success = false;
            $response->authorization_id = null;
        }

        $response->message = isset($response->message) ? $response->message : $data->errors;

        return (array) $response;

    }

    /**
     *
     * @param  string $action
     * @param  number $money
     * @param  array  $parameters
     *
     * @return Response
     */
    private function commit($action, $method = RequestInterface::METHOD_POST)
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $url .= $action;

        $this->getAdapter()->setOption(CURLOPT_USERPWD, "{$this->options->username}:{$this->options->token}");


        $body = empty($this->post) ? null : json_encode($this->post);

        $data = $this->ssl_request($method, $url, $body);

        $response = $this->parse($data);

        $test_mode = $this->isTest();

        return new Response(
            $this->successFrom($response),
            $this->messageFrom($response),
            $response,
            array(
                'test' => $test_mode && $response['test'],
                'authorization' => $response['authorization_id'],
                'fraud_review' => null,
                'avs_result' => null,
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
        return $response['success'];
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
        return is_array($response['message'])
            ? implode(', ', $response['message'])
            : $response['message'];
    }
}
