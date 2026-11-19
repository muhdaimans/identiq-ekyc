<?php

namespace Prooffice\IdentiqEkyc\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Prooffice\IdentiqEkyc\IdentiqServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [IdentiqServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('identiq.base_url', 'https://identiq.test/api');
        $app['config']->set('identiq.token_url', 'https://identiq.test/oauth/token');
        $app['config']->set('identiq.client_id', 'client-1');
        $app['config']->set('identiq.client_secret', 'secret-1');
        $app['config']->set('identiq.scopes', 'onboarding:write results:read');
        $app['config']->set('identiq.webhooks.secrets', 'whsec_new,whsec_old');
    }
}
