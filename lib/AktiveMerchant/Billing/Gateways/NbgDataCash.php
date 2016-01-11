<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Common\XmlBuilder;

/**
 * National Bank of Greece DataCash gateway implementation.
 *
 * @package  Aktive-Merchant
 * @author   Andreas Kollaros
 * @license  MIT License http://www.opensource.org/licenses/mit-license.php
 */
class NbgDataCash extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit
{
    const TEST_URL = 'https://accreditation.datacash.com/Transaction/acq_a';
    const LIVE_URL = 'https://accreditation.datacash.com/Transaction/acq_a';

    const PURCHASE = 'auth';
    const AUTHORIZE = 'pre';
    const CAPTURE = 'fulfill';
    const VOID = 'cancel';
    const CREDIT = 'txn_refund';

    const SUCCESS = '1';

    /**
     * {@inheritdoc}
     */
    public static $money_format = 'dollars';

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
        'maestro'
    );

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'https://www.nbg.gr';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'Nbg DataCash';

    /**
     * {@inheritdoc}
     */
    public static $default_currency = 'EUR';

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
        $this->required_options('client, password', $options);

        if (isset($options['currency'])) {
            self::$default_currency = $options['currency'];
        }

        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $options = new Options($options);

        $this->buildXml($options, function ($xml) use ($money, $creditcard, $options) {
            $this->addInvoice($money, $options, $xml);
            $this->addCreditcard($creditcard, static::AUTHORIZE, $xml);
        });

        return $this->commit(static::AUTHORIZE, $money);
    }

    /**
     * {@inheritdoc}
     */
    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $options = new Options($options);

        $this->buildXml($options, function ($xml) use ($money, $creditcard, $options) {
            $this->addInvoice($money, $options, $xml);
            $this->addCreditcard($creditcard, static::PURCHASE, $xml);
        });

        return $this->commit(static::PURCHASE, $money);
    }

    /**
     * {@inheritdoc}
     */
    public function capture($money, $authorization, $options = array())
    {
        $options = new Options($options);

        $this->buildXml($options, function ($xml) use ($money, $authorization, $options) {
            $this->addInvoice($money, $options, $xml);
            $xml->HistoricTxn(function ($xml) use ($options, $authorization) {
                $xml->reference($options['reference']);
                $xml->authcode($authorization);
                $xml->method(static::CAPTURE);
            });
        });

        return $this->commit(static::CAPTURE, $money);
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
    private function addCustomerData($options)
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
    private function addAddress($options)
    {

    }

    /**
     * Adds invoice info if exists.
     *
     * @param array $options
     */
    private function addInvoice($money, $options, $xml)
    {
        $xml->TxnDetails(function ($xml) use ($money, $options) {
            $xml->merchantreference($options['order_id']);
            $xml->amount($this->amount($money), array('currency' => self::$default_currency));
        });
    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     */
    private function addCreditcard(CreditCard $creditcard, $action, $xml)
    {
        $xml->CardTxn(function ($xml) use ($creditcard, $action) {
            $xml->Card(function ($xml) use ($creditcard) {
                $xml->pan($creditcard->number);
                $year  = $this->cc_format($creditcard->year, 'two_digits');
                $month = $this->cc_format($creditcard->month, 'two_digits');
                $xml->expirydate("{$month}/{$year}");
                $xml->Cv2Avs(function ($xml) use ($creditcard) {
                    $xml->cv2($creditcard->verification_value);
                    $xml->ExtendedPolicy(function ($xml) {
                        $xml->cv2_policy(null, array(
                            'notprovided' => 'reject',
                            'notchecked' => 'accept',
                            'matched' => 'accept',
                            'notmatched' => 'reject',
                            'partialmatch' => 'reject'
                        ));
                        $xml->postcode_policy(null, array(
                            'notprovided' => 'accept',
                            'notchecked' => 'accept',
                            'matched' => 'accept',
                            'notmatched' => 'accept',
                            'partialmatch' => 'accept'
                        ));
                        $xml->address_policy(null, array(
                            'notprovided' => 'accept',
                            'notchecked' => 'accept',
                            'matched' => 'accept',
                            'notmatched' => 'accept',
                            'partialmatch' => 'accept'
                        ));
                    });
                });
            });
            $xml->method($action);
        });
    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    private function parse($body)
    {
        $response = array();
        $data = simplexml_load_string($body, 'SimpleXMLElement');

        $response['authorization_id'] = null;
        $response['avs_result_code'] = null;
        $response['card_code'] = null;
        $status = $data->status->__toString();
        $response['status'] = $status;
        $response['datacash_reference'] = $data->datacash_reference->__toString();
        $response['reason'] = $data->reason->__toString();
        $response['time'] = $data->time->__toString();

        if ($status == 1) { #success
            if ($cardTxn = $data->CardTxn) {
                $response = array_merge($response, $this->parseCardTxn($cardTxn, $data));
            }

            return $response;
        }

        if ($cardTxn = $data->CardTxn) {
            $response = array_merge($response, $this->parseCardTxn($cardTxn, $data));
        }


        return $response;
    }

    private function parseCardTxn($cardTxn, $data)
    {
        $response = array();
        $response['authorization_id'] = sprintf('%s;%s', $cardTxn->authcode->__toString(), $data['datacash_reference']);
        $response['card_scheme'] = $cardTxn->card_scheme->__toString();
        $response['country'] = $cardTxn->country->__toString();
        $response['issuer'] = $cardTxn->issuer->__toString();
        $response['response_code'] = $cardTxn->response_code->__toString();
        $response['acquirer'] = $data->acquirer->__toString();
        $response['mid'] = $data->mid->__toString();
        $response['rrn'] = $data->rrn->__toString();
        $response['stan'] = $data->stan->__toString();
        $response['mode'] = $data->mode->__toString();
        $response['aiic'] = $data->aiic->__toString();

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

        $data = $this->ssl_post($url, $this->postData($action, $parameters));

        $response = $this->parse($data);

        $test_mode = $this->isTest();

        return new Response(
            $this->successFrom($response),
            $this->messageFrom($response),
            $response,
            array(
                'test' => $test_mode,
                'authorization' => $response['authorization_id'],
                'fraud_review' => $this->fraudReviewFrom($response),
                'avs_result' => $this->avsResultFrom($response),
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
    private function successFrom($response)
    {
        return $response['status'] == static::SUCCESS;
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
        return $response['reason'];
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
    private function postData($action, $parameters = array())
    {
        $xml = $this->xml->__toString();

        return $xml;
    }

    private function buildXml($options, $block)
    {
        $this->xml = new XmlBuilder();
        $this->xml->Request(function ($xml) use ($block) {
            $xml->Authentication(function ($xml) {
                $xml->client($this->options['client']);
                $xml->password($this->options['password']);
            });
            $xml->Transaction(function ($xml) use ($block) {
                $block($xml);
            });
        }, array('version' => '2'));
    }

    private function parseAuthorization($authorization)
    {
        list($datacash_reference, $authcode) = explode(';', $authorization);

        return array(
            'datacash_reference' => $datacash_reference,
            'authcode' => $authcode
        );
    }
}
