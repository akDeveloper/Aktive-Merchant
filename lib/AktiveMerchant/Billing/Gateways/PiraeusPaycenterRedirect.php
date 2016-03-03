<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Options;

class PiraeusPaycenterRedirect extends Gateway
{
    const TEST_URL = 'https://paycenter.piraeusbank.gr/services/tickets/issuer.asmx';

    const LIVE_URL = 'https://paycenter.piraeusbank.gr/services/tickets/issuer.asmx';

    const AUTHORIZE = '00';

    const PURCHASE = '02';

    /**
     * {@inheritdoc }
     */
    public static $supported_countries = array('GR');

    /**
     * {@inheritdoc }
     */
    public static $homepage_url = 'http://www.piraeusbank.gr';

    /**
     * {@inheritdoc }
     */
    public static $display_name = 'Piraeus Paycenter';

    /**
     * {@inheritdoc }
     */
    public static $default_currency = 'EUR';

    protected $post;

    public function __construct($options = array())
    {
        Options::required('acquire_id, merchant_id, pos_id, user, password', $options);

        if (isset($options['currency'])) {
            self::$default_currency = $options['currency'];
        }

        $this->options = $options;
    }

    public function ticket($money, array $options = array())
    {
        Options::required('order_id', $options);

        $this->post = null;

        $amount = $this->amount($money);

        $this->post .= <<<XML
      <MerchantReference>{$options['order_id']}</MerchantReference>
      <Amount>$amount</Amount>
      <CurrencyCode>{$this->currency_lookup(self::$default_currency)}</CurrencyCode>
      <ExpirePreauth>0</ExpirePreauth>
      <Installments>0</Installments>
      <Bnpl>0</Bnpl>
      <Parameters></Parameters>
XML;

        return $this->commit(self::PURCHASE, $money);

    }

    protected function commit($action, $money, array $parameters = array())
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $header = 'http://piraeusbank.gr/paycenter/redirection/IssueNewTicket';

        $postData = $this->postData($action, $parameters);

        $headers = array(
            "POST /services/paymentgateway.asmx HTTP/1.1",
            "Host: paycenter.piraeusbank.gr",
            "Content-type: text/xml; charset=\"utf-8\"",
            "Content-length: " . strlen($postData),
            "SOAPAction: \"$header\""
        );

        try {
            $data = $this->ssl_post($url, $postData, array('headers' => $headers));
        } catch (\AktiveMerchant\Http\Adapter\Exception $e) {
            $data = $e->getResponseBody();
        }

        $response = $this->parse($data);

        $test_mode = $this->isTest();

        return new Response(
            $this->successFrom($response),
            $this->messageFrom($response),
            $response,
            array(
                'test' => $test_mode,
                'authorization' => $response['authorization_id']
            )
        );
    }

    /**
     *
     * @param string $body
     */
    protected function parse($body)
    {
        $body = preg_replace('#(</?)soap:#', '$1', $body);
        $xml = simplexml_load_string($body);

        $response = array(
            'result_code' => null,
            'result_description' => null,
            'authorization_id' => null,
            'timestamp' => null,
            'minutes_to_expiration' => null
        );

        if (isset($xml->Body->IssueNewTicketResponse)) {
            $result = $xml->Body->IssueNewTicketResponse->IssueNewTicketResult;

            $response['result_code'] = (string) $result->ResultCode;
            $response['authorization_id'] = (string) $result->TranTicket;
            $response['timestamp'] = (string) $result->Timestamp;
            $response['minutes_to_expiration'] = (int) $result->MinutesToExpiration;
            $response['result_description'] = (string) $result->ResultDescription;

            return $response;
        } else {
            if (isset($sml->Body)) {
                $body = $xml->Body;

                $response['result_code'] = (string) $body->Fault->faultcode;
                $response['result_description'] = (string) $body->Fault->faultstring;
                $response['authorization_id'] = null;
                $response['timestamp'] = null;
                $response['minutes_to_expiration'] = null;
            }
        }

        return $response;
    }

    /**
     *
     * @param array $response
     *
     * @return string
     */
    protected function successFrom($response)
    {
        return $response['result_code'] == '0';
    }

    /**
     *
     * @param array $response
     *
     * @return string
     */
    protected function messageFrom($response)
    {
        return $response['result_description'];
    }

    protected function postData($action, $parameters)
    {
        /**
         * Add final parameters to post data and
         * build $this->post to the format that your payment gateway understands
         */
        $password = md5($this->options['password']);
        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
      <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
        <soap:Body>
          <IssueNewTicket xmlns="http://piraeusbank.gr/paycenter/redirection">
            <Request>
              <Username>{$this->options['user']}</Username>
              <Password>{$password}</Password>
              <MerchantId>{$this->options['merchant_id']}</MerchantId>
              <PosId>{$this->options['pos_id']}</PosId>
              <AcquirerId>{$this->options['acquire_id']}</AcquirerId>
              <RequestType>$action</RequestType>
              {$this->post}
            </Request>
          </IssueNewTicket>
        </soap:Body>
      </soap:Envelope>
XML;

        return ($xml);
    }
}
