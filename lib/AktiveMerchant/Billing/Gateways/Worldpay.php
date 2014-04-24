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
class Worldpay extends Gateway implements 
    Interfaces\Charge//,
    //Interfaces\Credit, 
    //Interfaces\Recurring, 
    //Interfaces\Store
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

    public function authorize($money, CreditCard $creditCard, $options=array())
    {
        $this->required_options('order_id', $options);
        $this->build_authorization_request($money, $creditCard, $options);
        return $this->commit();
    }

    public function purchase($money, CreditCard $creditcard, $options = array())
    {

    }

    public function capture($money, $authorization, $options = array())
    {
        
    }

    public function build_authorization_request($money, $creditCard, $options)
    {
        $this->xml = new \Thapp\XmlBuilder\XmlBuilder('paymentService', new XmlNormalizer);
        $this->xml->setAttributeMapp(array('paymentService' => array('merchantCode', 'version')));

        $this->xml->load(array(
            'merchantCode' => $this->options['login'],
            'version' => '1.4',
            'order' => $this->add_order($money, $options),
            'paymentDetails' => $this->add_payment_method($money, $creditCard, $options)
        ));

        return $this->xml->createXML(true);
    }

    private function add_order($money, $options)
    {
        return array(
            '@attributes' => array(
                'orderCode' => $options['order_id'],
                'installationId' => $options['inst_id']
            ),
            array(
                'description' => 'Purchase',
                'amount' => $this->add_amount($money, $options)
            )
        );
    }

    private function add_payment_method($money, $creditCard, $options)
    {
        $cardCode = self::$card_codes[$creditCard->type];

        return array(
            $cardCode => array(
                'cardNumber' => $creditCard->number,
                'expiryDate' => array(
                    'date' => array(
                        '@attributes' => array(
                            'month' => $creditCard->month,
                            'year' => $creditCard->year
                        )
                    )
                )
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
}