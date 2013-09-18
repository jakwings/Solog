<?php
// 禁止非 GET 或非 HEAD 方式的网络请求
if ( !in_array($_SERVER['REQUEST_METHOD'], array('GET', 'HEAD')) ) {
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
