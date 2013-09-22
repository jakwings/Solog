<?php
// Add    增改一篇文章
// Del    删除一篇文章
// Item   查询文章列表
// :TODO: Track  发送 Trackback 信息
// Cache  管理页面缓存
// Access 查询访问记录
// Secure 更改用户名和密码
class ActionHandler
{
  public function Add($req) {
    $file_content = $this->_ReadFile($_FILES['file']['tmp_name']);
    if ( empty($file_content) ) {
      catch_error(503, '请上传完整的文件！', TRUE);
    }
    $cid = $req['id'];
    if ( !empty($cid) and intval($cid) < 0 ) {
      catch_error(503, '文章 ID 不能为非负数！', TRUE);
    }
    require_once './utils/meta.php';
    preg_match('/^(.*?)[\\r\\n]+---[\\r\\n]+(.*)$/s', $file_content, $match);
    $metas = parse_meta($match[1]);
    $content = $match[2];
    $this->_FormatMetas($metas);
    $gDatabase = $GLOBALS['gDatabase'];
    $gDatabase->Connect($GLOBALS['gSoCfg']['dir_database']);
    $cid = $this->_UpdateArticle($cid, $metas, $content);
    $gDatabase->Disconnect();
    echo '文章上传更新成功，其 ID 为：' . $cid . "\r\n" . 'OK';
    // 清空旧文章的缓存
    if ( $GLOBALS['gSoCfg']['cache_enabled'] ) {
      $this->_ClearCache($metas['type'], $metas['slug']);
    }
  }

  public function Del($req)
  {
    $type = $req['type'];  // 用于避免误删文章
    $cid  = $req['id'];
    $valid_types = array('post', 'index');
    if ( !in_array($type, $valid_types, TRUE) ) {
      catch_error(503, '文章类型不符合其中之一：' . implode(' ', $valid_types), TRUE);
    }
    if ( empty($cid) or intval($cid) < 0 ) {
      catch_error(503, '未找到对应的文章！', TRUE);
    }
    $cid = intval($cid);
    $gDatabase = $GLOBALS['gDatabase'];
    $gDatabase->Connect($GLOBALS['gSoCfg']['dir_database']);
    $metases = $gDatabase->Select('solog_contents', array(
      'action' => 'DEL+',
      'where' => function ($record) use ($cid, $type) {
        if ( $record['type'] === $type
          and $record['cid'] === $cid )
        {
          return TRUE;
        }
      },
    ));
    $metas = $metases[0];
    if ( empty($metas) ) {
      $gDatabase->Disconnect();
      catch_error(503, '未找到对应的文章！', TRUE);
    }
    $this->_UpdateMetadata($metas, $metas);
    $gDatabase->Update('solog_contents');
    $gDatabase->Disconnect();
    @unlink($GLOBALS['gSoCfg']['serv_root'] . $metas['file']);
    echo '文章删除成功' . "\r\n" . 'OK';
    if ( $GLOBALS['gSoCfg']['cache_enabled'] ) {
      // 清除缓存
      $this->_ClearCache($metas['type'], $metas['slug']);
    }
  }

