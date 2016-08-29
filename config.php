<?php
function genCookie() {
    //网页登录知乎后,谷歌浏览器->检查->Resources->Cookies
    $cookie_arr = array(
        '__utma' => '51854390.97457187.1471250812.1471250812.1471250812.1',
        '__utmb' => '51854390.2.10.1471250812',
        '__utmc' => '51854390',
        '__utmv' => '51854390.100-1|2=registration_date=20160701=1^3=entry_date=20160701=1',
        '__utmz' => '51854390.1471250812.1.1.utmcsr=zhihu.com|utmccn=(referral)|utmcmd=referral|utmcct=/',
        '_xsrf' => '6e332049911c5e4e18cee124535d5dd4',
        '_za' => 'b9599061-3f7a-4cd7-bae3-f4bda4a37d7d',
        '_zap' => 'e13f2900-92ff-433d-914c-bf9a5e3cbc9e',
        'a_t' => '"2.0AGBA80MEKQoXAAAAew7ZVwBgQPNDBCkKACAAtAJsYwoXAAAAYQJVTXYO2VcAGnNe404B5n79tW9-uKTyt61zirXjvtmuRVOqTl0KdFPVoMga9hvhuQ=="',
        'cap_id' => '"MDE5Yjc3N2Y3YWRkNDMwNTk0MWI0NzM4NzFmN2JkZjU=|1471250782|c1a8e6ce27415e006db82c8cac6cfd103429f11d"',
        'd_c0' => '"ACAAtAJsYwqPTl31eWdBbQYlHU8L9T-RWtA=|1471250783"',
        'l_cap_id' => '"NzdhOTk5MTM2ZTg4NDVjMTg1N2U5OTc1MjMzMzUwZjA=|1471250782|efad76e634c868df98fa6a1a36fa82344b0d1005"',
        'l_n_c' => '1',
        'login' => '"YWIwZDFlMjQwODY4NGE1YWI2Yzk4ODZhNDZmOGQwZjk=|1471250806|bb77ea9a2cb71eb4519509f29fd949ea5c2a0908"',
        'q_c1' => 'c58f5d70b8994f08aef0fe1fa55e355a|1471250782000|1471250782000',
        'z_c0' => 'Mi4wQUdCQTgwTUVLUW9BSUFDMEFteGpDaGNBQUFCaEFsVk5kZzdaVndBYWMxN2pUZ0htZnYyMWIzNjRwUEszclhPS3RR|1471250811|d33765814a75b0db93e7ed5b76a1a5742319e686',
    );

    $cookie = '';
    foreach ($cookie_arr as $key => $value) {
        if($key != 'z_c0')
            $cookie .= $key . '=' . $value . ';';
        else
            $cookie .= $key . '=' . $value;
    }

    return $cookie;
}