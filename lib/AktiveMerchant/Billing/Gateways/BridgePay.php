<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Billing\Response;
use Thapp\XmlBuilder\XmlBuilder;
use AktiveMerchant\Common\Address;
use AktiveMerchant\Billing\Gateways\Worldpay\XmlNormalizer;

/**
 * Description of Example
 *
 * @category Gateways
 * @package  Aktive-Merchant
 * @author   Dimitris Giannakakis <Dim.Giannakakis@yahoo.com>
 * @license  MIT License http://www.opensource.org/licenses/mit-license.php
 * @link     https://github.com/akDeveloper/Aktive-Merchant
 */
class BridgePay extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit
{
    const TEST_URL = 'https://gatewaystage.itstgate.com/SmartPayments/transact.asmx/ProcessCreditCard';
    const LIVE_URL = 'https://gateway.itstgate.com/SmartPayments/transact.asmx/ProcessCreditCard';
    const DISPLAY_NAME = 'BridgePay';

    /**
     * {@inheritdoc}
     */
    public static $money_format = 'dollars';

    /**
     * {@inheritdoc}
     */
    public static $supported_countries = array(
        'CA',
        'US'
    );

    /**
     * {@inheritdoc}
     */
    public static $supported_cardtypes = array(
        'visa',
        'master',
        'american_express',
        'discover',
        'diners_club',
        'maestro',
        'jcb'
    );

    protected $APPROVED = 'Approved';

    /**
     * {@inheritdoc}
     */
    public static $homepage_url = 'http://www.bridgepaynetwork.com/';

    /**
     * {@inheritdoc}
     */
    public static $display_name = 'BridgePay';

    /**
     * {@inheritdoc}
     */
    public static $default_currency = 'USD';

    /**
     * Contains the main body of the request.
     *
     * @var array
     */
    private $post = array();

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
        $this->required_options('username, password', $options);

        if (isset($options['currency']))
            self::$default_currency = $options['currency'];

        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $this->post = array();
        $options = new Options($options);
        $this->post_required_fields('Auth');

        $this->add_invoice($this->post, $money, $options);
        $this->add_creditcard($this->post, $creditcard);
        $this->add_customer_data($this->post, $options);

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $this->post = array();
        $options = new Options($options);
        $this->post_required_fields('Sale');

        $this->post['ExtData'] = '<Force>T</Force>';
        $this->add_invoice($this->post, $money, $options);
        $this->add_creditcard($this->post, $creditcard);
        $this->add_customer_data($this->post, $options);

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function capture($money, $authorization, $options = array())
    {
        $this->post = array();
        $options = new Options($options);
        $this->post_required_fields('Force');

        $this->add_invoice($this->post, $money, $options);
        $this->add_reference($this->post, $authorization);
        $this->add_customer_data($this->post, $options);

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function void($authorization, $options = array())
    {
        $this->post = array();
        $options = new Options($options);
        $this->post_required_fields('Void');

        $this->add_reference($this->post, $authorization);

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function credit($money, $identification, $options = array())
    {
        $this->post = array();
        $options = new Options($options);
        $this->post_required_fields('Return');

        $this->add_invoice($this->post, $money, $options);
        $this->add_reference($this->post, $identification);

        return $this->commit();
    }

    // Private methods

    /**
     * Customer data like e-mail, ip, web browser used for transaction etc
     *
     * @param array $options
     * @param array reference $post
     */
    private function add_customer_data(&$post, Options $options)
    {
        $address = array();

        if($options->billing_address) {

            $adr = $options->billing_address;

        } else if($options->address) {

            $adr = $options->address;

        }

        $post['Street'] = $adr->address1;
        $post['Zip'] = $adr->zip;
    }

    private function post_required_fields ($transaction_type)
    {
        $this->post = array (
            'TransType' => $transaction_type,
            'Amount' => null,
            'PNRef' => null,
            'InvNum' => null,
            'CardNum' =>null,
            'ExpDate' => null,
            'MagData' => null,
            'NameOnCard' => null,
            'Zip' => null,
            'Street' => null,
            'CVNum' => null,
            'MagData' => null,
            'ExtData' => null
        );
    }

    private function add_reference(&$post, $authorization)
    {
        $split = $this->split_authorization($authorization);

        $this->post['AuthCode'] = $split['AuthCode'];
        $this->post['PNRef'] = $split['PNRef'];
    }

    private function split_authorization($authorization)
    {
        list($authcode, $pnref) = explode('|', $authorization);

        $array = array(
            'AuthCode' => $authcode,
            'PNRef' => $pnref,
        );

        return $array;
    }


    private function add_invoice(&$post, $money,  Options $options)
    {
        $post['Amount'] = $this->amount($money);
        $post['InvNum'] = $options->order_id;
    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     * @param array reference $post
     */
    private function add_creditcard(&$post, CreditCard $creditcard)
    {
        $post['NameOnCard'] = $creditcard->type;
        $post['ExpDate'] = $this->cc_format($creditcard->month, 'two_digits').$this->cc_format($creditcard->year, 'two_digits' );
        $post['CardNum'] = $creditcard->number;
        $post['CVNum'] = $creditcard->verification_value;
    }


    private function commit()
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $data = $this->ssl_post($url, $this->post_data());

        $response = $this->parse($data);

        $test_mode = $this->isTest();

        return new Response(
            $this->success_from($response),
            $this->message_from($response),
            $response,
            array(
                'authorization' => $this->authorization_from($response),
                'test' => $test_mode

            )
        );
    }

    private function authorization_from($response)
    {
        $result = $response['AuthCode'].'|'.$response['PNRef'];

        return $result;
    }

    /**
     * Returns success flag from gateway response
     *
     * @param array $response
     *
     * @return boolean
     */
    private function success_from($response)
    {
        if ($response['RespMSG']== $this->APPROVED) {

            return true;
        } else {

            return false;
        }
    }

    /**
     * Returns message (error explanation  or success) from gateway response
     *
     * @param array $response
     *
     * @return array
     */
    private function message_from($response)
    {
        return $response['RespMSG'];
    }

    /**
     *
     * Returns avs result from gateway response
     *
     * @param array $response
     *
     * @return array
     */
    private function avs_result_from($response)
    {
        return array('code' => $response['avs_result_code']);
    }

    /**
     * Adds final parameters to post data and
     * build $this->post to the format that your payment gateway understands
     *
     * @param  string $action
     * @param  array  $parameters
     *
     * @return string $result
     */
    private function post_data()
    {
        $post =  array(
            'UserName' => $this->options['username'],
            'Password' => $this->options['password']
        );

        $result = $this->urlize(array_merge($post, $this->post));

        return $result;

    }

    /**
     * Parse the raw data response from gateway
     *
     * @param string $body
     */
    private function parse($body)
    {
       $xmlbuilder = new XmlBuilder();

       $xml = $xmlbuilder->loadXML($body, true);

       $request = $xmlbuilder->toArray($xml);

       return $request['Response'];
    }

}
