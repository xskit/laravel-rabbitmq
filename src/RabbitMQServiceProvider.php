<?php

namespace XsKit\LaravelRabbitMQ;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Support\DeferrableProvider;
use XsKit\LaravelRabbitMQ\Console\WorkCommand;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use XsKit\LaravelRabbitMQ\Queue\Connectors\RabbitMQConnector;
use XsKit\LaravelRabbitMQ\Queue\Worker;

class RabbitMQServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {

        $this->app->singleton('rabbitmq.queue.worker', function ($app) {
            return new Worker(
                $app['queue'], $app['events'], $app[ExceptionHandler::class]
            );
        });

        $this->app->singleton('command.rabbitmq.work', function ($app) {
            return new WorkCommand($app['rabbitmq.queue.worker']);
        });

        $this->commands([
            'command.rabbitmq.work'
        ]);

        $this->mergeConfigFrom(
            __DIR__ . '/../config/rabbitmq.php', 'queue.connections.rabbitmq'
        );

        /** @var QueueManager $queue */
        $queue = $this->app['queue'];

        $queue->addConnector('rabbitmq', function () {
            return new RabbitMQConnector($this->app['events']);
        });
    }

    /**
     * Register the application's event listeners.
     *
     * @return void
     */
    public function boot(): void
    {

    }

    public function provides()
    {
        return [
            'rabbitmq.queue.worker'
        ];
    }
}
