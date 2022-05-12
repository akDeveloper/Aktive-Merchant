<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Event;

use Symfony\Component\EventDispatcher\Event;

class PreSendEvent extends Event
{
    protected $request;

    /**
     * Gets request.
     *
     * @access public
     * @return mixed
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Sets request.
     *
     * @param mixed $request the value to set.
     * @access public
     * @return void
     */
    public function setRequest($request)
    {
        $this->request = $request;
    }
}
