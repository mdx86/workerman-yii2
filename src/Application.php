<?php

namespace tourze\workerman\yii2;

use tourze\workerman\yii2\web\ErrorHandler;
use tourze\workerman\yii2\web\Request;
use tourze\workerman\yii2\web\Response;
use tourze\workerman\yii2\web\Session;
use tourze\workerman\yii2\web\User;
use tourze\workerman\yii2\web\View;
use Workerman\Connection\ConnectionInterface;
use Workerman\Worker;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Controller;
use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\db\Connection;
use yii\helpers\ArrayHelper;

/**
 * Yii2 Application 类
 *
 * @property string rootPath
 */
class Application extends \yii\web\Application {

    /**
     * @var array 全局配置信息
     */
    public static $_globalConfig = [];

    /**
     * 设置全局配置信息
     *
     * @param array $config
     */
    public static function setGlobalConfig($config) {
        static::$_globalConfig = $config;
    }

    /**
     * 获取全局配置信息
     *
     * @return array
     */
    public static function getGlobalConfig() {
        return static::$_globalConfig;
    }

    /**
     * @var static 当前进行中的$app实例, 存放的是一个通用的, 可以供复制的app实例
     */
    public static $workerApp = null;

    /**
     * @var Worker 当前运行中的服务器实例
     */
    protected $_server;

    /**
     * @return Worker
     */
    public function getServer() {
        return $this->_server;
    }

    /**
     * @param Worker $server
     */
    public function setServer($server) {
        $this->_server = $server;
    }

    /**
     * @var ConnectionInterface 当前连接
     */
    protected $_connection;

    /**
     * @return ConnectionInterface
     */
    public function getConnection() {
        return $this->_connection;
    }

    /**
     * @param ConnectionInterface $connection
     */
    public function setConnection($connection) {
        $this->_connection = $connection;
    }

    /**
     * @var string
     */
    protected $_rootPath;

    /**
     * @return string
     */
    public function getRootPath() {
        return $this->_rootPath;
    }

    /**
     * @param string $rootPath
     */
    public function setRootPath($rootPath) {
        $this->_rootPath = $rootPath;
    }

    /**
     * @var array
     */
    public $bootstrapRefresh = [];

    /**
     * @var array 扩展缓存
     */
    public static $defaultExtensionCache = null;

    /**
     * 获取默认的扩展
     *
     * @return array|mixed
     */
    public function getDefaultExtensions() {
        if (static::$defaultExtensionCache === null) {
            $file = Yii::getAlias('@vendor/yiisoft/extensions.php');
            static::$defaultExtensionCache = is_file($file) ? include($file) : [];
        }
        return static::$defaultExtensionCache;
    }

    /**
     * @var bool
     */
    public static $webAliasInit = false;

