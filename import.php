<?php

include_once __DIR__ . '/vendor/autoload.php';

set_time_limit(0);

class Profiler extends Stackable {
    public $total = 0;
}

class WebWorker extends Worker
{
    /**
     * @var SafeLog
     */
    public $logger;

    /**
     * @var Profiler
     */
    public $profiler;

    /**
     * @param Profiler $profiler
     * @param SafeLog $logger
     */
    public function __construct(Profiler $profiler, SafeLog $logger) {
        $this->profiler = $profiler;
        $this->logger = $logger;
    }

    protected $connection;

    public function writeToDb(array $values)
    {

    }
}

/**
 * Class VkFetchThread
 *
 * @property WebWorker $worker
 */
class VkFetchThread extends Thread {
    protected $start;

    protected $end;

    public function __construct($start, $end)
    {
        $this->start  = $start;
        $this->end = $end;
    }

    public function run() {
        $limit = 300;
        $interval = round($this->end/$limit);

        for ($i = 0; $i < $interval; $i++) {
            $start = $i*$limit+1;
            $end = ($i*$limit)+$limit;

            $result = file_get_contents('https://api.vk.com/method/users.get?user_ids='.implode(',', array_keys(array_fill($start, $limit, 1))));
            $this->worker->profiler->total += $limit;

            $users = json_decode($result);
            if ($users) {
                $this->worker->writeToDb($users->response);
            }
        }
    }
}

/**
 * Class VkFetchThread
 *
 * @property WebWorker $worker
 */
class ProfilerThread extends Thread
{
    public function run() {
        while (true) {
            usleep(100000);
            $this->worker->logger->log("Total count {$this->worker->profiler->total}");
        }
    }
}


class SafeLog extends Stackable {
    public function log($message, $args = []) {
        $args = func_get_args();

        if (($message = array_shift($args))) {
            echo vsprintf(
                "{$message}\n", $args);
        }
    }
}

const FetchVkWorkers = 16;

$pool = new Pool(FetchVkWorkers+1, 'WebWorker', [new Profiler(), new SafeLog()]);

for ($i = 0; $i < FetchVkWorkers; $i++) {
    $pool->submit(new VkFetchThread(($i*100000000)+1, ($i*100000000)+100000000));
}

$pool->submit(new ProfilerThread());

$pool->shutdown();