  public function Item($req)
  {
    $type = strval($req['type']);
    $range = explode(',', $req['range'] ?: '1,10', 2);
    if ( in_array($type[0], array('c', 't'), TRUE) && strlen($type) < 2
      or in_array($type[0], array('i', 'p'), TRUE) && strlen($type) > 1 )
    {
      catch_error(503, '筛选关键词不正确！', TRUE);
    }
    $name = substr($type, 1);
    switch ( $type[0] ) {
      case 'i': $type = 'index'; break;
      case 'p': $type = 'post'; break;
      case 'c': $type = 'category'; break;
      case 't': $type = 'tag'; break;
      default: $type = 'post'; break;
    }
    $gDatabase = $GLOBALS['gDatabase'];
    $gDatabase->Connect($GLOBALS['gSoCfg']['dir_database']);
    if ( in_array($type, array('category', 'tag'), TRUE) ) {
      $mids = $gDatabase->Select('solog_metas', array(
        'action' => 'UNI',
        'where' => function ($record) use ($type, $name) {
          if ( $record['type'] === 'category' and $record['name'] === $name ) {
            return TRUE;
          }
          if ( $record['type'] === 'tag'
            and mb_strtolower($record['name']) === mb_strtolower($name) )
          {
            return TRUE;
          }
        },
        'column' => 'mid',
      ), TRUE);
      $cids = $gDatabase->Select('solog_relationships', array(
        'action' => 'UNI',
        'where' => function ($record) use ($mids) {
          return in_array($record['mid'], $mids, TRUE);
        },
        'column' => 'cid',
      ), TRUE);
      if ( count($cids) > 0 ) {
        $data = $gDatabase->Select('solog_contents', array(
          'where' => function ($record) use ($cids) {
            return in_array($record['cid'], $cids, TRUE);
          },
          'order' => array('created' => SORT_DESC),
          'column' => array('cid', 'created', 'title'),
        ), TRUE);
      } else {
        $data = array();
      }
    } else {
      $data = $gDatabase->Select('solog_contents', array(
        'where' => function ($record) use ($type) {
          return $record['type'] === $type;
        },
        'order' => array('created' => SORT_DESC),
        'column' => array('cid', 'created', 'title'),
      ), TRUE);
    }
    $gDatabase->Disconnect();
    $range[0] = intval($range[0]);
    $range[1] = intval($range[1]) ?: count($data);
    $data = array_slice($data, $range[0] - 1, $range[1] - $range[0] + 1);
    $data = array_reverse($data);
    $format = '%-3s %-10s %s';
    $lines  = array();
    $lines[] = sprintf($format, 'id', 'created', 'title');
    foreach ( $data as $item ) {
      $item['created'] = strftime('%F', $item['created']);
      array_unshift($item, $format);
      $lines[] = call_user_func_array('sprintf', $item);
    }
    $lines[] = 'OK';
    echo implode("\r\n", $lines);
  }

  //public function Track($req)
  //{
  //}

  public function Cache($req)
  {
    if ( !$GLOBALS['gSoCfg']['cache_enabled'] ) {
      catch_error(503, '博客缓存机制尚未启动！', TRUE);
    }
    $type = $req['type'];
    $valid_types = array('clear');
    if ( !in_array($type, $valid_types, TRUE) ) {
      catch_error(503, '操作类型应为其中之一：' . implode(' ', $valid_types), TRUE);
    }
    $cache_dir = $GLOBALS['gSoCfg']['dir_database'] . '/files/cache/';
    if ( $type === 'clear' ) {
      $is_all_deleted = TRUE;
      $filenames = glob($cache_dir . '*/*.txt');
      foreach ( $filenames as $filename ) {
        $is_all_deleted = $is_all_deleted and @unlink($filename);
      }
      if ( $is_all_deleted ) {
        echo '所有缓存删除成功！' . "\r\n" . 'OK';
        exit();
      } else {
        if ( count($filenames) > 1 ) {
          catch_error(503, '某些或所有缓存无法删除！', TRUE);
        } else {
          catch_error(503, '无法删除缓存！', TRUE);
        }
      }
    }
  }

  public function Access($req)
  {
    $status  = intval($req['status']);
    $range   = explode(',', $req['range'] ?: '1,10', 2);
    $headers = array('ip', 'date', 'status', 'comment', 'ua');
    if ( !is_null($req['status']) ) {
      $where = function ($record) use ($status) {
        return $record['status'] === $status;
      };
    } else {
      $where = NULL;
    }
    $gDatabase = $GLOBALS['gDatabase'];
    $gDatabase->Connect($GLOBALS['gSoCfg']['dir_database']);
    $records = $gDatabase->Select('solog_login', array(
      'where' => $where,
      'column' => $headers,
    ), TRUE);
    $gDatabase->Disconnect();
    $range[0] = intval($range[0]);
    $range[1] = intval($range[1]) ?: count($records);
    $records = array_reverse($records);
    $records = array_slice($records, $range[0] - 1, $range[1] - $range[0] + 1);
    $records = array_reverse($records);
    // 输出访问记录
    $lines  = array();
    $format = "%-15s\t%-24s\t%6s\t%-15s\t%s";
    array_unshift($headers, $format);
    $lines[] = call_user_func_array('sprintf', $headers);
    foreach ( $records as $record ) {
      $record['date'] = strftime('%F %H:%M:%S%z', $record['date']);
      switch ( $record['status'] ) {
        case 0: $record['status'] = 'OK'; break;
        case 1: $record['status'] = 'FAIL'; break;
        default: $record['status'] = '--';
      }
      array_unshift($record, $format);
      $lines[] = call_user_func_array('sprintf', $record);
    }
    $lines[] = 'OK';
    echo implode("\r\n", $lines);
  }

