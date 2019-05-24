<?php
/**
 * Created by PhpStorm.
 * User: Yxs <250915790@qq.com>
 * Date: 2019/4/24
 * Time: 18:39
 */

namespace XsKit\LaravelRabbitMQ\Queue;

use Exception;
use Throwable;
use Symfony\Component\Debug\Exception\FatalThrowableError;

class Worker extends \Illuminate\Queue\Worker
{

    protected $options;

    /**
     * set topic exchange routing key
     * @param array $value
     * @return $this
     */
    public function setOptions($value)
    {
        $this->options = $value;
        return $this;
    }

    /**
     * Get the next job from the queue connection.
     *
     * @param  RabbitMQQueue $connection
     * @param  string $queue
     * @return \Illuminate\Contracts\Queue\Job|null
     */
    protected function getNextJob($connection, $queue)
    {
        try {
            foreach (explode(',', $queue) as $queue) {
                if (!is_null($job = $connection->setOptions($this->options)->pop($queue))) {
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
