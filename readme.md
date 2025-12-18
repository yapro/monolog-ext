MonologExt
===============
The really useful Monolog`s extensions.

![lib tests](https://github.com/yapro/monolog-ext/actions/workflows/main.yml/badge.svg)

Installation
------------

Add as a requirement in your `composer.json` file or run
```sh
composer require yapro/monolog-ext dev-master
```

Configuration of Symfony >= 2.x
------------
You can use the best way to handle your logs because it's the easiest way:
```yaml
monolog:
    channels:
        - deprecation # Deprecations are logged in the dedicated "deprecation" channel when it exists
    handlers:
        main:
            type: service
            id: YaPro\MonologExt\Handler\WiseHandler
```
and don't forget to register the handler as a service:
```yaml
services:
  YaPro\MonologExt\Handler\WiseHandler: ~
```
You will get the features:
- writing logs to stderr ( https://12factor.net/logs )
- json representation
- ignoring logs when the client sends an invalid http request (4xx)
- log records of all levels in the application code (src dir, not in vendor dir)
- log records of the NOTICE level and higher in libraries (vendor dir)
- smart a record reducing (removing keys from the record context when the record size exceeds 8192 bytes)

and other features such as development mode.

You can also configure WiseHandler:

| env var name                              | default value | example | description                                                                                                           |
|-------------------------------------------|---------------|---------|-----------------------------------------------------------------------------------------------------------------------|
| EH_DEV_MODE_PHP_FPM                       | 0             | 1       | print out important debugging information and stop the execution (comfortable development in php-fpm)                 |
| EH_DEV_MODE_PHP_CLI                       | 0             | 1       | print out important debugging information and stop the execution (comfortable development in php-cli)                 |
| EH_MAX_DUMP_LEVEL                         | 5             | 3       | the nesting level of the objects to be serialized (3 is less detailed level than 5)                                   |
| EH_IGNORE_RECORD_LEVEL_BELOW              | 0             | 250     | errors with a level below the specified one will be ignored (250 is less then NOTICE, 0 == all records)               |
| EH_STOP_WHEN_RECORD_LEVEL_ABOVE           | 500           | 400     | an error with a level higher than the specified one will stop the http request with the 500 status (Logger::CRITICAL) |

You can also use the collection of monolog processors, that gives you the opportunity to handle and log different errors.

Add needed for you app to your config.yml

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

  # Moves the contents of the content field to the field specified in the processor constructor + removes the context field
  YaPro\MonologExt\Processor\RenameContextProcessor:
    class: YaPro\MonologExt\Processor\RenameContextProcessor
    tags:
      - { name: monolog.processor, handler: main, priority: -1 }

  # Moves the contents of the content field to the location specified in the record field + removes the context field
  YaPro\MonologExt\Processor\MoveContextProcessor:
    class: YaPro\MonologExt\Processor\MoveContextProcessor
    tags:
      - { name: monolog.processor, handler: main, priority: -1 }

  # Adds a request as curl command to a log record
  # Old version - https://github.com/yapro/monolog-ext/blob/php5/src/Monolog/Processor/RequestAsCurl.php
  monolog.processor.request_as_curl:
    class: Debug\Monolog\Processor\RequestAsCurl
    arguments: [ "@request_stack" ]
    tags:
      - { name: monolog.processor, handler: main }

  # not implemented yet. Old version - https://github.com/yapro/monolog-ext/blob/php5/src/Monolog/Processor/Guzzle.php
  monolog.processor.guzzle:
    class: Debug\Monolog\Processor\Guzzle
    tags:
      - { name: monolog.processor, handler: main }
  # странная особенность - если не объявить, то возникает ошибка: Cannot autowire service, no such service exists. You
  # should maybe alias this class to one of these existing services: "monolog.formatter.json", "monolog.formatter.loggly".
  # создал вопрос: https://github.com/symfony/symfony/issues/36527
  Monolog\Formatter\JsonFormatter:
    class: Monolog\Formatter\JsonFormatter

```
then use logger, examples:

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
```
By default, \YaPro\MonologExt\VarHelper extract an extra data into string by standard depth's level which is equal
to two. But, you can use any depth's level, example is equal a five:
```php
$logger->error('An error occurred', [ 'my mixed type var' => (new VarHelper)->dump($myVar, 5) ] );
```

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

## The configuration without Symfony framework.
------------

Here is a configuration sample:
```php
<?php

use Monolog\Logger;
use YaPro\MonologExt\Handler\WiseHandler;

$logger = new Logger('app');
$logger->pushHandler(new WiseHandler());

$logger->error('Payment error', ['order_id' => 42]);
```

If you want to handle errors:

```php
<?php

use YaPro\MonologExt\PrudentErrorHandler;

new PrudentErrorHandler($logger);
```

Tests
------------
```sh
docker build -t yapro/monolog-ext:latest -f ./Dockerfile ./
docker run --rm -v $(pwd):/app yapro/monolog-ext:latest bash -c "cd /app \
  && composer install --optimize-autoloader --no-scripts --no-interaction \
  && /app/vendor/bin/phpunit /app/tests"
```

Dev
------------
```sh
docker build -t yapro/monolog-ext:latest -f ./Dockerfile ./
docker run -it --rm --user=$(id -u):$(id -g) --add-host=host.docker.internal:host-gateway -v $(pwd):/app -w /app yapro/monolog-ext:latest bash
composer install -o
```
Run tests with xdebug:
```shell
PHP_IDE_CONFIG="serverName=common" \
XDEBUG_SESSION=common \
XDEBUG_MODE=debug \
XDEBUG_CONFIG="max_nesting_level=200 client_port=9003 client_host=host.docker.internal" \
/app/vendor/bin/phpunit /app/tests
```
