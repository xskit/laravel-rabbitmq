## 安装
```
composer require xskit/laravel-rabbitmq
```
## 使用方法
### Laravel
安装后使用 Laravel 自动发现,包将自动注册自己。

### Lumen
对于Lumen的使用，服务提供者应该手动注册，`bootstrap/app.php` 如下面所示:
```php
$app->register(XsKit\LaravelRabbitMQ\RabbitMQServiceProvider::class);
```

### 配置
修改配置可以根据 `config/queue.php` 配置到 `.env`文件中，或者添加配置到 `config/queue.php` 进行配置修改

### 队列监听，开始消费消息

- 使用方式
```
$ php artisan rabbitmq:work [options] [--] [<connection>] [--routing=<key>] [--no-ack]
```
- 查看help
```
$ php artisan rabbitmq:work --help
```
**选项**  
    --queue[=QUEUE]      工作队列名  
    --routing[=ROUTING]  消息路由  
    --no-ack             关闭消息确认  
    --daemon             以守护进程模式运行工作程序 (弃用)  
    --once               只处理队列上的下一个作业  
    --delay[=DELAY]      延迟失败作业的秒数 [default: "0"]  
    --force              强制在维护模式下运行  
    --memory[=MEMORY]    内存限制，以 M 为单位( [default: "128"]  
    --sleep[=SLEEP]     没有工作时睡觉的秒数 [default: "3"]  
    --timeout[=TIMEOUT]  子进程可以运行的秒数 [default: "60"]  
    --tries[=TRIES]      在记录作业失败之前尝试该作业的次数 [default: "0"]  


## 消息处理的方式
- 消息确认机制的说明，与 Laravel 队列 Job 的处理保持一致
    - Job 执行失败时，自动确认消息后进行消息重发
    - Job 执行成功时，消息自动确认
    - 在 Job 中 主动删除消息，就可以确认进行消息确认
    ```php
     $job->delete();
    ```
    
## 使用示例
- 场景一
    - 后台处理长时任务  
      
        1. 创建 laravel 队列任务，发布到 RabbitMQ （默认配置连接名：rabbitmq）连接。使用说明可查看 [Laravel 官方手册](https://laravel.com/docs/5.8/queues)。
           
        2. 区别在执行队列监听命令：
            ```
            // 连接到 rabbitmq ,监听 default 队列 ，接收当前队列名为路由的消息
            $ php artisan rabbitmq:work
            ```
    
- 场景二
    - 分布式消息处理，例如：
    
       - 在 A 项目上发布作业，名为 OneJob：
       ```bash
       $ php artisan make:job OneJob
       ```
       产生并修改如下：
      ```php
      namespace App\Jobs\OneJob
      
      use Illuminate\Bus\Queueable;
      use Illuminate\Queue\SerializesModels;
      use Illuminate\Queue\InteractsWithQueue;
      use Illuminate\Contracts\Queue\ShouldQueue;
      use Illuminate\Foundation\Bus\Dispatchable;
      use XsKit\LaravelRabbitMQ\Contracts\PublishJobContract;
      use XsKit\LaravelRabbitMQ\Contracts\QueueNotDeclare;
      
      class OneJob extends PublishJobContract implements QueueNotDeclare
      {
          use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
          public $data;
    
          public function __construct($data)
         {
              //定义连接到队列 rabbitmq  发送路由：b.queue
              $this->onConnection('rabbitmq')->onQueue('b.queue');
        
              $this->$data = $data;       
          }
      }
      ```
      
    - 在 B 项目上接收处理作业，创建一样的同名job：
      
        - 第一步 
        ```bash
        $ php artisan make:job OneJob
        ```
        - 第二步 ，产生并修改如下：
        ```php
         namespace App\Jobs\OneJob
         
         use Illuminate\Bus\Queueable;
         use Illuminate\Queue\SerializesModels;
         use Illuminate\Queue\InteractsWithQueue;
         use Illuminate\Contracts\Queue\ShouldQueue;
         use Illuminate\Foundation\Bus\Dispatchable;
         use XsKit\LaravelRabbitMQ\Contracts\ConsumeJobContract;
         
         class OneJob extends ConsumeJobContract
         {
             use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
        
             public $data;
        
             public function handle($data)
            {
                 //在这里处理你的业务           
             }
         }
        ```
        - 第三步，启动处理进程：
        ```bash
        # 处理 队列名为default ,接收 b.queue 路由消息 
        $ php artisan rabbitmq:work --routing=b.queue
        ```
    
       
