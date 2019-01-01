<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Http\Adapter\SoapClientAdapter;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Http\Adapter\Exception as AdapterException;

/**
 * PiraeusPaycenter gateway
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class PiraeusPaycenter extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit,
    Interfaces\Store
{
    const TEST_URL     = 'https://paycenter.piraeusbank.gr/services/paymentgateway.asmx';
    const LIVE_URL     = 'https://paycenter.piraeusbank.gr/services/paymentgateway.asmx';
    const WSDL         = 'https://paycenter.piraeusbank.gr/services/paymentgateway.asmx?WSDL';
    const TOKEN_WSDL   = 'https://paycenter.piraeusbank.gr/services/TokenService.asmx?WSDL';
    const BIN_WSDL     = 'https://paycenter.piraeusbank.gr/Services/BinServiceClient.asmx?WSDL';

    const ACTION       = 'ProcessTransaction';
    const TOKEN_ACTION = 'RequestToken';
    const BIN_ACTION   = 'GetInstallmentsSupported';

    /**
     * {@inheritdoc }
     */
    public static $supported_countries = array('GR');

    /**
     * {@inheritdoc }
     */
    public static $homepage_url = 'http://www.piraeusbank.gr';

    /**
     * {@inheritdoc }
     */
    public static $display_name = 'Piraeus Paycenter';

    /**
     * {@inheritdoc }
     */
    public static $default_currency = 'EUR';

    protected $options;

    private $post = array();

    private $CURRENCY_MAPPINGS = array(
        'USD' => 840, 'GRD' => 300, 'EUR' => 978
    );

    private $ENROLLED_MAPPINGS = array(
        'Y' => 'Yes',
        'N' => 'No',
        'U' => 'Undefined'
    );

    private $PARES_MAPPINGS = array(
        'U' => 'Unknown',
        'A' => 'Attempted',
        'Y' => 'Succeded',
        'N' => 'Failed'
    );

    private $SIGNATURE_MAPPINGS = array(
        'Y' => 'Yes',
        'N' => 'No',
        'U' => 'Undefined'
    );

    private $CARD_MAPPINGS = array(
        'visa'              => 'VISA',
        'master'            => 'MasterCard',
        'maestro'           => 'Maestro',
        'diners_club'       => 'DinersClub',
        'discover'          => 'DinersClub',
        'american_express'  => 'AMEX',
        'UNKNOWN'           => 'UNKNOWN'
    );

    /**
     *
     * @param array $options
     */
    public function __construct($options = array())
    {
        $this->required_options('acquire_id, merchant_id, pos_id, user, password, channel_type', $options);

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
     *
     * @param number     $money
     * @param CreditCard $creditcard
     * @param array      $options
     *
     * @return Response
     */
    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $this->post = array();
        $authorizeDays = isset($options['authorize_days'])
            ? (int) $options['authorize_days']
            : 30;
        $this->addInvoice($money, $options);
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['ExpirePreauth'] = $authorizeDays;
        $this->addCreditcard($creditcard);
        if ($creditcard->type !== 'UNKNOWN') {
            $this->addCentinelData($options);
        }

        return $this->commit('AUTHORIZE', $money);
    }

    /**
     *
     * @param number     $money
     * @param CreditCard $creditcard
     * @param array      $options
     *
     * @return Response
     */
    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $this->post = array();
        $this->addInvoice($money, $options);
        $this->addCreditcard($creditcard);
        if ($creditcard->type !== 'UNKNOWN') {
            $this->addCentinelData($options);
        }

        return $this->commit('SALE', $money);
    }

    /**
     *
     * @param number $money
     * @param string $authorization
     * @param array  $options
     *
     * @return Response
     */
    public function capture($money, $authorization, $options = array())
    {
        $this->post = array();
        $amount = $this->amount($money);
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['TransactionReferenceID'] = $authorization;
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['CurrencyCode'] = $this->currency_lookup(self::$default_currency);
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['Amount'] = $amount;

        return $this->commit('SETTLE', $money);
    }

    /**
     *
     * @param string $authorization
     * @param array  $options Required options are:
     *                        money The amount to refund.
     *                        order_id Unique merchant reference.
     * @return Response
     */
    public function void($authorization, $options = array())
    {
        Options::required('money, order_id', $options);

        $this->post = array();
        $money = $options['money'];
        $amount = $this->amount($money);
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['MerchantReference'] = $options['order_id'];
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['TransactionReferenceID'] = $authorization;
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['CurrencyCode'] = $this->currency_lookup(self::$default_currency);
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['Amount'] = $amount;

        return $this->commit('VOIDREQUEST', null);
    }

    /**
     *
     * @param number $money
     * @param string $identification
     * @param array  $options
     *
     * @return Response
     */
    public function credit($money, $identification, $options = array())
    {
        $this->post = array();
        $amount = $this->amount($money);
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['MerchantReference'] = $options['order_id'];
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['TransactionReferenceID'] = $identification;
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['CurrencyCode'] = $this->currency_lookup(self::$default_currency);
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['Amount'] = $amount;

        return $this->commit('REFUND', $money);
    }

    public function store(CreditCard $creditcard, $options = array())
    {
        Options::required('order_id', $options);

        $this->post = array();
        $password = md5($this->options['password']);
        $month = $this->cc_format($creditcard->month, 'two_digits');
        $this->post[static::TOKEN_ACTION]['Request']['CRS'] = $this->options['crs'];
        $this->post[static::TOKEN_ACTION]['Request']['Username'] = $this->options['user'];
        $this->post[static::TOKEN_ACTION]['Request']['Password'] = $password;
        $this->post[static::TOKEN_ACTION]['Request']['MerchantReference'] = $options['order_id'];
        $this->post[static::TOKEN_ACTION]['Request']['CardNumber'] = $creditcard->number;
        $this->post[static::TOKEN_ACTION]['Request']['ExpirationMonth'] = $month;
        $this->post[static::TOKEN_ACTION]['Request']['ExpirationYear'] = $creditcard->year;

        return $this->commit('TOKEN', 0);
    }

    public function unstore($reference, $options = array())
    {

    }

    public function followUp($merchantReference)
    {
        $this->post = array();
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['MerchantReference'] = $merchantReference;

        return $this->commit('FOLLOW_UP', 0);
    }

    public function isAvailable()
    {
        $this->post = array();

        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['MerchantReference'] = $this->generateUniqueId();
        return $this->commit('ISAVAILABLE', 0);
    }


    public function supportsInstallment($amount, CreditCard $creditcard)
    {
        $this->post[static::BIN_ACTION]['request']['Header']['Merchant']['MerchantID']  = $this->options['merchant_id'];
        $this->post[static::BIN_ACTION]['request']['Header']['Merchant']['AcquirerID']  = $this->options['acquire_id'];
        $this->post[static::BIN_ACTION]['request']['Header']['Merchant']['User']        = $this->options['user'];
        $this->post[static::BIN_ACTION]['request']['Header']['Merchant']['Password']    = md5($this->options['password']);
        $this->post[static::BIN_ACTION]['request']['Body']['Amount']                    = $this->amount($amount);
        $this->post[static::BIN_ACTION]['request']['Body']['Bin']                       = substr($creditcard->number, 0, 8);

        return $this->commit('BIN', 0);
    }

    /* Private */

    /**
     *
     * @param array $options
     */
    private function addInvoice($money, $options)
    {
        $options = new Options($options);
        $currency = $options['currency'] ?: self::$default_currency;
        $amount = $this->amount($money);
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['MerchantReference'] = $options['order_id'];
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['EntryType'] = 'KeyEntry';
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['CurrencyCode'] = $this->currency_lookup($currency);
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['Amount'] = $amount;
        if (isset($options['installments'])) {
            $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['Installments'] = $options['installments'];
        }
    }

    /**
     *
     * @param CreditCard $creditcard
     */
    private function addCreditcard(CreditCard $creditcard)
    {
        $month = null;
        if ($creditcard->month) {
            $month = $this->cc_format($creditcard->month, 'two_digits');
        }

        $cardholdername = trim(strtoupper($creditcard->name())) ?: null;
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['CardInfo']['CardType'] = $this->CARD_MAPPINGS[$creditcard->type];
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['CardInfo']['CardNumber'] = $creditcard->number;
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['CardInfo']['CardHolderName'] = $cardholdername;
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['CardInfo']['ExpirationMonth'] = $month;
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['CardInfo']['ExpirationYear'] = $creditcard->year;
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['CardInfo']['Cvv2'] = $creditcard->verification_value;
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['CardInfo']['Aid'] = '';
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['CardInfo']['Emv'] = '';
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['CardInfo']['PinBlock'] = '';
    }

    /**
     * Add required data from 3D centinel verification
     *
     * @param array $options
     */
    private function addCentinelData($options)
    {
        $this->required_options('cavv, eci_flag, xid, enrolled, pares_status, signature_verification', $options);

        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['AuthInfo']['Cavv'] = $options['cavv'];
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['AuthInfo']['Eci'] = $options['eci_flag'];
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['AuthInfo']['Xid'] = $options['xid'];
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['AuthInfo']['Enrolled'] = $this->ENROLLED_MAPPINGS[$options['enrolled']];
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['AuthInfo']['PAResStatus'] = $this->PARES_MAPPINGS[$options['pares_status']];
        $this->post['ProcessTransaction']['TransactionRequest']['Body']['TransactionInfo']['AuthInfo']['SignatureVerification'] = $this->SIGNATURE_MAPPINGS[$options['signature_verification']];
    }

    /**
     *
     * @param string $body
     */
    private function parse($body)
    {
        $response = array(
            'result_code' => 500,
            'result_description' => null
        );

        if (isset($body->TransactionResponse)) { # Handle transaction response.

            $header = $body->TransactionResponse->Header;
            $transaction = $body->TransactionResponse->Body->TransactionInfo;

            $response['request_type'] = $header->RequestType;
            $response['result_code'] = (string) $header->ResultCode;
            $response['result_description'] = (string) $header->ResultDescription;
            $response['support_reference_id'] = (string) $header->SupportReferenceID;

            $response['status'] = (string) $transaction->StatusFlag;

            if ($response['result_code'] == 0) {
                $response['response_description'] = (string) $transaction->ResponseDescription;
                $response['authorization_id'] = (string) $transaction->TransactionID;
                $response['response_code'] = (string) $transaction->ResponseCode;
                $response['approval_code'] = (string) $transaction->ApprovalCode;
                $response['package_no'] = (string) $transaction->PackageNo;
                $response['retrieval_ref'] = (string) $transaction->RetrievalRef;
                $response['merchant_reference'] = (string) $transaction->MerchantReference;
                $response['transaction_trace_num'] = (string) $transaction->TransactionTraceNum;
                $response['transaction_date_time'] = (string) $transaction->TransactionDateTime;
                if (isset($transaction->Token)) {
                    $response['token'] = (string) $transaction->Token;
                }
            }
        }

        if (isset($body->RequestTokenResult)) { # Handle tokenization response.
            $result = $body->RequestTokenResult;
            $response['result_code'] = (string) $result->ResultCode;
            $response['result_description'] = (string) $result->ResultDescription;
            $response['status'] = (string) $result->StatusFlag;
            $response['support_reference_id'] = (string) $result->SupportReferenceID;
            $response['merchant_reference'] = (string) $result->MerchantReference;
            if ($response['result_code'] == 0) {
                $response['authorization_id'] = (string) $result->Token;
            }
        }

        if (isset($body->GetInstallmentsSupportedResult)) {
            $result = $body->GetInstallmentsSupportedResult;
            $response['support_reference_id'] = (string) $result->Header->SupportReferenceID;
            $response['supports_installments'] = (string) $result->Body->SupportsInstallments;
            $response['installments'] = (string) $result->Body->Installments;
            $response['result_code'] = (string) $result->Body->ResultCode;
            $response['result_description'] = (string) $result->Body->ResultDescription;
        }

        return $response;
    }

    /**
     *
     * @param string $action
     * @param number $money
     * @param array  $parameters
     *
     * @return Response
     */
    private function commit($action, $money, $parameters = array())
    {
        $postData = $this->postData($action, $parameters);

        $adapter = new SoapClientAdapter();
        $adapter->setOption('action', static::ACTION);
        $url = static::WSDL;
        if ($action == 'TOKEN') {
            $adapter->setOption('action', static::TOKEN_ACTION);
            $url = static::TOKEN_WSDL;
        }
        if ($action == 'BIN') {
            $adapter->setOption('action', static::BIN_ACTION);
            $url = static::BIN_WSDL;
        }

        $this->setAdapter($adapter);

        $test_mode = $this->isTest();

        try {
            $data = $this->ssl_post($url, $postData);
        } catch (AdapterException $e) {
            return new Response(
                false,
                $e->getMessage(),
                array(),
                array(
                    'test' => $test_mode,
                    'authorization' => null
                )
            );
        }

        $response = $this->parse($data);

        return new Response(
            $this->successFrom($response, $action == 'TOKEN', $action == 'BIN'),
            $this->messageFrom($response),
            $response,
            array(
                'test' => $test_mode,
                'authorization' => isset($response['authorization_id']) ? $response['authorization_id'] : null
            )
        );
    }

    /**
     *
     * @param array $response
     *
     * @return string
     */
    private function successFrom($response, $token = false, $bin = false)
    {
        if ($token) {
            return $response['result_code'] == '0'
                && $response['status'] == 'Success';
        }

        if ($bin) {
            return $response['result_code'] == 0;
        }

        return $response['result_code'] == '0'
            && isset($response['response_code'])
            && ($response['response_code'] == '00'
            || $response['response_code'] == '11');
    }

    /**
     *
     * @param array $response
     *
     * @return string
     */
    private function messageFrom($response)
    {
        return isset($response['response_description'])
            ? $response['response_description']
            : $response['result_description'];
    }

    /**
     *
     * @param array $response
     *
     * @return string
     */
    private function fraudReviewFrom($response)
    {

    }

    /**
     *
     * @param array $response
     *
     * @return array
     */
    private function avsResultFrom($response)
    {
        return array('code' => $response['avs_result_code']);
    }

    /**
     *
     * @param string $action
     * @param array  $parameters
     */
    private function postData($action, $parameters = array())
    {
        if ($action == 'TOKEN') {
            return $this->post;
        }
        /**
         * Add final parameters to post data and
         * build $this->post to the format that your payment gateway understands
         */
        $password = md5($this->options['password']);
        $this->post['ProcessTransaction']['TransactionRequest']['Header']['RequestType'] = $action;
        $this->post['ProcessTransaction']['TransactionRequest']['Header']['RequestMethod'] = 'SYNCHRONOUS';
        $this->post['ProcessTransaction']['TransactionRequest']['Header']['MerchantInfo']['AcquirerID'] = $this->options['acquire_id'];
        $this->post['ProcessTransaction']['TransactionRequest']['Header']['MerchantInfo']['MerchantID'] = $this->options['merchant_id'];
        $this->post['ProcessTransaction']['TransactionRequest']['Header']['MerchantInfo']['PosID'] = $this->options['pos_id'];
        $this->post['ProcessTransaction']['TransactionRequest']['Header']['MerchantInfo']['ChannelType'] = $this->options['channel_type'];
        $this->post['ProcessTransaction']['TransactionRequest']['Header']['MerchantInfo']['User'] = $this->options['user'];
        $this->post['ProcessTransaction']['TransactionRequest']['Header']['MerchantInfo']['Password'] = $password;

        return $this->post;
    }
}
