## 安装
```
comooser require xspkg/laravel-rabbitmq
```
## 使用方法

安装后包将自动注册自己使用 Laravel 自动发现。
修改配置可以根据 `config/queue.php` 配置到 `.env`文件中，或者添加配置到 `config/queue.php` 进行配置修改

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
            // 连接到 rabbitmq ,监听 default 队列 ，路由 接收所有消息
            $ php artisan rabbitmq:work rabbitmq --queue=default --routing=#
            ```  
                 
- 场景二
    - 分布式消息处理
    
    
