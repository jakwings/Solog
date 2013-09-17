<?php
// 版本信息，各版本间不具兼容性。
$gSoCfg['version'] = 'Solog V1';

// 服务器根目录
$gSoCfg['serv_root'] = rtrim($_SERVER['DOCUMENT_ROOT'], '/') ?: '/';
// 博客相对路径
$gSoCfg['dir_root_rel'] = rtrim($gSoCfg['dir_root_rel'], '/') ?: '/';
// 博客数据库相对路径
$gSoCfg['dir_database_rel'] = rtrim($gSoCfg['dir_database_rel'], '/') ?: '/';

// 博客对应的服务器绝对路径
$gSoCfg['dir_root'] = $gSoCfg['serv_root'] . $gSoCfg['dir_root_rel'];
// 博客数据库绝对路径
$gSoCfg['dir_database'] = $gSoCfg['serv_root'] . $gSoCfg['dir_database_rel'];
// 博客主题文件夹名称
$gSoCfg['web_theme'] = $gSoCfg['web_theme'] ?: 'template';
// 博客主题的相对路径
$gSoCfg['dir_theme_rel'] = rtrim($gSoCfg['dir_root_rel'], '/') . '/themes/' . $gSoCfg['web_theme'];
// 博客主题的绝对路径
$gSoCfg['dir_theme'] = $gSoCfg['serv_root'] . $gSoCfg['dir_theme_rel'];
// 域名所使用的传输协议
$tmp = explode('/', $_SERVER['SERVER_PROTOCOL']);
$gSoCfg['web_scheme'] = strtolower($tmp[0]);
unset($tmp);
// 博客域名
$gSoCfg['web_host'] = rtrim($_SERVER['SERVER_NAME'], '.');
// 博客网址
$gSoCfg['web_link'] = $gSoCfg['web_scheme'] . '://' . ($gSoCfg['web_link'] ?: $gSoCfg['web_host'] . rtrim($gSoCfg['dir_root_rel'], '/') . '/');

// 博客页面缓存时间（秒）
$gSoCfg['cache_lifetime'] = $gSoCfg['cache_lifetime'] ?: 60 * 60 * 24 * 7;
?>
