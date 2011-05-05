<?php

class Merchant_Billing_PayflowCommon extends Merchant_Billing_Gateway
{
    const XMLNS = 'http://www.paypal.com/XMLPay';
    const TEST_URL = 'https://pilot-payflowpro.paypal.com';
    const LIVE_URL = 'https://payflowpro.paypal.com';

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
        'Service not Requested' => 'P'
    );

    protected $default_currency = 'USD';
    protected $supported_countries = array('US', 'CA', 'SG', 'AU');

    protected $options;
    protected $partner = 'PayPal';
    protected $timeout = 60;

    private $xml = '';

    function __construct($options = array())
    {
        $this->required_options(array('login', 'password', $options));

        $this->options = $options;
        if(isset($options['partner']))
            $this->partner = $options['partner'];

        if(isset($options['currency']))
            $this->default_currency = $options['currency'];
    }

    function capture($money, $authorization, $options)
    {
        $request = $this->build_reference_request('capture', $money, $authorization, $options);
        $this->commit($request);
    }

    function void($authorization, $options)
    {
        $request = $this->build_reference_request('void', null, $authorization, $options);
        $this->commit($request);
    }

    protected function build_request($body)
    {
        $this->xml .= <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<XMLPayRequest Timeout="{$this->timeout}" version="2.1" xmlns="{XMLNS}">
    <RequestData>
        <Vendor>{$this->options['login']}</Vendor>
        <Partner>{$this->partner}</Partner>
        <Transactions>
            <Transaction>
                <Verbosity>MEDIUM</Verbosity>
XML;
        $this->xml .= $body;

        $this->xml .= <<<XML
            </Transaction>
        </Transactions>
    </RequestData>
    <RequestAuth>
        <UserPass>
            <User>{isset($this->options['user']) ? $this->options['user'] : $this->options['login']}</User>
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
                <PNRef>
                    {$authorization}
                </PNRef>
             </{$action}>
XML;
        if(!is_null($money))
        {
            $bodyXml .= <<< XML
                <Invoice>
                    <TotalAmt Currency="{$this->currency_lookup($this->default_currency)}">
                        {$this->amount($money)}
                    </TotalAmt>
                </Invoice>
XML;
        }

        return $this->build_request($bodyXml);
    }

    protected function add_address($options)
    {
        return <<<XML
            <Name>{$options['name']}</Name>
            <EMail>{$options['email']}</EMail>
            <Address>
                <Street1>{$options['address1']}</Street1>
                <City>{$options['city']}</City>
                <State>{$options['state']}</StateProv>
                <Zip>{$options['zip']}</PostalCode>
                <Country>{$options['country']}</Country>
            </Address>
XML;
    }

    private function parse($response_xml)
    {
        $xml = simplexml_load_string($response_xml);

        $response = array();

        $root = $xml->ResponseData;
        $transaction = $root->TransactionResult;

        if(!is_null($transaction) && $transaction->Duplicate == 'true')
            $response['duplicate'] = true;

        foreach($root->children() as $node)
            $this->parse_element(&$response, $node);

        return $response;
    }

    private function parse_element(&$response, $node)
    {
        $nodeName = $node->getName();

        switch(true)
        {
            case $nodeName == 'RPPaymentResult':
                if(!isset($response[$nodeName]))
                    $response[$nodeName] = array();

                $payment_result_response = array();

                foreach($node->children() as $child)
                    $this->parse_element(&$payment_result_response, $child);

                foreach($payment_result_response as $key => $value)
                    $response[$nodeName][$key] = $value;
                break;

            case $node->children()->count() > 0:
                foreach($node->children() as $child)
                    $this->parse_element(&$response, $child);
                break;

            case preg_match('/amt$/', $nodeName):
                $response[$nodeName] = $node->attributes()->Currency;
                break;

            case $nodeName == 'ExtData':
                $response[$node->attributes()->Name] = $node->attributes()->Value;
                break;

            default:
                $response[$nodeName] = $node;

        }
    }

    private function build_headers($content_length)
    {
        return array(
            "Content-Type" => "text/xml",
            "Content-Length" => $content_length,
            "X-VPS-Client-Timeout" => $this->timeout,
        );
    }

    protected function commit()
    {
        $url = $this->is_test() ? self::TEST_URL : self::LIVE_URL;
        $response = $this->parse($this->ssl_post($url, $this->xml));

        //TODO: Add params
        return new Merchant_Billing_Response($response['Result'] == 0, $response['Message']);
    }
}
