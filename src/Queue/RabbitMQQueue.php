<?php

namespace XsKit\LaravelRabbitMQ\Queue;

use Illuminate\Support\Arr;
use Interop\Amqp\AmqpConsumer;
use XsKit\LaravelRabbitMQ\Contracts\QueueAutoDeclare;
use XsKit\LaravelRabbitMQ\Contracts\QueueNotDeclare;
use RuntimeException;
use Illuminate\Queue\Queue;
use Illuminate\Support\Str;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Psr\Log\LoggerInterface;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\Impl\AmqpBind;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use XsKit\LaravelRabbitMQ\Queue\Jobs\RabbitMQJob;

class RabbitMQQueue extends Queue implements QueueContract
{
    protected $sleepOnError;

    protected $queueName;
    protected $queueOptions;
    protected $exchangeOptions;

    protected $declaredExchanges = [];
    protected $declaredQueues = [];

    protected $job;

    protected $optionRoutingKey;

    protected $optionNoAck;


    /**
     * @var AmqpContext
     */
    protected $context;
    protected $correlationId;

    public function __construct(AmqpContext $context, array $config)
    {
        $this->context = $context;
        $this->queueName = $config['queue'] ?? $config['options']['queue']['name'];
        $this->queueOptions = $config['options']['queue'];
        $this->queueOptions['arguments'] = isset($this->queueOptions['arguments']) ?
            json_decode($this->queueOptions['arguments'], true) : [];

        $this->exchangeOptions = $config['options']['exchange'];
        $this->exchangeOptions['arguments'] = isset($this->exchangeOptions['arguments']) ?
            json_decode($this->exchangeOptions['arguments'], true) : [];

        $this->sleepOnError = $config['sleep_on_error'] ?? 5;
    }

    protected function createPayload($job, $queue, $data = '')
    {

        $this->queueOptionsHandle($job);

        return parent::createPayload($job, $queue, $data);

    }

    private function queueOptionsHandle($job)
    {
        if ($job instanceof QueueNotDeclare) {
            $this->queueOptions['declare'] = false;
            $this->queueOptions['bind'] = false;
        } elseif ($job instanceof QueueAutoDeclare) {
            $this->queueOptions['declare'] = true;
            $this->queueOptions['bind'] = true;
        }
    }

    /**
     * 声明队列
     * @param null $queueName 队列名
     * @return int
     */
    public function size($queueName = null): int
    {
        /** @var AmqpQueue $queue */
        list($queue) = $this->declareEverything($queueName);

        return $this->context->declareQueue($queue);
    }

