<?php

class Merchant_Billing_PayflowCommon extends Merchant_Billing_Gateway
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

    function __construct($options = array())
    {
        $this->required_options('login, password', $options);

        $this->options = $options;
        if (isset($options['partner']))
            $this->partner = $options['partner'];

        if (isset($options['currency']))
            self::$default_currency = $options['currency'];
    }

    function capture($money, $authorization, $options)
    {
        $request = $this->build_reference_request('Capture', $money, $authorization, $options);
        return $this->commit($request);
    }

    function void($authorization, $options)
    {
        $request = $this->build_reference_request('Void', null, $authorization, $options);
        return $this->commit($request);
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

    private function build_reference_request($action, $money, $authorization, $options)
    {
        $bodyXml = <<<XML
             <{$action}>
                <PNRef>{$authorization}</PNRef>
XML;
        if (!is_null($money)) {
            $default_currency = self::$default_currency;
            $bodyXml .= <<< XML
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

        if (isset($address['name']))
            $xml .= "<Name>{$address['name']}</Name>";

        if (isset($options['email']))
            $xml .= "<EMail>{$options['email']}</EMail>";

        if (isset($address['phone']))
            $xml .= "<Phone>" . $address['phone'] . "</Phone>";

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

        if (isset($transactionAttrs['Duplicate']) && $transactionAttrs['Duplicate'] == 'true')
            $response['duplicate'] = true;

        foreach ($root->children() as $node)
            $this->parse_element(&$response, $node);

        return $response;
    }

    private function parse_element(&$response, $node)
    {
        $nodeName = $node->getName();

        switch (true) {
            case $nodeName == 'RPPaymentResult':
                if (!isset($response[$nodeName]))
                    $response[$nodeName] = array();

                $payment_result_response = array();

                foreach ($node->children() as $child)
                    $this->parse_element(&$payment_result_response, $child);

                foreach ($payment_result_response as $key => $value)
                    $response[$nodeName][$key] = $value;
                break;

            case $node->children()->count() > 0:
                foreach ($node->children() as $child)
                    $this->parse_element(&$response, $child);
                break;

            case preg_match('/amt$/', $nodeName):
                $response[$nodeName] = $node->attributes()->Currency;
                break;

            case $nodeName == 'ExtData':
                $response[$node->attributes()->Name] = $node->attributes()->Value;
                break;

            default:
                $response[$nodeName] = (string) $node;
        }
    }

    protected function commit()
    {
        $url = $this->is_test() ? self::TEST_URL : self::LIVE_URL;
        $response = $this->parse($this->ssl_post($url, $this->xml));
        $this->xml = null;

        return new Merchant_Billing_Response(
            $response['Result'] == 0,
            $response['Message'],
            $response,
            $this->options_from($response));
    }

    private function options_from($response)
    {
        $options = array();
        $options['authorization'] = isset($response['PNRef']) ? $response['PNRef'] : null;
        $options['test'] = $this->is_test();
        if (isset($response['CVResult']))
            $options['cvv_result'] = $this->CVV_CODE[$response['CVResult']];
        if (isset($response['AVSResult']))
            $options['avs_result'] = array('code' => $response['AVSResult']);

        return $options;
    }

}