  public function Secure($req)
  {
    $username = $req['user'];
    $password = $req['pass'] ?: '';
    if ( empty($username)
      or in_array($username, $GLOBALS['gInvalidEntries'], TRUE)
      or strlen($password) < 10
      or strlen($password) > 30 )
    {
      catch_error(403, '用户名或密码不合法！', TRUE);
    }
    // 准备和检查用户信息
    $entry  = preg_replace('/([$\'\\\\])/', '\\\\$1', $GLOBALS['gServEntry']);
    $user   = preg_replace('/([$\'\\\\])/', '\\\\$1', $username);
    $hasher = new PasswordHash(8, FALSE);
    $hashed = $hasher->HashPassword($password);
    if ( strlen($hashed) < 20 or !$hasher->CheckPassword($password, $hashed) ) {
      catch_error(503, '生成密码信息时发生未知错误，可考虑更换密码！', TRUE);
    }
    $digest = preg_replace('/([$\'\\\\])/', '\\\\$1', $hashed);
    // 检查 .htaccess 重定向配置
    $htaccess = $this->_ReadFile('../.htaccess');
    if ( empty($htaccess) ) {
      catch_error(503, '读取 /.htaccess 文件失败，或文件内容为空！', TRUE);
    }
    $htaccess = preg_replace('/(RewriteRule .*index\\.php\\?)' . $entry . '=\\S+/m', '${1}' . $user . '=\\$1', $htaccess, 1, $count);
    if ( $count !== 1 ) {
      catch_error(503, '读取到不完整的 /.htaccess 文件！', TRUE);
    }
    // 检查博客入口配置
    $web_server = $this->_ReadFile('../utils/server.php');
    if ( empty($web_server) ) {
      catch_error(503, '读取 /utils/server.php 文件失败，或文件内容为空！', TRUE);
    }
    $web_server = preg_replace('/(\\$gServEntry)\\b.*$/m', '${1} = \'' . $user . '\';', $web_server, 1, $count);
    if ( $count !== 1 ) {
      catch_error(503, '读取到不完整的 /utils/server.php 文件！', TRUE);
    }
    // 检查博客 API 用户名和密码配置
    $api_server = $this->_ReadFile('./utils/server.php');
    if ( empty($api_server) ) {
      catch_error(503, '读取 /manage/utils/server.php 文件失败，或文件内容为空！', TRUE);
    }
    $api_server = preg_replace('/(\\$gServEntry)\\b.*$/m', '${1} = \'' . $user . '\';', $api_server, 1, $count);
    if ( $count !== 1 ) {
      catch_error(503, '读取到不完整的 /manage/utils/server.php 文件！', TRUE);
    }
    $api_server = preg_replace('/(\\$gServPassHash)\\b.*$/m', '${1} = \'' . $digest . '\';', $api_server, 1, $count);
    if ( $count !== 1 ) {
      catch_error(503, '读取到不完整的 /manage/utils/server.php 文件！', TRUE);
    }
    // 更新信息
    @ignore_user_abort(TRUE);
    if ( FALSE === @file_put_contents('../.htaccess', $htaccess, LOCK_EX)
      or FALSE === @file_put_contents('../utils/server.php', $web_server, LOCK_EX)
      or FALSE === @file_put_contents('./utils/server.php', $api_server, LOCK_EX) )
    {
      @ignore_user_abort(FALSE);
      catch_error(503, '修改失败！');
    }
    @ignore_user_abort(FALSE);
    echo '修改成功！' . "\r\n" . 'OK';
  }

