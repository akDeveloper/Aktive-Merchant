<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\XmlBuilder;
use AktiveMerchant\Common\Options;

/**
 * Integration of WorldPay gateway
 *
 * @author Tom Maguire
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Worldpay extends Gateway
{
    const TEST_URL = 'https://secure-test.worldpay.com/jsp/merchant/xml/paymentService.jsp';
    const LIVE_URL = 'https://secure.worldpay.com/jsp/merchant/xml/paymentService.jsp';

    const VERSION = '1.4';

    const SUCCESS_OK = 'ok';

    const SUCCESS_AUTHORISED = 'AUTHORISED';

    public static $default_currency = 'GBP';
    public static $money_format = 'cents';
    public static $supported_countries = array('HK', 'US', 'GB', 'AU', 'AD', 'BE', 'CH', 'CY', 'CZ', 'DE', 'DK', 'ES', 'FI', 'FR', 'GI', 'GR', 'HU', 'IE', 'IL', 'IT', 'LI', 'LU', 'MC', 'MT', 'NL', 'NO', 'NZ', 'PL', 'PT', 'SE', 'SG', 'SI', 'SM', 'TR', 'UM', 'VA');
    public static $supported_cardtypes = array('visa', 'master', 'american_express', 'discover', 'jcb', 'maestro', 'laser');
    public static $homepage_url = 'http://www.worldpay.com/';
    public static $display_name = 'WorldPay';

    public static $card_codes = array(
      'visa'             => 'VISA-SSL',
      'master'           => 'ECMC-SSL',
      'discover'         => 'DISCOVER-SSL',
      'american_express' => 'AMEX-SSL',
      'jcb'              => 'JCB-SSL',
      'maestro'          => 'MAESTRO-SSL',
      'laser'            => 'LASER-SSL',
      'diners_club'      => 'DINERS-SSL'
    );

    private $options = array();
    private $xml;
    private $timestamp;

    /**
     * Contructor
     *
     * @param string $options
     */
    public function __construct($options)
    {
        $this->required_options('login, password, inst_id', $options);

        $this->timestamp = strftime("%Y%m%d%H%M%S");

        if (isset($options['currency'])) {
            self::$default_currency = $options['currency'];
        }

        $this->options = $options;
    }

    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $this->required_options('order_id', $options);
        $this->build_authorization_request($money, $creditcard, $options);

        return $this->commit(self::SUCCESS_AUTHORISED);
    }

    public function capture($money, $authorization, $options = array())
    {
        $this->build_capture_request($money, $authorization, $options);

        return $this->commit(self::SUCCESS_OK);
    }

    public function build_authorization_request($money, $creditcard, $options, $testingXmlGeneration = false)
    {
        $this->xml = $this->createXmlBuilder();

        $this->xml->paymentService(function ($xml) use ($money, $creditcard, $options) {
            $xml->submit(function ($xml) use ($money, $creditcard, $options) {
                $this->addOrder($money, $creditcard, $options);
            });
        }, array('merchantCode' => $this->options['login'], 'version' => self::VERSION));

        return $this->xml;
    }

    public function build_capture_request($money, $authorization, $options, $testingXmlGeneration = false)
    {
        $this->xml = $this->createXmlBuilder();

        $this->xml->paymentService(function ($xml) use ($money, $authorization, $options) {
            $xml->modify(function ($xml) use ($money, $authorization, $options) {
                $this->addCaptureModification($money, $authorization, $options);
            });
        }, array('merchantCode' => $this->options['login'], 'version' => self::VERSION));

        return $this->xml;
    }

    private function createXmlBuilder()
    {
        $xml = new XmlBuilder();

        $xml->instruct('1.0', 'UTF-8');
        $xml->docType(
            'paymentService',
            '-//WorldPay//DTD WorldPay PaymentService v1//EN',
            'http://dtd.worldpay.com/paymentService_v1.dtd'
        );

        return $xml;
    }

    private function addOrder($money, $creditcard, $options)
    {
        $this->xml->order(function ($xml) use ($money, $creditcard, $options) {
            $xml->description('Purchase');
            $this->addAmount($money, $options);
            $this->addPaymentMethod($money, $creditcard, $options);
        }, array('orderCode' => $options['order_id'], 'installationId' => 'inst_id'));
    }

    private function addCaptureModification($money, $authorization, $options)
    {
        $this->xml->orderModification(function ($xml) use ($money, $authorization, $options) {
            $xml->capture(function ($xml) use ($money, $authorization, $options) {
                $now = new \DateTime(null, new \DateTimeZone('UTC'));
                $xml->date(
                    null,
                    array(
                        'dayOfMonth' => $now->format('d'),
                        'month' => $now->format('m'),
                        'year' => $now->format('Y'),
                    )
                );

                $this->addAmount($money, $options);
            });
        }, array('orderCode' => $authorization));
    }

    private function addPaymentMethod($money, $creditcard, $options)
    {
        $cardCode = self::$card_codes[$creditcard->type];

        $this->xml->paymentDetails(function ($xml) use ($money, $creditcard, $options, $cardCode) {
            $xml->$cardCode(function ($xml) use ($money, $creditcard, $options) {
                $xml->cardNumber($creditcard->number);
                $xml->expiryDate(function ($xml) use ($money, $creditcard, $options) {
                    $month = $this->cc_format($creditcard->month, 'two_digits');
                    $year = $this->cc_format($creditcard->year, 'four_digits');
                    $xml->date(null, array('month' => $month, 'year' => $year));
                });

                $xml->cardHolderName($creditcard->name());
                $xml->cvc($creditcard->verification_value);
                $this->addAddress($options);
            });
        });
    }

    private function addAmount($money, $options)
    {
        $currency = isset($options['currency']) ? $options['currency'] : self::$default_currency;

        $this->xml->amount(
            null,
            array(
                'value' => $this->amount($money),
                'currencyCode' => $currency,
                'exponent' => 2
            )
        );
    }

    private function addAddress($options)
    {
        $address = isset($options['billing_address']) ? $options['billing_address'] : $options['address'];

        $out = array();

        if (isset($address['name'])) {
            if (preg_match('/^\s*([^\s]+)\s+(.+)$/', $address['name'], $matches)) {
                $out['firstName'] = $matches[1];
                $out['lastName'] = $matches[2];
            }
        }

        if (isset($address['address1'])) {
            if (preg_match('/^\s*(\d+)\s+(.+)$/', $address['address1'], $matches)) {
                $out['street'] = $matches[2];
                $houseNumber = $matches[1];
            } else {
                $out['street'] = $address['address1'];
            }
        }

        if (isset($address['address2'])) {
            $out['houseName'] = $address['address2'];
        }

        if (isset($houseNumber)) {
            $out['houseNumber'] = $houseNumber;
        }

        $out['postalCode'] = isset($address['zip']) ? $address['zip'] : '0000';

        if (isset($address['city'])) {
            $out['city'] = $address['city'];
        }

        $out['state'] = isset($address['state']) ? $address['state'] : 'N/A';

        $out['countryCode'] = $address['country'];

        if (isset($address['phone'])) {
            $out['telephoneNumber'] = $address['phone'];
        }

        $this->xml->cardAddress(function ($xml) use ($out) {
            $xml->address(function ($xml) use ($out) {
                foreach ($out as $name => $value) {
                    $xml->$name($value);
                }
            });
        });
    }

    private function commit($successCriteria)
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $options = array('headers' => array(
            "Authorization: {$this->encodedCredentials()}"
        ));

        $response = $this->parse(
            $this->ssl_post($url, $this->xml->createXML(true), $options)
        );

        $success = $this->successFrom($response, $successCriteria);

        return new Response(
            $success,
            $this->messageFrom($success, $response, $successCriteria),
            $response->getArrayCopy(),
            $this->optionsFrom($response)
        );
    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    private function parse($body)
    {
        $response = array();

        $data = simplexml_load_string($body);

        foreach ($data as $node) {
            $this->parseElement($response, $node);
        }

        return new Options($response);
    }

    private function parseElement(&$response, $node)
    {
        foreach ($node->attributes() as $k => $v) {
            $response[$node->getName() . '_' . $k] = $v->__toString();
        }

        if ($node->count() > 0) {
            if ($node->getName()) {
                $response[$node->getName()] = true;
                foreach ($node as $n) {
                    $this->parseElement($response, $n);
                }
            }
        } else {
            $response[$node->getName()] = $node->__toString();
        }
    }

    private function successFrom($response, $successCriteria)
    {
        if ($successCriteria == 'ok') {
            return isset($response['ok']);
        }

        if (isset($response['lastEvent'])) {
            return $response['lastEvent'] == $successCriteria;
        }

        return false;
    }

    private function messageFrom($success, $response, $successCriteria)
    {
        if ($success) {
            return "SUCCESS";
        }

        if (isset($response['error'])) {
            return trim($response['error']);
        }

        return "A transaction status of $successCriteria is required.";
    }

    private function optionsFrom($response)
    {
        $options = array('test' => $this->isTest());

        $options['authorization'] = $response['orderStatus_orderCode'];
        $options['fraud_review'] = null;
        $options['avs_result'] = array('code' => $response['AVSResultCode']);
        $options['cvv_result'] = $response['CVCResultCode'];

        return $options;
    }

    private function encodedCredentials()
    {
        $credentials = $this->options['login'] . ':' . $this->options['password'];
        $encoded = base64_encode($credentials);

        return "Basic $encoded";
    }
}
