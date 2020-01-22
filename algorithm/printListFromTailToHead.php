<?php
/**
 * 输入一个链表，按链表从尾到头的顺序返回一个ArrayList。
 */

/*class ListNode{
    var $val;
    var $next = NULL;
    function __construct($x){
        $this->val = $x;
    }
}*/
function printListFromTailToHead($head)
{
    /*
        思路
        遍历链表中的每个节点，从头节点开始直到null。null表示到链表的结尾
        每个不为null的节点都会有值和指向下个位置的指针值
    */
    $list = [];
    while($head!=NULL) {
        $list[] = $head->val;
        $head = $head->next;
    }

    // 把数组反转
    $newlist = [];
    $length = count($list) - 1;
    for ($i=0;$i<=$length;$i++) {
        $newlist[$i] = $list[$length-$i];
    }
    return $newlist;
}