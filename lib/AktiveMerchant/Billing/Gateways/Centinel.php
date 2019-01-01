<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Gateways\Centinel\CentinelResponse;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Common\SimpleXmlBuilder;

/**
 * Integration of Centinel gateway
 *
 * @author Andreas Kollaros <andreas@larium.net>
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

    protected $options;

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

        $this->buildXml(static::LOOKUP);
        $this->addInvoice($money, $options);
        $this->addCreditcard($creditcard);
        $options['description'] and $this->addOrderDescription($options['description']);


        return $this->commit(static::LOOKUP, $money, $options);
    }

    public function authenticate($options = array())
    {
        Options::required('payload, transaction_id', $options);
        $options = new Options($options);

        $this->buildXml(static::AUTHENTICATE);
        $this->addCmpiLookupData($options);

        return $this->commit(static::AUTHENTICATE, null, $options);
    }

    /* Private */

    private function buildXml($action)
    {
        $this->xml = new SimpleXmlBuilder('1.0', 'UTF-8');
        $this->xml->CardinalMPI();
        $this->xml->MsgType($action);
        $this->xml->Version(static::VERSION);
        $this->xml->ProcessorId($this->options['processor_id']);
        $this->xml->MerchantId($this->options['login']);
        $this->xml->TransactionPwd($this->options['password']);
        $this->xml->TransactionType('C');
    }

    private function addCmpiLookupData($options)
    {
        $this->xml->TransactionId($options['transaction_id']);
        $this->xml->PAResPayload($options['payload']);
    }

    private function addOrderDescription($description)
    {
        $this->xml->OrderDescription($description);
    }

    private function addInvoice($money, $options)
    {
        $order_number = isset($options['order_id']) ? $options['order_id'] : null;

        $amount = $this->isTest() ? $this->amount("1") : $this->amount($money);
        $default_currency = static::$default_currency;
        $this->xml->OrderNumber($order_number);
        $this->xml->CurrencyCode($this->currency_lookup($default_currency));
        $this->xml->Amount($amount);
        if ($options['installment']) {
            $this->xml->Installment($options['installment']);
        }
    }

    private function addCreditcard(CreditCard $creditcard)
    {
        $month = $this->cc_format($creditcard->month, 'two_digits');
        $year = $this->cc_format($creditcard->year, 'four_digits');

        $this->xml->CardNumber($creditcard->number);
        $this->xml->CardExpMonth($month);
        $this->xml->CardExpYear($year);
    }

    private function parse($body)
    {
        $response = array();

        $response['avs_result_code'] = "";
        $response['card_code'] = "";
        return $response;
    }

    private function parseCmpiLookup($body)
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

    private function parseCmpiAuthenticate($body)
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

        $data = $this->ssl_post($url, $this->postData(), $parameters->getArrayCopy());

        $options = array('test' => $this->isTest());

        switch ($action) {
            case 'cmpi_lookup':
                $response = $this->parseCmpiLookup($data);
                $options['authorization'] = $response['transaction_id'];
                break;
            case 'cmpi_authenticate':
                $response = $this->parseCmpiAuthenticate($data);
                break;

            default:
                $response = $this->parse($data);
                break;
        }

        return new CentinelResponse(
            $this->successFrom($response),
            $this->messageFrom($response),
            $response,
            $options
        );
    }

    private function successFrom($response)
    {
        $cardholderEnrolled = isset($response['acs_url']);
        $acsUrlNotProvided = empty($response['acs_url']);

        if ($cardholderEnrolled && $acsUrlNotProvided) {
            return false;
        }

        $authStatus = isset($response['pares_status']) ? $response['pares_status'] : null;
        $isCmpiAuthenticateResponse = !is_null($authStatus);
        $autheticationFailed = !in_array($authStatus, array('Y', 'A'));

        if ($isCmpiAuthenticateResponse && $autheticationFailed) {
            return false;
        }

        return $response['error_no'] == '0';
    }

    private function messageFrom($response)
    {
        return $response['error_desc'];
    }

    private function postData()
    {
        $xml = $this->xml->__toString();

        return "cmpi_msg=" . urlencode(trim($xml));
    }
}
