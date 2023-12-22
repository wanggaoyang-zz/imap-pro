# 使用教程
### 安装

```
composer require php-pro/imap
```
### 使用示例
```
$example = new Example();
$day = -1;  //过去一套的
$example->pull('账户','密码', $day);
```
### 错误方案
```
如果提示 SECURITY PROBLEM: insecure server advertised AUTH=PLAIN (errflg=1) 

将error_reporting设置为如下
error_reporting(E_ALL & ~E_NOTICE);
```