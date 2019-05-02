<?php

namespace XsPkg\LaravelRabbitMQ;

use XsPkg\LaravelRabbitMQ\Console\WorkCommand;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use XsPkg\LaravelRabbitMQ\Queue\Connectors\RabbitMQConnector;

class RabbitMQServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->commands([
            WorkCommand::class
        ]);

        $this->mergeConfigFrom(
            __DIR__.'/../config/rabbitmq.php', 'queue.connections.rabbitmq'
        );
    }

    /**
     * Register the application's event listeners.
     *
     * @return void
     */
    public function boot(): void
    {
        /** @var QueueManager $queue */
        $queue = $this->app['queue'];

        $queue->addConnector('rabbitmq', function () {
            return new RabbitMQConnector($this->app['events']);
        });
    }
}
