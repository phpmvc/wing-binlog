<?php namespace Seals\Library;
use Wing\FileSystem\WFile;

/**
 * @author yuyi
 * @created 2016/9/23 8:27
 * @email 297341015@qq.com
 * @property Notify $notify
 * @property CacheInterface $process_cache
 */

class Worker implements Process
{
    protected static $version     = "1.13";
    protected $app_id             = "wing-binlog";
    protected $debug              = false;
    protected $start_time         = 0;
    protected $workers            = 1;
    protected $notify;
    protected $process_cache;
    protected $deamon             = false;
    protected static $server_pid  = __APP_DIR__."/server.pid";
    protected $processes          = [];
    protected $parse_processes    = [];
    protected $dispatch_processes = [];
    protected $event_process      = 0;
    protected static $is_deamon   = false;


    //队列名称
    const QUEUE_NAME = "seals:events:collector";

    /**
     * @构造函数
     */
    public function __construct($app_id = "wing-binlog")
    {
        gc_enable();

        $this->start_time       = time();
        $this->app_id           = $app_id;

        chdir(dirname(dirname(__DIR__)));

        register_shutdown_function(function()
        {
            $error = error_get_last();
            if ($error) {
                Context::instance()->logger->notice("process is shutdown", [
                    "process_id" => self::getCurrentProcessId(),
                    "errors"     => $error
                ]);
            }
            $this->clear();
        });

        set_error_handler(function($errno, $errstr, $errfile, $errline)
        {
            Context::instance()->logger->error("error happened",[
                "process_id"    => self::getCurrentProcessId(),
                "error_no"      => $errno,
                "error_message" => $errstr,
                "error_file"    => $errfile,
                "error_line"    => $errline
            ]);
        });

        $cpu = new Cpu();
        $this->setWorkersNum($cpu->cpu_num) ;

        unset($cpu);
        ini_set("memory_limit", Context::instance()->memory_limit);

    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        $this->clear();
    }

    public function getVersion()
    {
        return self::$version;
    }

    public static function version()
    {
        return self::$version;
    }

    /**
     * 事件通知方式实现
     *
     * @param Notify $notify
     */
    public function setNotify(Notify $notify)
    {
        $this->notify = $notify;
    }

    /**
     * 启用守护进程模式
     */
    public function enableDeamon()
    {
        $this->deamon = true;
    }

    /**
     * 禁用守护进程模式
     */
    public function disableDeamon()
    {
        $this->deamon = false;
    }

    /**
     * 设置进程标题，仅linux
     *
     * @param string $title 进程标题
     */
    public function setProcessTitle($title)
    {
        if (function_exists("setproctitle"))
            setproctitle($title);
        if (function_exists("cli_set_process_title"))
            cli_set_process_title($title);
    }

    /**
     * 启用debug模式
     */
    public function enabledDebug()
    {
        $this->debug = true;
        return $this;
    }

    /**
     * 禁用debug模式
     */
    public function disabledDebug()
    {
        $this->debug = false;
        return $this;
    }

    /**
     * @设置进程缓存路径
     * @param string $dir 目录路径
     */
    public function setProcessCache(CacheInterface $cache)
    {
        $this->process_cache = $cache;
    }


    /**
     * @退出时清理一些资源
     */
    private function clear()
    {
        $process_id = self::getCurrentProcessId();

        $this->process_cache->del("running_".$process_id);
        $this->process_cache->del("stop_".$process_id);
        $this->process_cache->del("status_".$process_id);
    }

    /**
     * @获取队列名称
     *
     * @return string
     */
    public function getQueueName()
    {
        return self::QUEUE_NAME;
    }

    /**
     * @设置进程运行状态
     *
     * @return self
     */
    public function setIsRunning()
    {
        $process_id = self::getCurrentProcessId();
        $this->process_cache->set("running_".$process_id, 1, 60);
        return $this;
    }

