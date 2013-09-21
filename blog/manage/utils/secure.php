<?php
// 运行到此处，即使客户端中断网络连接，访问也会被记录。
ignore_user_abort(TRUE);

$secure_check = function ($status, $comment = '') {
  $ip = $_SERVER['REMOTE_ADDR'];
  $ua = $_SERVER['HTTP_USER_AGENT'];
  $todb = new Todb();
  $todb->Connect($GLOBALS['gSoCfg']['dir_database']);
  $todb->Append('solog_login', array(
    'date' => time(),
    'ip' => $ip,
    'ua' => empty($comment) ? $comment : substr($ua, 0, 150),
    'status' => $status,
    'comment' => $comment
  ), TRUE);
  if ( $status === 0 ) {
    // 将过往所有非法访问记录的状态置为一般。
    $todb->Select('solog_login', array(
      'action' => 'SET',
      'where' => function (&$record) use ($ip) {
        if ( $record['status'] === 1 and $record['ip'] === $ip ) {
          $record['status'] = 2;
          return TRUE;
        }
      }
    ));
    $todb->Update('solog_login');
    // 每成功登录 5 次更新一次密码 digest，得保证配置文件没有被严重地修改。
    $count = $todb->Count('solog_login', function ($record) {
      return $record['status'] === 0;
    }, TRUE);
    if ( $count % 5 === 0 ) {
      global $gServPassword;
      global $gServPassHash;
      $hasher = new PasswordHash(8, FALSE);
      $hashed = $hasher->HashPassword($gServPassword);
      if ( strlen($hashed) < 20 ) {
        return;
      }
      $digest = preg_replace('/([$\'\\\\])/', '\\\\$1', $hashed);
      $api_server = @file_get_contents('./utils/server.php', FALSE, NULL);
      if ( empty($api_server) ) {
        return;
      }
      $api_server = preg_replace('/(\\$gServPassHash)\\b.*$/m', '${1} = \'' . $digest . '\';', $api_server, 1, $count);
      if ( $count !== 1 ) {
        return;
      }
      if ( FALSE !== @file_put_contents('./utils/server.php', $api_server, LOCK_EX) )
      {
        $gServPassHash = $hashed;
      }
    }
  } else {
    $count = $todb->Count('solog_login', function ($record) use ($ip) {
      return $record['status'] === 1 and $record['ip'] === $ip;
    }, TRUE);
    // 将验证错误次数达到 5 的用户的 IP 封禁。
    if ( $count >= 5 ) {
      $banned = PHP_EOL . 'Deny from ' . $ip;
      @file_put_contents('../.htaccess', $banned, LOCK_EX | FILE_APPEND);
    }
  }
  $todb->Disconnect();
  unset($todb);
};

// 不要企图为各种错误原因设置不同的错误代号，
// 那样会利于非法入侵者分析失败原因。

// 只允许指定 IP 登录。
//$valid_ips = array(
//  'IP地址1',
//  'IP地址2',
//  'IP地址X',
//  '12.34.56.78',
//);
//if ( !in_array($_SERVER['REMOTE_ADDR'], $valid_ips) ) {
//  $secure_check(1, '非法IP');
//  catch_error(403);
//}

// 不使用绝对域名。
if ( preg_match('/\\.$/', $_SERVER['SERVER_NAME']) ) {
  $secure_check(1, '绝对域名');
  catch_error(403);
}
// 禁止非 POST 方式的网络请求
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
  $secure_check(1, '非POST请求');
  catch_error(403);
}
// 禁止非直接连接
// PS：其实也可以利用这点设置多一层验证……虽然这步有点多余，
// 　　不过可以迷惑看过源代码但不知道你修改过什么的入侵者。
if ( !empty($_SERVER['HTTP_REFERER']) ) {
  $secure_check(1, '非直接连接');
  catch_error(403);
}
// 禁止不通过正确入口操作
if ( empty($gServPassword) ) {
  $secure_check(1, '用户名错误');
  catch_error(403);
}

// 验证密码，限制密码长度以抵御 DoS 攻击。（验证长密码挺耗系统资源）
if ( strlen($gServPassword) > 30 ) {
  $secure_check(1, '密码过长');
  catch_error(403);
}
$hasher = new PasswordHash(8, FALSE);
if ( !$hasher->CheckPassword($gServPassword, $gServPassHash) ) {
  $secure_check(1, '密码错误');
  catch_error(403);
}
// 下面的成功登录就不因为中断连接而继续记录了。
ignore_user_abort(FALSE);

$secure_check(0);
?>