    /**
     * 初始化流程
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function bootstrap() {
        if (!static::$webAliasInit) {
            /*
            $request = $this->getRequest();
            Yii::setAlias('@webroot', dirname($request->getScriptFile()));
            Yii::setAlias('@web', $request->getBaseUrl());
            */
            static::$webAliasInit = true;
        }

        $this->extensionBootstrap();
        $this->moduleBootstrap();
    }

    /**
     * 自动加载扩展的初始化
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function extensionBootstrap() {
        if (!$this->extensions) {
            $this->extensions = $this->getDefaultExtensions();
        }
        foreach ($this->extensions as $k => $extension) {
            if (!empty($extension['alias'])) {
                foreach ($extension['alias'] as $name => $path) {
                    Yii::setAlias($name, $path);
                }
            }
            if (isset($extension['bootstrap'])) {
                $this->bootstrap[] = $extension['bootstrap'];
                Yii::trace('Push extension bootstrap to module bootstrap list', __METHOD__);
            }
        }
    }

    /**
     * 自动加载模块的初始化
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function moduleBootstrap() {
        foreach ($this->bootstrap as $k => $class) {
            $component = null;
            if (is_string($class)) {
                if ($this->has($class)) {
                    $component = $this->get($class);
                } elseif ($this->hasModule($class)) {
                    $component = $this->getModule($class);
                } elseif (strpos($class, '\\') === false) {
                    throw new InvalidConfigException("Unknown bootstrapping component ID: $class");
                }
            }
            if (!isset($component)) {
                $component = Yii::createObject($class);
            }

            if ($component instanceof BootstrapInterface) {
                Yii::trace('Bootstrap with ' . get_class($component) . '::bootstrap()', __METHOD__);
                $this->bootstrap[$k] = $component;
                $component->bootstrap($this);
                $this->bootstrap[$k] = $component;
            } else {
                Yii::trace('Bootstrap with ' . get_class($component), __METHOD__);
            }
        }
    }

    /**
     * @param $errorHandler
     * @throws \yii\base\InvalidConfigException
     */
    public function setErrorHandler($errorHandler) {
        $this->set('errorHandler', $errorHandler);
    }

    /**
     * 返回一个异常处理器
     *
     * @return ErrorHandler
     */
    public function getErrorHandler() {
        return parent::getErrorHandler();
    }

    /**
     * 复制一个request对象
     *
     * @param Request $request
     * @throws \yii\base\InvalidConfigException
     */
    public function setRequest($request) {
        $this->set('request', $request);
    }

    /**
     * 返回当前request对象
     *
     * @return Request|\yii\web\Request
     */
    public function getRequest() {
        return parent::getRequest();
    }

    /**
     * 复制一个response对象
     *
     * @param Response $response
     * @throws \yii\base\InvalidConfigException
     */
    public function setResponse($response) {
        $this->set('response', $response);
    }

    /**
     * 返回当前response对象
     *
     * @return Response
     */
    public function getResponse() {
        return parent::getResponse();
    }

    /**
     * 复制一个view对象
     *
     * @param View|\yii\web\View $view
     * @throws \yii\base\InvalidConfigException
     */
    public function setView($view) {
        $this->set('view', $view);
    }

    /**
     * 返回当前view对象
     *
     * @return View
     */
    public function getView() {
        return parent::getView();
    }

    /**
     * 创建会话
     *
     * @param Session $session
     * @throws \yii\base\InvalidConfigException
     */
    public function setSession($session) {
        $this->set('session', $session);
    }

    /**
     * 返回当前session对象
     *
     * @return Session
     */
    public function getSession() {
        return parent::getSession();
    }

    /**
     * @return User
     */
    public function getUser() {
        return parent::getUser();
    }

    /**
     * @param $user
     * @throws \yii\base\InvalidConfigException
     */
    public function setUser($user) {
        $this->set('user', $user);
    }

    /**
     * 预热一些可以浅复制的对象
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function prepare() {
        $this->getLog()->setLogger(Yii::getLogger());
        $this->getSecurity();
        $this->getUrlManager();
        $this->getRequest()->setBaseUrl('');
        $this->getRequest()->setScriptUrl('/index.php');
        $this->getRequest()->setScriptFile('/index.php');
        $this->getRequest()->setUrl(null);
        $this->getResponse();
        foreach ($this->getResponse()->formatters as $type => $class) {
            $this->getResponse()->formatters[$type] = Yii::createObject($class);
        }
        $this->getSession();
        $this->getAssetManager();
        $this->getView();
        //$this->getDb();
        $this->prepareDbs();
        $this->getUser();
        $this->getMailer();
    }

    /**
     *  实例化所有数据库对象
     * @throws InvalidConfigException
     */
    protected function prepareDbs() {
        $dbClass = Connection::class;
        foreach ($this->components as $id => $config) {
            $class = ArrayHelper::getValue($config, 'class');
            $class = trim($class, '/\\');
            if ($class != $dbClass) {
                continue;
            }

            $this->get($id);
        }
    }

    /**
     * run之前先准备上下文信息
     */
    public function beforeRun() {
        Event::offAll();
        // widget计数器等要清空
        Widget::$counter = 0;
        Widget::$stack = [];
        $this->getErrorHandler()->setConnection($this->getConnection());
        $this->getRequest()->setConnection($this->getConnection());
        $this->getRequest()->setHostInfo('http://' . $_SERVER['HTTP_HOST']);
        $this->getRequest()->setPathInfo($_SERVER['ORIG_PATH_INFO']);
        $this->getResponse()->setConnection($this->getConnection());
        foreach ($this->bootstrap as $k => $component) {
            if (!is_object($component)) {
                if ($this->has($component)) {
                    $component = $this->get($component);
                } elseif ($this->hasModule($component)) {
                    $component = $this->getModule($component);
                }
            }
            if (in_array(get_class($component), $this->bootstrapRefresh)) {
                /** @var BootstrapInterface $component */
                $component->bootstrap($this);
            } elseif ($component instanceof Refreshable) {
                $component->refresh();
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function run() {
        if (!Application::$workerApp) {
            return parent::run();
        }
        $this->beforeRun();
        return parent::run();
    }

    /**
     * 阻止默认的exit执行
     *
     * @param int $status
     * @param mixed $response
     * @return int|void
     */
    public function end($status = 0, $response = null) {
        if (!Application::$workerApp) {
            return parent::run();
        }
        if ($this->state === self::STATE_BEFORE_REQUEST || $this->state === self::STATE_HANDLING_REQUEST) {
            $this->state = self::STATE_AFTER_REQUEST;
            $this->trigger(self::EVENT_AFTER_REQUEST);
        }

        if ($this->state !== self::STATE_SENDING_RESPONSE && $this->state !== self::STATE_END) {
            $this->state = self::STATE_END;
            $response = $response ?: $this->getResponse();
            $response->send();
        }
        return 0;
    }

    /**
     * 用于收尾
     */
    public function afterRun() {
        Yii::getLogger()->flush();
        $this->getSession()->close();
    }

    /**
     * @var array 保存 id => controller 的实例缓存
     */
    public static $controllerIdCache = [];

    /**
     * 保存控制器实例缓存, 减少一次创建请求的开销
     * 能提升些少性能.
     * 这里要求控制器在实现时, 业务逻辑尽量不要写在构造函数中
     *
     * @inheritdoc
     */
    public function createControllerByID($id) {
        if (!Application::$workerApp) {
            return parent::createControllerByID($id);
        }

        if (!isset(self::$controllerIdCache[$id])) {
            $controller = parent::createControllerByID($id);
            if (!$controller) {
                return $controller;
            }
            // 清空id和module的引用
            $controller->id = null;
            $controller->module = null;

            // FIX 修复自Yii2.0.36 版本 Controller 类添加 request 与 response 缓存属性，导致不同请求使用同一response对象，
            //从而导致第二个请求无法返回数据，响应Http的问题
            //参考: https://github.com/yiisoft/yii2/pull/18083
            $this->tryToResetRequestAndResponseOfController($controller);
            self::$controllerIdCache[$id] = clone $controller;
        }

        /** @var Controller $controller */
        $controller = clone self::$controllerIdCache[$id];
        $controller->id = $id;
        $controller->module = $this;
        $controller->init();
        return $controller;
    }

    /**
     * 尝试重置$controller 对象的 request 与 response 对象，针对Yii2.0.36及以上版本起效
     * @param $controller
     */
    protected function tryToResetRequestAndResponseOfController($controller) {
        $this->resetControllerCacheProperty($controller, 'request');
        $this->resetControllerCacheProperty($controller, 'response');
    }

    protected function resetControllerCacheProperty($controller, $property) {
        if (!property_exists($controller, $property)) {
            return false;
        }
        $controller->$property = $property;
        return true;
    }
}
