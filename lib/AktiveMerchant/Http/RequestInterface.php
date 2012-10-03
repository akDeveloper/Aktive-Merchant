<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Http;

interface RequestInterface
{

    public function setMethod($method);

    public function getMethod();

    public function setUrl($url);

    public function getUrl();

    public function setHeaders($headers);

    public function setBody($body);
    
    public function getBody();

    public function send();

    public function getResponseBody();

}
