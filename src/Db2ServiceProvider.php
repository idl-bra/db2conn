<?php

namespace IdLogistics\Db2Conn;

use IdLogistics\Db2Conn\Database\Db2Connection;
use IdLogistics\Db2Conn\Database\Db2Connector;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;

class Db2ServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/db2.php', 'db2');

        // Injeta as conexões declaradas em config/db2.php dentro de database.connections
        $config = $this->app['config'];

        foreach ((array) $config->get('db2.connections', []) as $name => $connection) {
            if (! $config->has("database.connections.{$name}")) {
                $config->set("database.connections.{$name}", $connection);
            }
        }

        Connection::resolverFor('db2_odbc', function ($connection, $database, $prefix, $config) {
            return new Db2Connection($connection, $database, $prefix, $config);
        });

        $this->app->bind('db.connector.db2_odbc', fn () => new Db2Connector);
    }

    /**
     * Bootstrap the application events.
     */
    public function boot(): void
    {
        // Publicar configuração
        $this->publishes([
            __DIR__.'/../config/db2.php' => config_path('db2.php'),
        ], 'db2-config');

        // Publicar dashboard de testes
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/packages/db2conn'),
        ], 'db2-dashboard');

        // Publicar rotas de teste
        $this->publishes([
            __DIR__.'/../routes/db2-test.php' => base_path('routes/db2-test.php'),
        ], 'db2-routes');

        // Carregar rotas de teste se existirem
        if (file_exists(base_path('routes/db2-test.php'))) {
            $this->loadRoutesFrom(base_path('routes/db2-test.php'));
        }

        // Carregar views do package
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'db2conn');
    }
}
