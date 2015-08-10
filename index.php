<?php

require 'vendor/autoload.php';

// $routes = include __DIR__ . '/routes.php';

$gateway = new \ProxyFactory\ProxyFactory("http://httpbin.org/");
$gateway->handleRequest();


