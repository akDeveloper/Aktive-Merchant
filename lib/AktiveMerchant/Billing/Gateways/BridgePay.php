<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Address;

/**
 * Integration of TGATE PathwayLINK gateway from BridgePay.
 *
 * @link http://www.bridgepaynetwork.com/developerCenterTGATE.html
 *
 * @author Dimitris Giannakakis <Dim.Giannakakis@yahoo.com>
 * @license MIT License http://www.opensource.org/licenses/mit-license.php
 */
class BridgePay extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit
{
    const TEST_URL = 'https://gatewaystage.itstgate.com/SmartPayments/transact.asmx/ProcessCreditCard';
    const LIVE_URL = 'https://gateway.itstgate.com/SmartPayments/transact.asmx/ProcessCreditCard';

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

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        $this->post = array();
        $options = new Options($options);
        $this->postRequiredFields('Auth');

        $this->addInvoice($money, $options);
        $this->addCreditcard($this->post, $creditcard);
        $this->addAddress($options);

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        $this->post = array();
        $options = new Options($options);
        $this->postRequiredFields('Sale');

        $this->post['ExtData'] = '<Force>T</Force>';
        $this->addInvoice($money, $options);
        $this->addCreditcard($this->post, $creditcard);
        $this->addAddress($options);

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function capture($money, $authorization, $options = array())
    {
        $this->post = array();
        $options = new Options($options);
        $this->postRequiredFields('Force');

        $this->addInvoice($money, $options);
        $this->addReference($authorization);
        $this->addAddress($options);

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function void($authorization, $options = array())
    {
        $this->post = array();
        $options = new Options($options);
        $this->postRequiredFields('Void');

        $this->addReference($authorization);

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function credit($money, $identification, $options = array())
    {
        $this->post = array();
        $options = new Options($options);
        $this->postRequiredFields('Return');

        $this->addInvoice($money, $options);
        $this->addReference($identification);

        return $this->commit();
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
     *
     * @param Options $options
     *
     * @return void
     */
    private function addAddress(Options $options)
    {
        $address = $options['billing_address'] ?: $options['address'];

        $this->post['Street'] = $address->address1;
        $this->post['Zip'] = $address->zip;
    }

    private function postRequiredFields($transaction_type)
    {
        $this->post = array (
            'TransType' => $transaction_type,
            'Amount' => null,
            'PNRef' => null,
            'InvNum' => null,
            'CardNum' => null,
            'ExpDate' => null,
            'MagData' => null,
            'NameOnCard' => null,
            'Zip' => null,
            'Street' => null,
            'CVNum' => null,
            'ExtData' => null
        );
    }

    private function addReference($authorization)
    {
        $this->post = array_merge(
            $this->post,
            $this->splitAuthorization($authorization)
        );
    }

    private function splitAuthorization($authorization)
    {
        list($authcode, $pnref) = explode('|', $authorization);

        return array(
            'AuthCode' => $authcode,
            'PNRef' => $pnref,
        );
    }

    private function addInvoice($money, Options $options)
    {
        $this->post['Amount'] = $this->amount($money);
        $this->post['InvNum'] = $options->order_id;
    }

    /**
     * Adds a CreditCard object
     *
     * @param CreditCard $creditcard
     * @param array reference $post
     */
    private function addCreditcard(&$post, CreditCard $creditcard)
    {
        $post['NameOnCard'] = $creditcard->type;
        $post['ExpDate'] = $this->cc_format($creditcard->month, 'two_digits')
            .$this->cc_format($creditcard->year, 'two_digits');
        $post['CardNum'] = $creditcard->number;
        $post['CVNum'] = $creditcard->verification_value;
    }


    private function commit()
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $data = $this->ssl_post($url, $this->postData());

        $response = $this->parse($data);

        return new Response(
            $this->successFrom($response),
            $this->messageFrom($response),
            $response,
            array(
                'authorization' => $this->authorizationFrom($response),
                'test' => $this->isTest(),
                'avs_result' => $this->avsResultFrom($response),
                'cvv_result' => $this->cvvResultFrom($response),
            )
        );
    }

    private function authorizationFrom($response)
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
    private function successFrom($response)
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
    private function messageFrom($response)
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
    private function avsResultFrom($response)
    {
        if (isset($response['GetAVSResult'])) {
            return array('code' => $response['GetAVSResult']);
        }

        return array('code' => 'U');
    }

    private function cvvResultFrom($response)
    {
        if (isset($response['GetCVResult'])) {
            return $response['GetCVResult'];
        }

        return 'P';
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
    private function postData()
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
        $xmlObject = simplexml_load_string($body);
        foreach ($xmlObject as $key => $value) {
            $response[$key] = (string) $value;
        }

        return $response;
    }
}
