<?php
// 用两个栈来实现一个队列，完成队列的Push和Pop操作。 队列中的元素为int类型。
/*
    思路
    栈：先进后出，一端操作     1,2,3 =>  3,2,1
    队列：先进先出，两端操作   1,2,3 => 1,2,3
    2个栈捣腾一下实现队列的效果
*/
$stack = [];
function mypush($node)
{
    global $stack;
    $stack[] = $node;
}
function mypop()
{
    global $stack;
    // 队列是先进的先出(把栈中第一个元素弹出去)
    $newstack = [];
    $length = count($stack) - 1;
    for ($i=1;$i<=$length;$i++) {
        $newstack[] = $stack[$i];
    }
    $popvalue = $stack[0];
    $stack = $newstack;
    return $popvalue;
}