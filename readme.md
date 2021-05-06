MonologExt
===============
Very useful extensions for Monolog.

Installation
------------

Add as a requirement in your `composer.json` file or run
```sh
composer require yapro/monologext dev-master
```

For Symfony >= 2.x
------------

It is a collection of monolog processors, that gives you the opportunity to handle and log different errors.

Add needed for you services to your config.yml
```yml
services:
    # Adds exception`s information to a log record
    YaPro\MonologExt\Processor\AddInformationAboutExceptionProcessor:
        class: YaPro\MonologExt\Processor\AddInformationAboutExceptionProcessor
        tags:
            - { name: monolog.processor, handler: main }

    # Adds a call stack of the log-record location
    YaPro\MonologExt\Processor\AddStackTraceOfCallPlaceProcessor:
      class: YaPro\MonologExt\Processor\AddStackTraceOfCallPlaceProcessor
      tags:
        - { name: monolog.processor, handler: main }

    # Stop execution when problems occur (very useful in tests)
    YaPro\MonologExt\Processor\StopExecutionWhenProblemProcessor:
      class: YaPro\MonologExt\Processor\StopExecutionWhenProblemProcessor
      tags:
        - { name: monolog.processor, handler: main }
```
and then use logger service, examples:

```php
$logger->info('I just got the logger');
$logger->error('An error occurred');
```

Look up, variable $e will be transformed to string (Monolog`s functionality), and you will get: Message of Exception + Stack trace
```php
$logger->warning('My warning', array(
   'my' => 'data',
   'exception' => $e,// now you can see the above written custom stack trace as a string
));
$logger->warning('My second warning', array($e));// the short variant of version which you can see the above
}
```
By default, \YaPro\MonologExt\VarHelper extract an extra data into string by standard depth's level which is equal
to two. But, you can use any depth's level, example is equal a five:
```php
$logger->error('An error occurred', [ 'my mixed type var' => VarHelper::dump($myVar, 5) ] );
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

ExtraException is exception which you can to create as object, to add the extra data and throw away. After throwing the 
Monolog ExceptionProcessor will catches this exception and saves extra data to logs. Examples:

```php
throw (new ExtraException())->setExtra('mixed data');
```

Recommendation
------------------------
Add service json_formatter to file app/config/config.yml
It will help you to format error in the json, and then you can use https://www.elastic.co/products/kibana for aggregate all errors.
```yml
services:
    json_formatter:
        class: Monolog\Formatter\JsonFormatter
```
And don`t forget to add a monolog formatter:
```yml
monolog:
    handlers:
        main:
            formatter: json_formatter
```
If you wish to collect some data of http request, you can add WebProcessor:
```yml
services:
    monolog.processor.web:
        class: Monolog\Processor\WebProcessor
        tags:
            - { name: monolog.processor, handler: main }
```

Tests
------------
```sh
docker build -t yapro/monologext:latest -f ./Dockerfile ./
docker run --rm -v $(pwd):/app yapro/monologext:latest bash -c "cd /app \
  && composer install --optimize-autoloader --no-scripts --no-interaction \
  && /app/vendor/bin/phpunit /app/tests"
```

Dev
------------
```sh
docker build -t yapro/monologext:latest -f ./Dockerfile ./
docker run -it --rm -v $(pwd):/app -w /app yapro/monologext:latest bash
composer install -o
```
