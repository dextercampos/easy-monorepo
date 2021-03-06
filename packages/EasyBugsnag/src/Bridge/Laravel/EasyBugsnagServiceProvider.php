<?php

declare(strict_types=1);

namespace EonX\EasyBugsnag\Bridge\Laravel;

use Bugsnag\Client;
use EonX\EasyBugsnag\Bridge\BridgeConstantsInterface;
use EonX\EasyBugsnag\Bridge\Laravel\Doctrine\SqlOrmLogger;
use EonX\EasyBugsnag\Bridge\Laravel\Request\LaravelRequestResolver;
use EonX\EasyBugsnag\ClientFactory;
use EonX\EasyBugsnag\Configurators\BasicsConfigurator;
use EonX\EasyBugsnag\Configurators\RuntimeVersionConfigurator;
use EonX\EasyBugsnag\Interfaces\ClientFactoryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use LaravelDoctrine\ORM\Loggers\Logger;

final class EasyBugsnagServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/easy-bugsnag.php' => \base_path('config/easy-bugsnag.php'),
        ]);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/easy-bugsnag.php', 'easy-bugsnag');

        $this->registerClient();
        $this->registerConfigurators();
        $this->registerDoctrineOrm();
        $this->registerRequestResolver();
    }

    private function registerClient(): void
    {
        // Client Factory + Client
        $this->app->singleton(
            ClientFactoryInterface::class,
            static function (Container $app): ClientFactoryInterface {
                return (new ClientFactory())
                    ->setConfigurators($app->tagged(BridgeConstantsInterface::TAG_CLIENT_CONFIGURATOR))
                    ->setRequestResolver($app->make(LaravelRequestResolver::class));
            }
        );

        $this->app->singleton(
            Client::class,
            static function (Container $app): Client {
                return $app->make(ClientFactoryInterface::class)->create(\config('easy-bugsnag.api_key'));
            }
        );
    }

    private function registerConfigurators(): void
    {
        $this->app->singleton(
            BasicsConfigurator::class,
            static function (Container $app): BasicsConfigurator {
                /** @var \Illuminate\Contracts\Foundation\Application $app */
                $basePath = $app->basePath();

                return new BasicsConfigurator($basePath . '/app', $basePath, (string)$app->environment());
            }
        );
        $this->app->tag(BasicsConfigurator::class, [BridgeConstantsInterface::TAG_CLIENT_CONFIGURATOR]);

        $this->app->singleton(
            RuntimeVersionConfigurator::class,
            static function (Container $app): RuntimeVersionConfigurator {
                /** @var \Illuminate\Contracts\Foundation\Application $app */
                $version = $app->version();
                $runtime = Str::contains($version, 'Lumen') ? 'lumen' : 'laravel';

                return new RuntimeVersionConfigurator($runtime, $version);
            }
        );
        $this->app->tag(RuntimeVersionConfigurator::class, [BridgeConstantsInterface::TAG_CLIENT_CONFIGURATOR]);
    }

    private function registerDoctrineOrm(): void
    {
        if (\config('easy-bugsnag.doctrine_orm', false) === false
            || \interface_exists(Logger::class) === false) {
            return;
        }

        $this->app->singleton(SqlOrmLogger::class);
    }

    private function registerRequestResolver(): void
    {
        // Request Resolver
        $this->app->singleton(LaravelRequestResolver::class);
    }
}
