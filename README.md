## 安装
```
comooser require xskit/laravel-rabbitmq
```
## 使用方法

安装后使用 Laravel 自动发现，包将自动注册自己。
修改配置可以根据 `config/queue.php` 配置项配置到 `.env`文件中，或者添加配置到 `config/queue.php` 进行配置修改

开始队列监听  
比原laravel队列 多一个配置项 `--routing` 做为rabbitmq 的 routing_key配置
- 使用方式
```
$ php artisan rabbitmq:work [options] [--] [<connection>] [--routing=<key>]
```
- 查看help
```
$ php artisan rabbitmq:work --help
```

## 使用示例
- 场景一
    - 后台处理长时任务  
       
        1. 创建 laravel 队列任务 或 事件处理，发布到 RabbitMQ （默认配置连接名：rabbitmq）连接。使用说明可查看 [Laravel 官方手册](https://laravel.com/docs/5.8)。
                       
        2. 执行队列监听命令：
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
      
       