  private function _FormatMetas(&$metas)
  {
    $valid_types = array('post', 'index');
    if ( !in_array($metas['type'], $valid_types, TRUE) ) {
      catch_error(503, '文章类型须为其中之一：' . implode(' ', $valid_types), TRUE);
    }
    if ( empty($metas['slug']) ) {
      catch_error(503, '文章 slug 不能为空！', TRUE);
    }
    if ( strlen($metas['slug']) > 200 ) {
      catch_error(503, '文章 slug 的长度不能大于 200 个字节！', TRUE);
    }
    if ( strlen($metas['title']) > 200 ) {
      catch_error(503, '文章 title 的长度不能大于 200 个字节！', TRUE);
    }
    $metas['cid']      = NULL;
    $metas['file']     = NULL;
    $metas['order']    = $metas['order'];
    $metas['created']  = strtotime($metas['created']) ?: time();
    $metas['modified'] = strtotime($metas['modified']) ?: time();
    $metas['feed']     = '' === $metas['feed'] ? TRUE : !!$metas['feed'];
    $metas['comment']  = '' === $metas['comment'] ? TRUE : !!$metas['comment'];
  }

  private function _UpdateArticle($cid, $metas, $content)
  {
    $gDatabase = $GLOBALS['gDatabase'];
    if ( empty($cid) ) {
      // 添加新文章
      $type = $metas['type'];
      $slug = $metas['slug'];
      $date = $metas['created'];
      $dup = $gDatabase->Select('solog_contents', array(
        'where' => function ($record) use ($type, $date, $slug) {
          if ( $type === 'post' ) {
            $date_1 = strftime('%Y-%m-%d', $date);
            $date_2 = strftime('%Y-%m-%d', $record['created']);
            if ( $type === $record['type']
              and $slug === $record['slug']
              and $date_1 === $date_2 )
            {
              return TRUE;
            }
          } else {
            if ( $type === $record['type'] and $slug === $record['slug'] ) {
              return TRUE;
            }
          }
        }
      ), TRUE);
      if ( count($dup) > 0 ) {
        $gDatabase->Disconnect();
        catch_error(503, '已存在同一 slug（且同一天发表）的文章：' . $dup[0]['cid'], TRUE);
      }
      $cid = $gDatabase->Max('solog_contents', 'cid', TRUE) + 1;
      $metas['cid'] = $cid;
      $metas['file'] = '/files/' . $metas['type'] . '/' . $cid . '.txt';
      $filename = $GLOBALS['gSoCfg']['dir_database'] . $metas['file'];
      if ( FALSE === @file_put_contents($filename, $content, LOCK_EX) ) {
        $gDatabase->Disconnect();
        catch_error(503, '无法保存文章内容到数据库！', TRUE);
      }
      $headers = $gDatabase->GetHeaders('solog_contents', TRUE);
      $new_record = array();
      foreach ( $headers as $header ) {
        $new_record[$header] = $metas[$header];
      }
      $gDatabase->Insert('solog_contents', $new_record);
      $old_metas = array();
    } else {
      // 更新旧文章
      $cid = intval($cid);
      $metas['cid'] = $cid;
      $metas['file'] = '/files/' . $metas['type'] . '/' . $cid . '.txt';
      $old_metases = $gDatabase->Select('solog_contents', array(
        'action' => 'DEL+',
        'where' => function ($record) use ($cid) {
          return $record['cid'] === $cid;
        },
      ));
      $old_metas = $old_metases[0];
      if ( empty($old_metas) ) {
        $gDatabase->Disconnect();
        catch_error(503, '未找到符合该 ID 的文章：' . $cid, TRUE);
      }
      $headers = $gDatabase->GetHeaders('solog_contents', TRUE);
      $new_record = array();
      foreach ( $headers as $header ) {
        $new_record[$header] = $metas[$header];
      }
      $gDatabase->Insert('solog_contents', $new_record);
      if ( $metas['file'] !== $old_metas['file'] ) {
        @unlink($GLOBALS['gSoCfg']['serv_root'] . $old_metas['file']);
      }
      $filename = $GLOBALS['gSoCfg']['dir_database'] . $metas['file'];
      if ( FALSE === @file_put_contents($filename, $content, LOCK_EX) ) {
        $gDatabase->Disconnect();
        catch_error(503, '无法保存文章内容到数据库！', TRUE);
      }
    }
    $this->_UpdateMetadata($metas, $old_metas);
    $gDatabase->Update('solog_contents');
    return $cid;
  }

