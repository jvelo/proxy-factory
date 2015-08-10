<?php

use ProxyFactory\ProxyFactory;

class ProxyFactoryTests extends \Proxy_TestCase
{

    public function testSimpleGet()
    {
        $this->withCassette("get");
        $this->start();
        $response = $this->get("get?hello=world");
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getBody());
        $this->assertObjectHasAttribute("args", $json);
        $this->assertObjectHasAttribute("hello", $json->args);
        $this->assertEquals("world", $json->args->hello);
        $this->assertEquals("application/json", $response->getHeaderLine("Content-Type"));
    }

    public function testXForwardedForIsPresentByDefault()
    {
        $this->withCassette("headers-x-forwarded-for");
        $this->start();
        $response = $this->get("headers?show_env=1");
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getBody());
        $this->assertObjectHasAttribute("headers", $json);
        $this->assertObjectHasAttribute("X-Forwarded-For", $json->headers);
        $forwardedFor = $json->headers->{'X-Forwarded-For'};
        $this->assertNotEmpty($forwardedFor);
        $this->assertRegExp("/{$this->host}/i",$forwardedFor);
    }

    public function testDisableXForwardedFor()
    {
        $this->withCassette("headers-x-forwarded-for-disabled");
        $this->configure(function(ProxyFactory $factory){
            $factory->withoutXForwardedFor();
        });
        $this->start();
        $response = $this->get("headers?show_env=1");
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getBody());
        $this->assertObjectHasAttribute("headers", $json);
        $this->assertObjectHasAttribute("X-Forwarded-For", $json->headers);
        $forwardedFor = $json->headers->{'X-Forwarded-For'};
        $this->assertNotEmpty($forwardedFor);
        $this->assertNotRegExp("/{$this->host}/i",$forwardedFor);
    }


}