<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\StoredCreditCard;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Billing\Gateways\SecurePayAu\DateTime;
use AktiveMerchant\Common\Options;


/**
 * Description of Example
 *
 * @category Gateways
 * @package  Aktive-Merchant
 * @author   Michael Gall <michael@wakeless.net>
 * @license  MIT License http://www.opensource.org/licenses/mit-license.php
 * @link     https://github.com/akDeveloper/Aktive-Merchant
 */
class SecurePayAu extends Gateway implements 
    Interfaces\Charge,
    Interfaces\Credit
{

      const API_VERSION = 'xml-4.2';
      const PERIODIC_API_VERSION = 'spxml-3.0';

      public static $test_url = 'https://www.securepay.com.au/test/payment';
      public static $live_url = 'https://www.securepay.com.au/xmlapi/payment';

      public static $test_periodic_url = 'https://test.securepay.com.au/xmlapi/periodic';
      public static $live_periodic_url = 'https://api.securepay.com.au/xmlapi/periodic';

      public static $supported_countries = array('AU');
      public static $supported_cardtypes = ['visa', 'master', 'american_express', 'diners_club', 'jcb'];

      # The homepage URL of the gateway
      public static $homepage_url = 'http://securepay.com.au';

      # The name of the gateway
      public static $display_name = 'SecurePay';

      public static $request_timeout = 60;

      public static $money_format = 'cents';
      public static $default_currency = 'AUD';

      protected $options = array();

      # 0 Standard Payment
      # 4 Refund
      # 6 Client Reversal (Void)
      # 10 Preauthorise
      # 11 Preauth Complete (Advice)
      static private $TRANSACTIONS = array( 
        'purchase' => 0,
        'authorization' => 10,
        'capture' => 11,
        'void' => 6,
        'refund' => 4
      );

    static private $PERIODIC_ACTIONS = array(
        'add_triggered'    => "add",
        'remove_triggered' => "delete",
        'trigger'          => "trigger"
      );

      static private $PERIODIC_TYPES = array(
        'add_triggered'    => 4,
        'remove_triggered' => null,
        'trigger'          => null
      );

      static private $SUCCESS_CODES = [ '00', '08', '11', '16', '77' ];

      function __construct($options = array()) {
        $this->required_options(array('login', 'password'), $options);
        $this->options = $options;
      }

      function purchase($money, CreditCard $credit_card, $options = array()) {
        $this->required_options('order_id', $options);
        if($credit_card instanceof StoredCreditCard) {
            $options["billing_id"] = $credit_card->billing_id();
            return $this->commit_periodic($this->build_periodic_item('trigger', $money, null, $options));
        } else {
            return $this->commit('purchase', $this->build_purchase_request( $money, $credit_card, $options));
        }
    }

        //If not CC. Not sure why we aren't supporting this.
        //else
        //  options[:billing_id] = credit_card_or_stored_id.to_s
        //  commit_periodic(build_periodic_item(:trigger, money, nil, options))
        //end

      function authorize($money, CreditCard $credit_card, $options = array()) {
        $this->required_options('order_id', $options);
        return $this->commit('authorization', $this->build_purchase_request($money, $credit_card, $options));
      }

      function capture($money, $reference, $options = array()) {
        return $this->commit('capture', $this->build_reference_request($money, $reference));
      }

      function refund($money, $reference, $options = array()) {
        return $this->commit('refund', $this->build_reference_request($money, $reference));
      }

      function credit($money, $reference, $options = array()) {
        //deprecated CREDIT_DEPRECATION_MESSAGE
        return $this->refund($money, $reference);
      }     

      function void($reference, $options = array()) {
        return $this->commit('void', $this->build_reference_request(null, $reference));
      }

      function store($creditcard, $options = array()) {
        $this->required_options(array("billing_id", "amount"), $options);
        return $this->commit_periodic($this->build_periodic_item('add_triggered', $options['amount'], $creditcard, $options));
      }

      function unstore($identification, $options = array()) {
        $options['billing_id'] = $identification;
        return $this->commit_periodic($this->build_periodic_item('remove_triggered', @$options['amount'], null, $options));
      }

      private

    function addCCInfo($xml, $credit_card) {
      $ccInfo = $xml->addChild("CreditCardInfo");
      $ccInfo->addChild("cardNumber", @$credit_card->number);
      $ccInfo->addChild("expiryDate", $this->expdate($credit_card));

      if(@$credit_card->verification_value) {
          $ccInfo->addChild("cvv", $credit_card->verification_value);
      }
      return $ccInfo;
    }

      function build_purchase_request($money, $credit_card, $options) {

        $xml = $this->build_base_xml();
        $xml->addChild("amount", $this->amount($money));
        $xml->addChild("currency", @$options['currency'] ?: $this->currency($money));
        $xml->addChild("purchaseOrderNo", preg_replace("/[ ']/", "", $options["order_id"]));

        $this->addCCInfo($xml, $credit_card);

        return $xml->asXML();
      }

      function currency() {
        return self::$default_currency;
      }

      function build_base_xml() {
        return $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><SecurePayMessage></SecurePayMessage>');
      }

      function build_reference_request($money, $reference) {
        $xml = $this->build_base_xml();

        @list($transaction_id, $order_id, $preauth_id, $original_amount) = explode("*", $reference);

        $original_amount = $original_amount ?: 0;

        $xml->addChild("amount", $money ? $this->amount($money) : $original_amount);
        $xml->addChild("txnID", $transaction_id);
        $xml->addChild("purchaseOrderNo", $order_id);
        if($preauth_id) $xml->addChild("preauthID", $preauth_id);
        return $xml->asXML();
      }

      function request_timeout() {
        return self::$request_timeout;
      }

      function addBaseMessages($xml) {
        $messageInfo = $xml->addChild("MessageInfo");
        $messageInfo->addChild("messageID", substr($this->generateUniqueId(), 0, 30));
        $messageInfo->addChild("messageTimestamp", $this->generate_timestamp());
        $messageInfo->addChild("timeoutValue", $this->request_timeout());
        $messageInfo->addChild("apiVersion", self::API_VERSION);

        $merchantInfo = $xml->addChild("MerchantInfo");
        $merchantInfo->addChild("merchantID", $this->options['login']);
        $merchantInfo->addChild("password", $this->options['password']);

        return $xml;
      }

      function build_request($action, $body) {
        $xml = $this->build_base_xml();
        
        $pay = $this->addBaseMessages($xml);

        $pay->addChild("RequestType", "Payment");
        $payment = $pay->addChild("Payment");

        $txnList = $payment->addChild("TxnList");
        $txnList["count"] = 1;

        $txn = $txnList->addChild("Txn", new \SimpleXMLElement($body));
        $txn["ID"] = 1;
        $txn->addChild("txnType", self::$TRANSACTIONS[$action]);
        $txn->addChild("txnSource", 23);

        $dom = dom_import_simplexml($xml);
        $this->importChildNodes($dom->getElementsByTagName("Txn")->item(0), $body);

        return $dom->ownerDocument->saveXML();
    }

    function importChildNodes($node, $xml) {
        
        $body = dom_import_simplexml(new \SimpleXMLElement($xml));
        foreach($body->childNodes as $child) {
            $node->appendChild($node->ownerDocument->importNode($child, true));
        }
    }


    function ssl_post($endpoint, $data, $options = array()) {
        $options = $options + array("headers" => array("Content-Type: text/xml;charset=ISO-8859-1"));
        return parent::ssl_post($endpoint, $data, $options);
    }

    function commit($action, $request) {
        $response = $this->parse(
            $this->ssl_post(
                $this->isTest() ? self::$test_url : self::$live_url, 
                $this->build_request($action, $request)
            )
        );

        return $this->buildResponseObject($response);
    }


      function build_periodic_item($action, $money, $credit_card, $options) {
        $xml = $this->build_base_xml();
        $xml->addChild("actionType", self::$PERIODIC_ACTIONS[$action]);
        $xml->addChild("clientID", $options['billing_id']);

        if(!$credit_card instanceof StoredCreditCard) $this->addCCInfo($xml, $credit_card);
        $xml->addChild("amount", $this->amount($money));

        if(self::$PERIODIC_TYPES[$action]) {

            $xml->addChild("periodicType", self::$PERIODIC_TYPES[$action]);
        }
        return $xml->asXML();
    }

      function build_periodic_request($body) {
        $xml = $this->build_base_xml();
        $pay = $this->addBaseMessages($xml);

        $pay->addChild("RequestType", "Periodic");
        $periodic = $pay->addChild("Periodic");

        $list = $periodic->addChild("PeriodicList");
        $list["count"] = 1;

        $item = $list->addChild("PeriodicItem");
        $item["ID"] = 1;

        $dom = dom_import_simplexml($xml);
        $this->importChildNodes($dom->getElementsByTagName("PeriodicItem")->item(0), $body);


        return $dom->ownerDocument->saveXML();
    }

    function commit_periodic($request) {
        $response = $this->parse(
            $this->ssl_post(
                $this->isTest() ? self::$test_periodic_url : self::$live_periodic_url, 
                $this->build_periodic_request($request)
            )
        );

        return $this->buildResponseObject($response);

    }

    function buildResponseObject($response) {
        return new Response($this->isSuccess($response), $this->message_from($response), $response, 
            array("test" => $this->isTest(), "authorization" => $this->authorization_from($response)));
    }

    function isSuccess($response) {
        return in_array($response["response_code"], self::$SUCCESS_CODES);
    }


      function authorization_from($response) {
        return implode("*", array($response['txn_id'], $response['purchase_order_no'], $response['preauth_id'], $response['amount']));
      }

      function message_from($response) {
        return $response["response_text"] ?: $response['status_description'];
      }

      function expdate($credit_card) {
        return $this->cc_format(@$credit_card->month, 'two_digits')."/".$this->cc_format(@$credit_card->year, "two_digits");
      }

      function parse($body) {
        $xml = simplexml_load_string(trim($body));
        if(!$xml) {
            $errors = array();
            foreach(libxml_get_errors() as $error) {
                $errors[] = $error->message;
                throw new Exception(implode("\n\r", $errors));
            }
        }

        $response = new Options;
        foreach($xml->children() as $v) {
            $this->parseElement($response, $v);
        }
        return $response;
      }

      function parseElement(&$response, $element) {
        if($element->children()) {
            foreach($element->children() as $child) {
                $this->parseElement($response, $child);
            }
        } else {
            $response[$this->underscore($element->getName())] = (string)$element;
        }
      }

      function generate_timestamp() {
      # YYYYDDMMHHNNSSKKK000sOOO
        $date = new DateTime;
        return $date->format("YdmGis000000");
    }
}



