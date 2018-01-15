<?php
/**
 * Created by PhpStorm.
 * User: wanrenliang
 * Date: 2018/1/10
 * Time: 上午2:24
 */
class commonServer{

    const DEBUG_LOG = '/tmp/hook.log';
    const CHECK_CODE_PID = '/tmp/check_code.pid';
    const EXPIRE_TIME= 86400 * 30;
    const MERGE_ID   = 'merge_request_id_%d';
    const MERGE_LIST = 'merge_request_list';
    const CHECK_STYLE_ERROR = 'check_type_errors';
    const CHECK_CODE_MSG = 'deploy:%d:check_code';
    const ROOT_PATH = __DIR__;
    const PROJECT_PATH = '/var/www/test_project';
    const ERROR_DETAIL_URL = 'http://121.40.175.176:8699/show.php';
    const CHECK_AGAIN_URL  = 'http://121.40.175.176:8699/collect.php';

    public function pushData($iid, $setData = []) {
        $redis     = $this->getLogStashRedis();
        if ($setData) {
            if (is_array($setData)) {
                $setData = json_encode($setData);
            }
            $redis->set(sprintf(self::MERGE_ID, $iid), $setData, self::EXPIRE_TIME);
        }
        //把每次git提交事件的ID放到待消费的队列中
        $redis->lPush(self::MERGE_LIST, $iid);
    }

    /**
     * 连接记录数据的Redis
     * @param string $host
     * @param string $port
     * @param float $timeout
     * @return Redis
     */
    public function getLogStashRedis($host = '127.0.0.1', $port = '6380', $timeout = 0.02) {
        $redis = new Redis();
        $redis->connect($host, $port, $timeout);
        $ping  = $redis->ping();
        file_put_contents(self::DEBUG_LOG, "redis-connect:{$ping}\n", 8);
        return $redis;
    }

    /**
     * 从队列中消费数据
     * @return mixed
     */
    public function next() {
        $redis     = $this->getLogStashRedis();
        return $redis->rPop(self::MERGE_LIST);
    }

    /**
     * 根据ID取数据队列中的ID
     * @param $iid
     * @return mixed
     */
    public function get($iid) {
        $redis     = $this->getLogStashRedis();
        return $redis->get(sprintf(self::MERGE_ID, $iid));
    }

    /**
     * 根据
     * @param $iid
     * @return mixed
     */
    public function getCheckMsg($iid) {
        $redis     = $this->getLogStashRedis();
        return $redis->get(sprintf(self::CHECK_CODE_MSG, $iid));
    }

    /**
     * 往钉钉的机器人里面发消息
     * @param $text
     * @return bool
     */
    public function sendWarning($text) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oapi.dingtalk.com/robot/send?access_token=13fae2a0b8a9a4b25bf8ac826309ee6066d5157ad407b4a286b36ad344e56cfb');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json;charset=utf-8']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'msgtype' => 'text','text' => ['content' => $text]
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $data = json_decode(curl_exec($ch), true);
        curl_close($ch);
        return isset($data['errcode']) && $data['errcode'] === 0;
    }
}