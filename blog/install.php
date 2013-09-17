<?php
// 注意，该脚本程序会在安装完毕后被自动删除！
// 请记住你的用户名和密码。

////////////////////////////////////////////////////////////////////////////////
// 用户名，保密，不得为 '' 'action' 'type' 'id' 'range' 'user' 'pass' 'status'
$gUsername = '';
// 提交到入口的密码，不能为空，请不要使用个人常用密码，密码长度不能小于 10 个
// 或大于 30 个英文字符（一个中文字符可能相当于两个或以上的英文字符）。
$gPassword = '';
////////////////////////////////////////////////////////////////////////////////

// 填好上面的，下面的就不用看了。

require_once './utils/config.php';
require_once './utils/mconfig.php';
require_once './utils/error.php';
require_once './utils/todb.class.php';
require_once './manage/utils/phpass.class.php';

@error_reporting(E_ERROR);
@header('Content-Type: text/plain; charset="utf-8"');


////////////////////////////////////////////////////////////////////////////////
// 检查配置
if ( $gSoCfg['debug'] ) {
  exit('请在配置中关闭博客的调试模式后重新安装！');
}
if ( !is_dir($gSoCfg['dir_root']) ) {
  catch_error(503, '博客目录配置错误，请修正配置后重新安装！');
}
if ( !is_dir($gSoCfg['dir_database']) ) {
  catch_error(503, '博客数据库不存在，请修正配置后重新安装！');
}
if ( !is_dir($gSoCfg['dir_theme']) ) {
  catch_error(503, '博客主题配置错误，请修正配置后重新安装！');
}
if ( FALSE === date_default_timezone_set($gSoCfg['web_timezone']) ) {
  catch_error(503, '博客时区配置错误，请修正配置后重新安装！');
}
$invalid_usernames = array(
  'action', 'type', 'id', 'range',
  'user', 'pass', 'status'
);
if ( empty($gUsername) or in_array($gUsername, $invalid_usernames, TRUE) ) {
  catch_error(503, '请添加合法的用户名后重新安装！');
}
if ( strlen($gPassword) < 10 or strlen($gPassword) > 30 )
{
  catch_error(503, '请添加合法的密码后重新安装！');
}


////////////////////////////////////////////////////////////////////////////////
// 建立数据库
$todb = new Todb();
$todb->Connect($gSoCfg['dir_database']);
if ( !$todb->ListTables('solog_contents') ) {
  $todb->CreateTable('solog_contents', array(
    'cid', 'type', 'title', 'slug', 'file',
    'created', 'modified', 'order', 'comment', 'feed'
  ));
}
if ( !$todb->ListTables('solog_relationships') ) {
  $todb->CreateTable('solog_relationships', array('cid', 'mid'));
}
if ( !$todb->ListTables('solog_metas') ) {
  $todb->CreateTable('solog_metas', array(
    'mid', 'type', 'name', 'slug', 'order', 'count'
  ));
}
if ( !$todb->ListTables('solog_login') ) {
  $todb->CreateTable('solog_login', array(
    'date', 'ip', 'status', 'ua', 'comment'
  ));
}
if ( !$todb->ListTables('solog_metas')
  or !$todb->ListTables('solog_login')
  or !$todb->ListTables('solog_contents')
  or !$todb->ListTables('solog_relationships') )
{
  $todb->Disconnect();
  catch_error(503, '创建数据库失败！');
}
$todb->Disconnect();


////////////////////////////////////////////////////////////////////////////////
// 修改 server.php 中的入口设置，以及密码验证字符串。
$user = preg_replace('/([$\'\\\\])/', '\\\\$1', $gUsername);
$hasher = new PasswordHash(8, FALSE);
$hashed = $hasher->HashPassword($gPassword);
if ( strlen($hashed) < 20 or !$hasher->CheckPassword($gPassword, $hashed) ) {
  catch_error(503, '生成密码信息时发生未知错误，可考虑更换密码！');
}
$digest = preg_replace('/([$\'\\\\])/', '\\\\$1', $hashed);
// 修改博客入口
$web_server = @file_get_contents('./utils/server.php', FALSE, NULL);
if ( empty($web_server) ) {
  catch_error(503, '读取 ./utils/server.php 文件失败，或文件内容为空！');
}
$web_server = preg_replace('/(\\$gServEntry)\\b.*$/m', '${1} = \'' . $user . '\';', $web_server, 1, $count);
if ( $count !== 1 ) {
  catch_error(503, '读取到不完整的 ./utils/server.php 文件！');
}
if ( FALSE === @file_put_contents('./utils/server.php', $web_server, LOCK_EX) )
{
  catch_error(503, '修改配置文件 ./utils/server.php 失败！');
}
// 继续修改用户名和密码 digest
$api_server = @file_get_contents('./manage/utils/server.php', FALSE, NULL);
if ( empty($api_server) ) {
  catch_error(503, '读取 ./manage/utils/server.php 文件失败，或文件内容为空！');
}
$api_server = preg_replace('/(\\$gServEntry)\\b.*$/m', '${1} = \'' . $user . '\';', $api_server, 1, $count);
if ( $count !== 1 ) {
  catch_error(503, '读取到不完整的 ./manage/utils/server.php 文件！');
}
$api_server = preg_replace('/(\\$gServPassHash)\\b.*$/m', '${1} = \'' . $digest . '\';', $api_server, 1, $count);
if ( $count !== 1 ) {
  catch_error(503, '读取到不完整的 ./manage/utils/server.php 文件！');
}
if ( FALSE === @file_put_contents('./manage/utils/server.php', $api_server, LOCK_EX) )
{
  catch_error(503, '修改配置文件 ./manage/utils/server.php 失败！');
}


