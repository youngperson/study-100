<?php
/**
 *  生成Redis的配置文件
 *  三台机器、每台机器下3个Redis节点，三台构成Redis集群
 *  机器说明(内存4g、64位、硬盘300G)
 */
$redisConfList = [
    ['ip' => '10.111.11.139', 'ports' => [7000, 7001, 7002], 'maxmemory' => '1gb', 'auto-aof-rewrite-percentage' => mt_rand(80, 100)],
    ['ip' => '10.111.11.140', 'ports' => [7000, 7001, 7002], 'maxmemory' => '1gb', 'auto-aof-rewrite-percentage' => mt_rand(80, 100)],
    ['ip' => '10.111.11.141', 'ports' => [7000, 7001, 7002], 'maxmemory' => '1gb', 'auto-aof-rewrite-percentage' => mt_rand(80, 100)]
];

$file = "redis_%s:%s.conf";
foreach ($redisConfList as $redisConf) {
    $ip        = $redisConf['ip'];
    $maxmemory = $redisConf['maxmemory'];
    $autoaofrewritepercentage = $redisConf['auto-aof-rewrite-percentage'];
    $ports     = $redisConf['ports'];
    foreach ($ports as $port) {
        $str = $redisConfTemplate = redisConfTemplate($ip, $port, $maxmemory, $autoaofrewritepercentage);
        file_put_contents(sprintf($file, $ip, $port), $str, FILE_APPEND);
    }
}


function redisConfTemplate($ip, $port, $maxmemory, $autoaofrewritepercentage) {
    $str = "#GENERAL
daemonize yes
port {$port}
bind {$ip}
loglevel notice
logfile /var/log/redis/redis_{$port}.log
pidfile /var/run/redis_{$port}.pid
tcp-backlog 511
timeout 20
tcp-keepalive 0
loglevel notice
maxmemory {$maxmemory}
maxclients 100000
maxmemory-policy volatile-ttl
databases 16
dir ./
slave-serve-stale-data yes
#slave只读
slave-read-only yes
#not use default
repl-disable-tcp-nodelay yes
slave-priority 100
#打开aof持久化
appendonly yes
appendfilename \"appendonly_{$port}.aof\"
#每秒一次aof写
appendfsync everysec
#关闭在aof rewrite的时候对新的写操作进行fsync
no-appendfsync-on-rewrite yes
auto-aof-rewrite-min-size 64mb
lua-time-limit 5000
stop-writes-on-bgsave-error yes
rdbcompression yes
rdbchecksum yes
dbfilename dump_{$port}.rdb
repl-diskless-sync no
repl-diskless-sync-delay 5
repl-disable-tcp-nodelay no
#打开redis集群
cluster-enabled yes
cluster-config-file nodes_{$port}.conf
#节点互连超时的阀值
cluster-node-timeout 15000
#一个主节点在拥有多少个好的从节点的时候就要割让一个从节点出来给其他没有从节点的主节点
cluster-migration-barrier 1
#如果某一些key space没有被集群中任何节点覆盖，最常见的就是一个node挂掉，集群将停止接受写入
cluster-require-full-coverage no
auto-aof-rewrite-percentage {$autoaofrewritepercentage}
slowlog-log-slower-than 10000
slowlog-max-len 128
latency-monitor-threshold 0
notify-keyspace-events \"\"
hash-max-ziplist-entries 512
hash-max-ziplist-value 64
list-max-ziplist-size -2
list-compress-depth 0
list-max-ziplist-entries 512
list-max-ziplist-value 64
set-max-intset-entries 512
zset-max-ziplist-entries 128
zset-max-ziplist-value 64
hll-sparse-max-bytes 3000
activerehashing yes
client-output-buffer-limit normal 0 0 0
client-output-buffer-limit slave 256mb 64mb 60
client-output-buffer-limit pubsub 32mb 8mb 60
hz 10
aof-rewrite-incremental-fsync yes";
    return $str;
}