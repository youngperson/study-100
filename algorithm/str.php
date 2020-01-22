<?php
/**
 * 请实现一个函数，将一个字符串中的每个空格替换成“%20”。
 * 例如，当字符串为We Are Happy.则经过替换之后的字符串为We%20Are%20Happy。
 */
function replaceSpace($str)
{
    /*
    思路
        字符串由一个个的字符组成，我们需要遍历字符串中的每个字符
        找到空格后往里面填充%20，相当于是1个字符用3个字符去填充(多需要2个空间)
        hello world   =>    hello%20world
        0 1 2 3 4 5   6 7
        0 1 2 3 4 567 8 9

    */
    $index = 0;
    $newstr = "";
    $length = strlen($str);
    for ($i = 0; $i < $length; $i++) {
        $one = $str[$i];
        $newstr[$index] = $one;
        if ($one == " ") {
            $newstr[$index] = "%";
            $newstr[$index + 1] = "2";
            $newstr[$index + 2] = "0";
            $index += 3;    // 下一个从这个下标开始
        } else {
            $index++;
        }
    }
    return $newstr;
}
echo replaceSpace("hello world");
