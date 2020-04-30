<?php

declare(strict_types=1);

namespace YaPro\MonologExt;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class LoggerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // подменяем symfony-логгер, чтобы исключить логирование 403 исключение: AccessDeniedHttpException
        $container->getDefinition('monolog.logger_prototype')->setClass(LoggerDecorator::class);
    }
}
