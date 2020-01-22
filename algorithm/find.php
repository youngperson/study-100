<?php
/*
 * 在一个二维数组中（每个一维数组的长度相同），每一行都按照从左到右递增的顺序排序，
 * 每一列都按照从上到下递增的顺序排序。请完成一个函数，
 * 输入这样的一个二维数组和一个整数，判断数组中是否含有该整数。
 */
function Find($target, $array)
{
    // 0 1 2   3
    // 4 5 6   7
    // 8 9 10 11
    /*
    思路
        利用二维数组是由上到下，由左到右递增的规律
        选右上角或者左下角的元素a[row][col]与target进行比较
        为什么?这2个对角线的点往不同的方向走增减的趋势不同，其它的点趋势相同(排除法)
        一维有序数组点选择中间的，二维有序数组点选两头的。
        以右上角的点为例：
            当target小于元素a[row][col]时，那么target必定在元素a所在行的左边，即col--
            当target大于元素a[row][col]是，那么target必定在元素a所在列的下边，即row++
    */

    // 一个while遍历：从对比的点开始，然后控制下标移动。这里从右上角的点开始
    $row = 0;
    $col = count($array[0]) - 1;
    $rownum = count($array) - 1;
    while(($row <= $rownum) && ($col >= 0)) {
        echo($row . "、" . $col . "=>" . $array[$row][$col] . "\r\n");
        if($target == $array[$row][$col]) {
            return true;
        } else if($target > $array[$row][$col]) {
            $row++;
        } else {
            $col--;
        }
    }
    return false;

    // 从左下角的点开始
    /*
    $row = count($array) - 1;
    $col = 0;
    $colnum = count($array[0]) - 1;
    while (($row >= 0) && ($col <= $colnum)) {
        echo($row . "、" . $col . "=>" . $array[$row][$col] . "\r\n");
        if ($target == $array[$row][$col]) {
            return true;
        } else if ($target > $array[$row][$col]) {
            $col++;
        } else {
            $row--;
        }
    }
    return false;
    */
}
$rtn = Find(7, [[1,2,8,9],[2,4,9,12],[4,7,10,13],[6,8,11,15]]);
var_dump($rtn);
