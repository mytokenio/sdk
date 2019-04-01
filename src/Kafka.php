<?php
/**
 * Created by PhpStorm.
 * User: henter
 * Date: 2018/03/02
 * Time: 19:25
 */

class Kafka
{
    private static $instance;

    /**
     * 消费者Client
     *
     * @var object
     */
    protected static $consumerClient = [];

    /**
     * 需手动commit的消费者
     * @var array
     */
    protected static $manualCommitConsumerClient = [];

    /**
     * 生产者Client
     *
     * @var object
     */
    protected static $producerClient = [];

    /**
     * largest从新数据开始消费
     */
    const RESTORE_OFFSET_LARGEST = 'largest';

    /**
     * smallest从能消费的旧数据开始
     */
    const RESTORE_OFFSET_SMALLEST = 'smallest';

    private $host = '127.0.0.1';

    public function __construct()
    {
        $this->host = \SDK::config('kafka', '127.0.0.1:9092');
    }

    /**
     * @return self
     */
    public static function getInstance()
    {
        if (!static::$instance) {
            static::$instance = new self();
        }
        return static::$instance;
    }

    /**
     * kafka消费端配置
     *
     * @param $group
     * @param $restore
     * @param bool $autoCommit
     * @return \RdKafka\Conf
     */
    private function getConsumerConfig($group, $restore, $autoCommit = true)
    {
        $conf = new \RdKafka\Conf();
        $conf->setRebalanceCb(function (\RdKafka\KafkaConsumer $kafka, $err, array $partitions = null) {
            /**
             * TODO: 这里的 callback 内不要调用 dump Log 等，否则业务内 exit 时可能出现 segfault，原因未知
             */

            switch ($err) {
                case RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
                    $kafka->assign($partitions);
                    break;

                case RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
                    $kafka->assign(null);
                    break;

                default:
                    throw new \Exception($err);
            }
        });

        // Configure the group.id. All consumer with the same group.id will consume
        // different partitions.
        $conf->set('group.id', $group);
        // Initial list of Kafka brokers
        $conf->set('metadata.broker.list', $this->host);
        $conf->set('heartbeat.interval.ms', 10000);

        $topicConf = new \RdKafka\TopicConf();

        $topicConf->set('auto.commit.enable', $autoCommit ? 'true' : 'false');
        $topicConf->set('auto.commit.interval.ms', 1000);

        // Set where to start consuming messages when there is no initial offset in
        // offset store or the desired offset is out of range.
        // 'smallest': start from the beginning
        // smallest从能消费的旧数据开始，largest从新数据开始消费
        $topicConf->set('auto.offset.reset', $restore);

        // Set the configuration to use for subscribed/assigned topics
        $conf->setDefaultTopicConf($topicConf);

        return $conf;
    }

    /**
     * 获取消费者客户端
     *
     * @param array $topics
     * @param string $group
     * @param string $restore
     * @return \RdKafka\KafkaConsumer
     */
    public function getConsumerClient(array $topics, string $group = 'unknown', string $restore = self::RESTORE_OFFSET_LARGEST)
    {
        if (!isset(self::$consumerClient[$group])) {
            $conf = $this->getConsumerConfig($group, $restore);
            $consumer = new \RdKafka\KafkaConsumer($conf);

            $consumer->subscribe($topics);

            //echo "Waiting for partition assignment... may take some time \n";

            self::$consumerClient[$group] = $consumer;
        }
        return self::$consumerClient[$group];
    }

    /**
     * @param array $topics
     * @param string $group
     * @param string $restore
     * @return \RdKafka\KafkaConsumer
     */
    public function getManualCommitConsumerClient(array $topics, string $group = 'unknown', string $restore = self::RESTORE_OFFSET_LARGEST)
    {
        if (!isset(self::$manualCommitConsumerClient[$group])) {
            $conf = $this->getConsumerConfig($group, $restore, false);
            $consumer = new \RdKafka\KafkaConsumer($conf);

            $consumer->subscribe($topics);

            //echo "Waiting for partition assignment... may take some time \n";

            self::$manualCommitConsumerClient[$group] = $consumer;
        }
        return self::$manualCommitConsumerClient[$group];
    }

    /**
     * @param \RdKafka\Message $message
     * @param int $expire 过期时间
     * @return string
     * @throws \Exception
     */
    public function checkMessage(\RdKafka\Message $message, $expire = 0)
    {
        switch ($message->err) {
            case RD_KAFKA_RESP_ERR_NO_ERROR:
                //超出过期时间
                if ($expire && !empty($message->timestamp) && (time() - $message->timestamp/1000 > $expire)) {
                    return false;
                }

                return true;
                break;

            case RD_KAFKA_RESP_ERR__PARTITION_EOF:  // no message
            case RD_KAFKA_RESP_ERR__TIMED_OUT:  // timeout
                break;

            default:
                throw new \Exception($message->errstr(), $message->err);
                break;
        }
        return false;
    }

    /**
     * 向kafka投递数据
     *
     * @param string $topic
     * @param $msg
     * @return bool
     */
    public function pub(string $topic, $msg)
    {
        try {
            $context = [];
            if (is_array($msg)) {
                $context = $msg;
                $msg = \json_encode($msg);
            }
            if (!isset(self::$producerClient[$topic])) {
                $rk = new \RdKafka\Producer();
                $rk->setLogLevel(LOG_DEBUG);
                $rk->addBrokers($this->host);
                $topicClient = $rk->newTopic($topic);
                self::$producerClient[$topic] = [$topicClient, $rk];
            } else {
                list($topicClient, $rk) = self::$producerClient[$topic];
            }

            $topicClient->produce(RD_KAFKA_PARTITION_UA, 0, $msg);
            $rk->poll(0);

            while ($rk->getOutQLen() > 0) {
                $rk->poll(20);
            }

            \Log::getLogger('kafka_'.$topic)->info($msg, $context);
        } catch (\Exception $e) {
            \Log::getLogger('exception')->error($e->getMessage(), ['e' => $e]);
            return false;
        }
        return true;
    }
}
