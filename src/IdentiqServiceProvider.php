<?php

namespace Prooffice\IdentiqEkyc;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Prooffice\IdentiqEkyc\Auth\TokenProvider;
use Prooffice\IdentiqEkyc\Http\VerifyWebhookSignature;
use Prooffice\IdentiqEkyc\Http\WebhookController;
use Prooffice\IdentiqEkyc\Webhooks\SignatureVerifier;

class IdentiqServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/identiq.php', 'identiq');

        $this->app->singleton(IdentiqConfig::class, fn (Application $app) => IdentiqConfig::fromArray($app['config']->get('identiq', [])));

        $this->app->singleton(TokenProvider::class, fn (Application $app) => new TokenProvider(
            $app->make(Http::class),
            $app['cache']->store($app['config']->get('identiq.token_cache.store')),
            $app['encrypter'],
            $app->make(IdentiqConfig::class),
        ));

        $this->app->singleton(IdentiqClient::class, fn (Application $app) => new IdentiqClient(
            $app->make(Http::class),
            $app->make(TokenProvider::class),
            $app->make(IdentiqConfig::class),
        ));

        $this->app->singleton(SignatureVerifier::class, function (Application $app) {
            $secrets = $app['config']->get('identiq.webhooks.secrets', []);
            $secrets = is_array($secrets) ? $secrets : explode(',', (string) $secrets);

            return new SignatureVerifier(
                array_values(array_filter(array_map('trim', $secrets), fn ($s) => $s !== '')),
                (int) $app['config']->get('identiq.webhooks.tolerance', 300),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/identiq.php' => $this->app->configPath('identiq.php'),
            ], 'identiq-config');
        }

        if ($this->app['config']->get('identiq.webhooks.enabled', true) && ! $this->app->routesAreCached()) {
            Route::post($this->app['config']->get('identiq.webhooks.path', 'identiq/webhook'), WebhookController::class)
                ->middleware([...(array) $this->app['config']->get('identiq.webhooks.middleware', []), VerifyWebhookSignature::class])
                ->name('identiq.webhook');
        }
    }
}
