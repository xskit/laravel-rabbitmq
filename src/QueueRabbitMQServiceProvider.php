<?php

namespace XsPkg\LaravelQueueRabbitMQ;

use XsPkg\LaravelQueueRabbitMQ\Console\WorkCommand;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use XsPkg\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class QueueRabbitMQServiceProvider extends ServiceProvider
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
