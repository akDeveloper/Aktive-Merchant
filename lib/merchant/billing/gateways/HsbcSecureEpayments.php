<?php

/**
 * Description of Merchant_Billing_HsbcSecureEpayments
 *
 * @package Aktive Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Merchant_Billing_HsbcSecureEpayments extends Merchant_Billing_Gateway
{
    const TEST_URL = 'https://www.secure-epayments.apixml.hsbc.com';
    const LIVE_URL = 'https://www.secure-epayments.apixml.hsbc.com';

    private $CARD_TYPE_MAPPINGS = array(
        'visa' => 1, 'master' => 2, 'american_express' => 8, 'solo' => 9,
        'switch' => 10, 'maestro' => 14
    );
    private $HSBC_CVV_RESPONSE_MAPPINGS = array(
        '0' => 'X',
        '1' => 'M',
        '2' => 'N',
        '3' => 'P',
        '4' => 'S',
        '5' => 'X',
        '6' => 'I',
        '7' => 'U'
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
        'DECLINED_FRAUDULENT_REVIEW', 'CVV_FAILURE');
    private $options = array();
    private $xml;
    private $payment_mode = "Y"; #Test mode
    private $payment_mech_type = "CreditCard";
    public static $default_currency = 'EUR';
    public static $supported_countries = array('US', 'GB');
    public static $supported_cardtypes = array('visa', 'master', 'american_express', 'switch', 'solo', 'maestro');
    public static $homepage_url = 'http://www.hsbc.co.uk/1/2/business/cards-payments/secure-epayments';
    public static $display_name = 'HSBC Secure ePayments';
    public static $money_format = 'cents';

    public function __construct($options = array())
    {
        $this->required_options('login, password, client_id', $options);

        if (isset($options['currency']))
            self::$default_currency = $options['currency'];

        $this->options = $options;

        $mode = $this->mode();
        if ($mode == 'live')
            $this->payment_mode = 'P';#Production mode
    }

    public function authorize($amount, Merchant_Billing_CreditCard $creditcard, $options = array())
    {
        $this->build_xml($amount, 'PreAuth', $creditcard, $options);
        return $this->commit(__FUNCTION__);
    }

    public function purchase($amount, Merchant_Billing_CreditCard $creditcard, $options = array())
    {
        $this->build_xml($amount, 'Auth', $creditcard, $options);
        return $this->commit(__FUNCTION__);
    }

    public function capture($amount, $authorization, $options = array())
    {
        $options = array_merge($options, array('authorization' => $authorization));
        $this->build_xml($amount, 'PostAuth', null, $options);
        return $this->commit(__FUNCTION__);
    }

    public function void($identification, $options = array())
    {
        $this->build_xml(null, 'Void', null, $options);
    }

    private function build_xml($amount, $type, $creditcard=null, $options=array())
    {
        $this->start_xml();
        $this->insert_data($amount, $creditcard, $type, $options);
        $this->end_xml();
    }

    private function insert_data($amount, $creditcard, $type, $options=array())
    {

        $this->xml .= <<<XML
        <OrderFormDoc>
          <Mode DataType="String">{$this->payment_mode}</Mode>
XML;

        if (null !== $creditcard) {
            $month = $this->cc_format($creditcard->month, 'two_digits');
            $year = $this->cc_format($creditcard->year, 'two_digits');

            if (isset($options['order_id']))
                $this->xml .= "<Id DataType=\"String\">{$options['order_id']}</Id>";

            $this->xml .= <<<XML
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
XML;
            $this->add_billing_address($options);
            $this->add_shipping_address($options);
            $this->xml .= '</Consumer>';
        }

        $this->add_transaction_element($amount, $type, $options);

        if (is_null($creditcard))
            $this->add_item_elemts($options);
    }

    private function add_transaction_element($amount, $type, $options)
    {
        if ($type == 'PreAuth' || $type == 'Auth') {
            $this->xml .= <<<XML
      <Transaction>
        <Type DataType="String">{$type}</Type>
XML;
            if (isset($options['three_d_secure'])) {
                $this->xml .= <<<XML
              <PayerSecurityLevel DataType="S32">{$options['three_d_secure']['security_level']}</PayerSecurityLevel>
              <CardholderPresentCode DataType="S32">{$options['three_d_secure']['cardholder_present_code']}</CardholderPresentCode>
              <PayerAuthenticationCode DataType="String">{$options['three_d_secure']['cavv']}</PayerAuthenticationCode>
              <PayerTxnId DataType="String">{$options['three_d_secure']['xid']}</PayerTxnId>
XML;
            }
            $this->xml .= <<<XML
        <CurrentTotals>
          <Totals>
            <Total DataType="Money" Currency="{$this->currency_lookup(self::$default_currency)}">{$amount}</Total>
          </Totals>
        </CurrentTotals>
      </Transaction>
XML;
        } elseif ($type == 'PostAuth' || $type == 'Void') {
            $this->xml .= <<<XML
      <Id DataType="String">{$options['authorization']}</Id>
      <Transaction>
        <Type DataType="String">{$type}</Type>
        <CurrentTotals>
          <Totals>
            <Total DataType="Money" Currency="{$this->currency_lookup(self::$default_currency)}">{$amount}</Total>
          </Totals>
        </CurrentTotals>
      </Transaction>
XML;
        }
    }

    private function add_item_elemts($options)
    {
        if (isset($options['order_items'])) {
            $this->xml .= '<OrderItemList>';

            $i = 1;

            foreach ($options['order_items'] as $orderItem) {
                $description = strlen($orderItem['description']) > 63 ? substr($orderItem['description'], 0, 60) . '...' : $orderItem['description'];
                $this->xml .= <<<XML
                    <OrderItem>
                        <Id DataType="String">{$orderItem['id']}</Id>
                        <ItemNumber DataType="S32">{$i}</ItemNumber>
                        <Desc DataType="String">{$description}</Desc>
                        <Qty DataType="S32">{$orderItem['quantity']}</Qty>
                        <Price DataType="Money" Currency="{$this->currency_lookup(self::$default_currency)}">{$orderItem['unit_price']}</Price>
                        <Total DataType="Money" Currency="{$this->currency_lookup(self::$default_currency)}">{$orderItem['total']}</Total>
                    </OrderItem>
XML;
                $i++;
            }

            $this->xml .= '</OrderItemList>';
        }
    }

    private function add_billing_address($options)
    {
        if (isset($options['billing_address'])) {
            $this->xml .= <<<XML
        <BillTo>
          <Location>
            <Email DataType="String">{$options['email']}</Email>
XML;
            $this->add_address($options['billing_address']);
            $this->xml .= <<<XML
            <TelVoice DataType="String">{$options['billing_address']['phone']}</TelVoice>
          </Location>
        </BillTo>
XML;
        }
    }

    private function add_shipping_address($options)
    {
        if (isset($options['shipping_address'])) {
            $this->xml .= <<<XML
        <ShipTo>
          <Location>
            <Email DataType="String">{$options['email']}</Email>
XML;
            $this->add_address($options['shipping_address']);
            $this->xml .= <<<XML
            <TelVoice DataType="String">{$options['shipping_address']['phone']}</TelVoice>
          </Location>
        </ShipTo>
XML;
        }
    }

    private function add_address($options)
    {
        $this->xml .= <<<XML
      <Address>
        <Name DataType="String">{$options['name']}</Name>
        <Street1 DataType="String">{$options['address1']}</Street1>
        <Street2 DataType="String">{$options['address2']}</Street2>
        <City DataType="String" >{$options['city']}</City>
        <StateProv DataType="String">{$options['state']}</StateProv>
        <PostalCode DataType="String">{$options['zip']}</PostalCode>
        <Country DataType="String">{$this->COUNTRY_CODE_MAPPINGS[$options['country']]}</Country>
      </Address>
XML;
    }

    private function start_xml()
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

    private function end_xml()
    {
        $this->xml .= <<<XML
          </OrderFormDoc>
        </EngineDoc>
      </EngineDocList>
XML;
    }

    private function commit($action)
    {
        $url = $this->is_test() ? self::TEST_URL : self::LIVE_URL;
        $response = $this->parse($this->ssl_post($url, $this->xml));

        $r = new Merchant_Billing_Response($this->success_from($action, $response),
                $this->message_from($response),
                $response,
                $this->options_from($response)
        );

        return $r;
    }

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

            if (isset($messages->MaxSev))
                $response['severity'] = (string) $messages->MaxSev;

            if (count($messages->Message) == 2) {
                $message = $messages->Message[1];
            } else {
                $message = $messages->Message;
            }

            if (isset($message->AdvisedAction))
                $response['advised_action'] = (string) $message->AdvisedAction;

            if (isset($message->Text))
                $response['error_message'] = (string) $message->Text;
        }
        /**
         * Parse overview
         */
        if (!empty($overview)) {

            if (isset($overview->CcErrCode))
                $response['return_code'] = (string) $overview->CcErrCode;

            if (isset($overview->CcReturnMsg))
                $response['return_message'] = (string) $overview->CcReturnMsg;

            if (isset($overview->TransactionId))
                $response['transaction_id'] = (string) $overview->TransactionId;

            if (isset($overview->AuthCode))
                $response['auth_code'] = (string) $overview->AuthCode;

            if (isset($overview->TransactionStatus))
                $response['transaction_status'] = (string) $overview->TransactionStatus;

            if (isset($overview->Mode))
                $response['mode'] = (string) $overview->Mode;
        }

        /**
         * Parse transaction
         */
        if (!empty($transaction->CardProcResp)) {

            if (isset($transaction->CardProcResp->AvsRespCode))
                $response['avs_code'] = (string) $transaction->CardProcResp->AvsRespCode;

            if (isset($transaction->CardProcResp->AvsDisplay))
                $response['avs_display'] = (string) $transaction->CardProcResp->AvsDisplay;

            if (isset($transaction->CardProcResp->Cvv2Resp))
                $response['cvv2_resp'] = (string) $transaction->CardProcResp->Cvv2Resp;
        }

        return $response;
    }

    private function options_from($response)
    {
        $options = array();
        $options['authorization'] = isset($response['transaction_id']) ? $response['transaction_id'] : null;
        $options['test'] = (true == $this->is_test()) || empty($response['mode']) || $response['mode'] != 'P';
        $options['fraud_review'] = isset($response['return_code']) ? in_array($response['return_code'], $this->FRAUDULENT) : false;

        if (isset($response['cvv2_resp'])) {
            if (in_array($response['cvv2_resp'], $this->HSBC_CVV_RESPONSE_MAPPINGS))
                $options['cvv_result'] = $this->HSBC_CVV_RESPONSE_MAPPINGS[$response['cvv2_resp']];
        }
        $options['avs_result'] = $this->avs_code_from($response);

        return $options;
    }

    private function success_from($action, $response)
    {
        if ($action == 'authorize' || $action == 'purchase' || $action == 'capture') {
            $transaction_status = $this->TRANSACTION_STATUS_MAPPINGS['accepted'];
        } elseif ($action == 'void') {
            $transaction_status = $this->TRANSACTION_STATUS_MAPPINGS['void'];
        } else {
            $transaction_status = null;
        }

        return ( ( isset($response['return_code']) && $response['return_code'] == self::APPROVED ) &&
        $response['transaction_id'] != null &&
        $response['auth_code'] != null &&
        $response['transaction_status'] == $transaction_status);
    }

    private function message_from($response)
    {
        return (isset($response['return_message']) ? $response['return_message'] : $response['error_message']);
    }

    private function avs_code_from($response)
    {
        if (empty($response['avs_display']))
            return array('code' => 'U');
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

    private $COUNTRY_CODE_MAPPINGS = array(
        'AF' => 004,
        'AL' => 008,
        'DZ' => 012,
        'AS' => 016,
        'AD' => 020,
        'AO' => 024,
        'AI' => 660,
        'AQ' => 010,
        'AG' => 028,
        'AR' => 032,
        'AM' => 051,
        'AW' => 533,
        'AU' => 036,
        'AT' => 040,
        'AZ' => 031,
        'BS' => 044,
        'BH' => 048,
        'BD' => 050,
        'BB' => 052,
        'BY' => 112,
        'BE' => 056,
        'BZ' => 084,
        'BJ' => 204,
        'BM' => 060,
        'BT' => 064,
        'BO' => 068,
        'BA' => 070,
        'BW' => 072,
        'BV' => 074,
        'BR' => 076,
        'IO' => 086,
        'VG' => 092,
        'BN' => 096,
        'BG' => 100,
        'BF' => 854,
        'BI' => 108,
        'KH' => 116,
        'CM' => 120,
        'CA' => 124,
        'CV' => 132,
        'KY' => 136,
        'CF' => 140,
        'TD' => 148,
        'CL' => 152,
        'CN' => 156,
        'CX' => 162,
        'CC' => 166,
        'CO' => 170,
        'KM' => 174,
        'CG' => 178,
        'CD' => 180,
        'CK' => 184,
        'CR' => 188,
        'CI' => 384,
        'HR' => 191,
        'CU' => 192,
        'CY' => 196,
        'CZ' => 203,
        'DK' => 208,
        'DJ' => 262,
        'DM' => 212,
        'DO' => 214,
        'TP' => 626,
        'EC' => 218,
        'EG' => 818,
        'SV' => 222,
        'GQ' => 226,
        'ER' => 232,
        'EE' => 233,
        'ET' => 231,
        'FK' => 238,
        'FO' => 234,
        'FM' => 583,
        'FJ' => 242,
        'FI' => 246,
        'FR' => 250,
        'GF' => 254,
        'PF' => 258,
        'TF' => 260,
        'GA' => 266,
        'GM' => 270,
        'GE' => 268,
        'DE' => 276,
        'GH' => 288,
        'GI' => 292,
        'GR' => 300,
        'GL' => 304,
        'GD' => 308,
        'GP' => 312,
        'GU' => 316,
        'GT' => 320,
        'GN' => 324,
        'GW' => 624,
        'GY' => 328,
        'HT' => 332,
        'HM' => 334,
        'HN' => 340,
        'HK' => 344,
        'HU' => 348,
        'IS' => 352,
        'IN' => 356,
        'ID' => 360,
        'IR' => 364,
        'IQ' => 368,
        'IE' => 372,
        'IM' => 833,
        'IL' => 376,
        'IT' => 380,
        'JM' => 388,
        'JP' => 392,
        'JO' => 400,
        'KZ' => 398,
        'KE' => 404,
        'KI' => 296,
        'KW' => 414,
        'KG' => 417,
        'LA' => 418,
        'LV' => 428,
        'LB' => 422,
        'LS' => 426,
        'LR' => 430,
        'LY' => 434,
        'LI' => 438,
        'LT' => 440,
        'LU' => 442,
        'MO' => 446,
        'MK' => 807,
        'MG' => 450,
        'MW' => 454,
        'MY' => 458,
        'MV' => 462,
        'ML' => 466,
        'MT' => 470,
        'MH' => 584,
        'MQ' => 474,
        'MR' => 478,
        'MU' => 480,
        'YT' => 175,
        'FX' => 249,
        'MX' => 484,
        'MD' => 498,
        'MC' => 492,
        'MN' => 496,
        'MS' => 500,
        'MA' => 504,
        'MZ' => 508,
        'MM' => 104,
        'NA' => 516,
        'NR' => 520,
        'NP' => 524,
        'NL' => 528,
        'AN' => 530,
        'NC' => 540,
        'NZ' => 554,
        'NI' => 558,
        'NE' => 562,
        'NG' => 566,
        'NU' => 570,
        'NF' => 574,
        'KP' => 408,
        'MP' => 580,
        'NO' => 578,
        'OM' => 512,
        'PK' => 586,
        'PW' => 585,
        'PS' => 275,
        'PA' => 591,
        'PG' => 598,
        'PY' => 600,
        'PE' => 604,
        'PH' => 608,
        'PN' => 612,
        'PL' => 616,
        'PT' => 620,
        'PR' => 630,
        'QA' => 634,
        'RE' => 638,
        'RO' => 642,
        'RU' => 643,
        'RW' => 646,
        'WS' => 882,
        'SM' => 674,
        'ST' => 678,
        'SA' => 682,
        'SN' => 686,
        'CS' => 891,
        'SC' => 690,
        'SL' => 694,
        'SG' => 702,
        'SK' => 703,
        'SI' => 705,
        'SB' => 090,
        'SO' => 706,
        'ZA' => 710,
        'GS' => 239,
        'KR' => 410,
        'ES' => 724,
        'LK' => 144,
        'SH' => 654,
        'KN' => 659,
        'LC' => 662,
        'PM' => 666,
        'VC' => 670,
        'SD' => 736,
        'SR' => 740,
        'SJ' => 744,
        'SZ' => 748,
        'SE' => 752,
        'CH' => 756,
        'SY' => 760,
        'TW' => 158,
        'TJ' => 762,
        'TZ' => 834,
        'TH' => 764,
        'TG' => 768,
        'TK' => 772,
        'TO' => 776,
        'TT' => 780,
        'TN' => 788,
        'TR' => 792,
        'TM' => 795,
        'TC' => 796,
        'TV' => 798,
        'VI' => 850,
        'UG' => 800,
        'UA' => 804,
        'AE' => 784,
        'UK' => 826,
        'US' => 840,
        'UM' => 581,
        'UY' => 858,
        'UZ' => 860,
        'VU' => 548,
        'VA' => 336,
        'VE' => 862,
        'VN' => 704,
        'WF' => 876,
        'EH' => 732,
        'YE' => 887,
        'ZM' => 894,
        'ZW' => 716
    );

}
?>



