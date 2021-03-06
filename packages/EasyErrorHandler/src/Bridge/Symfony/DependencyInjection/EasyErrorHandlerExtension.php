<?php

declare(strict_types=1);

namespace EonX\EasyErrorHandler\Bridge\Symfony\DependencyInjection;

use Bugsnag\Client;
use EonX\EasyErrorHandler\Bridge\BridgeConstantsInterface;
use EonX\EasyErrorHandler\Interfaces\ErrorReporterProviderInterface;
use EonX\EasyErrorHandler\Interfaces\ErrorResponseBuilderProviderInterface;
use EonX\EasyWebhook\Events\FinalFailedWebhookEvent;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class EasyErrorHandlerExtension extends Extension
{
    /**
     * @param mixed[] $configs
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $loader = new PhpFileLoader($container, new FileLocator([__DIR__ . '/../Resources/config']));

        $container->setParameter(BridgeConstantsInterface::PARAM_BUGSNAG_THRESHOLD, $config['bugsnag_threshold']);
        $container->setParameter(
            BridgeConstantsInterface::PARAM_BUGSNAG_IGNORED_EXCEPTIONS,
            \count($config['bugsnag_ignored_exceptions']) > 1 ? $config['bugsnag_ignored_exceptions'] : null
        );

        $container->setParameter(BridgeConstantsInterface::PARAM_IS_VERBOSE, $config['verbose']);
        $container->setParameter(
            BridgeConstantsInterface::PARAM_OVERRIDE_API_PLATFORM_LISTENER,
            $config['override_api_platform_listener']
        );
        $container->setParameter(BridgeConstantsInterface::PARAM_RESPONSE_KEYS, $config['response']);
        $container->setParameter(BridgeConstantsInterface::PARAM_TRANSLATION_DOMAIN, $config['translation_domain']);

        $container
            ->registerForAutoconfiguration(ErrorReporterProviderInterface::class)
            ->addTag(BridgeConstantsInterface::TAG_ERROR_REPORTER_PROVIDER);

        $container
            ->registerForAutoconfiguration(ErrorResponseBuilderProviderInterface::class)
            ->addTag(BridgeConstantsInterface::TAG_ERROR_RESPONSE_BUILDER_PROVIDER);

        $loader->load('services.php');

        if ($config['use_default_builders'] ?? true) {
            $loader->load('default_builders.php');
        }

        if ($config['override_api_platform_listener'] ?? true) {
            $loader->load('api_platform_builders.php');
        }

        if ($config['use_default_reporters'] ?? true) {
            $loader->load('default_reporters.php');
        }

        if (($config['bugsnag_enabled'] ?? true) && \class_exists(Client::class)) {
            $loader->load('bugsnag_reporter.php');
        }

        // EasyWebhook Bridge
        if (\class_exists(FinalFailedWebhookEvent::class)) {
            $loader->load('easy_webhook.php');
        }
    }
}
