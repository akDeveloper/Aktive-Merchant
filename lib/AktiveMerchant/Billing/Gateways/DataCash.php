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
class DataCash extends Gateway implements
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

    const METHOD_ECOMM = 'ecomm';
    const METHOD_MOTO = 'cnp';

    const SUCCESS = '1';

    /**
     * {@inheritdoc}
     */
    public static $money_format = 'dollars';

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
    protected $post;

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
            static::$default_currency = $options['currency'];
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
            $this->addCreditcard($creditcard, static::AUTHORIZE, $xml, $options);
        });

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $options = new Options($options);

        $this->buildXml($options, function ($xml) use ($money, $creditcard, $options) {
            $this->addInvoice($money, $options, $xml);
            $this->addCreditcard($creditcard, static::PURCHASE, $xml, $options);
        });

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function capture($money, $authorization, $options = array())
    {
        $options = new Options($options);

        $reference = $this->parseAuthorization($authorization);

        $this->buildXml($options, function ($xml) use ($money, $reference, $options) {
            $this->addInvoice($money, $options, $xml);
            $xml->HistoricTxn(function ($xml) use ($options, $reference) {
                $xml->reference($reference['datacash_reference']);
                $xml->authcode($reference['authcode']);
                $xml->method(static::CAPTURE);
            });
        });

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function void($authorization, $options = array())
    {
        $reference = $this->parseAuthorization($authorization);

        $this->buildXml($options, function ($xml) use ($reference) {
            $xml->HistoricTxn(function ($xml) use ($reference) {
                $xml->reference($reference['datacash_reference']);
                $xml->method(static::VOID);
            });
        });

        return $this->commit();
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
        $reference = $this->parseAuthorization($identification);

        $this->buildXml($options, function ($xml) use ($money, $reference) {
            $xml->TxnDetails(function ($xml) use ($money) {
                $xml->amount($this->amount($money));
            });
            $xml->HistoricTxn(function ($xml) use ($reference) {
                $xml->reference($reference['datacash_reference']);
                $xml->method(static::CREDIT);
            });
        });

        return $this->commit();
    }

    public function store(CreditCard $creditcard, $options = array())
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
    protected function commit()
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $postData = $this->postData();

        $data = $this->ssl_post($url, $postData);

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

    protected function buildXml($options, $block)
    {
        $this->xml = new XmlBuilder();
        $this->xml->instruct('1.0', 'UTF-8');
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

    /**
     * Customer data like e-mail, ip, web browser used for transaction etc
     *
     * @param array $options
     */
    protected function addCustomerData($xml, $options)
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
    protected function addAddress($options)
    {

    }

    /**
     * Adds invoice info if exists.
     *
     * @param array $options
     */
    protected function addInvoice($money, $options, $xml)
    {
        $xml->TxnDetails(function ($xml) use ($money, $options) {
            $xml->merchantreference($options['order_id']);
            $xml->amount($this->amount($money), array('currency' => static::$default_currency));
            if ($options['cardholder_registered']) {
                $xml->capturemethod(static::METHOD_ECOMM);
            }
        });
    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     */
    protected function addCreditcard(CreditCard $creditcard, $action, $xml, $options = array())
    {
        $xml->CardTxn(function ($xml) use ($creditcard, $action, $options) {
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
            $type = $creditcard->type == 'visa' ? 'visa' : 'ucaf';
            if ($options['cardholder_registered']) {
                $xml->Secure(function ($xml) use ($options) {
                    $xml->cardholder_registered($options['cardholder_registered']);
                    if ($options['aav']) {
                        $xml->security_code($options['aav']);
                    }

                    if ($options['eci']) {
                        $xml->eci($options['eci']);
                    }

                    if ($options['xid']) {
                        $xml->transactionID($options['xid']);
                    }
                }, array('type' => $type));
            }
        });
    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    protected function parse($body)
    {
        $response = array();

        $data = simplexml_load_string($body);

        foreach ($data as $node) {
            $this->parseElement($response, $node);
        }

        $response['authorization_id'] = null;
        $response['avs_result_code'] = null;
        $response['card_code'] = null;

        $this->setAuthorization($response);

        return $response;
    }

    protected function parseElement(&$response, $node)
    {
        if ($node->count() > 0) {
            foreach ($node as $n) {
                $this->parseElement($response, $n);
            }
        } else {
            $response[$node->getName()] = $node->__toString();
        }
    }

    private function setAuthorization(&$response)
    {
        if (isset($response['datacash_reference'])
            && isset($response['authcode'])
        ) {
            $response['authorization_id'] = sprintf(
                '%s;%s',
                $response['datacash_reference'],
                $response['authcode']
            );
        }

        if (isset($response['datacash_reference'])) {
            $response['authorization_id'] = $response['datacash_reference'];
        }

    }

    /**
     * Returns success flag from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    protected function successFrom($response)
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
    protected function messageFrom($response)
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
    protected function fraudReviewFrom($response)
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
    protected function avsResultFrom($response)
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
    protected function postData()
    {
        $xml = $this->xml->__toString();

        return $xml;
    }

    protected function parseAuthorization($authorization)
    {
        list($datacash_reference, $authcode) = explode(';', $authorization);

        return array(
            'datacash_reference' => $datacash_reference,
            'authcode' => $authcode
        );
    }
}