  private function _UpdateMetadata($newMetas, $oldMetas)
  {
    $gDatabase = $GLOBALS['gDatabase'];
    $new_cid = $newMetas['cid'];
    $old_cid = $oldMetas['cid'];
    // 新的要建立关联的分类 id
    $new_mids = array();
    // 取得相关关系表信息
    $old_mids = $gDatabase->Select('solog_relationships', array(
      'action' => 'UNI',
      'where' => function ($record) use ($old_cid) {
        return $record['cid'] === $old_cid;
      },
      'column' => 'mid',
    ), TRUE);
    // 根据引用次数自动创建或删除文章目录和标签
    $new_tags       = $newMetas['tags'] ?: array();
    $new_categories = $newMetas['categories'] ?: array();
    if ( !is_array($new_tags) ) {
      $new_tags = array($new_tags);
    } else {
      $new_tags = array_unique($new_tags, SORT_REGULAR);
    }
    if ( !is_array($new_categories) ) {
      $new_categories = array($new_categories);
    } else {
      $new_categories = array_unique($new_categories, SORT_REGULAR);
    }
    $discarded_mids = array();
    $gDatabase->Select('solog_metas', array(
      'action' => 'SET',
      'where' => function (&$record) use ($old_mids, &$new_tags, &$new_categories, &$discarded_mids) {
        $types = array('category', 'tag');
        if ( in_array($record['mid'], $old_mids, TRUE)
          and in_array($record['type'], $types, TRUE) )
        {
          if ( $record['type'] === 'category' ) {
            $index = array_search($record['name'], $new_categories, TRUE);
            if ( FALSE !== $index ) {
              // 只留下新添加的目录
              unset($new_categories[$index]);
            } else {
              // 不再使用的旧目录引用数减少 1
              $record['count'] = $record['count'] > 0 ? $record['count'] - 1 : 0;
              $discarded_mids[] = $record['mid'];
            }
          }
          if ( $record['type'] === 'tag' ) {
            $index = FALSE;
            foreach ( $new_tags as $i => $name ) {
              if ( mb_strtolower($record['name']) === mb_strtolower($name) ) {
                $index = $i;
                break;
              }
            }
            if ( FALSE !== $index ) {
              // 只留下新添加的标签
              unset($new_tags[$index]);
            } else {
              // 不再使用的旧标签引用数减少 1
              $record['count'] = $record['count'] > 0 ? $record['count'] - 1 : 0;
              $discarded_mids[] = $record['mid'];
            }
          }
          return TRUE;
        }
      },
    ));
    $empty_mids = array();
    if ( count($old_mids) > 0 ) {
      // 删除不再使用的分类
      $gDatabase->Select('solog_metas', array(
        'action' => 'DEL',
        'where' => function ($record) use ($old_mids) {
          $types = array('category', 'tag');
          $index = array_search($record['mid'], $old_mids, TRUE);
          if ( FALSE !== $index
            and in_array($record['type'], $types, TRUE) )
          {
            if ( $record['count'] < 1 ) {
              // 收集引用数为小于 0 的分类 id
              $empty_mids[] = $record['mid'];
              return TRUE;
            }
          }
        },
      ));
    }
    if ( count($empty_mids) > 0 ) {
      // 删除失效的关联
      $gDatabase->Select('solog_relationships', array(
        'action' => 'DEL',
        'where' => function ($record) use ($empty_mids) {
          if ( in_array($record['mid'], $empty_mids, TRUE) ) {
            return TRUE;
          }
        },
      ));
    }
    if ( count($discarded_mids) > 0 ) {
      // 删除抛弃的关联
      $gDatabase->Select('solog_relationships', array(
        'action' => 'DEL',
        'where' => function ($record) use ($old_cid, $discarded_mids) {
          if ( $record['cid'] === $old_cid
            and in_array($record['mid'], $discarded_mids, TRUE) )
          {
            return TRUE;
          }
        },
      ));
    }
    if ( count($new_categories) > 0 or count($new_tags) > 0 ) {
      // 增加已存在的分类引用次数
      $gDatabase->Select('solog_metas', array(
        'action' => 'SET',
        'where' => function (&$record) use (&$new_categories, &$new_tags, &$new_mids) {
          if ( $record['type'] === 'category' ) {
            $index = array_search($record['name'], $new_categories, TRUE);
            if ( FALSE !== $index ) {
              $record['count'] = ($record['count'] ?: 0) + 1;
              // 添加未关联目录的 id
              $new_mids[] = $record['mid'];
              // 只留下未创建的目录
              unset($new_categories[$index]);
              return TRUE;
            }
          }
          if ( $record['type'] === 'tag' ) {
            $index = FALSE;
            foreach ( $new_tags as $i => $name ) {
              if ( mb_strtolower($record['name']) === mb_strtolower($name) ) {
                $index = $i;
                break;
              }
            }
            if ( FALSE !== $index ) {
              $record['count'] = ($record['count'] ?: 0) + 1;
              // 添加未关联标签的 id
              $new_mids[] = $record['mid'];
              // 只留下未创建的标签
              unset($new_tags[$index]);
              return TRUE;
            }
          }
        },
      ));
    }
    $max_mid = $gDatabase->Max('solog_metas', 'mid') ?: 0;
    if ( count($new_categories) > 0 ) {
      // 创建新分类
      foreach ( $new_categories as $category ) {
        $max_mid++;
        $new_record = array(
          'mid' => $max_mid,
          'type' => 'category',
          'name' => $category,
          'slug' => $category,
          'count' => 1,
        );
        $gDatabase->Insert('solog_metas', $new_record);
        $new_mids[] = $max_mid;
      }
    }
    if ( count($new_tags) > 0 ) {
      // 创建新标签
      foreach ( $new_tags as $tag ) {
        $max_mid++;
        $new_record = array(
          'mid' => $max_mid,
          'type' => 'tag',
          'name' => $tag,
          'slug' => $tag,
          'count' => 1,
        );
        $gDatabase->Insert('solog_metas', $new_record);
        $new_mids[] = $max_mid;
      }
    }
    if ( count($new_mids) > 0 ) {
      // 为目前的文章与新分类建立关联
      foreach ( $new_mids as $new_mid ) {
        $gDatabase->Insert('solog_relationships', array(
          'cid' => $new_cid,
          'mid' => $new_mid,
        ));
      }
    }
    // 由于 TextOfDatabase 的特性，即使没有修改过也不会进行读写影响运行效率。
    $gDatabase->Update('solog_metas');
    $gDatabase->Update('solog_relationships');
  }

