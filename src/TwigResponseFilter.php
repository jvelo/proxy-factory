<?php

namespace ProxyFactory;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TwigResponseFilter
{

    function __construct($options = array())
    {
        $loader = new \Twig_Loader_Filesystem(getcwd() . '/views/');
        $this->twig = new \Twig_Environment($loader);
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        if (strpos($response->getHeaderLine("Content-Type"), "application/json") == 0) {
            $json = json_decode($response->getBody()->getContents(), true);
            $response = $response->withBody(\GuzzleHttp\Psr7\stream_for($this->twig->render('layout.twig', $json)));
            $response = $response->withHeader("Content-Type", "text/html");

        }

        return $response;
    }

}