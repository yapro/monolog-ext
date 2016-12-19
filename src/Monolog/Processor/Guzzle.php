<?php

namespace Debug\Monolog\Processor;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Post\PostBody;
use GuzzleHttp\Message\ResponseInterface;

class Guzzle
{
    const MAXIMUM_LENGTH_OR_RESPONSE_BODY = 300;

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
            $record['extra']['guzzleRequest'] = [
                'host' => $request->getHost(),
                'url' => $request->getUrl(),
                'config' => $request->getConfig(),
                'method' => $request->getMethod(),
                'headers' => implode('; ', $headers),
                'postFields' => $postFields,
            ];
            if ($record['message']->getResponse() instanceof ResponseInterface) {
                $responseBody = $record['message']->getResponse()->getBody();
                if (mb_strlen($responseBody) === self::MAXIMUM_LENGTH_OR_RESPONSE_BODY) {
                    $responseBody = mb_substr($responseBody, 0, self::MAXIMUM_LENGTH_OR_RESPONSE_BODY) . '...the message was cropped to 3000 symbols.';
                }
                $record['extra']['guzzleRequest']['response'] = $responseBody;
            }
        }
        return $record;
    }
}