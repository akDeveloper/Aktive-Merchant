<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\Gateways\Paypal\PaypalCommon;
use AktiveMerchant\Billing\Gateways\Paypal\PaypalExpressResponse;
use AktiveMerchant\Common\Country;
use AktiveMerchant\Common\Address;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Billing\Response;

/**
 * Description of PaypalExpress
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class PaypalExpress extends PaypalCommon
{
    const TEST_REDIRECT_URL = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=';
    const LIVE_REDIRECT_URL = 'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=';

    private $version = '94.0';
    protected $options = array();
    private $post = array();
    private $token;
    private $payer_id;
    public static $default_currency = 'USD';
    public static $supported_countries = array('US');
    public static $homepage_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=xpt/merchant/ExpressCheckoutIntro-outside';
    public static $display_name = 'PayPal Express Checkout';

    public function __construct($options = array())
    {
        Options::required('login, password, signature', $options);

        $this->options = new Options($options);

        if (isset($options['version'])) {
            $this->version = $options['version'];
        }
        if (isset($options['currency'])) {
            self::$default_currency = $options['currency'];
        }
    }

    /**
     * Method from Gateway overridden to allow negative values
     */
    public function amount($money)
    {
        if (null === $money) {
            return null;
        }

        if (!is_numeric($money)) {
            throw new \InvalidArgumentException('money amount must be a number.');
        }
        $cents = $money * 100;

        return ($this->money_format() == 'cents')
            ? number_format($cents, 0, '', '')
            : number_format($money, 2);
    }

    /**
     * Authorize and Purchase actions
     *
     * @param number $amount  Total order amount
     * @param Array  $options
     *               token    token param from setup action
     *               payer_id payer_id param from setup action
     *
     * @return Response
     */
    public function authorize($amount, $options = array())
    {
        return $this->doAction($amount, "Authorization", $options);
    }

    /**
     *
     * @param number $amount
     * @param array $options
     *
     * @return Response
     */
    public function purchase($amount, $options = array())
    {
        return $this->doAction($amount, "Sale", $options);
    }

    public function capture($money, $authorization, $options = array())
    {
        $this->required_options('complete_type', $options);

        $this->post = array();

        $params = array(
            'METHOD'            => 'DoCapture',
            'AMT'               => $this->amount($money),
            'AUTHORIZATIONID'   => $authorization,
            'COMPLETETYPE'      => $options['complete_type'],
            "CURRENCYCODE"      => $this->options['currency'] ?: self::$default_currency
        );

        $this->post = array_merge(
            $this->post,
            $params
        );

        return $this->commit('DoCapture');
    }

    public function credit($money, $identification, $options = array())
    {
        $this->required_options('refund_type', $options);

        $this->post = array();

        $params = array(
            'METHOD'  => 'RefundTransaction',
        );

        $this->post['REFUNDTYPE'] = $options['refund_type']; //Must be Other, Full or Partial

        if ($this->post['REFUNDTYPE'] != 'Full') {
            $this->post['AMT'] = $this->amount($money);
        }

        $this->post['TRANSACTIONID'] = $identification;

        $this->post = array_merge(
            $this->post,
            $params
        );

        return $this->commit('RefundTransaction');
    }

    /**
     * Void an authorization.
     *
     * Available option fields are:
     * - note: (Optional) Informational note about this void that is displayed
     *          to the buyer in email and in their transaction history.
     *          Character length and limitations: 255 single-byte characters
     *
     * - message: (Optional) A message ID used for idempotence to uniquely
     *            identify a message. This ID can later be used to request the
     *            latest results for a previous request without generating a
     *            new request. Examples of this include requests due to timeouts
     *            or errors during the original request.
     *            Character length and limitations: 38 single-byte characters
     *
     * {@inheritdoc }
     */
    public function void($authorization, $options = array())
    {
        $this->post = array();

        $params = array(
            'METHOD'            => 'DoVoid',
            'AUTHORIZATIONID'   => $authorization
        );

        if (isset($options['note'])) {
            $params['NOTE'] = $options['note'];
        }

        if (isset($options['message'])) {
            $params['MSGSUBID'] = $options['message'];
        }

        $this->post = array_merge(
            $this->post,
            $params
        );

        return $this->commit('DoVoid');
    }

    public function recurring($money, $options = array())
    {
        Options::required('start_date, period, frequency, token, description', $options);

        $this->post = array();

        $params = array(
            'METHOD' => 'CreateRecurringPaymentsProfile',
            'PROFILESTARTDATE' => $options['start_date'],
            'BILLINGPERIOD' => $options['period'],
            'BILLINGFREQUENCY' => $options['frequency'],
            'AMT' => $this->amount($money),
            'TOKEN' => urlencode(trim($options['token'])),
            'DESC' => $options['description'],
        );

        $this->addAddress($options);

        $this->post = array_merge(
            $this->post,
            $params,
            $this->getOptionalParams($options)
        );

        return $this->commit('CreateRecurringPaymentsProfile');

    }

    public function getRecurringDetails($profile_id)
    {
        $this->post = array();

        $this->post['PROFILEID'] = $profile_id;
        $this->post['METHOD'] = 'GetRecurringPaymentsProfileDetails';

        return $this->commit('GetRecurringPaymentsProfileDetails');
    }

    public function setupRecurring($money, $options = array())
    {

        $this->post = array();
        $this->post['L_BILLINGAGREEMENTDESCRIPTION0'] = $options['desc'];
        $this->post['L_BILLINGTYPE0'] = 'RecurringPayments';

        return $this->setup($money, 'Authorization', $options);

    }

    /**
     * Setup Authorize and Purchase actions
     *
     * @param number $money  Total order amount
     * @param array  $options
     *               currency           Valid currency code ex. 'EUR', 'USD'. See http://www.xe.com/iso4217.php for more
     *               return_url         Success url (url from  your site )
     *               cancel_return_url  Cancel url ( url from your site )
     *
     * @return Response
     */
    public function setupAuthorize($money, $options = array())
    {
        $this->post = array();
        return $this->setup($money, 'Authorization', $options);
    }

    /**
     *
     * @param number $money
     * @param array $options
     *
     * @return Response
     */
    public function setupPurchase($money, $options = array())
    {
        $this->post = array();
        return $this->setup($money, 'Sale', $options);
    }

    private function setup($money, $action, $options = array())
    {

        $this->required_options('return_url, cancel_return_url', $options);

        $params = array(
            'METHOD'               => 'SetExpressCheckout',
            'PAYMENTREQUEST_0_AMT' => $this->amount($money),
            'RETURNURL'            => $options['return_url'],
            'CANCELURL'            => $options['cancel_return_url']
        );

        $this->addAddress($options);

        if (isset($options['header_image'])) {
            $params['HDRIMG'] = $options['header_image'];
        }

        $this->post = array_merge(
            $this->post,
            $params,
            $this->getOptionalParams($options)
        );

        return $this->commit($action);
    }

    private function doAction($money, $action, $options = array())
    {
        if (!isset($options['token'])) {
            $options['token'] = $this->token;
        }

        if (!isset($options['payer_id'])) {
            $options['payer_id'] = $this->payer_id;
        }

        $this->required_options('token, payer_id', $options);

        $this->post = array();

        $params = array(
            'METHOD'               => 'DoExpressCheckoutPayment',
            'PAYMENTREQUEST_0_AMT' => $this->amount($money),
            'TOKEN'                => $options['token'],
            'PAYERID'              => $options['payer_id']
        );

        $this->addAddress($options);

        $this->post = array_merge(
            $this->post,
            $params,
            $this->getOptionalParams($options)
        );

        return $this->commit($action);
    }

    private function getOptionalParams($options)
    {
        $params = array();

        if (isset($options['payment_breakdown'])) {
            $breakdown = $options['payment_breakdown'];
            $params['PAYMENTREQUEST_0_ITEMAMT'] = $this->amount($breakdown['item_total']);
            $params['PAYMENTREQUEST_0_SHIPPINGAMT'] = $this->amount($breakdown['shipping']);
            $params['PAYMENTREQUEST_0_HANDLINGAMT'] = $this->amount($breakdown['handling']);
        }

        if (isset($options['items'])) {
            foreach ($options['items'] as $key => $item) {
                if (isset($item['name'])) {
                    $params["L_PAYMENTREQUEST_0_NAME$key"]   = $item['name'];
                }

                if (isset($item['description'])) {
                    $params["L_PAYMENTREQUEST_0_DESC$key"]   = $item['description'];
                }

                if (isset($item['unit_price'])) {
                    $params["L_PAYMENTREQUEST_0_AMT$key"]    = $this->amount($item['unit_price']);
                }

                if (isset($item['quantity'])) {
                    $params["L_PAYMENTREQUEST_0_QTY$key"]    = $item['quantity'];
                }

                if (isset($item['id'])) {
                    $params["L_PAYMENTREQUEST_0_NUMBER$key"] = $item['id'];
                }
            }
        }

        if (isset($options['email'])) {
            $params['EMAIL'] = $options['email'];
        }

        if (isset($options['extra_options'])) {
            $params = array_merge($params, $options['extra_options']);
        }

        return $params;
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
     *
     * common pattern for address is
     *
     * <code>
     *      $billing_address = isset($options['billing_address'])
     *          ? $options['billing_address']
     *          : $options['address'];
     *      $shipping_address = $options['shipping_address'];
     * </code>
     *
     * @param  array $options
     *
     * @return void
     */
    private function addAddress($options)
    {

        $billing_address = isset($options['billing_address'])
            ? $options['billing_address']
            : (array_key_exists('address', $options) ? $options['address'] : array());

        if (empty($billing_address)) {
            return;
        }

        // Paypal Express needs 2 digits alpha2 country code.
        $country_code = Country::find($billing_address['country'])
            ->getCode('alpha2');
        $billing_address['country'] = $country_code;

        $address = $this->mapAddress($billing_address);

        $this->post = array_merge($this->post, $address->getMappedFields());
    }

    private function mapAddress($billing_address)
    {
        $address = new Address($billing_address);

        $address->map('name', 'PAYMENTREQUEST_0_SHIPTONAME')
            ->map('phone', 'PAYMENTREQUEST_0_SHIPTOPHONENUM')
            ->map('city', 'PAYMENTREQUEST_0_SHIPTOCITY')
            ->map('address1', 'PAYMENTREQUEST_0_SHIPTOSTREET')
            ->map('address2', 'PAYMENTREQUEST_0_SHIPTOSTREET2')
            ->map('state', 'PAYMENTREQUEST_0_SHIPTOSTATE')
            ->map('country', 'PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE')
            ->map('zip', 'PAYMENTREQUEST_0_SHIPTOZIP');

        return $address;
    }

    /**
     *
     * @param string $token
     *
     * @return string url address to redirect
     */
    public function urlForToken($token)
    {
        $redirect_url = $this->isTest()
            ? self::TEST_REDIRECT_URL
            : self::LIVE_REDIRECT_URL;
        return $redirect_url . $token;
    }

    /**
     *
     * @param string $token
     * @param string $payer_id
     *
     * @return Response
     */
    public function get_details_for($token, $payer_id)
    {

        $this->payer_id = urldecode($payer_id);
        $this->token = urldecode($token);

        $params = array(
            'METHOD' => 'GetExpressCheckoutDetails',
            'TOKEN' => $token
        );
        $this->post = array_merge($this->post, $params);

        return $this->commit(null);
    }

    /**
     * {@inheritdoc}
     */
    protected function postData($action)
    {
        $params = array(
            'USER'                           => $this->options['login'],
            'PWD'                            => $this->options['password'],
            'VERSION'                        => $this->version,
            'SIGNATURE'                      => $this->options['signature'],
            'PAYMENTREQUEST_0_CURRENCYCODE'  => self::$default_currency
        );

        if (in_array($this->post['METHOD'], array('SetExpressCheckout', 'DoExpressCheckoutPayment'))) {
            $params['PAYMENTREQUEST_0_PAYMENTACTION'] = $action;
        }

        $this->post = array_merge($this->post, $params);
        return $this->urlize($this->post);
    }

    /**
     * {@inheritdoc}
     */
    protected function buildResponse($success, $message, $response, $options = array())
    {
        return new PaypalExpressResponse($success, $message, $response, $options);
    }
}
