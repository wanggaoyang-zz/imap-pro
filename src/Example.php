<?php

namespace Imap\Pro;

use Imap\Pro\Library\Charset;
use Imap\Pro\Library\ImapClient;
use Throwable;

class Example
{
    const ATTACH_FILE_PATH = './runtime/attach/';

    const HTML_FILE_PATH = './runtime/html/';
    /**
     * @param string $username
     * @param string $password
     * @param int $day
     * @param string $mailServer
     * @param int $port
     * @return string
     */

    public function pull(string $username, string $password, int $day, string $mailServer = 'imap.qq.com', int $port = 143)
    {
        try {
            $mailLink = "{{$mailServer}:$port}INBOX"; // 143 is the port when not SSL
            // 连接服务
            $imapServer = new ImapClient($mailLink, $username, $password);
            $mail = $imapServer->create();
            $date = date("d M Y", strtotime("$day days")); // 包含前一天的邮件
            $result = imap_search($mail, "SINCE \"$date\"");
            var_dump($result);
            // 数据处理
            if (!$result) {
                imap_close($mail);
                return 'fail';
            }
            $charset = new Charset();
            foreach ($result as $i) {
                $header = imap_headerinfo($mail, $i); // 邮件头信息
                // 是否有效头ID
                if (!isset($header->message_id)) {
                    continue;
                }
                $host = ($header->from)[0]->host; // 发件账户host地址
                $message_id = $header->message_id;
                if (strpos(strtolower($header->subject), 'utf-8')) {
                    $subject = base64_decode(substr($header->subject, 10)); // 主题
                } else {
                    $subject = $imapServer->subjectDecode($header->subject);
                }
                try {
                    // 根据渠道类型区分处理 1 Boss 处理邮件附件；其他处理邮件内容
                    if (strpos($host, 'zhipin.com') !== false) {
                        // 下载附件
                        $attach = $imapServer->getDownAttach($mail, $i, self::ATTACH_FILE_PATH);
                        var_dump($host, $message_id, $subject, $attach);
                    } elseif (strpos($host, 'zhaopinmail.com') !== false) {
                        // body内容写入html
                        $body = $imapServer->getBody($mail, $i);
                        $htmlfileName = self::HTML_FILE_PATH . uniqid() . '.html';
                        file_put_contents($htmlfileName, '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>' . $charset->charsetToUTF8($body) . '</body></html>');
                        var_dump($host, $message_id, $subject, $htmlfileName);
                    }
                } catch (Throwable $exception) {
                    var_dump($exception->getMessage());
                    continue;
                }
            }
            imap_close($mail);
        } catch (\Throwable $exception) {
            throw new \Exception($exception->getMessage());
        }
        return 'success';
    }

}