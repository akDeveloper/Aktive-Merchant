<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing;

/**
 * Description of CvvResult
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class CvvResult
{
    private static $MESSAGES = array(
        'D' => 'Suspicious transaction',
        'I' => 'Failed data validation check',
        'M' => 'Match',
        'N' => 'No Match',
        'P' => 'Not Processed',
        'S' => 'Should have been present',
        'U' => 'Issuer unable to process request',
        'X' => 'Card does not support verification'
    );

    protected $code = 'X';

    protected $message;

    public function __construct($code)
    {
        if ($code != "") {
            $this->code = $code;
        }

        $this->message = isset(self::$MESSAGES[$this->code]) ? self::$MESSAGES[$this->code] : 'Unknown';
    }

    public static function messages()
    {
        return self::$MESSAGES;
    }

    public function toArray()
    {
        return array(
            'code' => $this->code,
            'message' => $this->message
        );
    }
}
