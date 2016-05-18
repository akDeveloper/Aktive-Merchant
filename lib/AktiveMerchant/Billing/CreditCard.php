<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing;

/**
 * A CreditCard object represents a physical credit card, and is capable of validating the various
 * data associated with these.
 *
 * At the moment, the following credit card types are supported:
 *   + Visa
 *   + MasterCard
 *   + Discover
 *   + American Express
 *   + Diner's Club
 *   + JCB
 *   + Switch
 *   + Solo
 *   + Dankort
 *   + Maestro
 *   + Forbrugsforeningen
 *   + Laser
 *
 * For testing purposes, use the 'bogus' credit card type. This skips the vast majority of
 * validations, allowing you to focus on your core concerns until you're ready to be more concerned
 * with the details of particular credit cards or your gateway.
 *
 * Testing With CreditCard
 * Often when testing we don't care about the particulars of a given card type. When using the 'test'
 * mode in your {Gateway}, there are six different valid card numbers: 1, 2, 3, 'success', 'fail',
 * and 'error'.
 *
 * Example Usage
 * <code>
 *   $cc = new CreditCard( array(
 *     'first_name' => 'Steve',
 *     'last_name'  => 'Smith',
 *     'month'      => '9',
 *     'year'       => '2010',
 *     'type'       => 'visa',
 *     'number'     => '4242424242424242'
 *   ))
 *
 *   $cc->isValid() # => true
 *   $cc->displayNumber() # => XXXX-XXXX-XXXX-4242
 * </code>
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */

class CreditCard extends CreditCardMethods
{
    /**
     * Card holder first name
     *
     * @var string
     */
    public $first_name;

    /**
     * Card holder last name
     *
     * @var string
     */
    public $last_name;
    public $month;
    public $year;
    public $type;
    public $number;
    public $verification_value;
    public $token;

    /**
     * Required for Switch / Solo cards
     */
    public $start_month;
    public $start_year;
    public $issue_number;

    public $require_verification_value = true;

    private $errors;


    public function __construct($options)
    {
        $defaults = array(
            'first_name' => null,
            'last_name' => null,
            'month' => null,
            'year' => null,
            'number' => null,
            'token' => null,
        );

        $options = array_merge($defaults, $options);

        $this->first_name = $options['first_name'];
        $this->last_name = $options['last_name'];
        $this->month = $options['month'];
        $this->year = $options['year'];
        $this->number = $options['number'];

        if (isset($options['verification_value'])) {
            $this->verification_value = $options['verification_value'];
        }

        if (isset($options['start_month'])) {
            $this->start_month = $options['start_month'];
        }

        if (isset($options['start_year'])) {
            $this->start_year = $options['start_year'];
        }

        if (isset($options['issue_number'])) {
            $this->issue_number = $options['issue_number'];
        }

        if (isset($options['type'])) {
            $this->type = $options['type'];
        } else {
            $this->type = self::type($this->number);
        }

        $this->errors = new \AktiveMerchant\Common\Error();
    }

    public function errors()
    {
        return $this->errors->errors();
    }

    public function expireDate()
    {
        return new ExpiryDate($this->month, $this->year);
    }

    public function isExpired()
    {
        return $this->expireDate()->isExpired();
    }

    public function name()
    {
        return $this->first_name . " " . $this->last_name;
    }

    public function displayNumber()
    {
        return self::mask($this->number);
    }

    public function lastDigits()
    {
        return self::getLastDigits($this->number);
    }

    public function isValid()
    {
        $this->validate();
        $errors = $this->errors();
        return empty($errors);
    }

    private function validate()
    {
        if ($this->token !== null) {
            return true;
        }

        $this->validate_essential_attributes();

        // Skip test if gateway is Bogus
        if (self::type($this->number) == 'bogus') {
            return true;
        }

        $this->validate_card_type();
        $this->validate_card_number();
        $this->validate_verification_value();
        $this->validate_switch_or_solo_attributes();
    }

    private function validate_card_number()
    {
        if (self::isValidNumber($this->number) === false) {
            $this->errors->add('number', 'is not a valid credit card number');
        }
        if (self::isMatchingType($this->number, $this->type) === false) {
            $this->errors->add('type', 'is not the correct card type');
        }
    }

    private function validate_card_type()
    {
        if ($this->type === null || $this->type == "") {
            $this->errors->add('type', 'is required');
        }
        $card_companies = self::getCardCompanies();
        if (!isset($card_companies[$this->type])) {
            $this->errors->add('type', 'is invalid');
        }
    }

    private function validate_verification_value()
    {
        if ($this->require_verification_value === true) {
            if ($this->verification_value === null
                || $this->verification_value == "") {
                $this->errors->add('verification_value', 'is required');
            }
        }
    }

    private function validate_switch_or_solo_attributes()
    {
        if (in_array($this->type, array('solo', 'switch'))) {
            if ((self::isValidMonth($this->start_month) === false
                && self::isValidStartYear($this->start_year) == false)
                || self::isValidIssueNumber($this->issue_number) == false
            ) {
                if (self::isValidMonth($this->start_month) === false) {
                    $this->errors->add('start month', 'is invalid');
                }
                if (self::isValidStartYear($this->start_year) === false) {
                    $this->errors->add('start year', 'is invalid');
                }
                if (self::isValidIssueNumber($this->issue_number) === false) {
                    $this->errors->add('issue number', 'cannot be empty');
                }
            }
        }
    }

    private function validate_essential_attributes()
    {
        if ($this->first_name === null || $this->first_name == "") {
            $this->errors->add('first_name', 'cannot be empty');
        }
        if ($this->last_name === null || $this->last_name == "") {
            $this->errors->add('last_name', 'cannot be empty');
        }
        if (self::isValidMonth($this->month) === false) {
            $this->errors->add('month', 'is not a valid month');
        }
        if ($this->isExpired() === true) {
            $this->errors->add('year', 'expired');
        }
        if (self::isValidExpiryYear($this->year) === false) {
            $this->errors->add('year', 'is not a valid year');
        }
    }
}
