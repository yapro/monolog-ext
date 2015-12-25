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
    private $request;

    /**
     * @var ServerBag
     */
    private $serverBag;

    function __construct(RequestStack $requestStack){
        $this->request = $requestStack->getCurrentRequest();
        $this->serverBag = new ServerBag($_SERVER);
    }

    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        $parts = [
            'command' => 'curl',
            'headers' => implode(' ', $this->getHeaders()),
            'data' => '--data \'' . $this->request->getContent().'\'',
            'url' => $this->request->getSchemeAndHttpHost() . $this->request->getPathInfo(),
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