<?php

abstract class Proxy_TestCase extends Http_TestCase
{
    protected $routerFileName;

    protected $cassette;

    protected $configurationFunctionSource;

    protected function withCassette($cassette)
    {
        $this->cassette = $cassette;
        return $this;
    }

    protected function configure(Closure $closure)
    {
        $this->configurationFunctionSource = $this->getClosureSource($closure);
        return $this;
    }

    protected function start()
    {
        $routerSource = <<<'EOT'
<?php

require 'vendor/autoload.php';

use VCR\VCR;

$gateway = new \ProxyFactory\ProxyFactory("http://httpbin.org/");

EOT;
        if (isset($this->configurationFunctionSource)) {
            $routerSource .= "\$configure = (";
            $routerSource .= $this->configurationFunctionSource;
            $routerSource .= "\n";
            $routerSource .= "\$configure(\$gateway);\n";
        }

        if (isset($this->cassette)) {
            $routerSource .= "VCR::turnOn();\n";
            $routerSource .= "VCR::insertCassette(\"{$this->cassette}.yml\");\n";
        }

        $routerSource .= "\$gateway->handleRequest();\n";

        if (isset($this->cassette)) {
            $routerSource .= "VCR::eject();\n";
            $routerSource .= "VCR::turnOff();\n";
        }

        $this->routerFileName = "tests/routes/generated/" . md5($routerSource) . '.php';
        $routerFile = fopen($this->routerFileName, "w") or die("Unable to write router file");
        fwrite($routerFile, $routerSource);
        fclose($routerFile);

        parent::withRouter($this->routerFileName);
        parent::startAndWaitForServer();
    }

    protected function tearDown()
    {
        parent::tearDown();
    }


    protected function setUp()
    {
        parent::withRoot("tests/");
    }

    /**
     * From http://www.metashock.de/2013/05/dump-source-code-of-closure-in-php/
     *
     * @param callable $c the closure to get the source of
     * @return string the closure source
     */
    function getClosureSource(Closure $c)
    {
        $str = 'function (';
        $r = new ReflectionFunction($c);
        $params = array();
        foreach ($r->getParameters() as $p) {
            $s = '';
            if ($p->isArray()) {
                $s .= 'array ';
            } else if ($p->getClass()) {
                $s .= $p->getClass()->name . ' ';
            }
            if ($p->isPassedByReference()) {
                $s .= '&';
            }
            $s .= '$' . $p->name;
            if ($p->isOptional()) {
                $s .= ' = ' . var_export($p->getDefaultValue(), TRUE);
            }
            $params [] = $s;
        }
        $str .= implode(', ', $params);
        $str .= '){' . PHP_EOL;
        $lines = file($r->getFileName());
        for ($l = $r->getStartLine(); $l < $r->getEndLine(); $l++) {
            $str .= $lines[$l];
        }
        return $str;
    }


}