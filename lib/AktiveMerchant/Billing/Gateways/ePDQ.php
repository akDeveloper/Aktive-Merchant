<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;

/**
 * AktiveMerchant Barclay's ePDQ Gateway Library
 *
 * @package Aktive-Merchant
 * @author  Kieran Graham (AirPOS Ltd.)
 */
class ePDQ extends Gateway implements Interfaces\Charge
{
    const TEST_URL = 'https://secure2.mde.epdq.co.uk:11500';
    const LIVE_URL = 'https://secure2.mde.epdq.co.uk:11500';

    private $CARD_TYPE_MAPPINGS = array(
        'visa' => 1,
        'master' => 2,
        'american_express' => 8,
        'discover' => 3,
    );

    private $COUNTRY_CODE_MAPPINGS = array(
        'GB' => 826,
        'US' => 840,
    );

    private $CVV_RESPONSE_MAPPINGS = array(
        '0' => 'X',
        '1' => 'M',
        '2' => 'N',
        '3' => 'P',
        '4' => 'S',
        '5' => 'X',
        '6' => 'I',
        '7' => 'U',
    );

    private $TRANSACTION_STATUS_MAPPINGS = array(
        'accepted' => "A",
        'declined' => "D",
        'fraud' => "F",
        'error' => "E",
        'void' => "V",
        'reserved' => "U"
    );

    const APPROVED = 1;
    const DECLINED = 50;
    const DECLINED_FRAUDULENT = 500;
    const DECLINED_FRAUDULENT_VOIDED = 501;
    const DECLINED_FRAUDULENT_REVIEW = 502;
    const CVV_FAILURE = 1055;

    private $FRAUDULENT = array(
        'DECLINED_FRAUDULENT',
        'DECLINED_FRAUDULENT_VOIDED',
        'DECLINED_FRAUDULENT_REVIEW',
        'CVV_FAILURE'
    );

    protected $options = array();

    private $xml;

    private $payment_mode = "P"; #Production

    private $payment_mech_type = "CreditCard";

    public static $default_currency = 'GBP';

    public static $supported_countries = array('US', 'GB');

    public static $supported_cardtypes = array('visa', 'master', 'american_express', 'discover');

    public static $homepage_url = 'http://www.barclaycard.co.uk/';

    public static $display_name = 'ePDQ';

    public static $money_format = 'cents';

    /**
     * __constructor
     */
    public function __construct($options = array())
    {
        trigger_error('ePDQ gateway is deprecated. An integration of Epdq DirectLink should be implemented.');

        $this->required_options('login, password, client_id', $options);

        if (isset($options['currency'])) {
            self::$default_currency = $options['currency'];
        }

        $this->options = $options;
    }

    /**
     * Authorize
     *
     * @param int - Amount to charge for authorize.
     * @param CreditCard - Credit card to charge.
     * @param array - Options to pass.
     * @return Response
     */
    public function authorize($amount, CreditCard $creditcard, $options = array())
    {
        $this->buildXml($amount, $creditcard, 'PreAuth', $options);
        return $this->commit(__FUNCTION__);
    }

    /**
     * Purchase
     *
     * @param int - Amount to charge for purchase.
     * @param Merchant_billing_CreditCard - Credit card to charge.
     * @param array - Options to pass.
     * @return Response
     */
    public function purchase($amount, CreditCard $creditcard, $options = array())
    {
        $this->buildXml($amount, $creditcard, 'Auth', $options);
        return $this->commit(__FUNCTION__);
    }

    /**
     * Capture
     *
     * @param int - Amount to capture.
     * @param  -
     * @param array - Options to pass.
     */
    public function capture($amount, $authorization, $options = array())
    {
        $options = array_merge($options, array('authorization', $authorization));
        $this->buildXml($amount, $creditcard, 'PostAuth', $options);
    }

    /**
     * Void
     *
     * @param string - Payment identification.
     * @param array - Options to pass.
     */
    public function void($identification, $options = array())
    {
        $this->buildXml($amount, $creditcard, 'Void', $options);
    }

    /**
     * Build XML
     *
     * @param int
     * @param CreditCard
     * @param string
     * @param array
     */
    private function buildXml($amount, CreditCard $creditcard, $type, $options = array())
    {
        $this->startXml();
        $this->insertData($amount, $creditcard, $type, $options);
        $this->endXml();
    }

