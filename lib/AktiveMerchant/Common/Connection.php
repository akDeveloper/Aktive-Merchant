<?php

/**
 * A connection to a remote server.
 * 
 * Used to send an HTTP or HTTPS request to a URL.
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class Merchant_Connection
{

    private $endpoint;

    /**
     * Create a new connection with the given endpoint
     * 
     * @param string $endpoint URL of remote endpoint
     */
    public function __construct($endpoint)
    {
        $this->endpoint = $endpoint;
    }

    /**
     * Send an HTTP or HTTPS request.
     * 
     * 
     * @param string $method Type of HTTP request ('post' for a POST, anything else for a GET)
     * @param string $body Body of request to send
     * @param array $options Options for this request, including:
     *                       <ul>
     *                         <li>timeout - Timeout in seconds
     *                         <li>user_agent - User-agent header to send
     *                         <li>headers - Array of additional headers to send.
     *                             Each header should be a string with the header name, 
     *                             followed by a colon, followed by the header value.  See
     *                             CURLOPT_HTTPHEADER for details.
     *                         <li>allow_unsafe_ssl - Set to a true value to allow SSL transactions
     *                             even if the certificate fails.
     *                       </ul>
     * @throws Merchant_Billing_Exception If the request fails at the network or HTTP layer
     */
    public function request($method, $body, $options = array())
    {

        $timeout = isset($options['timeout']) ? $options['timeout'] : '0';
        $user_agent = isset($options['user_agent']) ? $options['user_agent'] : null;
        $headers = isset($options['headers']) ? $options['headers'] : array();

        $server = parse_url($this->endpoint);

        if (!isset($server['port']))
            $server['port'] = ($server['scheme'] == 'https') ? 443 : 80;

        if (!isset($server['path']))
            $server['path'] = '/';

        if (isset($server['user']) && isset($server['pass']))
            $headers[] = 'Authorization: Basic ' . base64_encode($server['user'] . ':' . $server['pass']);

        $transaction_url = $server['scheme'] . '://' . $server['host'] . $server['path'] . (isset($server['query']) ? '?' . $server['query'] : '');

        Merchant_Logger::save_request($body);

        if (function_exists('curl_init')) {
            $curl = curl_init($transaction_url);

            curl_setopt($curl, CURLOPT_PORT, $server['port']);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, empty($options['allow_unsafe_ssl']) ? 0 : 1);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
            curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
            if (isset($user_agent))
                curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);

            if ($method == 'post')
                curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);

            $response = curl_exec($curl);

            // Check for outright failure
            if ($response === FALSE) {
              $ex = new Merchant_Billing_Exception(curl_error($curl), curl_errno($curl));
              curl_close($curl);
              throw $ex;
            }
            
            // We got a response, so let's log it
            Merchant_Logger::log("Merchant response: $response");
            Merchant_Logger::save_response($response);
            
            // Now check for an HTTP error
            $curlInfo = curl_getinfo($curl);
            if (($curlInfo['http_code'] < 200) && ($curlInfo['http_code'] >= 300)) {
              $ex = new Merchant_Billing_Exception("HTTP Status #" . $this->m_curlinfo['http_code']."\n".($this->m_doc?"\n$this->m_doc":"")."CurlInfo:\n".print_r($this->m_curlinfo,TRUE));
              curl_close($curl);
              throw $ex;
            }
            curl_close($curl);
            
            // OK, the response was OK at the HTTP level at least!  Pass it up a layer.
            return $response;
        } else {
            throw new Merchant_Billing_Exception('curl is not installed!');
        }
    }

}

?>
