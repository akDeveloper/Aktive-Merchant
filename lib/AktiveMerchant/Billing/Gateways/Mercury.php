<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Options;

/**
 * Description of Mercury gateway
 *
 * If your Mercury account has tokenization turned off you should pass the
 * option argument 'tokenization' => false when creating the gateway.
 * In this case you have to pass the credit card again for the actions capture/credit
 * and void.
 *
 *
 *
 * @category Gateways
 * @package  Aktive-Merchant
 * @author   Andreas Kollaros <andreaskollaros@ymail.com>
 * @license  MIT License http://www.opensource.org/licenses/mit-license.php
 * @link     https://github.com/akDeveloper/Aktive-Merchant
 */
class Mercury extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit
{
    const TEST_URL = 'https://w1.mercurydev.net/ws/ws.asmx';
    const LIVE_URL = 'https://w1.mercurypay.com/ws/ws.asmx';

    /**
     * {@inheritdoc}
     */
    public static $supported_countries = array('US');

    /**
     * {@inheritdoc}
     */
    public static $supported_cardtypes = array(
        'visa',
        'master',
        'american_express',
        'discover',
        'diners_club',
        'jcb'
    );

    protected $card_codes = array(
        'visa' => 'VISA',
        'master' => 'M/C',
        'american_express' => 'AMEX',
        'discover' => 'DCVR',
        'diners_club' => 'DCLB',
        'jcb' => 'JCB'
    );

    protected $envelope_namespaces = array(
        'xmlns:xsd="http://www.w3.org/2001/XMLSchema"',
        'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"',
        'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
    );

    protected $headers = array(
        'SOAPAction: http://www.mercurypay.com/CreditTransaction',
        'Content-Type: text/xml; charset=utf-8'
    );

    protected $success_codes = array(
        'Approved', 'Success'
    );

    protected $use_tokenization = true;

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'http://www.mercurypay.com';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'Mercury';

    /**
     * {@inheritdoc}
     */
    public static $default_currency = 'USD';

    /**
     * Additional options needed by gateway
     *
     * @var array
     */
    private $options;

    /**
     * Contains the main body of the request.
     *
     * @var array
     */
    private $post;

    /**
     * creates gateway instance from given options.
     *
     * @param array $options An array contains:
     *                       login
     *                       password
     *                       currency (optional)
     *                       tokenization (optional) true or false.
     *
     * @return Gateway The gateway instance.
     */
    public function __construct($options = array())
    {
        Options::required('login, password', $options);

        if (isset($options['currency']))
            self::$default_currency = $options['currency'];

        $this->options = new Options($options);

        $this->use_tokenization = array_key_exists('tokenization', $options)
            ? $options['tokenization']
            : true;
    }

