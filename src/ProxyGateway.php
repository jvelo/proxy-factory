<?php

namespace ProxyGateway;

use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\RequestInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing;
use Symfony\Component\HttpKernel;
use Symfony\Component\Routing\RouteCollection;

use GuzzleHttp\Client;

class ProxyGateway {

    /**
     * @var RouteCollection additional routes this gateway handles
     */
    protected $routes;

    /**
     * @var Routing\RequestContext the request context of this gateway request execution
     */
    protected $context;

    /**
     * @var HttpKernel\Controller\ControllerResolverInterface the resolver used by this gateway
     */
    protected $resolver;

    /**
     * @var Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * @param string $uri
     * @param array $arguments
     * @throws RuntimeException
     */
    function __construct($uri = "http://www.mocky.io/v2/", $arguments = array())
    {
        $this->proxyUri = $uri;

        if (!isset($arguments['routes'])) {
            $this->routes = new RouteCollection();
        } else {
            if (!$arguments['routes'] instanceof RouteCollection) {
                throw new RuntimeException("Invalid router");
            }
            $this->routes = $arguments['routes'];
        }

        $this->context = new Routing\RequestContext();
        $this->matcher = new Routing\Matcher\UrlMatcher($this->routes, $this->context);
        $this->resolver = new HttpKernel\Controller\ControllerResolver();

        return $this;
    }

    /**
     * @param RequestInterface $request
     */
    function handleRequest(RequestInterface $request = null) {
        if ($request == null) {
            $this->request = Request::createFromGlobals();
        } else {
            $this->request = $request;
        }

        $this->context->fromRequest($this->request);
        $path = $this->request->getPathInfo();

        error_log($path);
        error_log($this->proxyUri);
        $client = $this->getHttpClient();
        //var_dump($client);
        try {
            $response = $client->get(ltrim($path, '/'));
        } catch (ClientException $e) {
            var_dump($e);
        }

        var_dump($response);
    }

    /**
     * @return \GuzzleHttp\Client the client used to communicate with the endpoint
     */
    protected function getHttpClient() {
        return new Client([
            'base_uri' => $this->proxyUri,
            'timeout'  => 10.0,
        ]);
    }

}