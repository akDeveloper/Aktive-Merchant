<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Common\SimpleXmlBuilder;

/**
 * Abstract instegration of Modirum gateway.
 *
 * This gateway is used from Cardlink (Eurobank) and Alphababank integrations.
 *
 * @author Andreas Kollaros <andreas@larium.net>
 * @license http://www.opensource.org/licenses/mit-license.php
 */
abstract class Modirum extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit
{
    const TEST_URL = '';
    const LIVE_URL = '';

    const MPI_TEST_URL = '';
    const MPI_LIVE_URL = '';

    const SALE      = 'SaleRequest';
    const AUTHORIZE = 'AuthorisationRequest';
    const CAPTURE   = 'CaptureRequest';
    const CANCEL    = 'CancelRequest';
    const REFUND    = 'RefundRequest';
    const RECURRING = 'RecurringOperationRequest';
    const STATUS    = 'StatusRequest';

    /**
     * {@inheritdoc}
     */
    public static $money_format = 'dollars';

    /**
     * {@inheritdoc}
     */
    public static $supported_countries = array('GR');

    /**
     * {@inheritdoc}
     */
    public static $supported_cardtypes = array(
        'visa',
        'master'
    );

    public static $homepage_url = 'http://www.modirum.com';

    /**
     * {@inheritdoc}
     */
    public static $default_currency = 'EUR';

    /**
     * Additional options needed by gateway
     *
     * @var array
     */
    protected $options;

    /**
     * Contains the main body of the request.
     *
     * @var array
     */
    protected $xml;

    protected $CARD_MAPPINGS = array(
        'visa'   => 'visa',
        'master' => 'mastercard'
    );

    protected $ENROLLED_MAPPINGS = array(
        1 => 'Y',
        2 => 'Y',
        3 => 'Y',
        4 => 'Y',
    );

    protected $AUTHENTICATION_MAPPINGS = array(
        1 => 'Y',
        2 => 'N',
        3 => 'N',
        4 => 'A',
    );

    public static function getMpiUrl()
    {
        return  Base::is_test() ? static::MPI_TEST_URL : static::MPI_LIVE_URL;
    }

    /**
     * creates gateway instance from given options.
     *
     * @param array $options an array contains login parameters of merchant
     *                       and optional currency.
     *
     * @return Gateway The gateway instance.
     */
    public function __construct($options = array())
    {
        $this->required_options('merchant_id, shared_secret', $options);

        if (isset($options['currency'])) {
            self::$default_currency = $options['currency'];
        }

        $this->options = $options;
    }

    public function amount($money)
    {
        return number_format($money, 2, '.', '');
    }

    /**
     * {@inheritdoc}
     */
    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $options = new Options($options);

        $this->buildXml(static::AUTHORIZE, $options);
        $this->addInvoice($money, $options, static::AUTHORIZE);
        $this->addCreditcard($creditcard, $options, static::AUTHORIZE);
        $this->addThreedSecure($options);

