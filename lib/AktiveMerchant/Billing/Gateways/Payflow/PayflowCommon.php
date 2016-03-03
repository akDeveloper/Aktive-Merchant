<?php

namespace AktiveMerchant\Billing\Gateways\Payflow;

use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\Response;

class PayflowCommon extends Gateway
{
    const TEST_URL = 'https://pilot-payflowpro.paypal.com';
    const LIVE_URL = 'https://payflowpro.paypal.com';

    protected $XMLNS = 'http://www.paypal.com/XMLPay';
    protected $CARD_MAPPING = array(
        'visa' => 'Visa',
        'master' => 'MasterCard',
        'discover' => 'Discover',
        'american_express' => 'Amex',
        'jcb' => 'JCB',
        'diners_club' => 'DinersClub',
        'switch' => 'Switch',
        'solo' => 'Solo'
    );
    protected $CVV_CODE = array(
        'Match' => 'M',
        'No Match' => 'N',
        'Service Not Available' => 'U',
        'Service Not Requested' => 'P'
    );
    public static $default_currency = 'USD';
    public static $supported_countries = array('US', 'CA', 'SG', 'AU');
    protected $options;
    protected $partner = 'PayPal';
    protected $timeout = 60;
    private $xml = '';

    public function __construct($options = array())
    {
        $this->required_options('login, password', $options);

        $this->options = $options;
        if (isset($options['partner'])) {
            $this->partner = $options['partner'];
        }

        if (isset($options['currency'])) {
            self::$default_currency = $options['currency'];
        }
    }

    public function capture($money, $authorization, $options)
    {
        $this->build_reference_request('Capture', $money, $authorization);
        return $this->commit($options);
    }

    public function void($authorization, $options)
    {
        $this->build_reference_request('Void', null, $authorization);
        return $this->commit($options);
    }

    protected function build_request($body)
    {
        $this->xml .= <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<XMLPayRequest Timeout="{$this->timeout}" version="2.1" xmlns="{$this->XMLNS}">
    <RequestData>
        <Vendor>{$this->options['login']}</Vendor>
        <Partner>{$this->partner}</Partner>
        <Transactions>
            <Transaction>
                <Verbosity>MEDIUM</Verbosity>
XML;
        $this->xml .= $body;

        $user = isset($this->options['user']) ? $this->options['user'] : $this->options['login'];

        $this->xml .= <<<XML
            </Transaction>
        </Transactions>
    </RequestData>
    <RequestAuth>
        <UserPass>
            <User>{$user}</User>
            <Password>{$this->options['password']}</Password>
        </UserPass>
    </RequestAuth>
</XMLPayRequest>
XML;
    }

    private function build_reference_request($action, $money, $authorization)
    {
        $bodyXml = <<<XML
             <{$action}>
                <PNRef>{$authorization}</PNRef>
XML;
        if (!is_null($money)) {
            $default_currency = self::$default_currency;
            $bodyXml .= <<<XML
                <Invoice>
                    <TotalAmt Currency="{$default_currency}">{$this->amount($money)}</TotalAmt>
                </Invoice>
XML;
        }

        $bodyXml .= "</{$action}>";

        return $this->build_request($bodyXml);
    }

    protected function add_address($options, $address)
    {
        $xml = '';

        if (isset($address['name'])) {
            $xml .= "<Name>{$address['name']}</Name>";
        }

        if (isset($options['email'])) {
            $xml .= "<EMail>{$options['email']}</EMail>";
        }

        if (isset($address['phone'])) {
            $xml .= "<Phone>" . $address['phone'] . "</Phone>";
        }

        $xml .= <<<XML
            <Address>
                <Street>{$address['address1']}</Street>
                <City>{$address['city']}</City>
                <State>{$address['state']}</State>
                <Zip>{$address['zip']}</Zip>
                <Country>{$address['country']}</Country>
            </Address>
XML;
        return $xml;
    }

    private function parse($response_xml)
    {
        $xml = simplexml_load_string($response_xml);

        $response = array();

        $root = $xml->ResponseData;
        $transactionAttrs = $root->TransactionResults->TransactionResult->attributes();

        if (isset($transactionAttrs['Duplicate'])
            && $transactionAttrs['Duplicate'] == 'true'
        ) {
            $response['duplicate'] = true;
        }

        foreach ($root->children() as $node) {
            $response = array_merge($response, $this->parse_element($node));
        }

        return $response;
    }

    private function parse_element($node)
    {
        $parsed = array();

        $nodeName = $node->getName();

        switch (true) {
            case $nodeName == 'RPPaymentResult':
                if (!isset($parsed[$nodeName])) {
                    $parsed[$nodeName] = array();
                }

                $payment_result_response = array();

                foreach ($node->children() as $child) {
                    $payment_result_response = array_merge(
                        $payment_result_response,
                        $this->parse_element($child)
                    );
                }

                foreach ($payment_result_response as $key => $value) {
                    $parsed[$nodeName][$key] = $value;
                }
                break;

            case $node->children()->count() > 0:
                foreach ($node->children() as $child) {
                    $parsed = array_merge($parsed, $this->parse_element($child));
                }

                break;

            case preg_match('/amt$/', $nodeName):
                $parsed[$nodeName] = $node->attributes()->Currency;
                break;

            case $nodeName == 'ExtData':
                $parsed[(string)$node->attributes()->Name] = (string)$node->attributes()->Value;
                break;

            default:
                $parsed[$nodeName] = (string) $node;
        }

        return $parsed;
    }

    protected function commit($options)
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;
        $data = $this->ssl_post($url, $this->xml, $options);
        $response = $this->parse($data);
        $this->xml = null;

        return new Response(
            $response['Result'] == 0,
            $response['Message'],
            $response,
            $this->options_from($response)
        );
    }

    private function options_from($response)
    {
        $options = array();
        $options['authorization'] = isset($response['PNRef'])
            ? $response['PNRef']
            : null;
        $options['test'] = $this->isTest();
        if (isset($response['CVResult'])) {
            $options['cvv_result'] = $this->CVV_CODE[$response['CVResult']];
        }

        if (isset($response['AVSResult'])) {
            $options['avs_result'] = array('code' => $response['AVSResult']);
        }

        return $options;
    }
}
