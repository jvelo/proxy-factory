<?php

require 'vendor/autoload.php';

// $routes = include __DIR__ . '/routes.php';

$gateway = new \ProxyGateway\ProxyGateway("http://www.mocky.io/v2/");
$gateway->handleRequest();