<?php

namespace Debug\Monolog\Processor;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\ServerBag;

class RequestAsCurl
{
    /**
     * @var Request;
     */
    private $requestStack;

    /**
     * @var ServerBag
     */
    private $serverBag;

    function __construct(RequestStack $requestStack){
        $this->requestStack = $requestStack;
        $this->serverBag = new ServerBag($_SERVER);
    }

    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        $request = $this->requestStack->getCurrentRequest();;
        $parts = [
            'command' => 'curl',
            'headers' => implode(' ', $this->getHeaders()),
            'data' => '--data \'' . $request->getContent().'\'',
            'url' => $request->getSchemeAndHttpHost() . $request->getPathInfo(),
        ];
        $record['requestAsCurl'] = implode(' ', $parts);
        return $record;
    }

    /**
     * information about case sensitivity:
     * @link http://stackoverflow.com/questions/7718476/are-http-headers-content-type-c-case-sensitive
     * @return array
     */
    private function getHeaders()
    {
        $headers = [];
        foreach ($this->serverBag->getHeaders() as $key => $value) {
            $key = str_replace(' ', '-', str_replace('_', ' ', $key));
            $headers[] = '-H \'' . $key . ': ' . $value . '\'';
        }
        return $headers;
    }
}