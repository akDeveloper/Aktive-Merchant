<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Exception;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Billing\Gateways\Worldpay\XmlNormalizer;

/**
 * WorldPay
 *
 * @package Aktive-Merchant
 */
class Worldpay extends Gateway
{
    const TEST_URL = 'https://secure-test.worldpay.com/jsp/merchant/xml/paymentService.jsp';
    const LIVE_URL = 'https://secure.worldpay.com/jsp/merchant/xml/paymentService.jsp';

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
        $this->required_options('login, password', $options);

        $this->timestamp = strftime("%Y%m%d%H%M%S");

        if (isset($options['currency']))
            self::$default_currency = $options['currency'];

        $this->options = $options;
    }

    public function authorize($money, CreditCard $creditcard, $options=array())
    {
        $this->required_options('order_id', $options);
        $this->build_authorization_request($money, $creditcard, $options);
        return $this->commit('AUTHORISED');
    }

    public function capture($money, $authorization, $options = array())
    {
        // TODO
    }

    public function build_authorization_request($money, $creditcard, $options, $testingXmlGeneration = false)
    {
        $this->xml = new \Thapp\XmlBuilder\XmlBuilder('paymentService', new XmlNormalizer);
        $this->xml->setAttributeMapp(array('paymentService' => array('merchantCode', 'version')));

        $this->xml->load(array(
            'merchantCode' => $this->options['login'],
            'version' => '1.4',
            'order' => $this->add_order($money, $options),
            'paymentDetails' => $this->add_payment_method($money, $creditcard, $options)
        ));

        // Dirty hack for testing XML generation
        if ($testingXmlGeneration) {
            return $this->xml->createXML(true);
        }
    }

    private function add_order($money, $options)
    {
        $attrs = array('orderCode' => $options['order_id']);

        if (isset($options['inst_id'])) {
            $attrs['installationId'] = $options['inst_id'];
        }

        return array(
            '@attributes' => $attrs,
            array(
                'description' => 'Purchase',
                'amount' => $this->add_amount($money, $options)
            )
        );
    }

    private function add_payment_method($money, $creditcard, $options)
    {
        $cardCode = self::$card_codes[$creditcard->type];

        return array(
            $cardCode => array(
                'cardNumber' => $creditcard->number,
                'expiryDate' => array(
                    'date' => array(
                        '@attributes' => array(
                            'month' => $this->cc_format($creditcard->month, 'two_digits'),
                            'year' => $this->cc_format($creditcard->year, 'four_digits')
                        )
                    )
                ),
                'cardHolderName' => $creditcard->name(),
                'cvc' => $creditcard->verification_value,
                'cardAddress' => $this->add_address($options)
            )
        );
    }

    private function add_amount($money, $options)
    {
        $currency = isset($options['currency']) ? $options['currency'] : self::$default_currency;

        return array(
            '@attributes' => array(
                'value' => $this->amount($money),
                'currencyCode' => $currency,
                'exponent' => 2
            )
        );
    }

    private function add_address($options)
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

        return array('address' => $out);
    }

    private function commit($successCriteria)
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;
        $response = $this->parse($this->ssl_post($url, $this->xml->createXML(true)));
        $success = $this->success_from($response, $successCriteria);
        return new Response(
            $success, 
            $this->message_from($success, $response, $successCriteria), 
            $this->params_from($response), 
            $this->authorization_from($response)
        );
    }

    private function parse($response_xml)
    {
        $xml = simplexml_load_string($response_xml);
        return $xml;
    }

    private function success_from($response, $successCriteria)
    {
        return (string) $response->reply->orderStatus->payment->lastEvent == $successCriteria;
    }

    private function message_from($success, $response, $successCriteria)
    {
        if ($success) {
            return "SUCCESS";
        }

        return trim((string) $response->reply->orderStauts->iso8583ReturnCodeDescription)
            ?: trim((string) $response->reply->orderStatus->error)
            ?: $this->required_status_message($response, $successCriteria);
    }

    private function required_status_message($response, $successCriteria)
    {
        if ($response->reply->lastEvent != $successCriteria) {
            return "A transaction status of $successCriteria is required.";
        }
    }

    private function params_from($response)
    {
        $params = array();

        foreach ($response as $key => $value) {
            $params[$key] = $value;
        }

        return $params;
    }

    private function authorization_from($response)
    {
        foreach ($response as $key => $value) {
            if (preg_match('/_order_code$/', $key)) {
                return $value;
            }
        }
    }
}