<?php
/**
 * Created by PhpStorm.
 * User: wanrenliang
 * Date: 2018/1/10
 * Time: 上午2:10
 */

include('./common.php');
class collectData extends commonServer {

    //钩子的入口,把每次请求的ID放入队列中待消费,
    //把每次请求需要的数据记录30天
    public function client() {
        //获取到输入流中的数据
        $input    = file_get_contents('php://input');
        $inputLog = $input . "\n";
        //打日志调试
        file_put_contents(self::DEBUG_LOG, $inputLog, 8);

        //钩子传递的数据是json字符串
        $setData = [];
        if ($post = json_decode($input, true)) {
            //只需要Git动作是merge_request的,可以去研究下不同事件的action对应值
            //针对页面中merge_request的分析,不同的action,数据格式不太一样
            if ($post['object_attributes']['action'] != 'open') {
                file_put_contents(self::DEBUG_LOG, "当前动作不是合并请求\n", 8);
                die;
            }

            //组装我们需要的数据存储起来
            $iid = $post['object_attributes']['iid'];
            $setData = [
                'project'      => $post['repository']['name'],
                'branch'       => $post['object_attributes']['source_branch'],
                'targetBranch' => $post['object_attributes']['target_branch'],
                'username'     => $post['user']['username'],
                'url'          => $post['object_attributes']['url'],
                'root'         => self::ROOT_PATH
            ];

            //判断合并的目标分支是不是master
            //if ($post['targetBranch'] != 'master') {
            //    die;
            //}
        } else if ($iid = $_GET['iid']) {
            //pass 重新检测把当前请求的ID放到队列中去
        } else {
            file_put_contents(self::DEBUG_LOG, "参数错误\n", 8);
            die;
        }

        //把获取的数据存入在Redis中
        $this->pushData($iid, $setData);
    }
}

$hook = new collectData();
$hook->client();