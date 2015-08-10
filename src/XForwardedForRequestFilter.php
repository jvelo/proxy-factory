<?php

namespace ProxyFactory;

use Psr\Http\Message\ServerRequestInterface;

class XForwardedForRequestFilter
{
    private $trustPreviousReverseProxies = true;

    function __construct($options = array())
    {
        if (isset($options['ignorePreviousProxies']) && $options['ignorePreviousProxies']) {
            $this->trustPreviousReverseProxies = false;
        }
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $forwardedFor = array();

        if ($this->trustPreviousReverseProxies && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwardedFor[]= (explode(",", $_SERVER['HTTP_X_FORWARDED_FOR']));
        }

        $forwardedFor[]= $_SERVER['REMOTE_ADDR'];

        error_log("Header: " . implode(",", $forwardedFor));

        return $request->withHeader("X-Forwarded-For", implode(",", $forwardedFor));
    }

}