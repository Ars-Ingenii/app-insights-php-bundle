<?php

declare(strict_types=1);

namespace AppInsightsPHP\Symfony\AppInsightsPHPBundle\DependencyInjection;

use App\Azure\ApplicationInsights\Handler\AzureAppInsightsTraceHandler;
use AppInsightsPHP\Client\Client;
use AppInsightsPHP\Client\Configuration;
use AppInsightsPHP\Client\Configuration\Dependenies;
use AppInsightsPHP\Client\Configuration\Exceptions;
use AppInsightsPHP\Client\Configuration\Requests;
use AppInsightsPHP\Client\Configuration\Traces;
use AppInsightsPHP\Monolog\Handler\AppInsightsDependencyHandler;
use AppInsightsPHP\Symfony\AppInsightsPHPBundle\Cache\NullCache;
use Psr\Log\NullLogger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class AppInsightsPHPExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('app_insights_php.php');
        $loader->load('app_insights_php_console.php');

        $container->setParameter('app_insights_php.instrumentation_key', $config['instrumentation_key']);
        $container->setParameter('app_insights_php.doctrine.track_dependency', $config['doctrine']['track_dependency']);

        // Make autowiring possible
        $container->setAlias(Client::class, 'app_insights_php.telemetry')->setPublic(true);

        $container->setDefinition(
            'app_insights_php.configuration.exceptions',
            new Definition(Exceptions::class, [
                $config['exceptions']['enabled'],
                (array) $config['exceptions']['ignored_exceptions'],
            ])
        );
        $container->setDefinition(
            'app_insights_php.configuration.dependencies',
            new Definition(Dependenies::class, [
                $config['dependencies']['enabled'],
            ])
        );
        $container->setDefinition(
            'app_insights_php.configuration.requests',
            new Definition(Requests::class, [
                $config['requests']['enabled'],
            ])
        );
        $container->setDefinition(
            'app_insights_php.configuration.traces',
            new Definition(Traces::class, [
                $config['traces']['enabled'],
            ])
        );
        $container->setDefinition(
            'app_insights_php.configuration',
            new Definition(Configuration::class, [
                $config['enabled'],
                $config['gzip_enabled'],
                new Reference('app_insights_php.configuration.exceptions'),
                new Reference('app_insights_php.configuration.dependencies'),
                new Reference('app_insights_php.configuration.requests'),
                new Reference('app_insights_php.configuration.traces'),
            ])
        );

        $container
            ->getDefinition('app_insights_php.telemetry.factory')
            ->replaceArgument(1, new Reference('app_insights_php.configuration'));

        if ((bool) $config['failure_cache_service_id']) {
            $container->getDefinition('app_insights_php.telemetry.factory')
                ->replaceArgument(2, new Reference($config['failure_cache_service_id']));
        } else {
            $container->setDefinition('app_insights_php.failure_cache.null', new Definition(NullCache::class));
            $container->getDefinition('app_insights_php.telemetry.factory')
                ->replaceArgument(2, new Reference('app_insights_php.failure_cache.null'));
        }

        if ((bool) $config['fallback_logger']) {
            $container->getDefinition('app_insights_php.telemetry.factory')
                ->replaceArgument(3, new Reference($config['fallback_logger']['service_id']));

            if (isset($config['fallback_logger']['monolog_channel'])) {
                $container->getDefinition('app_insights_php.telemetry.factory')
                    ->addTag('monolog.logger', ['channel' => $config['fallback_logger']['monolog_channel']]);
            }
        } else {
            $container->setDefinition('app_insights_php.logger.null', new Definition(NullLogger::class));
            $container->getDefinition('app_insights_php.telemetry.factory')
                ->replaceArgument(3, new Reference('app_insights_php.logger.null'));
        }

        // Symfony
        if ($config['enabled']) {
            $loader->load('app_insights_php_symfony.php');
        }

        // Twig
        if (\class_exists('Twig_Environment') || \class_exists('Twig\\Environment')) {
            $loader->load('app_insights_php_twig.php');
        }

        // Doctrine
        if ($config['doctrine']['track_dependency']) {
            if (!\class_exists('AppInsightsPHP\\Doctrine\\DBAL\\Logging\\DependencyLogger')) {
                throw new \RuntimeException('Please first run `composer require download app-insights-php/doctrine-dependency-logger` if you want to log DBAL queries.');
            }

            $loader->load('app_insights_php_doctrine.php');
        }

        // Monolog
        if (\count($config['monolog']['handlers'])) {
            foreach ($config['monolog']['handlers'] as $name => $handlerConfig) {
                $id = \sprintf(\sprintf('app_insights_php.monolog.handler.%s', $name));
                // ARS-1129 Changed chandler
                switch ($handlerConfig['type']) {
                    case 'trace':
                        $class = AzureAppInsightsTraceHandler::class;
                        $level = $handlerConfig['level'];
                        $arguments = [
                            new Reference('app_insights_php.telemetry'),
                            \is_int($level) ? $level : \constant('Monolog\Logger::' . \strtoupper($level)),
                            (bool) $handlerConfig['bubble'],
                        ];

                        break;
                    case 'dependency':
                        $class = AppInsightsDependencyHandler::class;
                        $arguments = [
                            new Reference('app_insights_php.telemetry'),
                        ];

                        break;

                    default:
                        throw new \RuntimeException('Unrecognized monolog handler type %s', $handlerConfig['type']);
                }

                $container->register($id, $class)
                    ->setArguments($arguments)
                    ->setPublic(false);
            }
        }
    }
}
