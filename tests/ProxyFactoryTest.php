<?php

class ProxyFactoryTests extends \Proxy_TestCase {

    public function test() {
        $this->withCassette("5185415ba171ea3a00704eed");
        $this->start();
        $response = $this->get("5185415ba171ea3a00704eed");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("{\"hello\": \"world\"}", $response->getBody()->getContents());
    }

}