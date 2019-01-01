<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Common\Options;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Common\SimpleXmlBuilder;

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

    protected $options = array();
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
        $options = new Options($options);
        $this->required_options('order_id', $options);
        $this->buildAuthorizationRequest($money, $creditcard, $options);

        return $this->commit(self::SUCCESS_AUTHORISED);
    }

    public function capture($money, $authorization, $options = array())
    {
        $this->buildCaptureRequest($money, $authorization, $options);

        return $this->commit(self::SUCCESS_OK);
    }

    public function buildAuthorizationRequest($money, $creditcard, $options)
    {
        $this->createXmlBuilder();

        $this->xml->submit(null, 'paymentService');
        $this->addOrder($money, $creditcard, $options);
    }

    public function buildCaptureRequest($money, $authorization, $options)
    {
        $this->createXmlBuilder();

        $this->xml->modify(null, 'paymentService');
        $this->addCaptureModification($money, $authorization, $options);
    }

    private function createXmlBuilder()
    {
        $this->xml = new SimpleXmlBuilder();

        $paymentAttributes = array(
            'merchantCode' => $this->options['login'],
            'version' => self::VERSION,
        );

        $this->xml->paymentService(null, null, $paymentAttributes);
    }

    private function addOrder($money, $creditcard, $options)
    {
        $orderAttributes = array(
            'orderCode' => $options['order_id'],
            'installationId' => 'inst_id',
        );
        $this->xml->order(null, 'submit', $orderAttributes)
            ->description('Purchase', 'order');
        $this->addAmount($money, $options, 'order');
        $this->addPaymentMethod($money, $creditcard, $options);
    }

    private function addCaptureModification($money, $authorization, $options)
    {
        $this->xml->orderModification(null, 'modify', array('orderCode' => $authorization));
        $this->xml->capture(null, 'orderModification');
        $now = new \DateTime(null, new \DateTimeZone('UTC'));
        $this->xml->date(null, 'capture', array(
            'dayOfMonth' => $now->format('d'),
            'month' => $now->format('m'),
            'year' => $now->format('Y'),
        ));
        $this->addAmount($money, $options, 'capture');
    }

    private function addPaymentMethod($money, $creditcard, $options)
    {
        $cardCode = self::$card_codes[$creditcard->type];

        $month = $this->cc_format($creditcard->month, 'two_digits');
        $year = $this->cc_format($creditcard->year, 'four_digits');
        $this->xml->paymentDetails(null, 'order')
            ->$cardCode(null, 'paymentDetails')
            ->cardNumber($creditcard->number, $cardCode)
            ->expiryDate(null, $cardCode)
            ->date(null, 'expiryDate', array('month' => $month, 'year' => $year))
            ->cardHolderName($creditcard->name(), $cardCode)
            ->cvc($creditcard->verification_value, $cardCode);
        $this->addAddress($options, $cardCode);
    }

    private function addAmount($money, $options, $parentNode)
    {
        $currency = isset($options['currency']) ? $options['currency'] : self::$default_currency;

        $this->xml->amount(
            null,
            $parentNode,
            array(
                'value' => $this->amount($money),
                'currencyCode' => $currency,
                'exponent' => 2
            )
        );
    }

    private function addAddress($options, $cardCode)
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

        $this->xml->cardAddress(null, $cardCode)
            ->address(null, 'cardAddress');
        foreach ($out as $name => $value) {
            $this->xml->$name($value, 'address');
        }
    }

    private function commit($successCriteria)
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $options = array('headers' => array(
            "Authorization: {$this->encodedCredentials()}"
        ));

        $response = $this->parse(
            $this->ssl_post($url, $this->postData(), $options)
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

    private function postData()
    {
        $xmlHeader = '<?xml version="1.0" encoding="UTF-8"?>';
        $docType = '<!DOCTYPE paymentService PUBLIC "-//WorldPay//DTD WorldPay PaymentService v1//EN" "http://dtd.worldpay.com/paymentService_v1.dtd">';
        $xmlString = trim($this->xml->__toString());

        return str_replace($xmlHeader."\n", $xmlHeader.$docType, $xmlString);
    }
}
