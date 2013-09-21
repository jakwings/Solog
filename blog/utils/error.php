<?php
/**
* @param  {Integer} $errNum: （可选）HTTP 状态码
* @param  {String}  $errMsg: （可选）错误提示信息
* @param  {Boolean} $toRedirect: （可选）是否交由错误处理脚本处理
* @return void
*/
function catch_error($errNum = 404, $errMsg = '', $toRedirect = FALSE) {
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
  @header('Content-Type: text/html; charset="UTF-8"');
  @header('Cache-Control: max-age=0');
  @header('Expires: ' . date(DATE_RFC1123));
  if ( empty($errMsg) ) {
    $errMsg = $errStatus;
  }
  if ( !$toRedirect ) {
    if ( $GLOBALS['gSoCfg']['debug'] ) {
      echo <<<"EOT"
<pre style="white-space:pre-wrap;">
<b style="color:#B22222;">{$errStatus}</b><br>
Backtrace:<br>
EOT;
      @debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
      echo '</pre>';
      @trigger_error('SOLOG ERROR', E_USER_NOTICE);
      exit('<pre>' . htmlentities($errMsg, ENT_QUOTES, 'UTF-8') . '</pre>');
    }
    @trigger_error('SOLOG ERROR', E_USER_NOTICE);
    exit($errMsg);
  }
  // TODO: 重定向
  @trigger_error('SOLOG ERROR', E_USER_NOTICE);
  exit();
}
?>
