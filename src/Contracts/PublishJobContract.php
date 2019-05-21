<?php
/**
 * Created by PhpStorm.
 * User: Yxs <250915790@qq.com>
 * Date: 2019/4/28
 * Time: 10:05
 */

namespace XsKit\LaravelRabbitMQ\Contracts;


use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * 生产信息
 * Class PublishJobContract
 * @package XsKit\LaravelRabbitMQ\Contracts
 */
abstract class PublishJobContract implements ShouldQueue
{
}
