<?php
/**
 * Created by PhpStorm.
 * User: Yxs <250915790@qq.com>
 * Date: 2019/4/24
 * Time: 18:39
 */

namespace XsPkg\LaravelRabbitMQ\Queue;

use Exception;
use Throwable;
use Symfony\Component\Debug\Exception\FatalThrowableError;

class Worker extends \Illuminate\Queue\Worker
{

    protected $routingKey;

    public function setRoutingKey($key)
    {
        $this->routingKey = $key;
        return $this;
    }

    /**
     * Get the next job from the queue connection.
     *
     * @param  \Illuminate\Contracts\Queue\Queue $connection
     * @param  string $queue
     * @return \Illuminate\Contracts\Queue\Job|null
     */
    protected function getNextJob($connection, $queue)
    {
        try {
            foreach (explode(',', $queue) as $queue) {
                if (!is_null($job = $connection->setRoutingKey($this->routingKey)->pop($queue))) {
                    return $job;
                }
            }
        } catch (Exception $e) {
            $this->exceptions->report($e);

            $this->stopWorkerIfLostConnection($e);

            $this->sleep(1);
        } catch (Throwable $e) {
            $this->exceptions->report($e = new FatalThrowableError($e));

            $this->stopWorkerIfLostConnection($e);

            $this->sleep(1);
        }
    }
}
