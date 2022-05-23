<?php

use tourze\workerman\yii2\server\Server;

defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'prod');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = [
    'root' => __DIR__,
    'debug' => false,
    'xhprofLink' => 'http://127.0.0.1/xhprof/xhprof_html/index.php?run={tag}&source=xhprof_test',
    // bootstrap文件, 只会引入一次
    'bootstrapFile' => [
        __DIR__ . '/config/aliases.php',
    ],
    // Yii的配置文件, 只会引入一次
    'configFile' => [
        __DIR__ . '/config/config.php',
    ],
    // 有一些模块比较特殊, 无法实现Refreshable接口, 此时唯有在这里指定他的类名
    'bootstrapRefresh' => [],
    'server' => [
        'host' => '127.0.0.1',
        'port' => 6677,
        // 配置参考 http://doc3.workerman.net/worker-development/property.html
        'count' => 4,
        'name' => 'demo-http'
    ],
    'task' => [
        'host' => '127.0.0.1',
        'port' => 6678,
        'count' => 20,
        'name' => 'demo-task',
    ],
];

Server::runApp($config);
\Workerman\Worker::runAll();
