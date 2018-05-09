<?php
/**
 * daemon,中文含义为守护神或精灵的意思。其实它还有个意思：守护进程。
 *
 * 守护进程简单的说就是可以脱离终端在后台运行的进程，这在Linux中是非常常见的一种进程，比如Apache或者MySQL等服务启动后，就会以守护进程的方式常驻在内存中。
 *
 * 以PHP为例，假如我有个耗时间的任务需要跑在后台：将所有MySQL中user表中的2000万用户全部导入到Redis中做预热缓存，那么这个任务估计一时半会是不会结束的，这个时候我们需要编写一个php脚本以daemon形式运行在系统中，结束后自动推出。
 *
 * 在Linux中大概有三种形式实现脚本后台化：
 *
 * 1.在命令后添加1个&符号，比如php task.php &。这个方法的缺点在于如果terminal终端关闭，无论是正常关闭还是非正常关闭，这个php进程都会随着终端关闭而关闭，其次是代码中如果有echo或者print_r之类的输出文本，会被输出到当前的终端窗口中。
 *
 * 2.使用nohup命令 , 比如 nohup php task.php & . 默认情况下 , 代码中echo或者print_r之类输出的文本会被输出到php代码同级目录的nohup.out文件中 . 如果你用exit命令或者关闭按钮等正常手段关闭终端 , 该进程不会被关闭 , 依然会在后台持续运行 . 但是如果终端遇到异常退出或者终止 , 该php进程也会随即退出 . 本质上 , 也并非稳定可靠的daemon方案 .
 *
 * 3.使用fork和setsid , 我暂且称之为 : *nix解决方案 . 具体看下代码 :
 */

// 一次fork
$pid = pcntl_fork();
if ($pid < 0) {
    exit('fork error.');
} else if ($pid > 0) {
    exit('parent process.');
}

// 将当前子进程提升会话组组长 这是至关重要的一步
if (!posix_setsid()) {
    exit('setsid error.');
}

// 二次fork
$pid = pcntl_fork();
if ($pid < 0) {
    exit('fork error.');
} else if ($pid > 0) {
    exit('parent process.');
}

// 真正的逻辑代码 下面仅仅写个循环做为示例
for ($i = 1; $i <= 100; $i++) {
    sleep(1);
    file_put_contents('daemon.log', $i, FILE_APPEND);
}