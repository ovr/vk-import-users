<?php

include_once __DIR__ . '/vendor/autoload.php';

set_time_limit(0);

class Profiler extends Stackable
{
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

    static public $pdo1;

    static public $pdo2;

    static public $pdo3;

    /**
     * @param Profiler $profiler
     * @param SafeLog $logger
     */
    public function __construct(Profiler $profiler, SafeLog $logger)
    {
        $this->profiler = $profiler;
        $this->logger = $logger;
    }

    public function run()
    {
        self::$pdo1 = new PDO('mysql:host=localhost;dbname=vk_import', 'root', 'root', array(
            PDO::ATTR_PERSISTENT => true
        ));
        self::$pdo1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo1->exec("SET CHARACTER SET utf8");

        self::$pdo2 = new PDO('mysql:host=localhost;dbname=vk_import', 'root', 'root', array(
            PDO::ATTR_PERSISTENT => true
        ));
        self::$pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo2->exec("SET CHARACTER SET utf8");

        self::$pdo3 = new PDO('mysql:host=localhost;dbname=vk_import', 'root', 'root', array(
            PDO::ATTR_PERSISTENT => true
        ));
        self::$pdo3->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo3->exec("SET CHARACTER SET utf8");
    }

    public function writeToDb(array $values)
    {
        foreach ($values as $row) {
            $statement = self::${"pdo" . rand(1,3)}->prepare('INSERT INTO `users` (`id`, `firstname`, `lastname`) VALUES (?, ?, ?);');

            $statement->execute(array(
                $row->uid,
                $row->first_name,
                $row->last_name
            ));
        }
    }
}

/**
 * Class VkFetchThread
 *
 * @property WebWorker $worker
 */
class VkFetchThread extends Thread
{
    protected $start;

    protected $end;

    public function __construct($start, $end)
    {
        $this->start = $start;
        $this->end = $end;
    }

    public function run()
    {
        $limit = 300;
        $interval = round($this->end / $limit);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPGET, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, 0);

        for ($i = 0; $i < $interval; $i++) {
            $start = $i * $limit + 1;
            $end = ($i * $limit) + $limit;

            curl_setopt($curl, CURLOPT_URL, 'https://api.vk.com/method/users.get?user_ids=' . implode(',', array_keys(array_fill($start, $limit, 1))));

            if (!$result = curl_exec($curl)) {
                throw new \Exception('Curl http Error');
            }

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
    public function run()
    {
        while (true) {
            usleep(10000);
            $this->worker->logger->log("Total count {$this->worker->profiler->total}");
        }
    }
}


class SafeLog extends Stackable
{
    public function log($message, $args = [])
    {
        $args = func_get_args();

        if (($message = array_shift($args))) {
            echo vsprintf(
                "{$message}\n", $args);
        }
    }
}

const FetchVkWorkers = 128;

$pool = new Pool(FetchVkWorkers + 1, 'WebWorker', [new Profiler(), new SafeLog()]);

for ($i = 0; $i < FetchVkWorkers; $i++) {
    $pool->submit(new VkFetchThread(($i * 100000000) + 1, ($i * 100000000) + 100000000));
}

$pool->submit(new ProfilerThread());

$pool->shutdown();

var_dump($pool->worker->profiler->total);