    /**
     * Insert Data
     *
     * @param int
     * @param CreditCard
     * @param string
     * @param array
     */
    private function insertData($amount, CreditCard $creditcard, $type, $options = array())
    {
        $month = $this->cc_format($creditcard->month, 'two_digits');
        $year = $this->cc_format($creditcard->year, 'two_digits');

        $this->xml .= <<<XML
 <OrderFormDoc>
 <Mode DataType="String">{$this->payment_mode}</Mode>
 <Consumer>
 <PaymentMech>
 <Type DataType="String">{$this->payment_mech_type}</Type>
 <CreditCard>
 <Number DataType="String">{$creditcard->number}</Number>
 <Expires DataType="ExpirationDate">{$month}/{$year}</Expires>
 <Cvv2Val DataType="String">{$creditcard->verification_value}</Cvv2Val>
 <Cvv2Indicator DataType="String">1</Cvv2Indicator>
 </CreditCard>
 </PaymentMech>
 </Consumer>
XML;
        $this->addTransactionElement($amount, $type, $options);
        $this->addBillingAddress($options);
        $this->addShippingAddress($options);
    }

    /**
     * Add Transaction Element
     *
     * @param int
     * @param string
     * @param array
     */
    private function addTransactionElement($amount, $type, $options)
    {

        if ($type == 'PreAuth' || $type == 'Auth') {
            $this->xml .= <<<XML
 <Transaction>
 <Type DataType="String">{$type}</Type>
 <CurrentTotals>
 <Totals>
 <Total DataType="Money" Currency="{$this->currency_lookup(self::$default_currency)}">{$amount}</Total>
 </Totals>
 </CurrentTotals>
 </Transaction>
XML;
        } elseif ($type == 'PostAuth' || $type == 'Void') {
            $this->xml .= <<<XML
 <Transaction>
 <Type DataType="String">{$type}</Type>
 <Id DataType="String">{$options['authorization']}</Id>
 <CurrentTotals>
 <Totals>
 <Total DataType="Money" Currency="{$this->currency_lookup(self::$default_currency)}">{$amount}</Total>
 </Totals>
 </CurrentTotals>
 </Transaction>
XML;
        }
    }

    /**
     * Add Billing Address
     *
     * @param array
     */
    private function addBillingAddress($options)
    {
        if (isset($options['billing_address'])) {
            $this->xml .= <<<XML
 <BillTo>
 <Location>
 <Email DataType="String">{$options['email']}</Email>
XML;
            $this->addAddress($options['billing_address']);
            $this->xml .= <<<XML
 <TelVoice DataType="String">{$options['billing_address']['phone']}</TelVoice>
 </Location>
 </BillTo>
XML;
        }
    }

    /**
     * Add Shipping Address
     *
     * @param array
     */
    private function addShippingAddress($options)
    {
        if (isset($options['shipping_address'])) {
            $this->xml .= <<<XML
 <ShipTo>
 <Location>
 <Email DataType="String">{$options['email']}</Email>
XML;
            $this->addAddress($options['shipping_address']);
            $this->xml .= <<<XML
 <TelVoice DataType="String">{$options['shipping_address']['phone']}</TelVoice>
 </Location>
 </ShipTo>
XML;
        }
    }

    /**
     * Add Address
     *
     * @param array
     */
    private function addAddress($options)
    {
        $this->xml .= <<<XML
 <Address>
     <Name DataType="String">{$options['name']}</Name>
     <Company DataType="String">{$options['company']}</Company>
     <Street1 DataType="String">{$options['address1']}</Street1>
     <Street2 DataType="String">{$options['address2']}</Street2>
     <City DataType="String" >{$options['city']}</City>
     <StateProv DataType="String" >{$options['state']}</StateProv>
     <Country DataType="String">{$this->COUNTRY_CODE_MAPPINGS[$options['country']]}</Country>
     <PostalCode DataType="String">{$options['zip']}</PostalCode>
     </Addresss>
XML;
    }

    /**
     * Start XML
     */
    private function startXml()
    {
        $this->xml = <<<XML
     <?xml version="1.0" encoding="UTF-8"?>
     <EngineDocList>
     <DocVersion DataType="String">1.0</DocVersion>
     <EngineDoc>
     <ContentType DataType="String">OrderFormDoc</ContentType>
     <User>
     <Alias DataType="String">{$this->options['client_id']}</Alias>
     <Name DataType="String">{$this->options['login']}</Name>
     <Password DataType="String">{$this->options['password']}</Password>
     </User>
     <Instructions>
     <Pipeline DataType="String">Payment</Pipeline>
     </Instructions>
XML;
    }

    /**
     * End XML
     */
    private function endXml()
    {
        $this->xml .= <<<XML
     </OrderFormDoc>
     </EngineDoc>
     </EngineDocList>
XML;
    }

    /**
     * Commit
     *
     * @param string - Action.
     */
    private function commit($action)
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;
        $response = $this->parse($this->ssl_post($url, $this->xml));

