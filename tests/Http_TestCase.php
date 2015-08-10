<?php

use GuzzleHttp\Client;

/**
 * Class Http_TestCase test case that fires the PHP builtin server for performing HTTP tests.
 *
 * Inspired and adapted from http://tech.vg.no/2013/08/16/using-phps-built-in-web-server-in-behat-tests/
 */
abstract class Http_TestCase extends \PHPUnit_Framework_TestCase {

    protected $pid;

    protected $host = "127.0.0.1";

    protected $port = 7999;

    protected $root = ".";

    protected $router = null;

    protected $httpClientConfiguration = array();

    /**
     * @param $url
     * @return Psr\Http\Message\ResponseInterface;
     */
    protected function get($url) {
        return $this->getHttpClient()->get($url);
    }

    protected function getHttpClient() {
        $base_uri = "http://{$this->host}:{$this->port}";
        return new Client(array_merge([
            'base_uri' => $base_uri,
            'timeout'  => 1.0,
            'headers' => [
                'User-Agent' => 'Http_TestCase/1.0'
            ]
        ], $this->httpClientConfiguration));
    }

    protected function withRouter ($router) {
        $this->router = $router;
    }

    protected function withRoot($root) {
        $this->root = $root;
    }

    protected function setUp()
    {
        $this->startAndWaitForServer();
    }

    protected function startAndWaitForServer()
    {
        $this->pid = $this->startServer($this->host, $this->port);

        if (!$this->pid) {
            throw new RuntimeException('Could not start the web server');
        }

        $start = microtime(true);
        $connected = false;
        while (microtime(true) - $start <= (int) 1) {
            if ($this->canConnectToServer($this->host, $this->port)) {
                $connected = true;
                break;
            }
        }
        if (!$connected) {
            $this->killProcess($this->pid);
            throw new RuntimeException("Could not connect to the web server");
        }
    }

    protected function tearDown()
    {
        $this->killProcess($this->pid);
    }

    /**
     * @param $pid the PID to kill
     */
    private function killProcess($pid) {
        exec('kill ' . (int) $pid);
    }

    /**
     * Start the built in server
     *
     * @param string $host The hostname to use
     * @param int $port The port to use
     * @return int returns the PID of the server
     */
    private function startServer() {
        $command = sprintf('php -S %s:%d -t %s %s >/dev/null 2>&1 & echo $!',
            $this->host,
            $this->port,
            $this->root,
            $this->router
        );
        $output = array();
        exec($command, $output);
        return (int) $output[0];
    }

    /**
     * See if we can connect to the httpd
     *
     * @param string $host The hostname to connect to
     * @param int $port The port to use
     * @return boolean
     */
    private function canConnectToServer($host, $port) {
        // Disable error handler for now
        set_error_handler(function() { return true; });
        // Try to open a connection
        $sp = fsockopen($host, $port);
        // Restore the handler
        restore_error_handler();
        if ($sp === false) {
            return false;
        }
        fclose($sp);
        return true;
    }


}