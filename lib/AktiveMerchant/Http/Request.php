<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Http;

use AktiveMerchant\Billing\Exception;
use AktiveMerchant\Http\Adapter\cUrl;
use AktiveMerchant\Common\Options;
use AktiveMerchant\Event\PreSendEvent;
use AktiveMerchant\Event\PostSendEvent;
use AktiveMerchant\Event\RequestEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Request
 *
 * @uses    RequestInterface
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license MIT {@link http://opensource.org/licenses/mit-license.php}
 */
class Request implements RequestInterface
{
    /**
     * The adapter to use for sending the request.
     *
     * @var    AdapterInterface
     * @access protected
     */
    protected $adapter;

    protected $url;

    protected $method;

    protected $headers = array();

    protected $body;

    protected $options;

    protected $dispatcher;

    protected $config = array(
        'connect_timeout'   => 10,
        'timeout'           => 0,
        'ssl_verify_peer'   => true,
        'ssl_verify_host'   => 2,
        'user_agent'        => null,
        'ssl_version'       => null
    );

    /**
     * Creates an instance of Request class.
     *
     * Allowed configuration options are
     *
     * connect_timeout: The number of seconds to wait while trying to connect.
     *                  Use 0 to wait indefinitely.
     * timeout        : The maximum number of seconds to allow cURL functions
     *                  to execute.
     * ssl_verify_peer: FALSE to stop cURL from verifying the peer's certificate.
     * ssl_verify_host: 1 to check the existence of a common name in the SSL
     *                  peer certificate. 2 to check the existence of a common
     *                  name and also verify that it matches the hostname
     *                  provided.
     * user_agent     : The contents of the "User-Agent: " header to be used
     *                  in a HTTP request.
     *
     * Additional options can be set via adapter directly, using
     * AdapterInterface::setOptions() method.
     *
     * @oaram  string $url     The endpoint url
     * @oaram  string $method  The request method
     * @oaram  array  $options Configuration options for request.
     * @access public
     */
    public function __construct(
        $url,
        $method = self::METHOD_GET,
        array $options = array()
    ) {

        $this->setUrl($url);

        $this->setMethod($method);

        $this->options = new Options($options);

        $this->setup_options();
    }

    public function getAdapter()
    {
        $this->adapter = $this->adapter ?: new cUrl();

        return $this->adapter;
    }

    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Gets configuration options
     *
     * @access public
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * {@inheritdoc}
     */
    public function setMethod($method)
    {
        $this->method = strtoupper($method);
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * Appends a header type to request.
     *
     * @param  string $name
     * @param  string $value
     * @access public
     * @return void
     */
    public function addHeader($name, $value)
    {
        $this->headers[] = "$name: $value";
    }

    /**
     * {@inheritdoc}
     */
    public function setBody($body)
    {
        $this->body = $body;
    }

    /**
     * {@inheritdoc}
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function send()
    {
        $preSendEvent = new PreSendEvent();
        $preSendEvent->setRequest($this);
        $this->getDispatcher()->dispatch(RequestEvents::PRE_SEND, $preSendEvent);

        $return = $this->getAdapter()->sendRequest($this);

        $postSendEvent = new PostSendEvent();
        $postSendEvent->setRequest($this);
        $this->getDispatcher()->dispatch(RequestEvents::POST_SEND, $postSendEvent);

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseBody()
    {
        return $this->getAdapter()->getResponseBody();
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseHeaders()
    {
        return $this->getAdapter()->getResponseHeaders();
    }

    protected function setup_options()
    {
        $connect_timeout = $this->options['timeout']
            ?: $this->options['connect_timeout'];

        $this->config['connect_timeout'] = $connect_timeout
            ?: $this->config['connect_timeout'];

        $this->config['timeout'] = $this->options['request_timeout']
            ?: $this->config['timeout'];

        $this->config['ssl_verify_peer'] = $this->options['ssl_verify_peer'] !== null
            ? $this->options['ssl_verify_peer']
            : $this->config['ssl_verify_peer'];

        $this->config['ssl_verify_host'] = $this->options['ssl_verify_host'] !== null
            ? $this->options['ssl_verify_host']
            : $this->config['ssl_verify_host'];

        $this->config['user_agent'] = $this->options['user_agent']
            ?: $this->getDefaultAgent();

        if ($this->options['headers']) {
            $this->setHeaders($this->options['headers']->getArrayCopy());
        }

        $this->config['ssl_version'] = $this->options['ssl_version']
            ?: $this->config['ssl_version'];
    }

    private function getDefaultAgent()
    {
        $os = \php_uname('s');
        $machine = \php_uname('m');

        return "Aktive-Merchant "
            . \AktiveMerchant\Billing\Base::VERSION
            ." ($os $machine) "
            ."PHP/".\phpversion()." "
            ."ZendEngine/".\zend_version();
    }

    /**
     * Gets dispatcher.
     *
     * @since Method available since Release 1.1.0
     *
     * @return EventDispatcherInterface
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Sets dispatcher.
     *
     * @param EventDispatcherInterface $dispatcher
     *
     * @since Method available since Release 1.1.0
     *
     * @return void
     */
    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }
}
