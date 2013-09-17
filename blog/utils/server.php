<?php
// 不使用绝对域名。
if ( preg_match('/\\.$/', $_SERVER['SERVER_NAME']) ) {
  header("Location: {$gRequestScheme}://" . substr($_SERVER['HTTP_NAME'], 0, -1));
  exit();
}

// 博客入口，保密。
$gServEntry = 'solog';
// 网络请求的 URI 路径
$gServRequestURI = $_GET[$gServEntry];
// 禁止使用的入口名称
$gInvalidEntries = array(
  'action', 'type', 'id', 'range',
  'user', 'pass', 'status'
);

// 设定错误提示级别
if ( $gSoCfg['debug'] ) {
  @error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);
} else {
  @error_reporting(E_ERROR);
}

if ( in_array($gServEntry, $gInvalidEntries, TRUE) ) {
  catch_error(503, '博客入口配置错误！');
}
if ( !is_dir($gSoCfg['dir_root']) ) {
  catch_error(503, '博客目录配置错误！');
}
if ( !is_dir($gSoCfg['dir_database']) ) {
  catch_error(503, '博客数据库不存在！');
}
if ( !is_dir($gSoCfg['dir_theme']) ) {
  catch_error(503, '博客主题配置错误！');
}
if ( FALSE === date_default_timezone_set($gSoCfg['web_timezone']) ) {
  catch_error(503, '博客时区配置错误！');
}

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
?>
