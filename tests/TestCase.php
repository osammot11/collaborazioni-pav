<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        // A cached local/production config can override phpunit.xml and make RefreshDatabase destructive.
        // Never load the application's normal config/routes caches in a test process.
        foreach (['APP_CONFIG_CACHE' => 'config', 'APP_ROUTES_CACHE' => 'routes'] as $key => $name) {
            $path = sys_get_temp_dir().'/pav-tests-disabled-'.$name.'-'.getmypid().'.php';
            if (file_exists($path)) {
                throw new RuntimeException('Refusing to run tests with a cached application configuration.');
            }
            putenv($key.'='.$path);
            $_ENV[$key] = $_SERVER[$key] = $path;
        }
        $app = parent::createApplication();
        if (! $app->environment('testing') || $app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Test suite stopped: only APP_ENV=testing with SQLite :memory: is permitted.');
        }

        return $app;
    }
}
