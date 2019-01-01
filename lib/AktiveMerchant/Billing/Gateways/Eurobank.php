<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;

/**
 * Integration of Eurobank ProxyPay gateway.
 *
 * ProxyPay is deprecated by Eurobank.
 * Current active implementation is Cardlink gateway.
 *
 * @author Andreas Kollaros <andreas@larium.net>
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Eurobank extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit
{
    const TEST_URL = 'https://eptest.eurocommerce.gr/proxypay/apacsonline';
    const LIVE_URL = 'https://ep.eurocommerce.gr/proxypay/apacsonline';

    protected $options = array();
    private $xml;
    public static $default_currency = 'EUR';
    public static $supported_countries = array('GR');
    public static $supported_cardtypes = array('visa', 'master');
    public static $homepage_url = 'http://www.eurobank.gr/online/home/generic.aspx?id=79&mid=635';
    public static $display_name = 'Eurobank Euro-Commerce';
    public static $money_format = 'cents';

    /**
     *
     * @param array $options
     * Options
     * 'login'    - Your merchan id.         (REQUIRED)
     * 'password' - Your encrypted password. (REQUIRED)
     */
    public function __construct($options)
    {
        trigger_error('Eurobank gateway is deprecated. Use Cardlink gateway instead.');

        $this->required_options('login, password', $options);

        if (isset($options['currency'])) {
            self::$default_currency = $options['currency'];
        }

        $this->options = $options;
    }

    /**
     *
     * @param number     $money      - Total order amount.       (REQUIRED)
     * @param CreditCard $creditcard - A creditcard class object (REQUIRED)
     * @param array      $options
     *
     * @return Response
     */
    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $this->required_options('customer_email', $options);
        $this->buildXml($money, 'PreAuth', $creditcard, $options);
        return $this->commit();
    }

    public function purchase($money, CreditCard $creditcard, $options = array())
    {

    }

    /**
     *
     * @param number $money
     * @param string $authorization
     * @param array  $options
     *
     * @return Merchant_Billing_Response
     */
    public function capture($money, $authorization, $options = array())
    {
        $options = array_merge($options, array('authorization' => $authorization));
        $this->buildXml($money, 'Capture', null, $options);
        return $this->commit();
    }

    /**
     *
     * @param number $money
     * @param string $identification
     * @param array  $options
     *
     * @return Merchant_Billing_Response
     */
    public function credit($money, $identification, $options = array())
    {
        $options = array_merge($options, array('authorization' => $identification));
        $this->buildXml($money, 'Refund', null, $options);
        return $this->commit();
    }

    /**
     *
     * @param string $identification
     * @param array  $options
     *
     * @return Merchant_Billing_Response
     */
    public function void($identification, $options = array())
    {
        $options = array_merge($options, array('authorization' => $identification));
        $this->buildXml(0, 'Cancel', null, $options);
        return $this->commit();
    }

    /**
     *
     * @return Merchant_Billing_Response
     */
    private function commit()
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $post_data = 'APACScommand=NewRequest&data=' . trim($this->xml);
        $response = $this->parse($this->ssl_post($url, $post_data));

        /*
         * Sample of response
         <?xml version="1.0" encoding="UTF-8"?>
         <RESPONSE>
         <ERRORCODE>0</ERRORCODE>
         <ERRORMESSAGE>0</ERRORMESSAGE>
         <REFERENCE>or5342-CD</REFERENCE>
         <PROXYPAYREF>34543</PROXYPAYREF>
         <SEQUENCE>4562</SEQUENCE>
         </RESPONSE>
         */

        return new Response(
            $this->successFrom($response),
            $this->messageFrom($response),
            $response,
            $this->optionsFrom($response)
        );
    }

    /**
     *
     * @param string $response_xml
     *
     * @return array
     */
    private function parse($response_xml)
    {
        $xml = simplexml_load_string($response_xml);
        $response = array();

        $response['error_code'] = (string) $xml->ERRORCODE;
        $response['message'] = (string) $xml->ERRORMESSAGE;
        $response['reference'] = (string) $xml->REFERENCE;
        $response['proxypay_ref'] = (string) $xml->PROXYPAYREF;
        $response['sequence'] = (string) $xml->SEQUENCE;

        return $response;
    }

    private function successFrom($response)
    {
        return $response['error_code'] == '0';
    }

    /**
     *
     * @param string $response
     *
     * @return boolean
     */
    private function messageFrom($response)
    {
        return $response['message'];
    }

    /**
     *
     * @param string $response
     *
     * @return array
     */
    private function optionsFrom($response)
    {
        $options = array();
        $options['test'] = $this->isTest();
        $options['authorization'] = $response['reference'];
        $options['proxypay_ref'] = $response['proxypay_ref'];
        $options['sequence'] = $response['sequence'];

        return $options;
    }

    /**
     *
     * @param CreditCard $creditcard
     */
    private function buildPaymentInfo(CreditCard $creditcard)
    {
        $month = $this->cc_format($creditcard->month, 'two_digits');
        $year = $this->cc_format($creditcard->year, 'two_digits');

        $this->xml .= <<<XML
        <PaymentInfo>
        <CCN>{$creditcard->number}</CCN>
        <Expdate>{$month}{$year}</Expdate>
        <CVCCVV>{$creditcard->verification_value}</CVCCVV>
        <InstallmentOffset>0</InstallmentOffset>
        <InstallmentPeriod>0</InstallmentPeriod>
        </PaymentInfo>
XML;
    }

    /**
     *
     * @param number     $money
     * @param string     $type
     * @param CreditCard $creditcard
     * @param array      $options
     */
    private function buildXml(
        $money,
        $type,
        CreditCard $creditcard = null,
        $options = array()
    ) {
        $merchant_desc = isset($options['merchant_desc']) ? $options['merchant_desc'] : null;
        $merchant_ref = isset($options['authorization']) ? $options['authorization'] : "REF " . date("YmdH:i:s", time());
        $customer_email = isset($options['customer_email']) ? $options['customer_email'] : "";

        $this->xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <JProxyPayLink>
        <Message>
        <Type>{$type}</Type>
        <Authentication>
        <MerchantID>{$this->options['login']}</MerchantID>
        <Password>{$this->options['password']}</Password>
        </Authentication>
        <OrderInfo>
        <Amount>{$this->amount($money)}</Amount>
        <MerchantRef>{$merchant_ref}</MerchantRef>
        <MerchantDesc>{$merchant_desc}</MerchantDesc>
        <Currency>{$this->currency_lookup(self::$default_currency)}</Currency>
        <CustomerEmail>{$customer_email}</CustomerEmail>
        <Var1 />
        <Var2 />
        <Var3 />
        <Var4 />
        <Var5 />
        <Var6 />
        <Var7 />
        <Var8 />
        <Var9 />
        </OrderInfo>
XML;
        if ($creditcard != null) {
            $this->buildPaymentInfo($creditcard);
        }
        $this->xml .= <<<XML
        </Message>
        </JProxyPayLink>
XML;
    }
}
