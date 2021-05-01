MonologExt
===============
Very useful extensions for Monolog.

Installation
------------

Add as a requirement in your `composer.json` file or run
```sh
composer require yapro/monologext dev-master
```

Tests
------------
```sh
docker build -t yapro/monologext:latest -f ./Dockerfile ./
```

Dev
------------
```sh
docker build -t yapro/monologext:latest -f ./Dockerfile ./
docker run -it --rm -v $(pwd):/app -w /app yapro/monologext:latest bash
composer install --optimize-autoloader --no-scripts --no-interaction
./vendor/bin/phpunit tests/
```
