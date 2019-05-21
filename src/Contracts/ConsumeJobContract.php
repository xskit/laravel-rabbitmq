<?php
/**
 * Created by PhpStorm.
 * User: Yxs <250915790@qq.com>
 * Date: 2019/4/28
 * Time: 10:05
 */

namespace XsKit\LaravelRabbitMQ\Contracts;

/**
 * 消费消息
 * Class ConsumeJobContract
 * @package XsKit\LaravelRabbitMQ\Contracts
 */
abstract class ConsumeJobContract
{
    public abstract function handle();
}
