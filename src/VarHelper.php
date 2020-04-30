<?php

declare(strict_types=1);

namespace YaPro\MonologExt;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Throwable;

class VarHelper
{
    /**
     * @const - глубинна вложенности при экспорте "Значений" по умолчанию
     */
    public const DEFAULT_DEPTH_LEVEL = 2;

    /**
     * Возвращает текстовое представление переменной $value
     *
     * @see https://symfony.com/doc/current/components/var_dumper/advanced.html#dumpers
     *
     * @param $value
     * @param int $depthLevel
     *
     * @return string
     *
     * @throws \ErrorException
     */
    public function dump($value, int $depthLevel = self::DEFAULT_DEPTH_LEVEL): string
    {
        $varCloner = new VarCloner();
        $varCloner->setMinDepth($depthLevel);
        $varDumper = new CliDumper();

        return $varDumper->dump($varCloner->cloneVar($value), true);
    }

    /**
     * Экспорт исключения в запись лога
     *
     * @param Throwable $exception
     *
     * @return array[]
     *
     * @throws \ErrorException
     */
    public function dumpException(Throwable $exception): array
    {
        // Ориентируемся на структуру:
        // "context": { - место в проекте, которое вызвало запись в лог
        //      "code": 123,
        //      "message": "Call to a member function verify_phone() on null",
        //      "file": "/var/www/builds/src/Customer/V1/VerifyResource.php",
        //      "line": 28,
        //      "trace": "any string"
        //  }
        $context = [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'class' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
        ];
        if ($exception->getFile()) {
            $context['file'] = $exception->getFile();
        }
        if ($exception->getLine()) {
            $context['line'] = $exception->getLine();
        }

        return $context;
    }
}
