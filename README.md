Debug
===============

Installation
------------

Add Debug as a requirement in your `composer.json` file or run
```sh
$ composer require yapro/debug dev-master
```

For Symfony 2.x
------------

It is a collection of monolog processors, that gives you the opportunity to handle and log different errors.

Add needed for you services to file app/config/config.yml
```
services:
    monolog.processor.debug:
        class: Debug\Monolog\Processor\Debug
        tags:
            - { name: monolog.processor, handler: main }

    monolog.processor.guzzle:
        class: Debug\Monolog\Processor\Guzzle
        tags:
            - { name: monolog.processor, handler: main }

    monolog.processor.request_as_curl:
        class: Debug\Monolog\Processor\RequestAsCurl
        arguments:  [@request_stack]
        tags:
            - { name: monolog.processor, handler: main }
```
and then use logger service:

```
public function indexAction()
{
    $logger = $this->get('logger');
    $logger->info('I just got the logger');
    $logger->error('An error occurred');
}
```

For projects without Symfony 2 framework.
------------

Debug is a [Monolog Cascade](https://github.com/theorchard/monolog-cascade) extension that which gives you the opportunity to handle and log errors of different levels.

### Usage

Just use your logger as shown below
```php
Cascade::fileConfig($config);
Log::info('Well, that works!');
Log::error('Maybe not...', ['some'=>'extra data']);
```

### Configuring your loggers

Monolog Cascade supports the following config formats:
 - Yaml
 - JSON
 - Php array

### Configuration structure

Here is a sample Php array config file:

```php
<?php

$config = [
    'formatters' => [
        'dashed' => [
            //'class' => 'Monolog\Formatter\LineFormatter',
            'class' => \Monolog\Formatter\JsonFormatter::class
            //'format' => '%datetime%-%channel%.%level_name% - %message%'
        ]
    ],
    'handlers' => [
        'console' => [
            'class' => 'Monolog\Handler\StreamHandler',
            'level' => 'DEBUG',
            'formatter' => 'dashed',
            'stream' => 'php://stdout'
        ],
        'info_file_handler' => [
            'class' => 'Monolog\Handler\StreamHandler',
            'level' => 'INFO',
            'formatter' => 'dashed',
            'stream' => './example_info.log'
        ]
    ],
    'processors' => [
        'web_processor' => [
            'class' => 'Monolog\Processor\WebProcessor'
        ]
    ],
    'loggers' => [
        'mainLogger' => [
            'handlers' => [
                0 => 'console',
                1 => 'info_file_handler'
            ],
            'processors' => [
                0 => 'web_processor'
            ]
        ]
    ],
    'disable_existing_loggers' => true,
    'errorReporting' => E_ALL & ~E_DEPRECATED & ~E_STRICT,
];
```

More detailed information about the configurations - https://github.com/theorchard/monolog-cascade


What is ExtraException
------------------------

ExtraException is exception which you can to create as object, to add the extra data and throw away. After throwing the Debugger will catches this exception and saves extra data to logs. Examples:

```
throw (new ExtraException())->setExtra('mixed data');
```
or:
```
try {
    ...
} catch (\Exception $e) {
    throw (new ExtraException())->setCustomTrace($e->getTraceAsString());
```

Recomendation
------------------------
Add service json_formatter to file app/config/config.yml
It will help you to format error in the json, and then you can use https://www.elastic.co/products/kibana for aggregate all errors.
```
services:
    json_formatter:
        class: Monolog\Formatter\JsonFormatter
```
And don`t forget to add a monolog formatter:
```
monolog:
    handlers:
        main:
            formatter: json_formatter
```
If you wish to collect some data of http request, you can add WebProcessor:
```
services:
    monolog.processor.web:
        class: Monolog\Processor\WebProcessor
        tags:
            - { name: monolog.processor, handler: main }
```