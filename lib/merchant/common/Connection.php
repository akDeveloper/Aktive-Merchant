<?php
/**
 * Description of Connection
 *
 * @author Andreas Kollaros
 */
class Merchant_Connection {
  
  private $endpoint;

  public function __construct($endpoint) {
    $this->endpoint = $endpoint;
  }

  public function request ($method, $body, $options = array()){
    
    $timeout = isset($options['timeout']) ? $options['timeout'] : '0';
    $headers = isset($options['headers']) ? $options['headers'] : array();

    $server = parse_url($this->endpoint);

    if (!isset($server['port']))
      $server['port'] = ($server['scheme'] == 'https') ? 443 : 80;

    if (!isset($server['path'])) $server['path'] = '/';

    if (isset($server['user']) && isset($server['pass']))
      $headers[] = 'Authorization: Basic ' . base64_encode($server['user'] . ':' . $server['pass']);

    $transaction_url = $server['scheme'] . '://' . $server['host'] . $server['path'] . (isset($server['query']) ? '?' . $server['query'] : '');
    
    Merchant_Logger::save_request($body);

    if ( function_exists('curl_init') ) {
      $curl = curl_init($transaction_url);

      curl_setopt($curl, CURLOPT_PORT, $server['port']);
      curl_setopt($curl, CURLOPT_HEADER, 0);
      curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
      curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
      curl_setopt($curl, CURLOPT_CONNECTTIMEOUT , $timeout);
      if ($method == 'post')
        curl_setopt($curl, CURLOPT_POST, 1);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $body);

      $response = curl_exec($curl);
      
      curl_close($curl);

      Merchant_Logger::log($response);
      Merchant_Logger::save_response($response);

      return $response;
    } else {
      throw new Exception ('curl is not installed!');
    }
  }
}
?>
