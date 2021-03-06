<?php
/**
 * https://github.com/andreiz/php-zookeeper/blob/master/examples/Zookeeper_Example.php
 */
$zk = new Zookeeper('127.0.0.1:port,...,ip:port/path');
$brokersPath = '/brokers/ids';

$ids = $zk->getChildren($brokersPath);
$brokers = array();
foreach ($ids as $id) {
    $path = $brokersPath . "/{$id}";
    $val = $zk->get($path);
    $val = json_decode($val, true);
    $brokers[] = $val['host'] . ":" . $val['port'];
}
$brokersAddr = implode(',', $brokers);

//begin to consume
$conf = new RdKafka\Conf();

// Set the group id. This is required when storing offsets on the broker
$conf->set('group.id', 'my-alarm-group');
$conf->set('broker.version.fallback', '0.8.2.2');
// socket请求的超时时间。实际的超时时间为max.fetch.wait + socket.timeout.ms。
$conf->set('socket.timeout.ms', '400');

$consumer = new RdKafka\Consumer($conf);
$consumer->addBrokers($brokersAddr);
$consumer->setLogLevel(LOG_DEBUG);


$topicConf = new RdKafka\TopicConf();
$topicConf->set('auto.commit.interval.ms', 1000);

// Set the offset store method to 'file'
$topicConf->set('offset.store.method', 'file');
$topicConf->set('offset.store.path', sys_get_temp_dir());
//$topicConf->set('api.version.request', true);
//$topicConf->set('broker.version.fallback', '0.8.2.2');

// Alternatively, set the offset store method to 'broker'
// $topicConf->set('offset.store.method', 'broker');

// Set where to start consuming messages when there is no initial offset in
// offset store or the desired offset is out of range.
// 'smallest': start from the beginning
$topicConf->set('auto.offset.reset', 'largest');

$topic = $consumer->newTopic("Topic_Name", $topicConf);

// Start consuming partition 0
$metaData = $consumer->getMetadata(false, $topic, 1000);
$partitions = $metaData->getTopics()->current()->getPartitions();
$partition = count($partitions);
for($i=0; $i<=$partition; $i++){
    $topic->consumeStart($i, RD_KAFKA_OFFSET_STORED);
}

while (true) {
    for($i=0; $i<=$partition; $i++){
        $message = $topic->consume($i, 120*10000);
        switch ($message->err) {
            case RD_KAFKA_RESP_ERR_NO_ERROR:
                echo "resutl:".$i;
                var_dump($message->offset);
                var_dump($message->payload);
                echo "\n";
                break;
            case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                echo "No more messages; will wait for more\n";
                break;
            case RD_KAFKA_RESP_ERR__TIMED_OUT:
                echo "Timed out\n";
                break;
            default:
                throw new \Exception($message->errstr(), $message->err);
                break;
        }
    }
}
