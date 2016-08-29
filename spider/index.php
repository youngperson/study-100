<?php
require "./config.php";


$redis = new Redis();
$redis->connect('127.0.0.1', '6379');

//如果队列里面没有用户id则初始化一个
$init_user_id = 'gao-tai-ye';
if ($redis->llen('request_queue') == 0)
{
    $redis->lpush('request_queue', $init_user_id);
}

$pdo = new PDO('mysql:host=localhost;dbname=test','root','111111');


//死循环消费队列中的任务
$set_total = 10000;
while (1) {
    echo "--------begin get user info--------\n";
    //获取当前队列中的uid数量
    $total = $redis->llen('request_queue');
    //获取已经抓取过的uid数量
    $get_total = $redis->zcard('already_get_queue');
    if ($get_total>=$set_total || $total == 0) {
        echo "--------done--------\n";
        break;
    }

    $startTime = microtime();
    //从队列中取一个用户id
    $tmp_u_id = $redis->lpop('request_queue');
    //从集合中判断下该用户id是否抓取过
    $tmp_size = $redis->zscore('already_get_queue', $tmp_u_id);
    if (empty($tmp_size))
    {
        $endTime = microtime();
        $startTime = explode(' ', $startTime);

        $current_user = saveUserInfo($tmp_u_id);
        //print_r($userInfo);
        if($current_user['u_id']) {

            $sql = "insert into zh_user(
              u_id,u_name,address,img_url,business,gender,education,major,description,followees_count,followers_count,special_count,
              follow_topic_count,pv_count,approval_count,thank_count,ask_count,answer_count,started_count,public_edit_count,article_count,duplicate_count
            )value(
                    '{$current_user['u_id']}',
                    '{$current_user['u_name']}',
                    '{$current_user['address']}',
                    '{$current_user['img_url']}',
                    '{$current_user['business']}',
                    '{$current_user['gender']}',
                    '{$current_user['education']}',
                    '{$current_user['major']}',
                    '{$current_user['description']}',
                    {$current_user['followees_count']},
                    {$current_user['followers_count']},
                    {$current_user['special_count']},
                    {$current_user['follow_topic_count']},
                    {$current_user['pv_count']},
                    {$current_user['approval_count']},
                    {$current_user['thank_count']},
                    {$current_user['ask_count']},
                    {$current_user['answer_count']},
                    {$current_user['started_count']},
                    {$current_user['public_edit_count']},
                    {$current_user['article_count']},
                    {$current_user['duplicate_count']}
            )";
            
            //执行sql
            $pdo->exec($sql);
            
            //用户主动关注了的列表   
            if ($current_user['followees_count']) {
                $followees_result = request('GET', 'https://www.zhihu.com/people/' . $tmp_u_id . '/followees');
                $followees_result = getFollowUserId($followees_result);
                //压入队列
                //print_r($followees_result);
                $num = count($followees_result);
                for($i=0;$i<$num;$i++) {
                      $redis->lpush('request_queue', $followees_result[$i]);
                }
            }
            
            //用户被哪些人关注了的列表
            if ($current_user['followers_count']) {
                $followers_result = request('GET', 'https://www.zhihu.com/people/' . $tmp_u_id . '/followers');
                $followers_result = getFollowUserId($followers_result);
                //压入队列
                //print_r($followers_result);
                $num = count($followers_result);
                for($i=0;$i<$num;$i++) {
                      $redis->lpush('request_queue', $followers_result[$i]);
                }
            }
            
            $redis->zadd('already_get_queue', 1, $tmp_u_id);
        }else{
            echo "--------uid为空,账号可能被封了--------\n";
            break;
        }
        
        $endTime = explode(' ', $endTime);
        $total_time = $endTime[0] - $startTime[0] + $endTime[1] - $startTime[1];
        $timecost = sprintf("%.2f",$total_time);
        echo "--------const  " . $timecost . " second on $tmp_u_id--------\n";
    }else{
        echo "--------user $tmp_u_id info and followee and follower already get--------\n";
    }

}


//获取用户主动关注了哪些uid,被哪些uid关注的列表信息
function getFollowUserId($result)
{
    preg_match_all('#<a data-hovercard=".*?" href="https://www.zhihu.com/people/(.*?)" class="zg-link author-link"#U', $result, $out);
    return $out[1];
}

//================================
/**
 * [saveUserInfo 保存用户信息]
 * @param  [type]  $tmp_u_id    [用户ID]
 * @return [type]             [description]
 */
function saveUserInfo($tmp_u_id)
{
    echo "--------found new user {$tmp_u_id}--------\n";
    echo "--------start getting {$tmp_u_id} info--------\n";
    //获取该用户的个人信息,需要携带登录的cookie信息才能访问
    $result = request('GET', 'https://www.zhihu.com/people/' . $tmp_u_id . '/followees');
    if (empty($result))
    {
        $i = 0;
        //换个链接重试5次,获取个人信息,该链接不需要登录就可以访问到
        while(empty($result))
        {
            echo "--------empty result.try get $i time--------\n";
            $result = request('GET', 'https://www.zhihu.com/people/' . $tmp_u_id);
            if (++$i == 5)
            {
                exit($i);
            }
        }
    }

    //存储到数据库
    $current_user = getUserInfo($result);
    
    echo "--------get {$tmp_u_id} info done--------\n";
    return $current_user;
}

/**
 * [getUserInfo 获取用户]
 * @param  [type] $result [description]
 * @return [type]         [description]
 */
