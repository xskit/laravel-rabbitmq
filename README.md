## 安装
```
comooser require xspkg/laravel-rabbitmq
```
## 使用方法

安装后包将自动注册自己使用Laravel自动发现。
添加配置到 `config/queue.php` 设置队列rabbitmq的连接方式

开始队列监听  
比原laravel队列 多一个配置项 `--routing` 做为rabbitmq 的 routing_key配置
使用方式
```
$ rabbitmq:work [options] [--] [<connection>] [--routing=<key>]
```