////////////////////////////////////////////////////////////////////////////////
// 生成 .htaccess 文件
$base = rtrim($gSoCfg['dir_root_rel'], '/') . '/';
$regex_domain = preg_replace('/\\./', '\\\\.', $gSoCfg['web_host']);
$htaccess = <<<"EOT"
## 这里没看懂 Apache 官方手册别碰
Options FollowSymLinks SymLinksIfOwnerMatch
Order Deny,Allow

## 设定默认字符编码
AddDefaultCharset UTF-8
## 设定默认页面类型
DefaultType text/plain

<IfModule mod_dir.c>
  ## 设定默认服务页面
  DirectoryIndex index.html index.php
  ## 禁止自动将不存在的文件如 /foo 解释到文件夹 /foo/
  DirectorySlash Off
</IfModule>

<IfModule mod_alias.c>
  ## 禁止访问非公开的脚本
  RedirectMatch 404 (?<!^{$base}index|^{$base}manage/api)\.php$
</IfModule>

<FilesMatch "^\\.">
  ## 禁止浏览文件名以点开头的文件，如 .user.ini 。
  Order Allow,Deny
</FilesMatch>

<IfModule !mod_alias.c>
  <FilesMatch "(?<!^index|^api)\\.php\$">
    ## 禁止运行非公开的脚本
    Order Allow,Deny
  </FilesMatch>
</IfModule>

<IfModule mod_rewrite.c>
  RewriteEngine on
  RewriteBase {$base}
  ## 此处可用于网站维护时（仅限你的 IP 12.34.56.78 正常访问）
  #RewriteCond %{REMOTE_ADDR} !^12\.34\.56\.78$
  #RewriteCond %{REQUEST_URI} !^{$base}errors/
  #RewriteRule ^ - [R=503,L]

  ## 禁止盗图
  RewriteCond %{HTTP_REFERER} !^\$
  RewriteCond %{HTTP_REFERER} !^https?://([^.]+\\.)*{$regex_domain}\\.?/ [NC]
  RewriteCond %{REQUEST_URI}  !^{$base}errors/
  RewriteRule \\.(png|gif|jpe?g|bmp)\$ /errors/hotlink.gif [R,L,NC]
  ## 路由配置
  RewriteCond %{REQUEST_URI} ^{$base}(,\\d+)?\$ [OR]
  RewriteCond %{REQUEST_URI} ^{$base}(archives(,\\d+)?|feed\\.xml)\$ [OR]
  RewriteCond %{REQUEST_URI} ^{$base}(posts|categories|tags|i)/
  RewriteRule ^(.*)\$ index.php?{$gUsername}=\$1 [NS,L]
</IfModule>

<IfModule mod_headers.c>
  ## 禁止网页被直接盗用
  Header always set Frame-Options "deny"
  Header always set X-Frame-Options "deny"
  ## 让现代浏览器自行防御反射型 XSS 攻击
  Header always set Content-Security-Policy "reflected-xss filter"
  Header always set X-Content-Security-Policy "reflected-xss filter"
  Header always set X-WebKit-CSP "reflected-xss filter"
  Header always set X-XSS-Proctection "1"
  ## 禁止直接发送 PHP 版本信息
  Header always unset X-Powered-By
</IfModule>

<IfModule mod_expires.c>
  ## 设定浏览器缓存文件的时间
  ExpiresActive On
  ExpiresDefault "access plus 1 hours"
  ExpiresByType "text/html" "access plus 25 minutes"
  ExpiresByType "text/css" "access plus 1 hours"
  ExpiresByType "application/rss+xml" "access plus 30 minutes"
  ExpiresByType "application/javascript" "access plus 1 days"
  ExpiresByType "application/x-font-woff" "access plus 3 days"
  ExpiresByType "image/png" "access plus 3 days"
  ExpiresByType "image/jpg" "access plus 3 days"
  ExpiresByType "image/gif" "access plus 3 days"
  ExpiresByType "image/bmp" "access plus 3 days"
</IfModule>

<IfModule mod_php5.c>
  ## 关闭多余的魔术引号功能
  php_value magic_quotes_gpc off
  ## 设定 PHP 脚本默认字符编码
  php_value default_charset utf-8
</IfModule>

## 非博客页面的错误页面设定
ErrorDocument 403 {$base}errors/403.html
ErrorDocument 404 {$base}errors/404.html
ErrorDocument 500 {$base}errors/500.html
ErrorDocument 503 {$base}errors/503.html

## 下面会被附上被封禁 IP 的列表
EOT;
$need_htaccess = FALSE;
if ( FALSE === @file_put_contents('./.htaccess', $htaccess, LOCK_EX) ) {
  $need_htaccess = TRUE;
  //catch_error(503, '创建配置文件 ./.htaccess 失败！');
}


////////////////////////////////////////////////////////////////////////////////
// 清空缓存文件
$filenames = glob($gSoCfg['dir_database'] . '/files/cache/*/*.txt', GLOB_NOSORT);
foreach ( $filenames as $filename ) {
  @unlink($filename);
}


////////////////////////////////////////////////////////////////////////////////
// 安装文件自删除
if ( FALSE === @unlink($_SERVER['SCRIPT_FILENAME']) ) {
  echo '安装完毕，请手动删除安装脚本！';
} else {
  echo '安装完毕，安装脚本已被自动删除！';
}
if ( $need_htaccess ) {
  echo "\n\n" . '注意，.htaccess 文件生成失败，请手动在博客目录上传 .htaccess 文件，文件内容如下：' . "\n\n" . $htaccess;
}
?>
