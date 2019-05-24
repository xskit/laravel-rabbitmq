<?php

namespace XsKit\LaravelRabbitMQ\Console;

use XsKit\LaravelRabbitMQ\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

class WorkCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:work
                            {connection? : 队列连接的名称}
                            {--queue= : 工作队列名}
                            {--routing= : 消息路由}
                            {--no-ack : 关闭消息确认}
                            {--daemon : 以守护进程模式运行工作程序 (弃用)}
                            {--once : Only process the next job on the queue}
                            {--delay=0 : The number of seconds to delay failed jobs}
                            {--force : Force the worker to run even in maintenance mode}
                            {--memory=128 : The memory limit in megabytes}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--timeout=60 : 子进程可以运行的秒数}
                            {--tries=0 : Number of times to attempt a job before logging it failed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '以守护进程的身份开始处理RabbitMQ队列上的作业';

    /**
     * The queue worker instance.
     *
     * @var \Illuminate\Queue\Worker
     */
    protected $worker;

    /**
     * Create a new queue work command.
     *
     * @param  Worker $worker
     * @return void
     */
    public function __construct(Worker $worker)
    {
        parent::__construct();

        $this->worker = $worker;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->downForMaintenance() && $this->option('once')) {
            return $this->worker->sleep($this->option('sleep'));
        }

        // We'll listen to the processed and failed events so we can write information
        // to the console as jobs are processed, which will let the developer watch
        // which jobs are coming through a queue and be informed on its progress.
        $this->listenForEvents();

        $connection = $this->argument('connection')
            ?: 'rabbitmq';

        // We need to get the right queue for the connection which is set in the queue
        // configuration file for the application. We will pull it based on the set
        // connection being run for the queue operation currently being executed.
        $queue = $this->getQueue($connection);

        $options = [
            'routing_key' => $this->getRoutingKey() ?: $queue,
            'no_ack' => $this->option('no-ack')
        ];

        $this->runWorker(
            $connection, $queue, $options
        );
    }

    /**
     * Run the worker instance.
     *
     * @param  string $connection
     * @param  string $queue
     * @param $options
     * @return array
     */
    protected function runWorker($connection, $queue, $options)
    {
        $this->worker->setCache($this->laravel['cache']->driver());

        return $this->worker->setOptions($options)->{$this->option('once') ? 'runNextJob' : 'daemon'}(
            $connection, $queue, $this->gatherWorkerOptions()
        );
    }

    /**
     * Gather all of the queue worker options as a single object.
     *
     * @return WorkerOptions
     */
    protected function gatherWorkerOptions()
    {
        return new WorkerOptions(
            $this->option('delay'), $this->option('memory'),
            $this->option('timeout'), $this->option('sleep'),
            $this->option('tries'), $this->option('force')
        );
    }

    /**
     * Listen for the queue events in order to update the console output.
     *
     * @return void
     */
    protected function listenForEvents()
    {
        $this->laravel['events']->listen(JobProcessing::class, function ($event) {
            $this->writeOutput($event->job, 'starting');
        });

        $this->laravel['events']->listen(JobProcessed::class, function ($event) {
            $this->writeOutput($event->job, 'success');
        });

        $this->laravel['events']->listen(JobFailed::class, function ($event) {
            $this->writeOutput($event->job, 'failed');

            $this->logFailedJob($event);
        });
    }

    /**
     * Write the status output for the queue worker.
     *
     * @param  \Illuminate\Contracts\Queue\Job $job
     * @param  string $status
     * @return void
     */
    protected function writeOutput(Job $job, $status)
    {
        switch ($status) {
            case 'starting':
                $this->writeStatus($job, 'Processing', 'comment');
            case 'success':
                $this->writeStatus($job, 'Processed', 'info');
            case 'failed':
                $this->writeStatus($job, 'Failed', 'error');
        }
    }

    /**
     * Format the status output for the queue worker.
     *
     * @param  \Illuminate\Contracts\Queue\Job $job
     * @param  string $status
     * @param  string $type
     * @return void
     */
    protected function writeStatus(Job $job, $status, $type)
    {
        $this->output->writeln(sprintf(
            "<{$type}>[%s][%s] %s</{$type}> %s",
            Carbon::now()->format('Y-m-d H:i:s'),
            $job->getJobId(),
            str_pad("{$status}:", 11), $job->resolveName()
        ));
    }

    /**
     * Store a failed job event.
     *
     * @param  \Illuminate\Queue\Events\JobFailed $event
     * @return void
     */
    protected function logFailedJob(JobFailed $event)
    {
        $this->laravel['queue.failer']->log(
            $event->connectionName, $event->job->getQueue(),
            $event->job->getRawBody(), $event->exception
        );
    }

    /**
     * 获取工作的队列名
     *
     * @param  string $connection
     * @return string
     */
    protected function getQueue($connection)
    {
        return $this->option('queue') ?: $this->laravel['config']->get(
            "queue.connections.{$connection}.queue", 'default'
        );
    }

    /**
     * 获取消息路由名
     *
     * @return string
     */
    protected function getRoutingKey()
    {
        return $this->option('routing');
    }

    /**
     * 是否可以在维护模式下运行
     *
     * @return bool
     */
    protected function downForMaintenance()
    {
        return $this->option('force') ? false : $this->laravel->isDownForMaintenance();
    }
}
