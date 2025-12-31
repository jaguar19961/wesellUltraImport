<?php

namespace WeSellUltra\Import\Providers;

use Illuminate\Support\ServiceProvider;
use WeSellUltra\Import\Console\Commands\UltraImportCommand;
use WeSellUltra\Import\Services\UltraCatalogExporter;
use WeSellUltra\Import\Services\UltraClient;

class UltraImportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/ultra_import.php', 'ultra_import');

        $this->app->singleton(UltraClient::class, function ($app) {
            $config = $app['config']->get('ultra_import');
            $options = $config['options'];

            if (!isset($options['stream_context']) && !empty($options['login']) && !empty($options['password'])) {
                $authHeader = 'Authorization: Basic ' . base64_encode($options['login'] . ':' . $options['password']);
                $options['stream_context'] = stream_context_create([
                    'http' => [
                        'header' => $authHeader,
                    ],
                ]);
            }

            $options = array_filter($options, static fn ($value) => $value !== null);

            return new UltraClient($config['wsdl'], $options);
        });

        $this->app->singleton(UltraCatalogExporter::class, function ($app) {
            $config = $app['config']->get('ultra_import');

            return new UltraCatalogExporter(
                $app->make(UltraClient::class),
                $config['output_path'],
                $config['product_url_template'],
                $config['poll']
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/ultra_import.php' => config_path('ultra_import.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                UltraImportCommand::class,
            ]);
        }
    }
}
