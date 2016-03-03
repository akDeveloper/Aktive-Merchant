<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways\Centinel;

use AktiveMerchant\Billing\Response;

/**
 * Description of CentinelResponse
 *
 * @author Andreas Kollaros <andreas@larium.net>
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class CentinelResponse extends Response
{
    public function message()
    {
        if (isset($this->params['enrolled']) && $this->params['enrolled'] == 'N') {
            return 'Cardholder not enrolled! ';
        }

        return $this->params['error_no'] . ": " . $this->message;
    }

    /**
     * Some CMPI lookups can result is a reponse
     * where a CMPI authentication is not possible, but
     * it is still safe to proceed with the transaction.
     * More detailed information on these scenarios can be
     * found in the comments below.
     */
    public function isLiabilityShifted()
    {
        if (!$this->isCmpiLookupResponse()) {
            return false;
        }

        return $this->customerNotEnrolled() || $this->customerDeclinedEnrollment();
    }

    /**
     * From Cardinal Commerce:
     *
     * "N" Enrollment indicates that the cardholder is not enrolled
     * in the programs. This is not "Failed Authentication", but
     * rather a response from the Visa and MasterCard Directory
     * servers that indicates that the particular cardholder's bank
     * is not participating in the 3D Secure program. This transaction type is
     * still eligible for liability shift.
     */
    private function customerNotEnrolled()
    {
        return $this->params['enrolled'] == 'N';
    }

    /**
     * From Cardinal Commerce:
     *
     * MasterCard/Maestro transactions that result in the Y A Y are
     * the scenario where the consumer was asked to enroll (attempted
     * authentication) but said "no thanks" in the iframe. As a result,
     * the CAVV was not generated, as this value is generated when the
     * cardholder actually authenticates with the bank. In this scenario
     * there is no authentication, simply an attempt. But again, because
     * you're participating in the programs and attempted to authenticate
     * the cardholder you have successfully shifted liability to the
     * cardholder's issuing bank.
     */
    private function customerDeclinedEnrollment()
    {
        return isset($this->params['pares_status']) && $this->params['pares_status'] == 'A' && empty($this->params['cavv']);
    }

    private function isCmpiLookupResponse()
    {
        return isset($this->params['enrolled']);
    }
}
