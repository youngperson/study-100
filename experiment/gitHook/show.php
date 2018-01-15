<?php
/**
 * Created by PhpStorm.
 * User: wanrenliang
 * Date: 2018/1/10
 * Time: 下午7:43
 */
include('./common.php');
class showInfo extends commonServer {

    //显示错误信息在浏览器中
    public function errorInfo() {
        $data = $this->getCheckMsg($_GET['iid']);
        $this->display($data);
    }

    protected function display($msg, $type = 'application/json') {
        header('Content-type: '. $type .'; charset=utf-8');
        header('Cache-Control: max-age=0');
        print $msg;
        die;
    }
}

$show = new showInfo();
$show->errorInfo();