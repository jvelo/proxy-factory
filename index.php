<?php

require 'vendor/autoload.php';

// $routes = include __DIR__ . '/routes.php';

$gateway = new \ProxyFactory\ProxyFactory("http://www.mocky.io/v2/");
$gateway->handleRequest();


