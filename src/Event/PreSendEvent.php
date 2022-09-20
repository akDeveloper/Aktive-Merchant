<?php

declare(strict_types=1);

namespace AktiveMerchant\Event;

use Symfony\Contracts\EventDispatcher\Event;

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
