<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Gateways\Centinel\CentinelResponse;

/**
 * Description of Centinel gateway
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Centinel extends Gateway
{
    const TEST_URL = 'https://centineltest.cardinalcommerce.com/maps/txns.asp';
    const LIVE_URL = 'https://centinel.cardinalcommerce.com/maps/txns.asp';

    # The countries the gateway supports merchants from as 2 digit ISO country codes
    public static $supported_countries = array('US', 'GR');

    # The homepage URL of the gateway
    public static $homepage_url = 'http://www.cardinalcommerce.com';
    
    public static $display_name = 'Centinel 3D Secure';
    public static $money_format = 'cents';
    public static $default_currency = 'EUR';
    private $options;
    private $post;
    private $VERSION = '1.7';

    public function __construct($options = array())
    {
      $this->required_options('login, password, processor_id', $options);

        if (isset($options['currency']))
            self::$default_currency = $options['currency'];

        $this->options = $options;
    }

    public function lookup($money, CreditCard $creditcard, $options=array())
    {
        $this->required_options('order_id', $options);

        $this->add_invoice($money, $options);
        $this->add_creditcard($creditcard);

        return $this->commit('cmpi_lookup', $money, $options);
    }

    public function authenticate($options=array())
    {
        $this->required_options('payload, transaction_id', $options);
        $this->add_cmpi_lookup_data($options);

        return $this->commit('cmpi_authenticate', null, $options);
    }

    /* Private */

    private function add_cmpi_lookup_data($options)
    {
        $this->post .= <<<XML
        <TransactionId>{$options['transaction_id']}</TransactionId>
        <PAResPayload>{$options['payload']}</PAResPayload>
XML;
    }

    private function add_invoice($money, $options)
    {
        $order_number = isset($options['order_id']) ? $options['order_id'] : null;

        $amount = $this->isTest() ? $this->amount("1") : $this->amount($money);
        $default_currency = self::$default_currency;
        $this->post .= <<<XML
      <OrderNumber>{$order_number}</OrderNumber>
      <CurrencyCode>{$this->currency_lookup($default_currency)}</CurrencyCode>
      <Amount>{$amount}</Amount>
XML;
    }

    private function add_creditcard(CreditCard $creditcard)
    {
        $month = $this->cc_format($creditcard->month, 'two_digits');
        $year = $this->cc_format($creditcard->year, 'four_digits');
        $this->post .= <<<XML
      <CardNumber>{$creditcard->number}</CardNumber>
      <CardExpMonth>{$month}</CardExpMonth>
      <CardExpYear>{$year}</CardExpYear>
XML;
    }

    private function parse($body)
    {
        $response = array();

        $response['avs_result_code'] = "";
        $response['card_code'] = "";
        return $response;
    }

    private function parse_cmpi_lookup($body)
    {
        $xml = simplexml_load_string($body);

        $response = array();
        $response['transaction_id'] = (string) $xml->TransactionId;
        $response['error_no'] = (string) $xml->ErrorNo;
        $response['error_desc'] = (string) $xml->ErrorDesc;
        $response['eci_flag'] = (string) $xml->EciFlag;
        $response['payload'] = (string) $xml->Payload;
        $response['acs_url'] = (string) $xml->ACSUrl;
        $response['order_id'] = (string) $xml->OrderId;
        $response['transaction_type'] = (string) $xml->TransactionType;
        $response['enrolled'] = (string) $xml->Enrolled;
        return $response;
    }

    private function parse_cmpi_authenticate($body)
    {
        $xml = simplexml_load_string($body);

        $response = array();

        $response['eci_flag'] = (string) $xml->EciFlag;
        $response['pares_status'] = (string) $xml->PAResStatus;
        $response['signature_verification'] = (string) $xml->SignatureVerification;
        $response['xid'] = (string) $xml->Xid;
        $response['error_desc'] = (string) $xml->ErrorDesc;
        $response['error_no'] = (string) $xml->ErrorNo;
        $response['cavv'] = (string) $xml->Cavv;

        return $response;
    }

    private function commit($action, $money, $parameters)
    {
        $url = $this->isTest() ? static::TEST_URL : static::LIVE_URL;

        $this->getAdapter()->setOption(CURLOPT_SSLVERSION, 3);
        
        $data = $this->ssl_post($url, $this->post_data($action), $parameters);

        $options = array('test' => $this->isTest());

        switch ($action) {
            case 'cmpi_lookup':
                $response = $this->parse_cmpi_lookup($data);
                $options['authorization'] = $response['transaction_id'];
                break;
            case 'cmpi_authenticate':
                $response = $this->parse_cmpi_authenticate($data);
                break;

            default:
                $response = $this->parse($data);
                break;
        }

        return new CentinelResponse($this->success_from($response),
            $this->message_from($response), $response, $options);
    }

    private function success_from($response)
    {
        $cardholderEnrolled = isset($response['acs_url']);
        $acsUrlNotProvided = empty($response['acs_url']);
        
        if($cardholderEnrolled && $acsUrlNotProvided)
            return false;

        $authStatus = isset($response['pares_status']) ? $response['pares_status'] : null;
        $isCmpiAuthenticateResponse = !is_null($authStatus);
        $autheticationFailed = !in_array($authStatus, array('Y', 'A'));

        if($isCmpiAuthenticateResponse && $autheticationFailed)
          return false;

        return $response['error_no'] == '0';
    }

    private function message_from($response)
    {
        return $response['error_desc'];
    }

    private function post_data($action)
    {
        $data = <<<XML
      <?xml version="1.0" encoding="UTF-8"?>
        <CardinalMPI>
          <MsgType>{$action}</MsgType>
          <Version>{$this->VERSION}</Version>
          <ProcessorId>{$this->options['processor_id']}</ProcessorId>
          <MerchantId>{$this->options['login']}</MerchantId>
          <TransactionPwd>{$this->options['password']}</TransactionPwd>
          <TransactionType>C</TransactionType>
XML;
        $data .= $this->post;
        $data .= <<<XML
        </CardinalMPI>
XML;
        return "cmpi_msg=" . urlencode(trim($data));
    }

}

?>
