<?php
require_once './utils/config.php';       // 用户配置
require_once './utils/mconfig.php';      // 自动配置
require_once './utils/error.php';        // 错误处理
require_once './utils/server.php';       // 执行配置
require_once './utils/secure.php';       // 安全保护
require_once './utils/router.php';       // URI 路由器
require_once './utils/handler.php';      // 路由转接处理器
require_once './utils/todb.class.php';   // 文本数据库管理工具

// 开始处理 URI 请求
$gServRequestHandler = new RequestHandler();
route($gServRequestHandler, $gServRequestURI);
?>
