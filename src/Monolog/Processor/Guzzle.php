<?php

namespace Debug\Monolog\Processor;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Post\PostBody;
use GuzzleHttp\Message\ResponseInterface;

class Guzzle
{
    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        if ($record['message'] instanceof RequestException || $record['message'] instanceof ConnectException) {
            if (!array_key_exists('extra', $record)) {
                $record['extra'] = [];
            }
            $request = $record['message']->getRequest();
            $body = $request->getBody();
            $postFields = [];
            if ($body instanceof PostBody) {
                $postFields = $body->getFields();
            }
            $headers = [];
            foreach ($request->getHeaders() as $name => $values) {
                $headers[] = $name . ':' . implode(', ', $values);
            }
            $context['extra']['guzzleRequest'] = [
                'host' => $request->getHost(),
                'url' => $request->getUrl(),
                'config' => $request->getConfig(),
                'method' => $request->getMethod(),
                'headers' => implode('; ', $headers),
                'postFields' => $postFields,
            ];
            if ($record['message']->getResponse() instanceof ResponseInterface) {
                $context['extra']['guzzleRequest']['response'] = mb_substr($record['message']->getResponse()->getBody(), 0, 3000);
            }
        }
    }
}