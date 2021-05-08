# swoole-sidecar
 
## 简介

swoole-sidecar 是一款基于swoole 实现的sidecar模型，继承了swoole协程的高性能特征,默认采用守护进程的方式,
版本升级为1.1,更新配置全部从配置中心获取


## 功能

- 服务注册、服务发现
- 配置管理
- 代理服务侧的请求

## 运行环境

- [PHP 7.1+](https://github.com/php/php-src/releases)
- [Swoole 4.4+](https://github.com/swoole/swoole-src/releases)
- [Composer](https://getcomposer.org/)
- [apcu](https://github.com/krakjoe/apcu)

## 运行示例
```
cd ./swoole-sidecar && php sidecar.php start -d
```

## 停止运行
```
 php sidecar.php stop
```

## License

swoole-sidecar is an open-source software licensed under the MIT