<?php

namespace ProxyFactory;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel;
use Symfony\Component\Routing;
use Symfony\Component\Routing\RouteCollection;
use Zend\Diactoros\Uri;

class ProxyFactory {

    /**
     * @var RouteCollection additional routes this proxy handles
     */
    protected $routes;

    /**
     * @var Routing\RequestContext the request context of this proxy request execution
     */
    protected $context;

    /**
     * @var HttpKernel\Controller\ControllerResolverInterface the resolver used by this proxy
     */
    protected $resolver;

    /**
     * @var Request
     */
    protected $symfonyRequest;

    /**
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * @var HttpFoundationFactory bridge used to convert PSR-7 requests/responses to Symfony HTTP foundation equivalents
     */
    protected $bridge;

    /**
     * @var DiactorosFactory factory to convert Symfony HTTP foundation requests to PSR-7 requests
     */
    protected $psr7Factory;

    /**
     * @var Uri the base URI of the proxied end-point
     */
    protected $proxyUri;

    /**
     * @param string $uri
     * @param array $arguments
     * @throws RuntimeException
     */
    function __construct($uri = "http://www.mocky.io/v2/", $arguments = array())
    {
        $this->proxyUri = new Uri($uri);

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

        $this->bridge = new HttpFoundationFactory();
        $this->psr7Factory = new DiactorosFactory();

        return $this;
    }

    /**
     * @param RequestInterface|ServerRequestInterface $request
     * @throws RuntimeException
     */
    function handleRequest(ServerRequestInterface $request = null) {
        if ($request == null) {
            $this->symfonyRequest = Request::createFromGlobals();
            $this->request = $this->psr7Factory->createRequest($this->symfonyRequest);
        } else {
            $this->request = $request;
            $this->symfonyRequest = $this->bridge->createRequest($this->request);
        }

        $this->context->fromRequest($this->symfonyRequest);

        $proxiedRequest = $this->convertToProxiedRequest();

        $client = $this->getHttpClient();

        try {
            //echo "<pre>";
            $response = $client->send($proxiedRequest,  ['debug' => false]);
            //echo "</pre>";
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
            }
            else {
                throw new \RuntimeException("Failed to connect to end-point", 0, $e);
            }

        } catch (ConnectException $e) {
            throw new RuntimeException("Failed to connect to end-point", $e);
        }

        $symfonyResponse = $this->bridge->createResponse($response);
        $symfonyResponse->send();
    }

    /**
     * @return \GuzzleHttp\Client the client used to communicate with the endpoint
     */
    protected function getHttpClient() {
        return new Client([
            'base_uri' => $this->proxyUri,
            'timeout'  => 5.0,
        ]);
    }

    /**
     * @return \Psr\Http\Message\RequestInterface
     */
    public function convertToProxiedRequest()
    {
        $proxiedUri = $this->request->getUri()
            ->withScheme($this->proxyUri->getScheme())
            ->withHost($this->proxyUri->getHost())
            ->withPath(rtrim($this->proxyUri->getPath(), '/') . $this->request->getUri()->getPath());

        if ($this->proxyUri->getPort() != null) {
            $proxiedUri = $proxiedUri->withPort($this->proxyUri->getPort());
        } else {
            $proxiedUri = $proxiedUri->withPort($proxiedUri->getScheme() === "https" ? 443 : 80);
        }

        $proxiedRequest = $this->request
            ->withUri($proxiedUri)
            ->withHeader("host", $this->proxyUri->getHost());

        return $proxiedRequest;
    }

}