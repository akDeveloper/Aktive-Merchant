<?php

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Gateways\Payflow\PayflowCommon;
use AktiveMerchant\Billing\Gateways\Payflow\PayflowResponse;
use AktiveMerchant\Billing\Interfaces as Interfaces;

/**
 * Integration of Payflow gateway
 *
 * @author Tom Maguire
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Payflow extends PayflowCommon
{
    public static $supported_cardtypes = array('add', 'modify', 'cancel', 'inquiry', 'reactivate', 'payment');
    public static $homepage_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_payflow-pro-overview-outside';
    public static $display_name = 'PayPal Payflow Pro';

    public function authorize($money, $credit_card_or_reference, $options = array())
    {
        $this->buildSaleOrAuthorizationRequest(
            'Authorization',
            $money,
            $credit_card_or_reference,
            $options
        );

        return $this->commit($options);
    }

    public function purchase($money, $credit_card_or_reference, $options = array())
    {
        $this->buildSaleOrAuthorizationRequest(
            'Sale',
            $money,
            $credit_card_or_reference,
            $options
        );

        return $this->commit($options);
    }

    private function buildSaleOrAuthorizationRequest($action, $money, $credit_card_or_reference, $options)
    {
        return is_string($credit_card_or_reference)
            ? $this->buildReferenceSaleOrAuthorizationRequest(
                $action,
                $money,
                $credit_card_or_reference
            )
            : $this->buildCreditCardRequest(
                $action,
                $money,
                $credit_card_or_reference,
                $options
            );
    }

    private function buildReferenceSaleOrAuthorizationRequest($action, $money, $reference)
    {
        $default_currency = self::$default_currency;
        $bodyXml = <<<XML
             <{$action}>
                <PayData>
                    <Invoice>
                        <TotalAmt Currency="{$default_currency}">
                            {$this->amount($money)}
                        </TotalAmt>
                    </Invoice>
                    <Tender>
                        <Card>
                            <ExtData Name="ORIGID" Value="{$reference}"></ExtData>
                        </Card>
                    </Tender>
                </PayData>
             </{$action}>
XML;
        return $this->build_request($bodyXml);
    }

    private function buildCreditCardRequest($action, $money, $credit_card, $options)
    {
        $default_currency = self::$default_currency;

        $bodyXml = <<<XML
             <{$action}>
                <PayData>
                    <Invoice>
XML;
        if (isset($options['ip'])) {
            $bodyXml .= "<CustIp>" . $options['ip'] . "</CustIp>";
        }

        if (isset($options['order_id'])) {
            $bodyXml .= "<InvNum>" . $options['order_id'] . "</InvNum>";
            $bodyXml .= "<Comment>" . $options['order_id'] . "</Comment>";
        }

        if (isset($options['description'])) {
            $bodyXml .= "<Description>"
                . $options['description']
                . "</Description>";
        }

        if (isset($options['billing_address'])) {
            $bodyXml .= "<BillTo>"
                . $this->add_address($options, $options['billing_address'])
                . "</BillTo>";
        }

        if (isset($options['shipping_address'])) {
            $bodyXml .= "<ShipTo>"
                . $this->add_address($options, $options['shipping_address'])
                . "</ShipTo>";
        }

        $bodyXml .= <<<XML
                        <TotalAmt Currency="{$default_currency}">
                            {$this->amount($money)}
                        </TotalAmt>
                    </Invoice>
                    <Tender>
XML;

        if (isset($options['order_items'])) {
            $bodyXml .= "<Items>";

            foreach ($options['order_items'] as $key => $item) {
                $count = $key + 1;
                $bodyXml .= <<<XML
                    <Item Number="{$count}">
                        <SKU>{$item['id']}</SKU>
                        <UPC>{$item['id']}</UPC>
                        <Description>{$item['description']}</Description>
                        <Quantity>{$item['quantity']}</Quantity>
                        <UnitPrice>{$item['unit_price']}</UnitPrice>
                        <TotalAmt>{$item['total']}</TotalAmt>
                    </Item>
XML;
            }

            $bodyXml .= "</Items>";
        }

        $bodyXml .= $this->addCreditCard($credit_card, $options);

        $bodyXml .= <<<XML
                    </Tender>
                </PayData>
             </{$action}>
XML;
        return $this->build_request($bodyXml);
    }

    private function addCreditCard($creditcard, $options = array())
    {
        $month = $this->cc_format($creditcard->month, 'two_digits');
        $year = $this->cc_format($creditcard->year, 'four_digits');

        $xml = <<<XML
        <Card>
            <CardType>{$this->creditCardType($creditcard)}</CardType>
            <CardNum>{$creditcard->number}</CardNum>
            <ExpDate>{$year}{$month}</ExpDate>
            <NameOnCard>{$creditcard->first_name}</NameOnCard>
            <CVNum>{$creditcard->verification_value}</CVNum>
XML;

        if ($this->requires_start_date_or_issue_number($creditcard)) {
            if (!is_null($creditcard->start_month)) {
                $startMonth = $this->cc_format($creditcard->start_month, 'two_digits');
                $startYear = $this->cc_format($creditcard->start_year, 'four_digits');
                $xml .= '<ExtData Name="CardStart" Value="' . $startYear . $startMonth . '"></ExtData>';
            }

            if (!is_null($creditcard->issue_number)) {
                $xml .= '<ExtData Name="CardIssue" Value="'
                        . $this->cc_format(
                            $creditcard->issue_number,
                            'two_digits'
                        )
                        . '"></ExtData>';
            }
        }

        $xml .= "<ExtData Name=\"LASTNAME\" Value=\"{$creditcard->last_name}\"></ExtData>";

        if (isset($options['three_d_secure'])) {
            $tds = $options['three_d_secure'];
            $xml .= <<<XML
                <BuyerAuthResult>
                    <Status>{$tds['pares_status']}</Status>
                    <ECI>{$tds['eci_flag']}</ECI>
                    <CAVV>{$tds['cavv']}</CAVV>
                    <XID>{$tds['xid']}</XID>
                </BuyerAuthResult>
XML;
        }

        $xml .= "</Card>";
        return $xml;
    }

    private function creditCardType($credit_card)
    {
        return is_null($this->card_brand($credit_card))
            ? ''
            : $this->CARD_MAPPING[$this->card_brand($credit_card)];
    }
}
