<?php
// 禁止非 GET 方式的网络请求
if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
  catch_error(503);
}
// 禁止尝试直接对 index.php 传送额外参数
if ( count($_GET) > 1 ) {
  catch_error(503);
}
// 禁止不通过正确的入口传递参数
if ( is_null($gServRequestURI) ) {
  catch_error(503);
}
?>
