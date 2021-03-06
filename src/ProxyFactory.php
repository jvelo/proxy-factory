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
     * @var bool whether or not to add the X-Forwarded-For header
     */
    protected $addXForwardedFor = true;

    /**
     * @var array the headers to strip from the proxied response
     */
    protected $stripHeaders = array("Connection", "Content-Length");

    /**
     * @var array
     */
    protected $requestFilters = array();

    /**
     * @var array
     */
    protected $responseFilters = array();

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

        $this->bridge = new HttpFoundationFactory();
        $this->psr7Factory = new DiactorosFactory();

        return $this;
    }

    /**
     * @param $uri the URI to (reverse) proxy to
     * @return $this this proxy factory
     */
    public function withUri($uri) {
        $this->proxyUri = $uri;
        return $this;
    }

    public function withoutXForwardedFor() {
        $this->addXForwardedFor = false;
        return $this;
    }

    public function addRequestFilter(callable $filter) {
        $this->requestFilters[]= $filter;
        return $this;
    }

    public function addResponseFilter(callable $filter) {
        $this->responseFilters[]= $filter;
        return $this;
    }

    /**
     * @param RequestInterface|ServerRequestInterface $request
     * @throws RuntimeException
     */
    function handleRequest(ServerRequestInterface $request = null) {

        if ($this->addXForwardedFor) {
            $this->addRequestFilter(new XForwardedForRequestFilter());
        }

        if ($request == null) {
            $this->symfonyRequest = Request::createFromGlobals();
            $this->request = $this->psr7Factory->createRequest($this->symfonyRequest);
        } else {
            $this->request = $request;
        }

        $this->request = array_reduce($this->requestFilters, function($request, callable $filter){
            $transformed = $filter($request);
            if (!$transformed instanceof ServerRequestInterface) {
                throw new \LogicException("Request filter does not return a request");
            }
            return $transformed;
        }, $this->request);


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
            throw new \RuntimeException("Failed to connect to end-point", $e);
        }

        $response = array_reduce($this->responseFilters, function(ResponseInterface $response, callable $filter){
            $transformed = $filter($this->request, $response);
            if (!$transformed instanceof ResponseInterface) {
                throw new \LogicException("Response filter does not a response");
            }
            return $transformed;
        }, $response);

        $response = array_reduce($this->stripHeaders, function(ResponseInterface $response, $header) {
            return $response->withoutHeader($header);
        }, $response);

        $symfonyResponse = $this->bridge->createResponse($response);
        $symfonyResponse->prepare($this->symfonyRequest);

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

        if ($proxiedRequest->getBody()->getSize() === null
            && $proxiedRequest->getHeaderLine("User-Agent") == "Http_TestCase/1.0") {
            // FIXME
            // Prevents an incompatibility with PHP-VCR :
            // Without this the stream size is null and not 0 so CurlFactory#applyBody is applied and it sets a
            // CURLOPT_READFUNCTION on the request, but not a CURLOPT_INFILESIZE ; which makes VCR fails.
            // See https://github.com/guzzle/guzzle/commit/0a3065ea4639c1df8b9220bc8ca3fb529d7f8b52#commitcomment-12551295
            $proxiedRequest = $proxiedRequest->withBody(\GuzzleHttp\Psr7\stream_for());
        }

        return $proxiedRequest;
    }

}