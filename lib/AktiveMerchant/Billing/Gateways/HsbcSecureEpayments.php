<?php

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Common\Country;

/**
 * Integration of HsbcSecureEpayments
 *
 * @author Andreas Kollaros <andreas@larium.net>
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class HsbcSecureEpayments extends Gateway implements Interfaces\Charge
{
    const TEST_URL = 'https://www.secure-epayments.apixml.hsbc.com';
    const LIVE_URL = 'https://www.secure-epayments.apixml.hsbc.com';

    private $CARD_TYPE_MAPPINGS = array(
        'visa' => 1,
        'master' => 2,
        'american_express' => 8,
        'solo' => 9,
        'switch' => 10,
        'maestro' => 14,
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

    protected $TRANSACTION_STATUS_MAPPINGS = array(
        'accepted' => "A",
        'declined' => "D",
        'fraud' => "F",
        'error' => "E",
        'void' => "V",
        'reserved' => "U",
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
        'CVV_FAILURE',
    );

    protected $options = array();

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

        if (isset($options['currency'])) {
            self::$default_currency = $options['currency'];
        }

        $this->options = $options;

        if (!$this->isTest()) {
            $this->payment_mode = 'P'; #Production mode
        }
    }

    public function authorize($amount, CreditCard $creditcard, $options = array())
    {
        $this->buildXml($amount, 'PreAuth', $creditcard, $options);
        return $this->commit(__FUNCTION__, $options);
    }

    public function purchase($amount, CreditCard $creditcard, $options = array())
    {
        $this->buildXml($amount, 'Auth', $creditcard, $options);
        return $this->commit(__FUNCTION__, $options);
    }

    public function capture($amount, $authorization, $options = array())
    {
        $options['authorization'] = $authorization;
        $this->buildXml($amount, 'PostAuth', null, $options);
        return $this->commit(__FUNCTION__, $options);
    }

    /**
     * TODO
     */
    public function void($identification, $options = array())
    {
        $options['authorization'] = $identification;
        $this->buildXml(null, 'Void', null, $options);
    }

    /**
     * TODO
     */
    public function credit($money, $identification, $options = array())
    {
        // $type = 'Credit'
    }

    private function buildXml($amount, $type, $creditcard = null, $options = array())
    {
        $this->startXml();
        $this->insertData($amount, $creditcard, $type, $options);
        $this->endXml();
    }

    private function insertData($amount, $creditcard, $type, $options = array())
    {

        $this->xml .= <<<XML
        <OrderFormDoc>
          <Mode DataType="String">{$this->payment_mode}</Mode>
XML;

        if (null !== $creditcard) {
            $month = $this->cc_format($creditcard->month, 'two_digits');
            $year = $this->cc_format($creditcard->year, 'two_digits');

            if (isset($options['order_id'])) {
                $this->xml .= "<Id DataType=\"String\">{$options['order_id']}</Id>";
            }

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
            $this->addBillingAddress($options);
            $this->addShippingAddress($options);
            $this->xml .= '</Consumer>';
        }

        $this->addTransactionElement($amount, $type, $options);

        if (is_null($creditcard)) {
            $this->addItemElemts($options);
        }
    }

    private function addTransactionElement($amount, $type, $options)
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

    private function addItemElemts($options)
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

    private function addAddress($options)
    {
        $country = Country::find($options['country'])->getCode('numeric');

        $this->xml .= <<<XML
      <Address>
        <Name DataType="String">{$options['name']}</Name>
        <Street1 DataType="String">{$options['address1']}</Street1>
        <Street2 DataType="String">{$options['address2']}</Street2>
        <City DataType="String" >{$options['city']}</City>
        <StateProv DataType="String">{$options['state']}</StateProv>
        <PostalCode DataType="String">{$options['zip']}</PostalCode>
        <Country DataType="String">{$country}</Country>
      </Address>
XML;
    }

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

    private function endXml()
    {
        $this->xml .= <<<XML
          </OrderFormDoc>
        </EngineDoc>
      </EngineDocList>
XML;
    }

    private function commit($action, $options)
    {
        $url = $this->isTest() ? static::TEST_URL : static::LIVE_URL;
        $data = $this->ssl_post($url, $this->xml, $options);
        $response = $this->parse($data);

        $r = new Response(
            $this->successFrom($action, $response),
            $this->messageFrom($response),
            $response,
            $this->optionsFrom($response)
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

            if (isset($transaction->CardProcResp->AvsDisplay)) {
                $response['avs_display'] = (string) $transaction->CardProcResp->AvsDisplay;
            }

            if (isset($transaction->CardProcResp->Cvv2Resp)) {
                $response['cvv2_resp'] = (string) $transaction->CardProcResp->Cvv2Resp;
            }
        }

        return $response;
    }

    private function optionsFrom($response)
    {
        $options = array();
        $options['authorization'] = isset($response['transaction_id']) ? $response['transaction_id'] : null;
        $options['test'] = (true == $this->isTest()) || empty($response['mode']) || $response['mode'] != 'P';
        $options['fraud_review'] = isset($response['return_code']) ? in_array($response['return_code'], $this->FRAUDULENT) : false;

        if (isset($response['cvv2_resp'])) {
            if (array_key_exists($response['cvv2_resp'], $this->HSBC_CVV_RESPONSE_MAPPINGS)) {
                $options['cvv_result'] = $this->HSBC_CVV_RESPONSE_MAPPINGS[$response['cvv2_resp']];
            }
        }
        $options['avs_result'] = $this->avsCodeFrom($response);

        return $options;
    }

    protected function successFrom($action, $response)
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

    private function messageFrom($response)
    {
        return (isset($response['return_message']) ? $response['return_message'] : $response['error_message']);
    }

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
