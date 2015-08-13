<?php

require 'vendor/autoload.php';

use \ProxyFactory\TwigResponseFilter;

// $routes = include __DIR__ . '/routes.php';

$gateway = new \ProxyFactory\ProxyFactory("http://httpbin.org/");
$gateway->addResponseFilter(new TwigResponseFilter());
$gateway->handleRequest();

?>