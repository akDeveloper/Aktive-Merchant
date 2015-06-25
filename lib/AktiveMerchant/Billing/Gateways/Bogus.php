<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Billing\Exception;

/**
 * This is a gateway to test the integration between your application and
 * Aktive-Merchant. It implements all interfaces so you can check all payment actions.
 *
 * USAGE:
 * For authorize and purchase actions, use a CreditCard instance with number:
 * - 1 (For success action)
 * - 2 (For throwing an AktiveMerchant\Billing\Exception)
 * - other (For forcing action to fail)

 * For credit action use indentification with value:
 * - 1 (For success action)
 * - 2 (For throwing an AktiveMerchant\Billing\Exception)
 * - other (For forcing action to fail)
 *
 * For store and unstore action use reference with value:
 * - 1 (For success action)
 * - 2 (For throwing an AktiveMerchant\Billing\Exception)
 * - other (For forcing action to fail)
 *
 * For capture action use identification with value:
 * - 1 (For throwing an AktiveMerchant\Billing\Exception)
 * - 2 (For forcing action to fail)
 * - other (For success action)
 *
 * For void action use authorization with value:
 * - 1 (For throwing an AktiveMerchant\Billing\Exception)
 * - 2 (For forcing action to fail)
 * - other (For success action)
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
class Bogus extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit,
    Interfaces\Store
{
    const AUTHORIZATION = '53433';

    const SUCCESS_MESSAGE = "Bogus Gateway: Forced success";
    const FAILURE_MESSAGE = "Bogus Gateway: Forced failure";
    const ERROR_MESSAGE = "Bogus Gateway: Use CreditCard number 1 for success, 2 for exception and anything else for error";
    const CREDIT_ERROR_MESSAGE = "Bogus Gateway: Use trans_id 1 for success, 2 for exception and anything else for error";
    const UNSTORE_ERROR_MESSAGE = "Bogus Gateway: Use trans_id 1 for success, 2 for exception and anything else for error";
    const CAPTURE_ERROR_MESSAGE = "Bogus Gateway: Use authorization number 1 for exception, 2 for error and anything else for success";
    const VOID_ERROR_MESSAGE = "Bogus Gateway: Use authorization number 1 for exception, 2 for error and anything else for success";

    public static $supported_countries = array('US', 'GR');
    public static $supported_cardtypes = array('bogus');
    public static $homepage_url = 'http://example.com';
    public static $display_name = 'Bogus';

    public function authorize($money, CreditCard $creditcard, $options = array())
    {
        switch ($creditcard->number) {
            case '1':
                return new Response(
                    true,
                    self::SUCCESS_MESSAGE,
                    array('authorized_amount' => $money),
                    array(
                        'test' => true,
                        'authorization' => self::AUTHORIZATION
                    )
                );
                break;
            case '2':
                throw new Exception(self::ERROR_MESSAGE);
                break;
            default:
                return new Response(
                    false,
                    self::FAILURE_MESSAGE,
                    array('authorized_amount' => $money,
                    'error' => self::FAILURE_MESSAGE),
                    array('test' => true)
                );
                break;
        }
    }

    public function purchase($money, CreditCard $creditcard, $options = array())
    {
        switch ($creditcard->number) {
            case '1':
                return new Response(
                    true,
                    self::SUCCESS_MESSAGE,
                    array('paid_amount' => $money),
                    array(
                        'test' => true,
                        'authorization' => self::AUTHORIZATION
                    )
                );
                break;
            case '2':
                throw new Exception(self::ERROR_MESSAGE);
                break;
            default:
                return new Response(
                    false,
                    self::FAILURE_MESSAGE,
                    array(
                        'paid_amount' => $money,
                        'error' => self::FAILURE_MESSAGE
                    ),
                    array('test' => true)
                );
                break;
        }
    }

    public function credit($money, $identification, $options = array())
    {
        switch ($identification) {
            case '1':
                return new Response(
                    true,
                    self::SUCCESS_MESSAGE,
                    array('paid_amount' => $money),
                    array('test' => true)
                );
                break;
            case '2':
                throw new Exception(self::CREDIT_ERROR_MESSAGE);
                break;
            default:
                return new Response(
                    false,
                    self::FAILURE_MESSAGE,
                    array(
                        'paid_amount' => $money,
                        'error' => self::FAILURE_MESSAGE
                    ),
                    array('test' => true)
                );
                break;
        }
    }

    public function capture($money, $identification, $options = array())
    {
        switch ($identification) {
            case '1':
                throw new Exception(self::CREDIT_ERROR_MESSAGE);
                break;
            case '2':
                return new Response(
                    false,
                    self::FAILURE_MESSAGE,
                    array(
                        'paid_amount' => $money,
                        'error' => self::FAILURE_MESSAGE
                    ),
                    array('test' => true)
                );
                break;
            default:
                return new Response(
                    true,
                    self::SUCCESS_MESSAGE,
                    array('paid_amount' => $money),
                    array('test' => true)
                );
                break;
        }
    }

    public function void($authorization, $options = array())
    {
        switch ($authorization) {
            case '1':
                throw new Exception(self::VOID_ERROR_MESSAGE);
                break;
            case '2':
                return new Response(
                    false,
                    self::FAILURE_MESSAGE,
                    array(
                        'authorization' => $authorization,
                        'error' => self::FAILURE_MESSAGE),
                    array('test' => true)
                );
                break;
            default:
                return new Response(
                    true,
                    self::SUCCESS_MESSAGE,
                    array('authorization' => $authorization),
                    array('test' => true)
                );
                break;
        }
    }

    public function store(CreditCard $creditcard, $options = array())
    {
        switch ($creditcard->number) {
            case '1':
                return new Response(
                    true,
                    self::SUCCESS_MESSAGE,
                    array('billingid' => '1'),
                    array(
                        'test' => true,
                        'authorization' => self::AUTHORIZATION
                    )
                );
                break;
            case '2':
                throw new Exception(self::ERROR_MESSAGE);
                break;
            default:
                return new Response(
                    false,
                    self::FAILURE_MESSAGE,
                    array(
                        'billingid' => null,
                        'error' => self::FAILURE_MESSAGE
                    ),
                    array('test' => true)
                );
                break;
        }
    }

    public function unstore($reference, $options = array())
    {
        switch ($reference) {
            case '1':
                return new Response(
                    true,
                    self::SUCCESS_MESSAGE,
                    array(),
                    array('test' => true)
                );
                break;
            case '2':
                throw new Exception(self::UNSTORE_ERROR_MESSAGE);
                break;
            default:
                return new Response(
                    false,
                    self::FAILURE_MESSAGE,
                    array('error' => self::FAILURE_MESSAGE),
                    array('test' => true)
                );
                break;
        }
    }
}
