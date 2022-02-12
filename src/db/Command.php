<?php

namespace tourze\workerman\yii2\db;

use Yii;

class Command extends \yii\db\Command
{

    /**
     * @var array 原始参数
     */
    private $_originalPendingParams=[];

    /**
     * @var int 重连次数
     */
    public $reconnectTimes = 3;

    /**
     * @var int 当前重连次数
     */
    public $reconnectCount = 0;

    /**
     * 检查指定的异常是否为可以重连的错误类型
     *
     * @param \Exception $exception
     * @return bool
     */
    public function isConnectionError($exception)
    {
        if ($this->pdoStatement!=null || $exception instanceof yii\db\Exception)
        {
            $errorInfo = $this->pdoStatement ? $this->pdoStatement->errorInfo():$exception->errorInfo;
            //var_dump($errorInfo);
            if ($errorInfo[1] == 70100 || $errorInfo[1] == 2006)
            {
                return true;
            }
        }
        $message = $exception->getMessage();
        if (strpos($message, 'Error while sending QUERY packet. PID=') !== false)
        {
            return true;
        }
        return false;
    }

    /**
     * 上一层对PDO的异常返回封装了一次
     *
     * @inheritdoc
     */
    public function execute()
    {
        $rawSql = $this->getRawSql();
        //尝试还原查询参数，当mysql重连时
        $this->tryToRestorePendingParams();
        try
        {
            $result= parent::execute();
        }
        catch (\Exception $e)
        {
            $result=$this->tryReconnect(function (){
                return $this->execute();
            },$e,$rawSql);
        }catch (\Throwable $e){
            $result=$this->tryReconnect(function (){
                return $this->execute();
            },$e,$rawSql);
        }

        $this->finish();
        return $result;
    }

    /**
     * 上一层对PDO的异常返回封装了一次,
     *
     * @inheritdoc
     */
    public function queryInternal($method, $fetchMode = null)
    {
        //尝试还原查询参数，当mysql重连时
        $this->tryToRestorePendingParams();
        $rawSql=$this->getRawSql();
        try
        {
            $result =  parent::queryInternal($method,$fetchMode);
        }
        catch (\Exception $e)
        {
            $result=$this->tryReconnect(function ()use($method,$fetchMode){
                return $this->queryInternal($method,$fetchMode);
            },$e,$rawSql);
        }catch (\Throwable $e){
            $result=$this->tryReconnect(function ()use($method,$fetchMode){
                return $this->queryInternal($method,$fetchMode);
            },$e,$rawSql);
        }

        $this->finish();
        return $result;
    }

    /**
     * 尝试还原原始查询参数
     */
    private function tryToRestorePendingParams(){
        if($this->reconnectCount==0){
            $this->_originalPendingParams=$this->pendingParams;
        }else{
            $this->pendingParams=$this->_originalPendingParams;
        }
    }

    /**
     * 尝试重连操作
     * @param callable $fn
     * @param $e
     * @param $rawSql
     * @return mixed
     * @throws \yii\base\NotSupportedException
     * @throws \yii\db\Exception
     */
    private function tryReconnect(callable $fn,$e,$rawSql){
        if ($this->reconnectCount >= $this->reconnectTimes)
        {
            throw $this->db->getSchema()->convertException($e, $rawSql);
        }
        $isConnectionError = $this->isConnectionError($e);
        //var_dump($isConnectionError);
        if ($isConnectionError)
        {
            $this->reconnectDb();
            $result= $fn();
        } else {
            throw $this->db->getSchema()->convertException($e, $rawSql);
        }
        return $result;
    }

    /**
     * 重连数据库
     */
    private function reconnectDb(){
        $this->cancel();
        $this->db->close();
        $this->db->open();
        $this->pdoStatement = null;
        $this->reconnectCount++;
    }

    /**
     * 完成操作
     */
    private function finish(){
        $this->reconnectCount = 0;
        $this->_originalPendingParams=[];
    }
}