function getUserInfo($result)
{
    $user = array();
    //匹配个人中心页面的用户id和名字-用修正符U取消贪婪匹配的问题
    preg_match_all('#<a class="name" href="/people\/(.*)">(.*)</a>#U', $result, $out);
    $user['u_id'] = empty($out[1]) ? '' : $out[1][0];
    $user['u_name'] = empty($out[2]) ? '' : $out[2][0];

    //匹配地点
    preg_match('#<span class="location item" title=["|\'](.*?)["|\']>#', $result, $out);
    $user['address'] = empty($out[1]) ? '' : $out[1];

    //匹配图片url
    preg_match('#<img class="Avatar Avatar--l" src="(.*?)" srcset=".*?" alt=".*?" />#', $result, $out);
    $img_url_tmp = empty($out[1]) ? '' : $out[1];
    $user['img_url'] = getImg($img_url_tmp, $user['u_id']);
    // $user['img_url'] = $img_url_tmp;

    //匹配行业
    preg_match('#<span class="business item" title=["|\'](.*?)["|\']>#', $result, $out);
    $user['business'] = empty($out[1]) ? '' : $out[1];

    //匹配性别
    preg_match('#<i class="icon icon-profile-(.*?)male"></i>#', $result, $out);
    $user['gender'] = empty($out[1]) ? 'male' : 'female';

    //匹配毕业学校
    preg_match('#<span class="education item" title=["|\'](.*?)["|\']>#', $result, $out);
    $user['education'] = empty($out[1]) ? '' : $out[1];

    //匹配专业
    preg_match('#<span class="education-extra item" title=["|\'](.*?)["|\']>#', $result, $out);
    $user['major'] = empty($out[1]) ? '' : $out[1];

    //一句话描述
    preg_match('#<span class="content">\s(.*?)\s</span>#s', $result, $out);
    $user['description'] = empty($out[1]) ? '' : trim(strip_tags($out[1]));

    //关注了数量
    preg_match('#<span class="zg-gray-normal">关注了</span><br />\s<strong>(.*?)</strong><label> 人</label>#U', $result, $out);
    $user['followees_count'] = empty($out[1]) ? 0 : $out[1];

    //关注者数量
    preg_match('#<span class="zg-gray-normal">关注者</span><br />\s<strong>(.*?)</strong><label> 人</label>#U', $result, $out);
    $user['followers_count'] = empty($out[1]) ? 0 : $out[1];

    //专栏数量
    preg_match('#<strong>(.*?) 个专栏</strong>#', $result, $out);
    $user['special_count'] = empty($out[1]) ? 0 : intval($out[1]);

    //关注话题数量
    preg_match('#<strong>(.*?) 个话题</strong>#', $result, $out);
    $user['follow_topic_count'] = empty($out[1]) ? 0 : intval($out[1]);

    //获得赞同数量
    preg_match('#<span class="zm-profile-header-user-agree"><span class="zm-profile-header-icon"></span><strong>(.*?)</strong>赞同</span>#', $result, $out);
    $user['approval_count'] = empty($out[1]) ? 0 : $out[1];

    //获得感谢数量
    preg_match('#<span class="zm-profile-header-user-thanks"><span class="zm-profile-header-icon"></span><strong>(.*?)</strong>感谢</span>#', $result, $out);
    $user['thank_count'] = empty($out[1]) ? 0 : $out[1];

    //提问数量
    preg_match('#提问\s<span class="num">(.*?)</span>#', $result, $out);
    $user['ask_count'] = empty($out[1]) ? 0 : $out[1];

    //回答数量
    preg_match('#回答\s<span class="num">(.*?)</span>#', $result, $out);
    $user['answer_count'] = empty($out[1]) ? 0 : $out[1];

    //文章数量
    preg_match('#文章\s<span class="num">(.*?)</span>#', $result, $out);
    $user['article_count'] = empty($out[1]) ? 0 : $out[1];

    //主页面访问数量
    preg_match('#个人主页被 <strong>(.*?)</strong> 人浏览#', $result, $out);
    $user['pv_count'] = empty($out[1]) ? 0 : intval($out[1]);

    //收藏数量
    preg_match('#收藏\s<span class="num">(.*?)</span>#', $result, $out);
    $user['started_count'] = empty($out[1]) ? 0 : $out[1];

    //公共编辑数量
    preg_match('#公共编辑\s<span class="num">(.*?)</span>#', $result, $out);
    $user['public_edit_count'] = empty($out[1]) ? 0 : $out[1];
    $user['duplicate_count'] = 1;
    return $user;
}

/**
 * [getImg 处理防盗链图片]
 * @param  [type] $url  [description]
 * @param  [type] $u_id [description]
 * @return [type]       [description]
 */
function getImg($url, $u_id)
{
    if (file_exists('./images/' . $u_id . ".jpg")) {
        return "images/$u_id" . '.jpg';
    }
    if (empty($url)) {
        return '';
    }
    $context_options = array(
        'http' =>
            array(
                'header' => "Referer:https://www.zhihu.com",
            ));

    $context = stream_context_create($context_options);
    $img = file_get_contents($url, FALSE, $context);
    file_put_contents('./images/' . $u_id . ".jpg", $img);
    return "images/$u_id" . '.jpg';
}

//=====================================

/**
 * [request 执行一次curl请求]
 * @param  [string] $method     [请求方法]
 * @param  [string] $url        [请求的URL]
 * @param  array  $fields     [执行POST请求时的数据]
 * @return [stirng]             [请求结果]
 */
function request($method, $url, $fields = array())
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_COOKIE, genCookie());
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.130 Safari/537.36');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    if ($method === 'POST')
    {
        curl_setopt($ch, CURLOPT_POST, true );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }
    $result = curl_exec($ch);
    return $result;
}