        return new Response(
            $this->successFrom($action, $response),
            $this->messageFrom($response),
            $response,
            $this->optionsFrom($response)
        );
    }

    /**
     * Parse
     *
     * @param string
     * @return string
     */
    private function parse($response_xml)
    {
        $xml = simplexml_load_string($response_xml);

        $response = array();

        $messages = $xml->EngineDoc->MessageList;
        $overview = $xml->EngineDoc->Overview;
        $transaction = $xml->EngineDoc->OrderFormDoc->Transaction;

        /**
         * Parse messages
         */
        if (!empty($messages)) {
            if (isset($messages->MaxSev)) {
                $response['severity'] = (string) $messages->MaxSev;
            }

            if (count($messages->Message) == 2) {
                $message = $messages->Message[1];
            } else {
                $message = $messages->Message;
            }

            if (isset($message->AdvisedAction)) {
                $response['advised_action'] = (string) $message->AdvisedAction;
            }

            if (isset($message->Text)) {
                $response['error_message'] = (string) $message->Text;
            }
        }

        /**
         * Parse overview
         */
        if (!empty($overview)) {
            if (isset($overview->CcErrCode)) {
                $response['return_code'] = (string) $overview->CcErrCode;
            }

            if (isset($overview->CcReturnMsg)) {
                $response['return_message'] = (string) $overview->CcReturnMsg;
            }

            if (isset($overview->TransactionId)) {
                $response['transaction_id'] = (string) $overview->TransactionId;
            }

            if (isset($overview->AuthCode)) {
                $response['auth_code'] = (string) $overview->AuthCode;
            }

            if (isset($overview->TransactionStatus)) {
                $response['transaction_status'] = (string) $overview->TransactionStatus;
            }

            if (isset($overview->Mode)) {
                $response['mode'] = (string) $overview->Mode;
            }
        }

        /**
         * Parse transaction
         */
        if (!empty($transaction->CardProcResp)) {
            if (isset($transaction->CardProcResp->AvsRespCode)) {
                $response['avs_code'] = (string) $transaction->CardProcResp->AvsRespCode;
            }
        }

        if (isset($transaction->CardProcResp->AvsDisplay)) {
            $response['avs_display'] = (string) $transaction->CardProcResp->AvsDisplay;
        }

        if (isset($transaction->CardProcResp->Cvv2Resp)) {
            $response['cvv2_resp'] = (string) $transaction->CardProcResp->Cvv2Resp;
        }

        return $response;
    }

    /**
     * Options from Response
     *
     * @param array
     */
    private function optionsFrom($response)
    {
        $options = array();
        $options['authorization'] = $response['transaction_id'];
        $options['test'] = empty($response['mode']) || $response['mode'] != 'P';
        $options['fraud_review'] = in_array($response['return_code'], $this->FRAUDULENT);

        if (!empty($response['cvv2_resp'])) {
            $options['cvv_result'] = $this->CVV_RESPONSE_MAPPINGS[$response['cvv2_resp']];
        }
        $options['avs_result'] = $this->avsCodeFrom($response);
    }

    /**
     * Success From
     *
     * @param string
     * @param array
     * @return bool
     */
    private function successFrom($action, $response)
    {
        if ($action == 'authorize'
            || $action == 'purchase'
            || $action == 'capture'
        ) {
            $transaction_status = $this->TRANSACTION_STATUS_MAPPINGS['accepted'];
        } elseif ($action == 'void') {
            $transaction_status = $this->TRANSACTION_STATUS_MAPPINGS['void'];
        } else {
            $transaction_status = null;
        }

        return
            (
                $response['return_code'] == self::APPROVED &&
                $response['transaction_id'] != null &&
                $response['auth_code'] != null &&
                $response['transaction_status'] == $transaction_status
            );
    }

    /**
     * Message From
     *
     * @param array
     * @return string
     */
    private function messageFrom($response)
    {
        return (isset($response['return_message']) ? $response['return_message'] : $response['error_message']);
    }

    /**
     * AVS Code From
     *
     * @param array
     * @return array
     */
    private function avsCodeFrom($response)
    {
        if (empty($response['avs_display'])) {
            return array('code' => 'U');
        }

        switch ($response['avs_display']) {
            case 'YY':
                $code = "Y";
                break;

            case 'YN':
                $code = "A";
                break;

            case 'NY':
                $code = "W";
                break;

            case 'NN':
                $code = "C";
                break;

            case 'FF':
                $code = "G";
                break;

            default:
                $code = "R";
                break;
        }

        return array('code' => $code);
    }
}
