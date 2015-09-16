<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Gateways\Centinel\CentinelResponse;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Common\XmlBuilder;

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
    const LIVE_URL = 'https://centinel.piraeusbank.fdsecure.com/maps/txns.asp';


    /**
     *  Centicel actions
     */
    const AUTHENTICATE = 'cmpi_authenticate';
    const LOOKUP  = 'cmpi_lookup';

    /**
     * {@inheritdoc }
     */
    public static $supported_countries = array('US', 'GR');

    /**
     * {@inheritdoc }
     */
    public static $homepage_url = 'http://www.cardinalcommerce.com';

    /**
     * {@inheritdoc }
     */
    public static $display_name = 'Centinel 3D Secure';

    /**
     * {@inheritdoc }
     */
    public static $money_format = 'cents';

    /**
     * {@inheritdoc }
     */
    public static $default_currency = 'EUR';

    private $options;

    private $post;

    private $xml;

    const VERSION = '1.7';

    public function __construct($options = array())
    {
        Options::required('login, password, processor_id', $options);

        $this->options = new Options($options);

        $this->options['currency'] and self::$default_currency = $this->options['currency'];

    }

    public function lookup($money, CreditCard $creditcard, $options = array())
    {

        Options::required('order_id', $options);
        $options = new Options($options);

        $this->build_xml(static::LOOKUP, function($xml) use ($money, $creditcard, $options) {
            $this->add_invoice($money, $options, $xml);
            $this->add_creditcard($creditcard, $xml);
            $options['description'] and $this->add_order_description($options['description'], $xml);
        });


        return $this->commit(static::LOOKUP, $money, $options);
    }

    public function authenticate($options = array())
    {
        Options::required('payload, transaction_id', $options);
        $options = new Options($options);

        $this->build_xml(static::AUTHENTICATE, function($xml) use ($options){
            $this->add_cmpi_lookup_data($options, $xml);
        });

        return $this->commit(static::AUTHENTICATE, null, $options);
    }

    /* Private */

    private function build_xml($action, $block)
    {
        $this->xml = new XmlBuilder();
        $this->xml->instruct('1.0', 'UTF-8');
        $this->xml->CardinalMPI(function($xml) use ($action, $block){
            $xml->MsgType($action);
            $xml->Version(static::VERSION);
            $xml->ProcessorId($this->options['processor_id']);
            $xml->MerchantId($this->options['login']);
            $xml->TransactionPwd($this->options['password']);
            $xml->TransactionType('C');
            $block($xml);
        });
    }

    private function add_cmpi_lookup_data($options, $xml)
    {
        $xml->TransactionId($options['transaction_id']);
        $xml->PAResPayload($options['payload']);
    }

    private function add_order_description($description, $xml)
    {
        $xml->OrderDescription($description);
    }

    private function add_invoice($money, $options, $xml)
    {
        $order_number = isset($options['order_id']) ? $options['order_id'] : null;

        $amount = $this->isTest() ? $this->amount("1") : $this->amount($money);
        $default_currency = static::$default_currency;
        $xml->OrderNumber($order_number);
        $xml->CurrencyCode($this->currency_lookup($default_currency));
        $xml->Amount($amount);
        if ($options['installment']) {
            $xml->Installment($options['installment']);
        }
    }

    private function add_creditcard(CreditCard $creditcard, $xml)
    {
        $month = $this->cc_format($creditcard->month, 'two_digits');
        $year = $this->cc_format($creditcard->year, 'four_digits');

        $xml->CardNumber($creditcard->number);
        $xml->CardExpMonth($month);
        $xml->CardExpYear($year);
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

    protected function commit($action, $money, $parameters)
    {
        $url = $this->isTest() ? static::TEST_URL : static::LIVE_URL;


        $xml = $this->xml->__toString();

        $data = $this->ssl_post($url, $this->post_data($xml), $parameters->getArrayCopy());

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

    private function post_data($xml)
    {
        return "cmpi_msg=" . urlencode(trim($xml));
    }

}

?>