    /**
     * @获取进程运行状态
     *
     * @return bool
     */
    public function getIsRunning()
    {
        $process_id = self::getCurrentProcessId();
        return $this->process_cache->get("running_".$process_id) == 1;
    }

    /**
     * @检查退出信号，如果检测到退出信号，则直接退出
     *
     * @return void
     */
    public function checkStopSignal()
    {
        $process_id = self::getCurrentProcessId();
        $is_stop    = $this->process_cache->get("stop_".$process_id) == 1;

        if ($is_stop) {
            echo $process_id," get stop signal\r\n";
            exit(0);
        }
    }

    public function stop()
    {
        self::stopAll();
    }
    /**
     * @停止进程
     *
     * @return void
     */
    public static function stopAll()
    {

        $server_id = file_get_contents(self::$server_pid);
        posix_kill($server_id, SIGINT);
    }

    /**
     * @获取运行状态
     *
     * @return string
     */
    public function getStatus(){

        $arr   = [];
        $files = $this->process_cache->keys("status.*");

        foreach ($files as $file) {
            list(, $process_id) = explode("_",$file);
            $arr[$process_id]   = $this->process_cache->get($file) ;
        }

        $res = [];
        foreach ($arr as $process_id => $status) {
            $time_len = timelen_format(time() - $status["start_time"]);
            $index    = preg_replace("/\D/","",$status["name"]);

            if (strpos($status["name"], "dispatch") !== false) {
                $queue = new Queue(self::QUEUE_NAME .":ep". $index, Context::instance()->redis_local);
                $wlen  = $queue->length();
                unset($queue);
            } else {
                $queue = new Queue(self::QUEUE_NAME . $index, Context::instance()->redis_local);
                $wlen  = $queue->length();
                unset($queue);
            }

            $res[] = [
                "process_id" => sprintf("%-8d",$status["process_id"]),
                "start_time" => date("Y-m-d H:i:s", $status["start_time"]),
                "run_time"   => $time_len,
                "name"       => $status["name"],
                "work_len"   => $wlen
            ];

            if (!is_numeric($index))
                $index = 0;

            if (strpos($status["name"],"dispatch") !== false) {
                $names[] = $index+1;
            } elseif (strpos($status["name"],"workers") !== false) {
                $names[] = $index+100;
            } else {
                $names[] = 0;
            }
        }

        array_multisort($names, SORT_ASC, $res);

        $str = "进程id    开始时间            待处理任务  运行时长\r\n";
        foreach ($res as $v) {
            $str .= $v["process_id"] .
                "  " . $v["start_time"] .
                "  " . sprintf("%-6d", $v["work_len"]) .
                "       " . $v["run_time"] .
                "  " . $v["name"] . "\r\n";
        }
        return $str;
    }

    /**
     * 设置运行状态
     *
     * @return void
     */
    public function setStatus($name)
    {
        $process_id = self::getCurrentProcessId();
        $this->process_cache->set("status_".$process_id, [
            "process_id" => $process_id,
            "start_time" => $this->start_time,
            "name"       => $name,
            "updated"    => time()
        ],60);
    }

    /**
     * @重置标准错误和标准输出，linux支持
     *
     * @param string $output_name  可选参数，主要为了区分不同进程的输出
     * @throws \Exception
     */
    protected function resetStd()
    {
        if (strtolower(substr(php_uname('s'),0,3)) == "win") {
            return;
        }

        global $STDOUT, $STDERR;

        $file   = new WFile(Context::instance()->log_dir . "/seals_output_".date("Ymd")."_".self::getCurrentProcessId().".log");
        $file->touch();

        $handle = fopen($file->get(), "a+");
        if ($handle) {
            unset($handle);
            @fclose(STDOUT);
            @fclose(STDERR);
            $STDOUT = fopen($file->get(), "a+");
            $STDERR = fopen($file->get(), "a+");
        } else {
            throw new \Exception('can not open stdout file ' . $file->get());
        }
    }

