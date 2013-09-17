<?php
/**
* @param  {Array}   $handlers: 路由转接处理器
* @param  {String}  $uri: 网络请求的 URI ，形式为 [目录][,页码]
*/
function route($handler, $uri = '') {
  $uri_info = explode(',', $uri);
  $target = $uri_info[0] ?: '';
  $page_num = $uri_info[1] ? intval($uri_info[1]) : 1;
  $target_info = explode('/', $target, 2);
  $page_type = $target_info[0] ?: '';
  //var_dump($uri_info);
  //var_dump($target_info);
  if ( $handler->Support($page_type) ) {
    // 开始处理 [子目录],[页码]
    $handler->Handle($page_type, $target_info[1] ?: '', $page_num);
  } else {
    catch_error(404);
  }
}
?>
