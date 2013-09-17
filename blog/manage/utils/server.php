<?php
// 使用纯文本
@header('Content-Type: text/plain; charset="utf-8"');
// 设定错误提示信息级别为最低
@error_reporting(E_ERROR);

// 关闭 magic quotes
if ( function_exists('set_magic_quotes_runtime') ) {
  @set_magic_quotes_runtime(FALSE);
}

if ( function_exists('mb_regex_set_options') ) {
  // 多字节字符串函数默认设置
  mb_language('uni');
  mb_internal_encoding('UTF-8');
  mb_regex_encoding('UTF-8');
  mb_regex_set_options('pz');
} else {
  include_once $gSoCfg['dir_root'] . '/utils/mb_functions.php';
}

// 博客入口，保密，禁止使用 '' 'action' 'type' 'id' 'user' 'pass' 这几种值。
$gServEntry = 'solog';
// 通过 POST 方式提交到入口的密码，不能为空。
$gServPassword = $_POST[$gServEntry];
// 密码的 Hash Digest ，保密，自动生成。
$gServPassHash = '$2a$08$78rpWYbirohBl8lQ4fj39.ULb9GR2or1lOsI4DgWx1dZ/umURTsfq';

if ( !is_dir($gSoCfg['dir_root']) ) {
  catch_error(503, '博客目录配置错误！');
}
if ( !is_dir($gSoCfg['dir_database']) ) {
  catch_error(503, '博客数据库不存在！');
}
if ( FALSE === date_default_timezone_set($gSoCfg['web_timezone']) ) {
  catch_error(503, '博客时区配置错误！');
}
if ( empty($gServEntry)
  or empty($gServPassHash)
  or in_array($gServEntry, $gInvalidEntries, TRUE) )
{
  catch_error(503, '博客安全配置错误！');
}

$gServRequest = $_POST;
foreach ( $gServRequest as $index => $value ) {
  $gServRequest[$index] = rawurldecode($value);
}
$gInvalidEntries = array(
  'action', 'type', 'id', 'range',
  'user', 'pass', 'status'
);
?>