    /**
     * @服务状态
     */
    public function status()
    {
        return $this->getStatus();
    }

    /**
     * @获取进程数量
     */
    public function getWorkersNum()
    {
        return $this->workers;
    }

    /**
     * @设置进程数量
     * @param int $workers
     */
    public function setWorkersNum($workers)
    {
        $this->workers = $workers;
    }

    /**
     * @是否还在运行
     */
    public function isRunning()
    {
        return $this->getIsRunning();
    }

    /**
     * @获取当前进程id 仅linux
     *
     * @return int
     */
    public static function  getCurrentProcessId()
    {
        if (function_exists("getmypid"))
            return getmypid();

        if (function_exists("posix_getpid"))
            return posix_getpid();
        return 0;
    }

    /**
     * @守护进程化，需要安装pcntl扩展，linux支持
     */
    public static function daemonize()
    {
        if (self::$is_deamon)
            return;

        if (!function_exists("pcntl_fork"))
            return;

        self::$is_deamon = true;

        //修改掩码
        umask(0);

        //创建进程
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new \Exception('fork fail');
        } elseif ($pid > 0) {
            //父进程直接退出
            exit(0);
        }

        //创建进程会话
        if (-1 === posix_setsid()) {
            throw new \Exception("setsid fail");
        }


    }

    /**
     * 简单的进程调度实现，获取该分配的进程队列名称
     *
     * @param string $base_queue_name 基础队列名称
     * @return Queue
     */
    public function getWorker($base_queue_name)
    {
        $target_worker = new Queue($base_queue_name."1", Context::instance()->redis_local);

        if ($this->workers <= 1) {
            return $target_worker;
        }

        //如果没有空闲的进程 然后判断待处理的队列长度 那个待处理的任务少 就派发给那个进程
        $target_len    = $target_worker->length();

        for ($i = 2; $i <= $this->workers; $i++) {

            $_target_worker = new Queue($base_queue_name . $i, Context::instance()->redis_local);
            $len            = $_target_worker->length();

            if ($len < $target_len) {
                $target_worker = $_target_worker;
                $target_len    = $len;
            }

        }
        return $target_worker;
    }

    /**
     * try to resend fail events
     */
    protected function failureEventsSendRetry()
    {
        $failure_queue = new Queue(self::QUEUE_NAME.":failure:events", Context::instance()->redis_local);
        $len = $failure_queue->length();
        if ($len <= 0)
            return;
        echo "正在尝试重新发送失败的事件，请确保接收事件的服务可用...\r\n";

        for($i = 0; $i < $len; $i++) {
            $event   = $failure_queue->peek();
            $success = $this->notify->send($event);
            if (!$success) {
                echo "重新尝试失败\r\n";
                Context::instance()->logger->error("failure events send fail", $event);
            } else {
                $failure_queue->pop();
            }
        }
        echo "失败事件重新发送完毕\r\n";
    }


    /**
     * rpc api for restart
     *
     * @return int 1 means success
     */
    public static function restart()
    {
        posix_kill(file_get_contents(self::$server_pid), SIGUSR1);
        return 1;
    }

    /**
     * rpc api for update system
     *
     * @return int 1 means success
     */
    public static function update()
    {
        $command = new Command("cd ".__APP_DIR__." && git pull origin master && composer update");
        $command->run();
        unset($command);
        self::restart();
        return 1;
    }

    /**
     * signal handler
     *
     * @param int $signal
     */
    public function signalHandler($signal)
    {
        $server_id = file_get_contents(self::$server_pid);

        switch ($signal) {
            //stop all
            case SIGINT:
                if ($server_id == self::getCurrentProcessId()) {
                    foreach ($this->processes as $id => $pid) {
                        $this->process_cache->set("stop_".$pid, 1, 10);
                    }
                }
                echo self::getCurrentProcessId()," exit\r\n";
                exit(0);
                break;
            //restart
            case SIGUSR1:
                if ($server_id == self::getCurrentProcessId()) {
                    foreach ($this->processes as $id => $pid) {
                        $this->process_cache->set("stop_".$pid, 1, 10);
                    }
                }
                break;
            //show status
            case SIGUSR2:
                file_put_contents(__APP_DIR__."/testttt.log",time(),FILE_APPEND);
                //update status;
                break;
        }
    }

    /**
     * 解析进程
     * @param int $i
     */
    protected function forkParseWorker($i)
    {
        $process_id = pcntl_fork();

        if ($process_id < 0) {
            echo "fork a process fail\r\n";
            exit;
        }

        if ($process_id > 0) {
            $this->processes[]         = $process_id;
            $this->parse_processes[$i] = $process_id;
            return;
        }


        if ($this->deamon) {
            $this->resetStd();
        }

        echo "process queue dispatch ".$i." is running \r\n";
        ini_set("memory_limit", Context::instance()->memory_limit);

        $process_name = "php seals >> events collector - workers - ".$i;

        //设置进程标题 mac 会有warning 直接忽略
        $this->setProcessTitle($process_name);

        //由于是多进程 redis和pdo等连接资源 需要重置
        Context::instance()
            ->initRedisLocal()
            ->initPdo();

        $queue         = new Queue(self::QUEUE_NAME.$i, Context::instance()->redis_local);
        $failure_queue = new Queue(self::QUEUE_NAME.":failure:events", Context::instance()->redis_local);
        $filter        = Context::instance()->getAppConfig("filter");


        while (1) {
            ob_start();
            try {
                pcntl_signal_dispatch();
                $this->setStatus($process_name);
                $this->setIsRunning();

                do {

                    //最后一个进程做失败重发操作
                    if (!$i == $this->workers) {
                        //尝试发送失败的事件
                        $this->failureEventsSendRetry();
                    }

                    $len = $queue->length();
                    if ($len <= 0) {
                        unset($len);
                        break;
                    }

                    unset($len);

                    $cache_file = $queue->pop();

                    if (!$cache_file) {
                        unset($cache_file);
                        break;
                    }

                    if (!file_exists($cache_file) || !is_file($cache_file)) {
                        echo "cache file error => ",$cache_file,"\r\n";
                        echo "queue => ",$queue->getQueueName(),"\r\n";
                        $queue_all  = $queue->getAll();

                        $error_info = "redis pop error => ". $cache_file ."\r\n".$this->getQueueName()."\r\n";

                        if (is_array($queue_all))
                            $error_info .= json_encode($queue_all,JSON_UNESCAPED_UNICODE);
                        else
                            $error_info .= $queue_all;

                        Context::instance()->logger->error($error_info);

                        unset($cache_file);
                        break;
                    }

                    echo "parse cache file => ",$cache_file,"\r\n";
                    $file = new FileFormat($cache_file,\Seals\Library\Context::instance()->activity_pdo);

                    $file->parse(function ($database_name, $table_name, $event) use($failure_queue, $filter) {

                        $continue = false;
                        //过滤器支持
                        if (is_array($filter) && count($filter) > 0) {
                            foreach ($filter as $_database_name => $tables ) {
                                if ($_database_name != $database_name)
                                    continue;
                                /*if (strpos($_database_name,"/") !== false ) {
                                    $p = str_replace("/","",$_database_name);
                                    if (preg_match("/".$p."/", $database_name)) {
                                        $continue = true;
                                        break;
                                    }
                                } else {
                                    if($database_name == $_database_name) {
                                        $continue = true;
                                        break;
                                    }
                                }*/

                                foreach ($tables as $table) {
                                    if (strpos($table, "/") !== false ) {
                                        $p = str_replace("/", "", $table);
                                        if (preg_match("/".$p."/", $table_name)) {
                                            $continue = true;
                                            break;
                                        }
                                    } else {
                                        if($table_name == $table) {
                                            $continue = true;
                                            break;
                                        }
                                    }
                                }
                            }
                        }

                        if ($continue) {
                            echo $database_name,"=>",$table_name,"已设置为忽略\r\n";
                            return;
                        }

                        $params = [
                            "database_name" => $database_name,
                            "table_name"    => $table_name,
                            "event_data"    => $event,
                            "app_id"        => $this->app_id
                        ];
                        $success = $this->notify->send($params);
                        if (!$success) {
                            Context::instance()->logger->error(get_class($this->notify)." send failure",$params);
                            $failure_queue->push($params);
                        }
                    });

                    unset($file);

                    echo "unlink cache file => ",$cache_file,"\r\n";
                    $success = unlink($cache_file);

                    if (!$success) {
                        echo "unlink failure \r\n";
                        Context::instance()->logger->error("unlink failure => ".$cache_file);
                    } else {
                        echo "unlink success \r\n";
                    }
                    unset($cache_file);

                } while (0);

                $this->checkStopSignal();

            } catch (\Exception $e) {
                Context::instance()->logger->error($e->getMessage());
                var_dump($e->getMessage());
                unset($e);
            }

            $output = ob_get_contents();
            Context::instance()->logger->info($output);
            ob_end_clean();
            usleep(10000);

            if ($output && $this->debug) {
                echo $output;
            }
            unset($output);

        }

    }

    /**
     * 调度进程
     * @param int $i
     */
    protected function forkDispatchWorker($i)
    {
        $process_id = pcntl_fork();

        if ($process_id < 0) {
            echo "fork a process fail\r\n";
            exit;
        }

        if ($process_id > 0) {
            $this->processes[]            = $process_id;
            $this->dispatch_processes[$i] = $process_id;
            return;
        }

        if ($this->deamon) {
            $this->resetStd();
        }

        ini_set("memory_limit", Context::instance()->memory_limit);

        $process_name = "php seals >> events collector - dispatch - ".$i;

        //设置进程标题 mac 会有warning 直接忽略
        $this->setProcessTitle($process_name);

        //由于是多进程 redis和pdo等连接资源 需要重置
        Context::instance()
            ->initRedisLocal()
            ->initPdo();


        $bin = new \Seals\Library\BinLog(Context::instance()->activity_pdo);
        $bin->setCacheDir(Context::instance()->binlog_cache_dir);
        $bin->setDebug($this->debug);
        $bin->setCacheHandler(new \Seals\Cache\File(__APP_DIR__));

        $queue = new Queue(self::QUEUE_NAME. ":ep".$i, Context::instance()->redis_local);

        while (1) {
            clearstatcache();
            ob_start();

            try {
                do {
                    pcntl_signal_dispatch();
                    $this->setStatus($process_name);
                    $this->setIsRunning();

                    $res = $queue->pop();
                    if (!$res)
                        break;

                    echo "pos => ",$res,"\r\n";
                    list($start_pos, $end_pos) = explode(":",$res);

                    if (!$start_pos || !$end_pos)
                        break;

                    $cache_path = $bin->getSessions($start_pos, $end_pos);
                    unset($end_pos,$start_pos);

                    //进程调度 看看该把cache_file扔给那个进程处理
                    $target_worker = $this->getWorker(self::QUEUE_NAME);
                    echo "cache file => ",$cache_path,"\r\n";
                    $success = $target_worker->push($cache_path);

                    if (!$success) {
                        Context::instance()->logger->error(" redis rPush error => ".$cache_path);
                    }

                    unset($target_worker,$cache_path);

                    unset($cache_path);

                } while (0);

                $this->checkStopSignal();

            } catch (\Exception $e) {
                Context::instance()->logger->error($e->getMessage());
                var_dump($e->getMessage());
                unset($e);
            }

            $output = ob_get_contents();
            Context::instance()->logger->info($output);
            ob_end_clean();

            if ($output && $this->debug) {
                echo $output;
            }
            unset($output);
            usleep(10000);
        }
    }

    /**
     * 基础事件采集进程
     */
    protected function forkEventWorker()
    {
        $process_id = pcntl_fork();

        if ($process_id < 0) {
            echo "fork a process fail\r\n";
            exit;
        }

        if ($process_id > 0) {
            $this->processes[]   = $process_id;
            $this->event_process = $process_id;
            return;
        }

        if ($this->deamon) {
            $this->resetStd();
        }

        ini_set("memory_limit", Context::instance()->memory_limit);
        $process_name = "php seals >> events collector - ep";

        //设置进程标题 mac 会有warning 直接忽略
        $this->setProcessTitle($process_name);

        //由于是多进程 redis和pdo等连接资源 需要重置
        Context::instance()
            ->initRedisLocal()
            ->initPdo()
            ->zookeeperInit();

        $bin = new \Seals\Library\BinLog(Context::instance()->activity_pdo);
        $bin->setCacheDir(Context::instance()->binlog_cache_dir);
        $bin->setDebug($this->debug);
        $bin->setCacheHandler(new \Seals\Cache\File(__APP_DIR__));

        $zookeeper = new Zookeeper(Context::instance()->redis_zookeeper);

        $limit = 10000;
        while (1) {
            clearstatcache();
            ob_start();

            try {
                do {
                    pcntl_signal_dispatch();
                    $this->setStatus($process_name);
                    $this->setIsRunning();
                    //服务发现
                    $zookeeper->serviceReport();
                    RPC::run();

                    if (!$zookeeper->isLeader()) {
                        // echo "不是leader，不进行采集操作\r\n";
                        //if the current node is not leader and group is enable
                        //we need to get the last pos and last binlog from leader
                        //then save it to local
                        $last_res = $zookeeper->getLastPost();
                        if (is_array($last_res) && count($last_res) == 2) {
                            if ($last_res[0] && $last_res[1])
                                $bin->setLastPosition($last_res[0], $last_res[1]);
                        }
                        $last_binlog = $zookeeper->getLastBinlog();
                        if ($last_binlog) {
                            $bin->setLastBinLog($last_binlog);
                        }
                        break;
                    }

                    // echo "是leader\r\n";

                    //最后操作的binlog文件
                    $last_binlog         = $bin->getLastBinLog();
                    $zookeeper->setLastBinlog($last_binlog);

                    //当前使用的binlog 文件
                    $current_binlog      = $bin->getCurrentLogInfo()["File"];

                    //获取最后读取的位置
                    list($last_start_pos, $last_end_pos) = $bin->getLastPosition();
                    $zookeeper->setLastPost($last_start_pos, $last_end_pos);

                    //binlog切换时，比如 .00001 切换为 .00002，重启mysql时会切换
                    //重置读取起始的位置
                    if ($last_binlog != $current_binlog) {
                        $bin->setLastBinLog($current_binlog);
                        $zookeeper->setLastBinlog($current_binlog);

                        $last_start_pos = $last_end_pos = 0;
                        $bin->setLastPosition($last_start_pos, $last_end_pos);
                        $zookeeper->setLastPost($last_start_pos, $last_end_pos);
                    }

                    unset($last_binlog);

                    //得到所有的binlog事件 记住这里不允许加limit 有坑
                    $data = $bin->getEvents($current_binlog,$last_end_pos,$limit);
                    if (!$data) {
                        unset($current_binlog,$last_start_pos,$last_start_pos);
                        break;
                    }
                    unset($current_binlog,$last_start_pos,$last_start_pos);

                    $start_pos   = $data[0]["Pos"];
                    $has_session = false;

                    foreach ($data as $row){
                        if ($row["Event_type"] == "Xid") {
                            $queue = $this->getWorker(self::QUEUE_NAME. ":ep");

                            echo "push==>",$start_pos.":".$row["End_log_pos"],"\r\n";

                            $queue->push($start_pos.":".$row["End_log_pos"]);

                            unset($queue);
                            //设置最后读取的位置
                            $bin->setLastPosition($start_pos, $row["End_log_pos"]);
                            $zookeeper->setLastPost($start_pos, $row["End_log_pos"]);

                            $has_session = true;
                            $start_pos = $row["End_log_pos"];
                        }
                    }

                    //如果没有查找到一个事务 $limit x 2 直到超过 100000 行
                    if (!$has_session) {
                        $limit = 2*$limit;
                        echo "没有找到事务，更新limit=",$limit,"\r\n";
                        if ($limit >= 80000) {
                            //如果超过8万 仍然没有找到事务的结束点 放弃采集 直接更新游标
                            $row = array_pop($data);
                            echo "查询超过8万，没有找到事务，直接更新游标";
                            echo $start_pos, "=>", $row["End_log_pos"],"\r\n";

                            $bin->setLastPosition($start_pos, $row["End_log_pos"]);
                            $zookeeper->setLastPost($start_pos, $row["End_log_pos"]);

                            $limit = 10000;
                        }
                    } else {
                        $limit = 10000;
                    }

                } while (0);
                $this->checkStopSignal();
            } catch (\Exception $e) {
                Context::instance()->logger->error($e->getMessage());
                var_dump($e->getMessage());
                unset($e);
            }

            $output = ob_get_contents();
            Context::instance()->logger->info($output);
            ob_end_clean();

            if ($output && $this->debug) {
                echo $output;
            }
            unset($output);
            usleep(10000);
        }

    }

    /**
     * @启动进程 入口函数
     */
    public function start(){

        echo "帮助：\r\n";
        echo "启动服务：php seals server:start\r\n";
        echo "指定进程数量：php seals server:start --n 4\r\n";
        echo "4个进程以守护进程方式启动服务：php seals server:start --n 4 --d\r\n";
        echo "重启服务：php seals server:restart\r\n";
        echo "停止服务：php seals server:stop\r\n";
        echo "服务状态：php seals server:status\r\n";
        echo "\r\n";


        if(!self::$is_deamon)
        {
            //stop
            pcntl_signal(SIGINT, [$this, 'signalHandler'], false);
            //reload
            pcntl_signal(SIGUSR1, [$this, 'signalHandler'], false);
            //status
            pcntl_signal(SIGUSR2, [$this, 'signalHandler'], false);
            //ignore
            pcntl_signal(SIGPIPE, SIG_IGN, false);
        }

        //设置守护进程模式
        if ($this->deamon) {
            self::daemonize();
        }

        //启动元数据解析进程
        for ($i = 1; $i <= $this->workers; $i++) {
            $this->forkParseWorker($i);
            $this->forkDispatchWorker($i);
        }

        //尝试发送失败的事件
        $this->failureEventsSendRetry();
        $this->forkEventWorker();

        //write pid file
        file_put_contents(self::$server_pid, self::getCurrentProcessId());

        while (1) {
            pcntl_signal_dispatch();

            $status = 0;
            $pid    = pcntl_wait($status, WNOHANG);

            if ($pid > 0) {

                Context::instance()->logger->notice($pid." process shutdown, try create a new process");
                $id = array_search($pid, $this->processes);
                unset($this->processes[$id]);

                if ($pid == $this->event_process) {
                    $this->forkEventWorker();
                    continue;
                }

                $id = array_search($pid, $this->parse_processes);
                if ($id) {
                    unset($this->parse_processes[$id]);
                    $this->forkParseWorker($id);
                    continue;
                }

                $id = array_search($pid, $this->dispatch_processes);
                if ($id) {
                    unset($this->dispatch_processes[$id]);
                    $this->forkDispatchWorker($id);
                    continue;
                }
            }

            usleep(500000);
        }

        echo "service shutdown\r\n";
    }

}