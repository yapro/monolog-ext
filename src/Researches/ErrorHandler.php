<?php
declare(strict_types=1);

namespace Symfony\Component\ErrorHandler;

/**
 * Проблема: при неправильном значении пути к классу в services.yaml мы получаем 4 ошибки об одном и том же, а
 * различаются они только тем, что к основной ошибке дописывается какая-нибудь информация, например:
 * - "message":"Uncaught PHP Exception Error: \"Class \"YaPro\\SeoBundle\\MyListener\" not found\" at App_KernelProdContainer.php line 257"
 * - "message":"Uncaught Error: Class \"YaPro\\SeoBundle\\MyListener\" not found"
 * - "message":"Uncaught PHP Exception Symfony\\Component\\ErrorHandler\\Error\\ClassNotFoundError: \"Attempted to load class \"MyListener\" from namespace \"YaPro\\SeoBundle\".\nDid you forget a \"use\" statement for another namespace?\" at App_KernelProdContainer.php line 257"
 * - "message":"Uncaught Error: Class \"YaPro\\SeoBundle\\MyListener\" not found"
 *
 * Решение: подменять хендлер ошибок
 *
 *  Установка производится в конце файла backend/config/autoload.php добавлением строки:
 *  $composer->addClassMap(["Symfony\Component\ErrorHandler\ErrorHandler" => __DIR__ . "/MyErrorHandler/ErrorHandler.php"]);
 *
 * Для начала нужно пересобрать автолоад (а то композер может полагаться на кэш): composer dump-autoload
 *
 * Детали реализации:
 *
 * Итак, мы подменили глючный \Symfony\Component\ErrorHandler\ErrorHandler на нативный (который в PHP). Данный хендлер
 * вызывается в \Symfony\Bundle\FrameworkBundle\FrameworkBundle::boot и конфигурируется парой строк ниже:
 * $this->container->get('debug.error_handler_configurator')->configure($handler);
 * Но, все равно срабатывает \Symfony\Component\Console\Application::doRunCommand(), который ловит все исключения
 * и dispatch-ит их как события:
 * - Symfony\Component\Console\ConsoleEvents::ERROR
 * - Symfony\Component\Console\ConsoleEvents::TERMINATE
 * поэтому есть еще одна Console-зараза, которая пишет monolog-логи (получается 2 лога об одном и том же) -
 * \Symfony\Component\Console\EventListener\ErrorListener, который вроде как важен, потому что делает полезные вещи:
 * - Если исходное исключение реализует HttpExceptionInterface , то getStatusCode() и getHeaders() используются для
 *   заполнения заголовков и кода состояния объекта FlattenException
 * - Если исходное исключение реализует RequestExceptionInterface, то заполняется код состояния объекта 400 и никакие
 *   другие заголовки не изменяются.
 * Детали https://symfony.com/doc/current/components/http_kernel.html#9-handling-exceptions-the-kernel-exception-event
 *
 * Гребаный Nicolas Grekas запил еще \Symfony\Component\Runtime\Internal\BasicErrorHandler, вызываемый в web-версии app:
 *   \Symfony\Component\Runtime\Internal\SymfonyErrorHandler::register - правда только в дебаг-режиме:
 *   \Symfony\Component\Runtime\GenericRuntime::__construct
 *
 * В web-версии Symfony тоже ловит все Throwable исключения в \Symfony\Component\HttpKernel\HttpKernel::handle, а далее
 * dispatch-ит как событие Symfony\Component\HttpKernel\KernelEvents::EXCEPTION и далее пишет monolog-логи в веб-версии:
 * \Symfony\Component\HttpKernel\EventListener\ErrorListener::logException()
*/
class ErrorHandler
{
    private int $thrownErrors = 0x1FFF; // E_ALL - E_DEPRECATED - E_USER_DEPRECATED
    private int $scopedErrors = 0x1FFF; // E_ALL - E_DEPRECATED - E_USER_DEPRECATED
    private int $tracedErrors = 0x77FB; // E_ALL - E_STRICT - E_PARSE
    private int $screamedErrors = 0x55; // E_ERROR + E_CORE_ERROR + E_COMPILE_ERROR + E_PARSE

    public static function register()
    {
        $handler = new static();
        // когда регистрирую тут хендлеры, то при возникновении ошибки \Symfony\Component\HttpKernel\EventListener\DebugHandlersListener::configure
        // пытается передать своего обработчика в setExceptionHandler(), при этом далее ErrorListener-ры не срабатывают (ошибка
        // не пишется в лог, печаль), так что пока что я не буду регистрировать свои хендлеры (пусть работают ErrorListener-ры):
        // set_exception_handler([$handler, 'doNothing']);
        // set_error_handler([$handler, 'doNothing'], E_ALL);
        // register_shutdown_function([$handler, 'doNothing']); // срабатывает каждый раз, даже после отработки set_error_handler-а (теперь понятно, почему мы видим 2 ошибки каждый раз)
        // Если надумаю реализовать, то буду смотреть на https://github.com/yapro/monolog-ext/blob/php5/src/ErrorHandler.php

        return $handler;
    }
    public function doNothing()
    {
        // в случае ошибки обработчик ничего не делает, ведь есть ErrorListener-ры (они как раз пишут логи в json)
    }

    // вызывается в Http и Console:
    public function setDefaultLogger()
    {
        // native php stderr, but see the description of doNothing()
    }
    // вызывается в Http и Console:
    public function scopeAt(): int
    {
        return $this->scopedErrors;
    }
    // вызывается в Http и Console:
    public function throwAt(): int
    {
        return $this->thrownErrors;
    }
    // вызывается только в Http:
    public function screamAt(): int // int $levels, bool $replace = false
    {
        return $this->screamedErrors;
    }

    public function setExceptionHandler(): ?callable
    {
        return null; // я ставил брейкпоинт в этой строке и за месяц работы этот метод ни разу не вызывался
    }
}
