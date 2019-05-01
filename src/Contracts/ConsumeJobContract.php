<?php
/**
 * Created by PhpStorm.
 * User: Yxs <250915790@qq.com>
 * Date: 2019/4/28
 * Time: 10:05
 */

namespace XsPkg\LaravelRabbitMQ\Contracts;


abstract class ConsumeJobContract
{
    public abstract function handle();
}