  private function _ReadFile($filename)
  {
    if ( FALSE === ($fh = @fopen($filename, 'rb')) ) {
      return FALSE;
    }
    if ( FALSE === @flock($fh, LOCK_SH) ) {
      @fclose($fh);
      return FALSE;
    }
    $content = @file_get_contents($filename, FALSE, NULL);
    @flock($fh, LOCK_UN);
    @fclose($fh);
    return $content;
  }

  private function _ClearCache($type, $path, $pageNum = 1)
  {
    switch ( $type ) {
      case 'index': $type = 'i'; $cache_type = 'index'; break;
      case 'post': $type = 'posts'; $cache_type = 'post'; break;
      default: return;
    }
    $cache_dir = $GLOBALS['gSoCfg']['dir_database'] . '/files/cache/';
    if ( $cache_type === 'index' ) {
      $uri = rtrim($GLOBALS['gSoCfg']['dir_root_rel'], '/')
            . '/' . $type
            . ($path ? '/' . $path : '')
            . ',' . $pageNum;
      $cache_id = md5($uri);
      $cache_file = $cache_dir . $cache_type . '/' . $cache_id . '.txt';
      if ( is_file($cache_file) ) {
        unlink($cache_file) or catch_error(503, '无法删除缓存文件！', TRUE);
      }
    }
    if ( $cache_type === 'post' ) {
      // GLOB_BRACE 在某些非 GNU 系统中无法使用
      //$filenames = glob($cache_dir . '{post,home,category,tag,archive,feed}/*.txt', GLOB_NOSORT | GLOB_BRACE);
      $filenames = glob($cache_dir . 'post/*.txt', GLOB_NOSORT);
      $filenames = array_merge($filenames, glob($cache_dir . 'home/*.txt', GLOB_NOSORT));
      $filenames = array_merge($filenames, glob($cache_dir . 'category/*.txt', GLOB_NOSORT));
      $filenames = array_merge($filenames, glob($cache_dir . 'tag/*.txt', GLOB_NOSORT));
      $filenames = array_merge($filenames, glob($cache_dir . 'archive/*.txt', GLOB_NOSORT));
      $filenames = array_merge($filenames, glob($cache_dir . 'feed/*.txt', GLOB_NOSORT));
      foreach ( $filenames as $filename ) {
        unlink($filename) or catch_error(503, '无法删除缓存文件！', TRUE);
      }
    }
  }
}
?>
