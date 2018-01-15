<?php
/**
 * php命令行执行该分析的方法 php analysis.php(写绝对路径)
 * Created by PhpStorm.
 * User: wanrenliang
 * Date: 2018/1/10
 * Time: 上午2:59
 */
include('./common.php');
class cliServer extends commonServer {

    protected $iid;
    protected $url;
    protected $root;
    protected $project;
    protected $branch;
    protected $username;
    protected $targetBranch;

    protected $diffs = [];
    protected $files = [];
    protected $filesStr = [];
    protected $modify = [];

    protected $error = [];
    protected $errorLevel = 0;

    public function run() {
        if (pcntl_fork() != 0) {
            exit;
        }
        $this->restart();

        do {
            //从队列中消费ID
            $iid = $this->next();
            if (!$iid) {
                sleep(1);
                continue;
            }

            try {
                $this->setParams($iid);
                $this->fetch();
                $this->getModifyFiles();
                $this->getDiffs();
                $this->analysisDiffs();
                $this->check();
                $this->sendNotify();
            } catch (\Exception $e) {
                continue;
            }
            rand(1,10) > 1 or $this->restart();
        } while (true);
    }

    //杀掉之前的进程
    protected function restart() {
        if (pcntl_fork() != 0) {
            exit;
        }

        $pidFile = self::CHECK_CODE_PID;
        if (is_file($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            is_dir("/proc/{$pid}") && shell_exec("kill {$pid}");
        }

        $pid = getmypid();
        file_put_contents($pidFile, $pid);
    }

    /**
     * 设置核心数据
     * @param $iid
     * @throws \Exception
     */
    public function setParams($iid) {
        //获取到收集时候存储的数据
        $setParams = $this->get($iid);
        if (!$setParams) {
            throw new \Exception("iid:{$iid} Request for more than 30 days, no data");
        }
        $params = json_decode($setParams, true);
        $this->iid = $iid;
        foreach ($params as $attr => $value) {
            //把当时设置的数据的每个字段,按照key => val初始化成变量
            $this->$attr = $value;
        }
        file_put_contents(self::DEBUG_LOG, "本次操的合并请求ID是{$iid},取出的数据是{$setParams}\n", 8);

        $this->diffs = [];
        $this->files = [];
        $this->filesStr = [];
        $this->modify = [];
        $this->error = [];
    }

    /**
     * 拉代码,检测待检测分支是否存在
     * @throws \Exception
     */
    public function fetch() {
        //1.需要提前在钩子代码的平级目录先clone一份下来，因为防止chdir的时候找不到目录
        //2.或者是这个方法中发现没有，先克隆下来
        //项目的代码可以在任何的服务器上,钩子代码在钩子服务器上会去拉取待检测项目代码
        $path = $this->root . '/../' . $this->project;
        chdir($path);
        file_put_contents(self::DEBUG_LOG, "代码拉取到的目录路径{$path}\n", 8);
        //从远端拉取代码
        shell_exec('git fetch origin -p > /dev/null 2>&1');

        //判断待检测的分支是否存在
        $existsBranch = shell_exec('git branch -r | grep ' . $this->branch);
        if (is_null($existsBranch)) {
            throw new \Exception($this->branch . ' branch is not found');
        }
    }

    /**
     * 获取被修改过的文件名字(这里只检测.PHP文件)
     * @return array
     */
    public function getModifyFiles() {
        shell_exec("git checkout origin/{$this->branch} > /dev/null 2>&1");
        $diffCommand = "git log origin/{$this->targetBranch}..origin/{$this->branch} --raw";
        $diffCommand .= " | grep .php | grep -v D | awk '{print $6}' | sort | uniq";
        $files = explode("\n", shell_exec($diffCommand));
        file_put_contents(self::DEBUG_LOG, "执行的命令{$diffCommand}\n", 8);
        foreach ($files as $file) {
            if (!$file) {
                continue;
            }
            if (is_file($file)) {
                $this->files[] = $file;
            }
        }
        return $this->files;
    }

    /**
     * 从修改过的文件中找出被修改的内容
     * 得出每一行的形为来
     * @return array
     */
    public function getDiffs() {
        $diffs = [];
        foreach ($this->files as $file) {
            $fileDiff = shell_exec("git diff origin/{$this->targetBranch} {$file}");
            file_put_contents(self::DEBUG_LOG, "文件{$file}被修改过的内容{$fileDiff}\n", 8);
            $fileDiffs = explode('@@ -', $fileDiff);
            unset($fileDiffs[0]);
            foreach ($fileDiffs as $diff) {
                $diffs[$file][] = explode("\n", $diff);
            }
        }
        return $this->diffs = $diffs;
    }

    /**
     * 从被修改过的内容分析
     * @return array
     */
    public function analysisDiffs() {
        $modify = [];
        $format = json_encode($this->diffs);
        file_put_contents(self::DEBUG_LOG, "当次合并请求全部被修改过的内容{$format}\n", 8);
        foreach ($this->diffs as $file => $diffs) {
            foreach ($diffs as $diff) {
                preg_match('/\+(\d+),\d+ @@(.*)/', array_shift($diff), $matches);
                $startLine = $matches[2] ? $matches[1] - 1 : $matches[1];
                foreach ($diff as $row) {
                    if (!$row) {
                        $startLine ++;
                        continue;
                    }

                    if ($row[0] == '-') {
                        continue;
                    }

                    $startLine ++;
                    if ($row[0] == '+') {
                        $modify[$file][$startLine] = 1;
                    }
                }
            }
        }
        return $this->modify = $modify;
    }

    /**
     * 1、php -l 检测代码有无语法错误
     * 2、token_get_all 检测代码中有无调试代码
     * 3、phpcs检测代码的质量问题
     * @throws Exception
     */
    public function check() {
        foreach ($this->files as $file) {
            $syntaxOutput = $this->checkSyntax($file);
            if (strpos($syntaxOutput, 'No syntax errors') !== 0) {
                $this->error['syntax'][$file] = $syntaxOutput;
                continue;
            }

            list($this->filesStr[$file], $tokens) = $this->getFileStr($file);
            $this->eachToken($file, $tokens);

            $this->checkStyle($file);
        }
    }

    /**
     * 检测语法
     * @param $file
     * @return bool
     */
    public function checkSyntax($file) {
        return shell_exec("php -l {$file}");
    }

    /**
     * 读取文件内容
     * @param $file
     * @return array
     * @throws \Exception
     */
    protected function getFileStr($file) {
        $arrStr = ["\n"];
        $line = 0;
        $content = '';
        $fRes = fopen($file, 'r');
        while (!feof($fRes)) {
            $line ++;
            if ($line > 10000) {
                throw new \Exception('File too large to skip detection');
            }
            $row = fgets($fRes);
            $arrStr[] = trim($row);
            $content .= $row;
        }
        return [$arrStr, $this->getToken($content)];
    }

    /**
     * 获取解析器代号
     * https://php.golaravel.com/tokens.html
     * token_get_all() 解析提供的 source 源码字符，然后使用 Zend 引擎的语法分析器获取源码中的 PHP 语言的解析器代号
     * @param $fileStr
     * @return array
     */
    public function getToken($fileStr) {
        $rtn = [];
        $slice = null;
        $tokens = token_get_all($fileStr);
        foreach ($tokens as $index => $token) {
            if (is_array($token)) {
                $slice = $index;
                $rtn[$slice]['token'] = $token[0];
                $rtn[$slice]['code'] = $token[1];
                $rtn[$slice]['num'] = $token[2];
            } else {
                $rtn[$slice]['code'] .= $token;
            }
        }
        return $rtn;
    }

    /**
     * 遍历token
     * @param $file
     * @param $tokens
     */
    protected function eachToken($file, $tokens) {
        foreach ($tokens as $token) {
            if (!isset($this->modify[$file][$token['num']])) {
                continue;
            }
            if ($this->checkOutput($token)) {
                $this->error['output'][$file][$token['num']] = $this->filesStr[$file][$token['num']];
            } else if ($this->checkExit($token)) {
                $this->error['exit'][$file][$token['num']] = $this->filesStr[$file][$token['num']];
            }
        }
    }

    /**
     * 检测代码中有无输出、调试
     * @param $token
     * @return bool
     */
    public function checkOutput($token) {
        switch ($token['token']) {
            case T_PRINT:
            case T_ECHO:
                return true;
            case T_STRING:
                if (stripos($token['code'], 'var_dump') !== false) {
                    return true;
                } else if (stripos($token['code'], 'print_r') !== false) {
                    return true;
                }
        }
    }

    /**
     * 检测代码中有无断点
     * @param $token
     * @return bool
     */
    public function checkExit($token) {
        return $token['token'] == T_EXIT;
    }

    /**
     * phpcs检测出错误的类型,目标机器上需要安装phpcs
     * @param $file
     */
    public function checkStyle($file) {
        $errors = [];

        $json = json_decode(shell_exec("phpcs --standard={$this->root}/ruleset.xml {$file}"), true);
        $messages = $json['files'][getcwd() . '/' . $file]['messages'];
        foreach ($messages as $message) {
            $errors[$message['line'].'/'.$message['column']] = $message;
        }

        foreach ($errors as $key => $error) {
            if (isset($this->modify[$file][$error['line']])) {
                $this->error['style'][$file][$key] = $error['message'];
            }
        }
        $redis = $this->getLogStashRedis();
        $redis->hSet(static::CHECK_STYLE_ERROR, $file, json_encode($errors));
    }

    /**
     * 发出警告消息
     */
    public function sendNotify() {
        $eMsg = '';
        $eTypes = [];

        $msg = "Merge_Request 检测\n";
        $msg .= "User: {$this->username}\n";
        $msg .= "Branch: {$this->branch}\n";
        $msg .= "TargetBranch: {$this->targetBranch}\n";
        $msg .= "Diffs: {$this->url}/diffs\n";

        $errorLevel = 0;
        if ($this->error) {
            foreach ($this->error as $type => $err) {
                switch ($type) {
                    case 'syntax':
                        $eTypes[] = "语法错误";
                        $eMsg .= "以下文件有语法错误:\n";
                        foreach ($err as $file => $eItem) {
                            $eMsg .= "File:{$file} Error:{$eItem}";
                        }
                        if ($errorLevel < 4) {
                            $errorLevel = 4;
                        }
                        break;
                    case 'output':
                        $eTypes[] = "输出";
                        $eMsg .= "以下文件有输出:\n";
                        $eMsg .= $this->formatError($err);
                        if ($errorLevel < 2) {
                            $errorLevel = 2;
                        }
                        break;
                    case 'exit':
                        $eTypes[] = "断点";
                        $eMsg .= "以下文件有断点:\n";
                        $eMsg .= $this->formatError($err);
                        if ($errorLevel < 3) {
                            $errorLevel = 3;
                        }
                        break;
                    case 'style':
                        $eTypes[] = "规范不符";
                        $eMsg .= "以下文件不符合现有编码规范:\n";
                        $eMsg .= $this->formatError($err, 'Line/Column', 'Msg');
                        if ($errorLevel < 1) {
                            $errorLevel = 1;
                        }
                        break;
                }
            }

            $eTypes = implode(',', $eTypes);
            $errorLevels = [null, 'D', 'C', 'B', 'A'];
            $errorLevelNotes = [null, '规范不符 - 给予合并', '存在输出 - 谨慎合并', '存在断点 - 谨慎合并', '不予合并 - 严禁发布'];
            $msg .= "错误详情: " .  self::ERROR_DETAIL_URL . "?iid={$this->iid}\n";
            $msg .= "重新检测: " .  self::CHECK_AGAIN_URL . "?iid={$this->iid}\n";  //对应同一个合并请求,后来又提交了代码到这个没有关闭的合并请求中,可以选择重新检测
            $msg .= "存在问题: {$eTypes}\n";
            $msg .= "错误等级: {$errorLevels[$errorLevel]}\n";
            $msg .= "检测结果: {$errorLevelNotes[$errorLevel]}";

            //把检测结果保存到Redis中去
            $redis = $this->getLogStashRedis();
            $redis->set(sprintf(self::CHECK_CODE_MSG, $this->iid), $eMsg, self::EXPIRE_TIME);
        } else {
            $msg .= "检测结果：通过 - 给予合并\n";
        }

        //有信息就发出去
        if ($msg) {
            $this->sendWarning($msg);
        }
    }


    /**
     * 格式化错误
     * @param $err
     * @param string $beforeLabel
     * @param string $afterLabel
     * @return string
     */
    protected function formatError($err, $beforeLabel = 'Line', $afterLabel = 'Code') {
        $msg = '';
        foreach ($err as $file => $lines) {
            $msg .= "File:{$file}:\n";
            foreach ($lines as $line => $code) {
                list($row) = explode('/', $line);
                $fileRow = $this->filesStr[$file][$row];
                $msg .= "{$beforeLabel}:{$line} {$afterLabel}:{$code} Code:{$fileRow}\n";
            }
        }
        return $msg;
    }
}

$cli = new cliServer();
$cli->run();