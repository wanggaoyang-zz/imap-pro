<?php
namespace Imap\Pro\Library;

use Exception;

class ImapClient
{
    protected string $mailLink;
    protected string $username;
    protected string $password;

    public function __construct(string $mailLink,string $username, string $password)
    {
        $this->mailLink = $mailLink;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @return resource|null
     * @throws \Exception
     */
    public function create()
    {
        try {
            #初始化链接资源
            $imap = imap_open($this->mailLink, $this->username, $this->password);
            if ($imap === false) {
                throw new Exception('错误:' . imap_last_error());
            }
            return $imap;
        } catch (\Throwable $exception) {
            throw new Exception($exception->getMessage());
        }
    }


    /**
     * get the total count of the current mailbox
     * @return int
     */
    public function mailTotalCount($imap)
    {
        $check = imap_check($imap);
        return $check->Nmsgs;
    }

    /**
     * 获取邮件正文内容
     *
     * @param string $msgCount
     * @return string
     */
    public function getBody($imap, $msgCount)
    {
        $body = $this->getPart($imap, $msgCount, "TEXT/HTML");
        if ($body === '') {
            $body = $this->getPart($imap, $msgCount, "TEXT/PLAIN");
        }
        return $body;
    }


    private function getPart($imap, string $msgCount, string $mimeType, bool $structure = false, $partNumber = false)
    {
        // 如果未提供结构参数，则获取消息的结构
        $structure = $structure ?: imap_fetchstructure($imap, $msgCount);

        if ($structure) {
            // 如果 MIME 类型匹配，则获取指定部分的消息正文
            if ($mimeType == $this->getMimeType($structure)) {
                $partNumber = $partNumber ?: "1"; // 如果未提供部分编号，则默认为1
                $fromEncoding = $structure->parameters[0]->value;
                $text = imap_fetchbody($imap, $msgCount, $partNumber);

                // 根据编码类型进行解码
                if ($structure->encoding == 3) {
                    $text = imap_base64($text);
                } else if ($structure->encoding == 4) {
                    $text = imap_qprint($text);
                }
                // 转换编码为 UTF-8
                return mb_convert_encoding($text, 'utf-8', $fromEncoding);
            }

            // 如果是多部分消息，则递归地查找指定部分
            if ($structure->type == 1) {
                foreach ($structure->parts as $index => $subStructure) {
                    $prefix = $partNumber ? $partNumber . '.' : '';
                    $data = $this->getPart($imap, $msgCount, $mimeType, $subStructure, $prefix . ($index + 1));
                    if ($data) {
                        return $data;
                    }
                }
            }
        }

        return false;
    }

    /**
     * get the subtype and type of the message structure
     *
     * @param object $structure
     */
    private function getMimeType($structure)
    {
        $mimeType = array("TEXT", "MULTIPART", "MESSAGE", "APPLICATION", "AUDIO", "IMAGE", "VIDEO", "OTHER");
        if ($structure->subtype) {
            return $mimeType[(int)$structure->type] . '/' . $structure->subtype;
        }
        return "TEXT/PLAIN";
    }
    /**
     * get attach of the message
     *
     * @param string $msgCount
     * @param string $path
     * @return array
     */
    public function getDownAttach($imap, $msgCount, $path):array
    {
        $struckture = imap_fetchstructure($imap, $msgCount);
        $attach = array();
        if (isset($struckture->parts)) {
            foreach ($struckture->parts as $key => $value) {
                $encoding = $struckture->parts[$key]->encoding;
                if ($struckture->parts[$key]->ifdparameters) {
                    $name = $struckture->parts[$key]->dparameters[0]->value;
                    $name = $this->decodeName($name);
                    $message = imap_fetchbody($imap, $msgCount, $key + 1);
                    if ($encoding == 0) {
                        $message = imap_8bit($message);
                    } else if ($encoding == 1) {
                        $message = imap_8bit($message);
                    } else if ($encoding == 2) {
                        $message = imap_binary($message);
                    } else if ($encoding == 3) {
                        $message = imap_base64($message);
                    } else if ($encoding == 4) {
                        $message = quoted_printable_decode($message);
                    }
                    $this->downAttach($path, $name, $message);
                    $attach[] = $path . $name;
                }
            }
        }
        return $attach;
    }
    function getAttach($imap, $mid, $path)
    {
        // Get Attached File from Mail
        if (!$imap) {
            return false;
        }
        $struckture = imap_fetchstructure($imap, $mid);
        $attach = [];

        if (isset($struckture->parts)) {
            foreach ($struckture->parts as $part) {
                if ($part->subtype == 'OCTET-STREAM') {
                    $name = base64_decode($part->parameters[1]->value);
                    $name = iconv("gbk", 'utf-8', $name);
                    $name = substr($name, 7);
                    $message = imap_fetchbody($imap, $mid, $part->part_number);

                    switch ($part->encoding) {
                        case 1:
                        case 0:
                            $message = imap_8bit($message);
                            break;
                        case 2:
                            $message = imap_binary($message);
                            break;
                        case 3:
                            $message = imap_base64($message);
                            break;
                        case 4:
                            $message = quoted_printable_decode($message);
                            break;
                    }

                    // 文件名转换
                    $filename = uniqid() . '.' . pathinfo($name, PATHINFO_EXTENSION);
                    $filepath = $path . $filename;
                    file_put_contents($filepath, $message);
                    $attach[] = $filepath;
                }
            }
        }

        return $attach;
    }


    /**
     * download the attach of the mail to localhost
     *
     * @param string $filePath
     * @param string $message
     * @param string $name
     */
    public function downAttach(string $filePath, string $name, string $message)
    {
        if (!is_dir($filePath)) {
            mkdir($filePath);
        }
        file_put_contents($filePath . $name, $message);
    }


    /**
     * 附件名称编码
     * @param string $name
     * @return string
     */
    public function decodeName(string $name)
    {
        $name = explode('?UTF-8?Q?', $name);
        $newName = '';
        foreach ($name as $value) {
            $pos = strpos($value, '?=');
            $value = substr($value, 0, $pos);
            $newName .= quoted_printable_decode($value);
        }
        $extension = pathinfo($newName, PATHINFO_EXTENSION);
        return md5($newName) . '.' . ($extension ?: 'pdf');
    }

    /**
     * 解码中文主题
     *
     * @param string $subject
     * @return string
     */
    public function subjectDecode(string $subject): string
    {
        $beginStr = substr($subject, 0, 5);
        $separator = ($beginStr === '=?ISO') ? '=?ISO-2022-JP' : '=?GBK';
        $toEncoding = ($beginStr === '=?ISO') ? 'ISO-2022-JP' : 'GBK';

        $encode = strstr($subject, $separator);
        if (!$encode) {
            return $subject;
        }

        $explodeArr = explode($separator, $subject);
        $subjectArr = [];

        foreach ($explodeArr as $key => $value) {
            if ($key % 2 === 0) {
                $subjectArr[] = [$value, $explodeArr[$key + 1] ?? ''];
            }
        }

        $subSubjectArr = [];

        foreach ($subjectArr as $arr) {
            $subSubject = implode($separator, $arr);
            if (count($arr) === 1) {
                $subSubject = $separator . $subSubject;
            }

            $begin = strpos($subSubject, "=?");
            $end = strpos($subSubject, "?=");

            if ($end > 0) {
                $beginStr = ($begin > 0) ? substr($subSubject, 0, $begin) : '';
                $endStr = ((strlen($subSubject) - $end) > 2) ? substr($subSubject, $end + 2, -2) : '';
                $str = substr($subSubject, 0, $end - strlen($subSubject));
                $pos = strrpos($str, "?");
                $str = substr($str, $pos + 1);
                $subSubject = $beginStr . imap_base64($str) . $endStr;
                $subSubjectArr[] = iconv($toEncoding, 'utf-8', $subSubject);
            }
        }

        return implode('', $subSubjectArr);
    }

}