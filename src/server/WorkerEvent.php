<?php
/**
 * Created by PhpStorm.
 * User: jmd
 * Date: 2018/7/17
 * Time: 9:35
 */

namespace tourze\workerman\yii2\server;

use Workerman\Connection\ConnectionInterface;
use Workerman\Worker;
use yii\base\Event;

class WorkerEvent extends Event {

    /**
     * @var Worker $worker
     */
    public $worker;
    /**
     * @var ConnectionInterface $connection
     */
    public $connection;
    /**
     * @var Array $data
     */
    public $data;

}