    /**
     * {@inheritdoc}
     */
    public function authorize($money, CreditCard $creditcard, $options=array())
    {

        Options::required('order_id', $options);

        $options = array_merge($options, array('authorized'=>$this->amount($money)));
        $this->build_non_authorized_request('PreAuth', $money, $creditcard, $options);

        return $this->commit('PreAuth', $money, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function purchase($money, CreditCard $creditcard, $options=array())
    {

        Options::required('order_id', $options);
        $this->build_non_authorized_request('Sale', $money, $creditcard, $options);

        return $this->commit('Sale', $money);
    }

    /**
     * {@inheritdoc}
     */
    public function capture($money, $authorization, $options = array())
    {
        if (false == $this->use_tokenization) {
            Options::required('creditcard', $options);
        }

        $options = array_merge($options, array('authorized'=>$this->amount($money)));
        $this->build_authorized_request('PreAuthCapture', $money, $authorization, $options);

        return $this->commit('PreAuthCapture', $money);
    }

    /**
     * {@inheritdoc}
     */
    public function void($authorization, $options = array())
    {
        if (false == $this->use_tokenization) {
            Options::required('creditcard', $options);
        }

        $options = array_merge($options, array('reversal'=>true));
        $this->build_authorized_request('VoidSale', null, $authorization, $options);

        return $this->commit('VoidSale', null);
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
        if (false == $this->use_tokenization) {
            Options::required('creditcard', $options);
        }

        $this->build_authorized_request('Return', $money, $identification, $options);

        return $this->commit('Return', $money);
    }

    // Private methods

    private function build_non_authorized_request($action, $money, $creditcard, array $options = array())
    {
        $options = new Options($options);

        $this->post = $this->create_body_xml();

        $trans = $this->post->addChild('Transaction');
        $trans->addChild('TranType', 'Credit');
        $trans->addChild('TranCode', $action);
        if ($action == 'PreAuth' || $action == 'Sale') {
            $trans->addChild('PartialAuth', 'Allow');
        }

        $this->add_invoice($trans, $options->order_id, null, $options);
        $this->add_reference($trans, 'RecordNumberRequested');
        $this->add_customer_data($trans, $options);
        $this->add_amount($trans, $money, $options);
        $this->add_creditcard($trans, $creditcard);
        $this->add_address($trans, $options);
    }

    private function build_authorized_request($action, $money, $authorization, array $options = array())
    {
        $options = new Options($options);

        list($invoice_no, $ref_no, $auth_code, $acq_ref_data, $process_data, $record_no, $amount) = explode(';', $authorization);

        if ($options->reversal) {
            $ref_no = $invoice_no;
        }

        $this->post = $this->create_body_xml();

        $trans = $this->post->addChild('Transaction');
        $trans->addChild('TranType', 'Credit');
        $trans->addChild('TranCode', $action);
        if ($action == 'PreAuthCapture') {
            $trans->addChild('PartialAuth', 'Allow');
        }

        $trans->addChild('TranCode', $this->use_tokenization ? $action.'ByRecordNo' : $action);

        $this->add_invoice($trans, $invoice_no, $ref_no, $options);
        $this->add_reference($trans, $record_no);
        $this->add_customer_data($trans, $options);
        $this->add_amount($trans, isset($money) ? $money : ($amount/100), $options);
        if ($options->creditcard) {
            $this->add_creditcard($trans, $options->creditcard);
        }
        $this->add_address($trans, $options);

        $info = $trans->addChild('TranInfo');
        $info->addChild('AuthCode', $auth_code);
        if ($options->reversal) {
            $info->addChild('AcqRefData', $acq_ref_data);
            $info->addChild('ProcessData', $process_data);
        }
    }

    private function create_body_xml()
    {
        return new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><TStream></TStream>');
    }

    private function add_reference($trans, $record_no)
    {
        if ($this->use_tokenization) {
            $trans->addChild('Frequency', 'OneTime');
            $trans->addChild('RecordNo', $record_no);
        }
    }

    private function add_amount($trans, $money, $options = array())
    {

        $amount = $trans->addChild('Amount');
        $amount->addChild('Purchase', $this->amount($money));
        if ($options->authorized) {
            $amount->addChild('Authorize', $options->authorized);
        }
    }

    /**
     * Customer data like e-mail, ip, web browser used for transaction etc
     *
     * @param array $options
     */
    private function add_customer_data($trans, $options)
    {
        if ($options->ip) {
            $trans->addChild('IpAddress', $options->ip);
        }
        if ($options->customer) {
            $trans->addChild('CustomerInfo')
                ->addChild('CustomerCode', $options->customer);
        }

        $trans->addChild('MerchantID', $this->options->login);
    }

    /**
     * Options key can be 'shipping address' and 'billing_address' or 'address'
     *
     * Each of these keys must have an address array like:
     * <code>
     *      $address['name']
     *      $address['company']
     *      $address['address1']
     *      $address['address2']
     *      $address['city']
     *      $address['state']
     *      $address['country']
     *      $address['zip']
     *      $address['phone']
     * </code>
     * common pattern for address is
     * <code>
     * $billing_address = isset($options['billing_address'])
     *      ? $options['billing_address']
     *      : $options['address'];
     * $shipping_address = $options['shipping_address'];
     * </code>
     *
     * @param  array $options
     *
     * @return void
     */
    private function add_address($trans, $options)
    {
        if ($address = $options['billing_address'] ?: $options['address']) {
            $avs = $trans->addChild('AVS');
            $avs->addChild('Address', $address['address1']);
            $avs->addChild('zip', $address['zip']);
        }
    }

    /**
     * Adds invoice info if exists.
     *
     * @param array $options
     */
    private function add_invoice($trans, $invoice_no, $ref_no, $options)
    {
        $trans->addChild('InvoiceNo', $invoice_no);
        $trans->addChild('RefNo', $ref_no ?: $invoice_no);
        if($options['merchant']) {
            $trans->addChild('OperatorID', $options['merchant']);
        }
        if($options['description']) {
            $trans->addChild('Memo', $options['description']);
        }
    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     */
    private function add_creditcard($trans, CreditCard $creditcard)
    {
        $account = $trans->addChild('Account');
        $account->addChild('AcctNo', $creditcard->number);
        $account->addChild('ExpDate', $this->expdate($creditcard));
        $trans->addChild('CardType', $this->card_codes[$this->card_brand($creditcard)]);
        if ($cvv2 = $creditcard->verification_value) {
            $trans->addChild('CVVData', $cvv2);
        }
    }

    private function expdate(CreditCard $creditcard)
    {
        return $this->cc_format($creditcard->month, 'two_digits')
            . $this->cc_format($creditcard->year, 'two_digits');
    }
    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    private function parse($body)
    {
        $body = $this->substring_between($body, '<CreditTransactionResult>', '</CreditTransactionResult>');

        $body = html_entity_decode($body);
        $xml = new \SimpleXMLElement($body);

        $resonse = array();

        $cmd= $xml->xpath('//CmdResponse');
        foreach ($cmd[0] as $name=>$value) {
            $response[$name] = (string) $value;
        }

        if ($tran = $xml->xpath('//TranResponse')) {
            foreach ($tran[0] as $name=>$value) {
                if ($name == 'Amount') {
                    foreach ($value as $key => $amount) {
                        $response[$key] = (string) $amount;
                    }
                } else {
                    $response[$name] = (string) $value;
                }
            }
        }

        return new Options($response);

    }

    private function substring_between($haystack, $start, $end)
    {
        if (strpos($haystack, $start) === false || strpos($haystack, $end) === false) {
            return false;
        } else {
            $start_position = strpos($haystack, $start) + strlen($start);
            $end_position = strpos($haystack, $end);
            return substr($haystack, $start_position, $end_position - $start_position);
        }
    }

    /**
     *
     * @param  string $action
     * @param  number $money
     * @param  array  $parameters
     *
     * @return Response
     */
    private function commit($action, $money, $parameters = array())
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $data = $this->ssl_post($url, $this->post_data($action, $parameters), array('headers'=>$this->headers));

        $response = $this->parse($data);

        $test_mode = $this->isTest();

        return new Response(
            $this->success_from($response),
            $this->message_from($response),
            $response->getArrayCopy(),
            array(
                'test' => $test_mode,
                'authorization' => $this->authorization_from($response),
                'fraud_review' => $this->fraud_review_from($response),
                'avs_result' => $this->avs_result_from($response),
                'cvv_result' => $response->CVVResult
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
    private function success_from($response)
    {
        return in_array($response['CmdStatus'], $this->success_codes);
    }

    /**
     * Returns message (error explanation  or success) from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    private function message_from($response)
    {
        return $response['TextResponse'];
    }

    private function authorization_from($response)
    {
        if ($response->Purchase) {
            list($dollars, $cents) = explode('.', $response->Purchase);
        } else {
            $dollars = $cents = 0;
        }

        return implode(';', array(
            $response->InvoiceNo,
            $response->RefNo,
            $response->AuthCode,
            $response->AcqRefData,
            $response->ProcessData,
            $response->RecordNo,
            ($dollars * 100) + $cents
        ));
    }

    /**
     * Returns fraud review from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    private function fraud_review_from($response)
    {

    }

    /**
     *
     * Returns avs result from gateway response
     *
     * @param array $response
     *
     * @return string
     */
    private function avs_result_from($response)
    {
        return array('code' => null);
    }

    /**
     * Adds final parameters to post data and
     * build $this->post to the format that your payment gateway understands
     *
     * @param  string $action
     * @param  array  $parameters
     *
     * @return void
     */
    private function post_data($action, $parameters = array())
    {
        $str ='<?xml version="1.0" encoding="utf-8"?><soap:Envelope';
        foreach ($this->envelope_namespaces as $ns) {
            $str .= ' '.$ns;
        }
        $str .="></soap:Envelope>";

        $soap = new \SimpleXMLElement($str);
        $ct = $soap->addChild('soap:Body')
            ->addChild('CreditTransaction', null, static::$homepage_url);
        $ct->addChild('tran', '{body}');
        $ct->addChild('pw', $this->options->password);
        $output = $soap->asXML();

        $body = $this->post->asXML();
        $output = str_replace('{body}', htmlspecialchars($body), $output);

        return $output;
    }
}