    /** {@inheritdoc} */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $queue, $data), $queue, []);
    }

    /** {@inheritdoc} */
    public function pushRaw($payload, $queueName = null, array $options = [])
    {
        try {
            /**
             * @var AmqpTopic
             * @var AmqpQueue $queue
             */
            list($queue, $topic) = $this->declareEverything($queueName);

            $message = $this->context->createMessage($payload);
            $message->setRoutingKey($queue->getQueueName());
            $message->setCorrelationId($this->getCorrelationId());
            $message->setContentType('application/json');
            $message->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);

            if (isset($options['headers'])) {
                $message->setHeaders($options['headers']);
            }

            if (isset($options['properties'])) {
                $message->setProperties($options['properties']);
            }

            if (isset($options['attempts'])) {
                $message->setProperty(RabbitMQJob::ATTEMPT_COUNT_HEADERS_KEY, $options['attempts']);
            }

            $producer = $this->context->createProducer();
            if (isset($options['delay']) && $options['delay'] > 0) {
                $producer->setDeliveryDelay($options['delay'] * 1000);
            }

            $producer->send($topic, $message);

            return $message->getCorrelationId();
        } catch (\Exception $exception) {
            $this->reportConnectionError('pushRaw', $exception);

            return;
        }
    }

    /** {@inheritdoc} */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue, ['delay' => $this->secondsUntil($delay)]);
    }

    /**
     * 将保留的任务释放回队列
     *
     * @param  \DateTimeInterface|\DateInterval|int $delay
     * @param  string|object $job
     * @param  mixed $data
     * @param  string $queue
     * @param  int $attempts
     * @return mixed
     */
    public function release($delay, $job, $data, $queue, $attempts = 0)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue, [
            'delay' => $this->secondsUntil($delay),
            'attempts' => $attempts,
        ]);
    }

    /**
     * 设置路由
     * @param array $value
     * @return $this
     */
    public function setOptions($value)
    {
        $this->optionRoutingKey = Arr::get($value, 'routing_key');
        $this->optionNoAck = Arr::get($value, 'no_ack');
        return $this;
    }

    /** {@inheritdoc} */
    public function pop($queueName = null)
    {
        try {
            /** @var AmqpQueue $queue */
            list($queue) = $this->declareEverything($queueName);

            $consumer = $this->context->createConsumer($queue);

            if ($this->optionNoAck) {
                // no_ack
                $consumer->addFlag(AmqpConsumer::FLAG_NOACK);
            }

            if ($message = $consumer->receiveNoWait()) {
                return new RabbitMQJob($this->container, $this, $consumer, $message);
            }
        } catch (\Throwable $exception) {
            $this->reportConnectionError('pop', $exception);

            return;
        }
    }

    /**
     * 获取关联唯一id
     *
     * @return string
     */
    public function getCorrelationId(): string
    {
        return $this->correlationId ?: uniqid('', true);
    }

    /**
     * 设置要发布的消息的关联id
     *
     * @param string $id
     *
     * @return void
     */
    public function setCorrelationId(string $id): void
    {
        $this->correlationId = $id;
    }

    /**
     * @return AmqpContext
     */
    public function getContext(): AmqpContext
    {
        return $this->context;
    }

    /**
     * @param string $queueName
     *
     * @return array [Interop\Amqp\AmqpQueue, Interop\Amqp\AmqpTopic]
     */
    protected function declareEverything(string $queueName = null): array
    {
        $queueName = $this->getQueueName($queueName);
        //未设置交换机名，默认队列名
        $exchangeName = $this->exchangeOptions['name'] ?: $queueName;

        $topic = $this->context->createTopic($exchangeName);
        $topic->setType($this->exchangeOptions['type']);
        $topic->setArguments($this->exchangeOptions['arguments']);
        if ($this->exchangeOptions['passive']) {
            $topic->addFlag(AmqpTopic::FLAG_PASSIVE);
        }
        if ($this->exchangeOptions['durable']) {
            $topic->addFlag(AmqpTopic::FLAG_DURABLE);
        }
        if ($this->exchangeOptions['auto_delete']) {
            $topic->addFlag(AmqpTopic::FLAG_AUTODELETE);
        }

        if ($this->exchangeOptions['declare'] && !in_array($exchangeName, $this->declaredExchanges, true)) {
            //声明交换机
            $this->context->declareTopic($topic);

            $this->declaredExchanges[] = $exchangeName;
        }

        $queue = $this->context->createQueue($queueName);
        $queue->setArguments($this->queueOptions['arguments']);
        if ($this->queueOptions['passive']) {
            $queue->addFlag(AmqpQueue::FLAG_PASSIVE);
        }
        if ($this->queueOptions['durable']) {
            $queue->addFlag(AmqpQueue::FLAG_DURABLE);
        }
        if ($this->queueOptions['exclusive']) {
            $queue->addFlag(AmqpQueue::FLAG_EXCLUSIVE);
        }
        if ($this->queueOptions['auto_delete']) {
            $queue->addFlag(AmqpQueue::FLAG_AUTODELETE);
        }

        if ($this->queueOptions['declare'] && !in_array($queueName, $this->declaredQueues, true)) {
            $this->context->declareQueue($queue);

            $this->declaredQueues[] = $queueName;
        }

        if ($this->queueOptions['bind']) {
            $this->context->bind(new AmqpBind($queue, $topic, $this->optionRoutingKey ?: $queue->getQueueName()));
        }

        return [$queue, $topic];
    }

    protected function getQueueName($queueName = null)
    {
        return $queueName ?: $this->queueName;
    }

    protected function createPayloadArray($job, $queue, $data = '')
    {
        return array_merge(parent::createPayloadArray($job, $queue, $data), [
            'id' => $this->getRandomId(),
        ]);
    }

    /**
     * Get a random ID string.
     *
     * @return string
     */
    protected function getRandomId(): string
    {
        return Str::random(32);
    }

    /**
     * @param string $action
     * @param \Throwable $e
     * @throws \Exception
     */
    protected function reportConnectionError($action, \Throwable $e)
    {
        /** @var LoggerInterface $logger */
        $logger = $this->container['log'];

        $logger->error('AMQP error while attempting ' . $action . ': ' . $e->getMessage());

        // If it's set to false, throw an error rather than waiting
        if ($this->sleepOnError === false) {
            throw new RuntimeException('Error writing data to the connection with RabbitMQ', null, $e);
        }

        // Sleep so that we don't flood the log file
        sleep($this->sleepOnError);
    }
}
