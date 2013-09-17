<?php
/**
* @param  {Integer} $errNum: （可选）HTTP 状态码
* @param  {String}  $errMsg: （可选）错误提示信息
* @param  {Boolean} $toWarnUser: （可选）为正当登录的用户显示提示
* @return void
*/
function catch_error($errNum = 404, $errMsg = '', $toWarnUser = FALSE) {
  switch ( $errNum ) {
    case 403:
      $errStatus = '403 Forbidden';
      break;
    case 404:
      $errStatus = '404 NOT FOUND';
      break;
    case 503:
      $errStatus = '503 SERVICE UNAVAILABLE NOW';
      break;
    default:
      $errStatus = '503 UNKNOWN ERROR';
  }
  @header('HTTP/1.1 ' . $errStatus);
  @header('Content-Type: text/plain; charset="UTF-8"');
  if ( empty($errMsg) ) {
    $errMsg = $errStatus;
  }
  if ( $GLOBALS['gSoCfg']['debug'] or $toWarnUser ) {
    echo '*' . $errStatus . ':' . PHP_EOL . $errMsg . PHP_EOL;
    echo 'Backtrace:' . PHP_EOL;
    @debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
  }
  exit();
}
?>