        return $this->commit(static::AUTHORIZE);
    }

    /**
     * {@inheritdoc}
     */
    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $options = new Options($options);

        $this->buildXml(static::SALE, $options);
        $this->addInvoice($money, $options, static::SALE);
        $this->addCreditcard($creditcard, $options, static::SALE);
        $this->addThreedSecure($options);

        return $this->commit(static::SALE);
    }

    /**
     * {@inheritdoc}
     */
    public function capture($money, $authorization, $options = array())
    {
        Options::required('order_id, payment_method', $options);

        $options = new Options($options);

        $this->buildXml(static::CAPTURE, $options);
        $this->addInvoice($money, $options, static::CAPTURE);
        $this->addIdentification($authorization, $options, static::CAPTURE);

        return $this->commit(static::CAPTURE);
    }

    /**
     * {@inheritdoc}
     */
    public function void($authorization, $options = array())
    {
        Options::required('order_id, payment_method, money', $options);

        $options = new Options($options);

        $money = $options['money'];
        $this->buildXml(static::CANCEL, $options);
        $this->addInvoice($money, $options, static::CANCEL);
        $this->addIdentification($authorization, $options, static::CANCEL);

        return $this->commit(static::CANCEL);
    }

    /**
     *
     * @param  number $money
     * @param  string $identification
     * @param  array  $options
     *
     * @return Response
     */
    public function credit($money, $identification, $options = array())
    {
        Options::required('order_id, payment_method', $options);

        $options = new Options($options);

        $this->buildXml(static::REFUND, $options);
        $this->addInvoice($money, $options, static::REFUND);
        $this->addIdentification($identification, $options, static::REFUND);

        return $this->commit(static::REFUND);
    }

    public function status($authorization)
    {
        $options['order_id'] = $this->generateUniqueId();
        $this->buildXml(static::STATUS, $options);
        $this->xml->TransactionInfo(null, static::STATUS)
            ->TxId($authorization, 'TransactionInfo');

        return $this->commit(static::STATUS);
    }

    protected function buildXml($action, $options)
    {
        $messageId = md5(uniqid($options['order_id'], true));
        $this->xml = new SimpleXmlBuilder();
        $this->xml->Message(null, null, array('lang' => 'en', 'messageId' => $messageId, 'version' => '1.0'))
            ->$action(null, 'Message')
            ->Authentication(null, $action)
            ->Mid($this->options['merchant_id'], 'Authentication');
    }

    /**
     * Adds invoice info if exists.
     *
     * @param array $options
     */
    private function addInvoice($money, $options, $action)
    {
        $this->xml->OrderInfo(null, $action)
            ->OrderId($options['order_id'], 'OrderInfo')
            ->OrderDesc($options['order_id'], 'OrderInfo')
            ->OrderAmount($this->amount($money), 'OrderInfo')
            ->Currency(static::$default_currency, 'OrderInfo')
            ->PayerEmail("", 'OrderInfo');
        if (true == $options['moto']) {
            $this->xml->MOTO(1, 'OrderInfo');
        }
    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     */
    private function addCreditcard(CreditCard $creditcard, $options, $action)
    {
        $xml = $this->xml;
        $year  = $this->cc_format($creditcard->year, 'two_digits');
        $month = $this->cc_format($creditcard->month, 'two_digits');

        $xml->PaymentInfo(null, $action)
            ->PayMethod(
                $this->CARD_MAPPINGS[CreditCard::type($creditcard->number)],
                'PaymentInfo'
            )
            ->CardPan($creditcard->number, 'PaymentInfo')
            ->CardExpDate("{$year}{$month}", 'PaymentInfo')
            ->CardCvv2($creditcard->verification_value, 'PaymentInfo')
            ->CardHolderName(trim($creditcard->name()), 'PaymentInfo');

        if ($options['installments']) {
            $xml->InstallmentParameters(null, 'PaymentInfo')
                ->ExtInstallmentoffset(0, 'InstallmentParameters')
                ->ExtInstallmentperiod($options['installments'], 'InstallmentParameters');
        }
    }

    private function addIdentification($identification, $options, $action)
    {
        $this->xml->PaymentInfo(null, $action)
            ->PayMethod($options['payment_method'], 'PaymentInfo')
            ->CardPan($identification, 'PaymentInfo');
    }

    private function addThreedSecure($options)
    {
        if ($options['enrollment_status']) {
            $xml = $this->xml;
            $xml->ThreeDSecure(null, 'PaymentInfo');
            $xml->EnrollmentStatus($options['enrollment_status'], 'ThreeDSecure');
            $xml->AuthenticationStatus($options['authentication_status'], 'ThreeDSecure');
            $xml->CAVV($options['cavv'], 'ThreeDSecure');
            $xml->XID($options['xid'], 'ThreeDSecure');
            $xml->ECI($options['eci'], 'ThreeDSecure');
        }
    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    private function parse($body, $actionResponse)
    {
        $data = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOBLANKS);

        $defaults = array(
            'error_code' => 0,
            'message' => null,
            'authorization_id' => null,
            'status' => null,
            'response_code' => null,
            'notice' => null,
        );

        $message = $data->Message; # Always returned

        $messageXml = $this->normalizeXml($message);

        $digest = $this->calculateDigest($messageXml);
        if ($digest !== $data->Digest->__toString()) {
            $defaults['notice'] = 'Invalid digest.';
        }
        if (isset($message->ErrorMessage)) { # we 've got error
            $error_code = $message->ErrorMessage->ErrorCode->__toString();
            $description = $message->ErrorMessage->Description->__toString();

            $description = $this->messageFromErrorCode($error_code, $description);

            return array_merge($defaults, array(
                'error_code' => $error_code,
                'message'    => $description
            ));
        } else {
            $response = $message->$actionResponse;
            $statusResponse = array();

            if (isset($response->TransactionDetails)) {
                $statusResponse = $this->parseStatusResponse($response);
                $response = $response->TransactionDetails;
            }
            $description = $response->Description->__toString();
            if (isset($response->ErrorCode)) {
                $error_code = $response->ErrorCode->__toString();

                return array_merge($defaults, array(
                    'error_code' => $error_code,
                    'message'    => $this->messageFromErrorCode($error_code, $description)
                ));
            } else {
                preg_match("/(\w+),\s(\w+)\sresponse\scode\s(\w+)/", $description, $desc);
                $response_code = array_pop($desc);

                if (array_key_exists($response_code, static::$statusCode)) {
                    $description = static::$statusCode[$response_code];
                }

                return array_merge($defaults, array(
                    'message'           => $description,
                    'authorization_id'  => $response->TxId->__toString(),
                    'payment_ref'       => $response->PaymentRef->__toString(),
                    'risk_score'        => $response->RiskScore->__toString(),
                    'response_code'     => $response_code,
                    'status'            => $response->Status->__toString(),
                    'order_id'          => $response->OrderId->__toString(),
                    'payment_total'     => $response->PaymentTotal->__toString()
                ), $statusResponse);
            }
        }
    }

    private function parseStatusResponse($response)
    {
        $status = array();
        foreach ($response->TransactionDetails->Attribute as $a) {
            $key = $a->attributes()->__toString();
            $key = strtolower(str_replace(' ', '_', $key));
            $value = $a->__toString();
            $status[$key] = $value;
        }

        return $status;
    }

    private function normalizeXml($message)
    {
        $messageXml = $message->asXML();

        preg_match('/messageId=\"[\w]+\"/', $messageXml, $m);
        $messageId = $m[0];
        $messageXml = str_replace(
            'version="1.0" '.$messageId,
            $messageId.' version="1.0"',
            $messageXml
        );

        return $messageXml;
    }

    private function messageFromErrorCode($error_code, $description = null)
    {
        if (array_key_exists($error_code, static::$errorCode)) {
            return static::$errorCode[$error_code];
        }

        return $description;
    }

    /**
     *
     * @param  string $action
     *
     * @return Response
     */
    private function commit($action)
    {
        $url = $this->isTest() ? static::TEST_URL : static::LIVE_URL;

        $headers = array(
            "Content-type: text/xml"
        );
        $xml = $this->postData($action);

        $data = $this->ssl_post($url, $xml, array('headers' => $headers));

        $responseAction = str_replace('Request', 'Response', $action);
        $response = $this->parse($data, $responseAction);

        $test_mode = $this->isTest();

        return new Response(
            $this->successFrom($response),
            $this->messageFrom($response),
            $response,
            array(
                'test' => $test_mode,
                'authorization' => $response['authorization_id'],
            )
        );
    }

    /**
     * Returns success flag from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    private function successFrom($response)
    {
        return $response['response_code'] == '00';
    }

    /**
     * Returns message (error explanation  or success) from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    private function messageFrom($response)
    {
        return $response['message'];
    }

    /**
     * Adds final parameters to post data and
     * build $this->post to the format that your payment gateway understands
     *
     * @param  string $action
     *
     * @return string
     */
    private function postData($action)
    {
        $xml = $this->xml->__toString();
        $xml = str_replace('<?xml version="1.0" encoding="UTF-8"?>', null, $xml);
        $xml = str_replace("\n", null, $xml);
        $digest = $this->calculateDigest($xml);
        $xml .= '<Digest>'.$digest.'</Digest>';
        $content = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VPOS xmlns="http://www.modirum.com/schemas">%s</VPOS>';

        return sprintf($content, $xml);
    }

    /**
     * Calculate digest for message.
     *
     * @param string $xml
     * @return string
     */
    protected function calculateDigest($xml)
    {
        $xml = $this->canonicalize($xml);
        $utf8 = utf8_encode($xml . $this->options['shared_secret']);

        return base64_encode(sha1($utf8, true));
    }

    /**
     * Canonicalize message element according to specs.
     *
     * @param string $message
     * @return string
     */
    protected function canonicalize($xml)
    {
        $xml = str_replace('<?xml version="1.0" encoding="UTF-8"?>', null, $xml);

        $replacement = 'xmlns="http://www.modirum.com/schemas" ';
        $start  = 0;
        $length = 9;

        $xml = substr($xml, $start, $length)
             . substr_replace($xml, $replacement, $start, $length);

        $dom = new \DOMDocument("1.0", "utf-8");
        $dom->loadXML($xml);
        $xml = $dom->C14N();

        return $xml;
    }

    public static $statusCode = array(
        '00' => 'APPROVED OR COMPLETED SUCCESSFULLY',
        '01' => 'REFER TO CARD ISSUER',
        '02' => 'REFER TO SPECIAL CONDITIONS FOR CARD ISSUER',
        '03' => 'INVALID MERCHANT',
        '04' => 'PICK-UP',
        '05' => 'DO NOT HONOR',
        '06' => 'ERROR',
        '08' => 'HONOR WITH IDENTIFICATION',
        '11' => 'APPROVED (VIP)',
        '12' => 'INVALID TRANSACTION',
        '13' => 'INVALID AMOUNT',
        '14' => 'INVALID CARD NUMBER (NO SUCH NUMBER)',
        '15' => 'NO SUCH ISSUER',
        '30' => 'FORMAT ERROR',
        '31' => 'BANK NOT SUPPORTED BY SWITCH',
        '33' => 'EXPIRED CARD',
        '36' => 'RESTRICTED CARD',
        '38' => 'ALLOWABLE PIN TRIES EXCEEDED',
        '41' => 'LOST CARD',
        '43' => 'STOLEN CARD, PICK UP',
        '51' => 'NOT SUFFICIENT FUND',
        '54' => 'EXPIRED CARD',
        '55' => 'INCORRECT PERSONAL IDENTIFICATION NUMBER',
        '56' => 'NO RECORD FOUND',
        '57' => 'TRANSACTION NOT PERMITTED TO CARDHOLDER',
        '61' => 'EXCEEDS WITHDRAWAL AMOUNT LIMIT',
        '62' => 'RESTRICTED CARD',
        '65' => 'EXCEEDS WITHDRAWAL FREQUENCY LIMIT',
        '68' => 'RESPONSE RECEIVED TOO LATE',
        '75' => 'ALLOWABLE NUMBER OF PIN TRIES EXCEEDED',
        '76' => 'APPROVED COUNTRY CLUB',
        '77' => 'APPROVED PENDING IDENTIFICATION (SIGN PAPER DRAFT)',
        '78' => 'APPROVED BLIND',
        '79' => 'APPROVED ADMINISTRATIVE TRANSACTION',
        '80' => 'APPROVED NATIONAL NEG HIT OK',
        '81' => 'APPROVED COMMERCIAL',
        '82' => 'RESERVED FOR PRIVATE USE',
        '83' => 'NO ACCOUNTS',
        '84' => 'NO PBF',
        '85' => 'BF UPDATE ERROR',
        '86' => 'INVALID AUTHORIZATION TYPE',
        '87' => 'BAD TRACK DATA',
        '88' => 'PTLF ERROR',
        '89' => 'INVALID ROUTE SERVICE',
        '94' => 'DUPLICATE TRANSACTION',
        'N0' => 'UNABLE TO AUTHORIZE',
        'N1' => 'INVALID PAN LENGTH',
        'N2' => 'PREAUTHORIZATION FULL',
        'N3' => 'MAXIMUM ONLINE REFUND REACHED',
        'N4' => 'MAXIMUM OFFLINE REFUND REACHED',
        'N5' => 'MAXIMUM CREDIT PER REFUND REACHED',
        'N6' => 'MAXIMUM REFUND CREDIT REACHED',
        'N7' => 'CUSTOMER SELECTED NEGATIVE FILE REASON',
        'N8' => 'OVER FLOOR LIMIT',
        'N9' => 'MAXIMUM NUMBER OF REFUND CREDIT',
        'O1' => 'FILE PROBLEM',
        'O2' => 'ADVANCE LESS THAN MINIMUM',
        'O3' => 'DELINQUENT',
        'O4' => 'OVER LIMIT TABLE',
        'O5' => 'PIN REQUIRED',
        'O6' => 'MOD 10 CHECK',
        'O7' => 'FORCE POST',
        'O8' => 'BAD PBF',
        'O9' => 'NEG FILE PROBLEM',
        'P0' => 'CAF PROBLEM',
        'P1' => 'OVER DAILY LIMIT',
        'P2' => 'CAPF NOT FOUND',
        'P3' => 'ADVANCE LESS THAN MINIMUM',
        'P4' => 'NUMBER TIMES USED',
        'P5' => 'DELINQUENT',
        'P6' => 'OVER LIMIT TABLE',
        'P7' => 'ADVANCE LESS THAN MINIMUM',
        'P8' => 'ADMINISTRATIVE CARD NEEDED',
        'P9' => 'ENTER LESSER AMOUNT',
        'Q0' => 'INVALID TRANSACTION DATE',
        'Q1' => 'INVALID EXPIRATION DATE',
        'Q2' => 'INVALID TRANSACTION CODE',
        'Q3' => 'ADVANCE LESS THAN MINIMUM',
        'Q4' => 'NUMBER TIMES USED',
        'Q5' => 'DELINQUENT',
        'Q6' => 'OVER LIMIT TABLE',
        'Q7' => 'AMOUNT OVER MAXIMUM',
        'Q8' => 'ADMINISTRATIVE CARD NOT',
        'Q9' => 'ADMINISTRATIVE CARD NOT',
        'R0' => 'APPROVED ADMINISTRATIVE',
        'R1' => 'APPROVED ADMINISTRATIVE',
        'R2' => 'APPROVED ADMINISTRATIVE',
        'R3' => 'CHARGEBACK, CUSTOMER FILE',
        'R4' => 'CHARGEBACK, CUSTOMER FILE',
        'R5' => 'CHARGEBACK, INCORRECT',
        'R6' => 'CHARGEBACK, INCORRECT',
        'R7' => 'ADMINISTRATIVE TRANSACTIONS',
        'R8' => 'CARD ON NATIONAL NEGATIVE FILE',
        'S4' => 'PTLF FULL',
        'S5' => 'CHARGEBACK APPROVED',
        'S6' => 'CHARGEBACK APPROVED',
        'S7' => 'CHARGEBACK ACCEPTED',
        'S8' => 'ADMN FILE PROBLEM',
        'S9' => 'UNABLE TO VALIDATE PIN; SECURITY MODULE IS DOWN',
        'T1' => 'INVALID CREDIT CARD ADVANCE INCREMENT',
        'T2' => 'INVALID TRANSACTION DATE',
        'T3' => 'CARD NOT SUPPORTED',
        'T4' => 'AMOUNT OVER MAXIMUM',
        'T5' => 'CAF STATUS = 0 OR 9',
        'T6' => 'BAD UAF',
        'T7' => 'CASH BACK EXCEEDS DAILY',
        'T8' => 'INVALID ACCOUNT',
    );

    public static $errorCode = array(
        'M1' => 'Invalid merchant id',
        'M2' => 'Authentication failed (wrong password or digest)',
        'SE' => 'System Error (message contains error id, system or configuration error to be investigated)',
        'XE' => 'Invalid XML request not parseable or does not validate',
        'I0' => 'Invalid or unsupported request',
        'I1' => 'Message contains invalid data item',
        'I2' => 'Message contains invalid installment parameters',
        'I3' => 'Message contains invalid recurring parameters',
        'I4' => 'Message contains invalid or mismatching card data',
        'I5' => 'Message contains invalid expiration date card data',
        'I6' => 'Selected payment method does is not supported or not matching the payment card',
        'O1' => 'Operation is not allowed because logic is violated or wrong amounts',
        'O2' => 'Original transaction is not found to perform operation.',
    );
}
