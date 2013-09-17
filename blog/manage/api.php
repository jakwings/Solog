<?php
require_once '../utils/config.php';      // 用户配置
require_once '../utils/mconfig.php';     // 自动配置
require_once './utils/error.php';        // 错误处理
require_once './utils/server.php';       // 执行配置
require_once '../utils/todb.class.php';  // 文本数据库管理
require_once './utils/phpass.class.php'; // 密码验证工具库
require_once './utils/secure.php';       // 安全保护
require_once './utils/actions.php';

$gActionHandler = new ActionHandler();
if ( !method_exists($gActionHandler, $gServRequest['action']) ) {
  catch_error(503, '不存在该操作："' . $gServRequest['action'] . '"', TRUE);
}
$gDatabase = new Todb();
$gDatabase->Debug($gSoCfg['debug']);
// Tricky?: $gActionHandler->{$gServRequest['action']}($gServRequest);
call_user_func(array($gActionHandler, $gServRequest['action']), $gServRequest);
?>
