<?php


namespace Imap\Pro\Library;


class Charset
{
    /**
     * 将非GBK字符集的编码转为GBK
     *
     * @param mixed $mixed 源数据
     *
     * @return mixed GBK格式数据
     */
    function charsetToGBK($mixed)
    {
        if (is_array($mixed)) {
            foreach ($mixed as $k => $v) {
                if (is_array($v)) {
                    $mixed[$k] = $this->charsetToGBK($v);
                } else {
                    $encode = mb_detect_encoding($v, array('ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5'));
                    if ($encode == 'UTF-8') {
                        $mixed[$k] = iconv('UTF-8', 'GBK', $v);
                    }
                }
            }
        } else {
            $encode = mb_detect_encoding($mixed, array('ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5'));
            //var_dump($encode);
            if ($encode == 'UTF-8') {
                $mixed = iconv('UTF-8', 'GBK', $mixed);
            }
        }
        return $mixed;
    }


    /**
     * 将非UTF-8字符集的编码转为UTF-8
     *
     * @param mixed $mixed 源数据
     *
     * @return mixed utf-8格式数据
     */
    function charsetToUTF8($mixed)
    {
        if (is_array($mixed)) {
            foreach ($mixed as $k => $v) {
                if (is_array($v)) {
                    $mixed[$k] = $this->charsetToUTF8($v);
                } else {
                    $encode = mb_detect_encoding($v, array('ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5'));
                    if ($encode == 'EUC-CN') {
                        $mixed[$k] = iconv('GBK', 'UTF-8', $v);
                    }
                }
            }
        } else {
            $encode = mb_detect_encoding($mixed, array('ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5'));
            if ($encode == 'EUC-CN') {
                $mixed = iconv('GBK', 'UTF-8', $mixed);
            }
        }
        return $mixed;

    }
}