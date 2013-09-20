<?php
// 主题抽象类型，用于提供辅助函数及制定接口。
// private 部分不受外部影响。
// final method 无法被覆盖。
// public 部分为辅助属性及辅助函数。
// abstract method 为主题必须实现的 method 。
abstract class Renderer
{
  public $mDirAbs = NULL;     // 主题绝对路径
  public $mDirRel = NULL;     // 主题相对路径
  public $mCfg    = NULL;     // 博客配置信息
  public $mDB     = NULL;     // 数据库管理工具

  // 用于页面缓存
  private $mObLevel       = NULL;  // 缓冲区嵌套层数
  private $mCacheDir      = NULL;  // 缓存路径
  private $mCacheFile     = NULL;  // 缓存文件名
  private $mCacheLifetime = NULL;  // 缓存保留时间

  // 创建主题时的预处理
  final public function __construct()
  {
    // 引入配置信息
    $this->mCfg = $GLOBALS['gSoCfg'];
    $this->mDirAbs = $this->mCfg['dir_theme'];
    $this->mDirRel = $this->mCfg['dir_theme_rel'];
    // 连接数据库
    $this->mDB = new Todb();
    $this->mDB->Connect($this->mCfg['dir_database']);
    // 设置页面默认类型为 html
    @header('Content-Type: text/html; charset="utf-8"');
  }

  // 页面渲染完毕时的处理
  final public function __destruct()
  {
    if ( isset($this->mDB) and $this->mDB->IsConnected() ) {
      $this->mDB->Disconnect();
    }
    if ( $this->mCfg['cache_enabled']
      and $this->mObLevel === @ob_get_level() )
    {
      // 缓存开始
      @ob_end_flush();
    }
  }

  final public function Render($type, $path, $pageNum)
  {
    if ( $this->mCfg['cache_enabled'] ) {
      // 查找缓存或绑定缓存事件
      $this->_FindCache($type, $path, $pageNum);
    }

    switch ( $type ) {
      // 首页
      case '': $this->_GoHome($path, $pageNum); break;
      // 特殊页面
      case 'i': $this->_GoIndex($path, $pageNum); break;
      // 博文页面
      case 'posts': $this->_GoPost($path, $pageNum); break;
      // 订阅链接
      case 'feed.xml': $this->_GoFeed($path, $pageNum); break;
      // 博文归档
      case 'archives': $this->_GoArchive($path, $pageNum); break;
      // 目录
      case 'categories': $this->_GoCategory($path, $pageNum); break;
      // 标签
      case 'tags': $this->_GoTag($path, $pageNum); break;
      // 不存在页面
      default: $this->_GoError($path, $pageNum);
    }
  }

  private function _AutoCache($content, $phrase)
  {
    $last_error = @error_get_last();
    if ( $this->mObLevel === @ob_get_level()
      and ($phrase & PHP_OUTPUT_HANDLER_END)
      and $last_error['message'] !== 'SOLOG ERROR' )
    {
      // 清空过期的缓存文件
      $filenames = glob($this->mCacheDir . '*/*.txt', GLOB_NOSORT);
      foreach ( $filenames as $filename ) {
        if ( time() - filemtime($filename) > $this->mCacheLifetime ) {
          @unlink($filename);
        }
      }
      // 生成缓存文件
      ignore_user_abort(TRUE);
      @file_put_contents($this->mCacheFile, $content, LOCK_EX);
      ignore_user_abort(FALSE);
      return $content;
    }
    return $content;
  }
  private function _FindCache($type, $path, $pageNum)
  {
    $this->mCacheLifetime = $this->mCfg['cache_lifetime'];
    if ( !($this->mCacheLifetime > 0) ) {
      return;
    }
    switch ( $type ) {
      case '': $cache_type = 'home'; break;
      case 'i': $cache_type = 'index'; break;
      case 'posts': $cache_type = 'post'; break;
      case 'feed.xml': $cache_type = 'feed'; break;
      case 'archives': $cache_type = 'archive'; break;
      case 'categories': $cache_type = 'category'; break;
      case 'tags': $cache_type = 'tag'; break;
      default: $cache_type = 'other';
    }
    $uri = rtrim($this->mCfg['dir_root_rel'], '/')
          . '/' . $type
          . ($path ? '/' . $path : '')
          . ',' . $pageNum;
    // 切勿随意修改缓存 ID ，这会影响到更新文章时删除旧文章缓存的操作。
    $cache_id = md5($uri);
    $cache_dir = $this->mCfg['dir_database'] . '/files/cache/';
    $cache_file = $cache_dir . $cache_type . '/' . $cache_id . '.txt';
    $this->mCacheDir = $cache_dir;
    $this->mCacheFile = $cache_file;
    if ( is_readable($this->mCacheFile) ) {
      if ( $type === 'feed.xml' ) {
        @header('Content-Type: text/xml; charset="utf-8"');
      }
      @readfile($this->mCacheFile, FALSE);
      exit();
    }
    // 缓存准备
    @ob_start('self::_AutoCache');
    $this->mPageType = $type;
    $this->mObLevel  = @ob_get_level();
  }

  // 辅助函数：获取某种类型的所有文章
  public function GetArticlesByType($type, $order = NULL, $column = NULL)
  {
    static $cache = array();
    $cache_index = serialize(func_get_args());
    if ( isset($cache[$cache_index]) ) {
      return $cache[$cache_index];
    }
    $result = $this->mDB->Select('solog_contents', array(
      'where' => function ($record) use ($type) {
        return $record['type'] === $type;
      },
      'order' => $order,
      'column' => $column,
    ), TRUE);
    $cache[$cache_index] = $result;
    return $result;
  }
  // 辅助函数：获取某个 meta 的所有文章
  public function GetArticlesByMeta($type, $name, $order, $column)
  {
    static $cache = array();
    $cache_index = serialize(func_get_args());
    if ( isset($cache[$cache_index]) ) {
      return $cache[$cache_index];
    }
    $mids = $this->mDB->Select('solog_metas', array(
      'action' => 'UNI',
      'where' => function ($record) use ($type, $name) {
        if ( $record['type'] === 'category' ) {
          return strpos($record['name'] . '/', $name . '/') === 0;
        }
        if ( $record['type'] === 'tag' ) {
          return mb_strtolower($record['name']) === mb_strtolower($name);
        }
      },
      'column' => 'mid',
    ), TRUE);
    $cids = $this->mDB->Select('solog_relationships', array(
      'action' => 'UNI',
      'where' => function ($record) use ($mids) {
        return in_array($record['mid'], $mids, TRUE);
      },
      'column' => 'cid',
    ), TRUE);
    $result = $this->mDB->Select('solog_contents', array(
      'where' => function ($record) use ($cids) {
        return in_array($record['cid'], $cids, TRUE);
      },
      'order' => $order,
      'column' => $column,
    ), TRUE);
    $cache[$cache_index] = $result;
    return $result;
  }
  // 辅助函数：获取某个目录的所有文章
  public function GetArticlesByCategory($category, $order = NULL, $column = NULL)
  {
    return $this->GetArticlesByMeta('category', $category, $order, $column);
  }
  // 辅助函数：获取某个标签的所有文章
  public function GetArticlesByTag($tag, $order = NULL, $column = NULL)
  {
    return $this->GetArticlesByMeta('tag', $tag, $order, $column);
  }
  // 辅助函数：通过 URI 获取某种类型的唯一文章
  public function GetArticleByPath($type, $path, $column = NULL)
  {
    static $cache = array();
    $cache_index = serialize(func_get_args());
    if ( isset($cache[$cache_index]) ) {
      return $cache[$cache_index];
    }
    if ( $type === 'post' ) {
      $path_info = explode('/', $path, 2);
      $date = $path_info[0];
      $slug = preg_replace('/\\.html$/', '', $path_info[1] ?: '');
      $articles = $this->mDB->Select('solog_contents', array(
        'where' => function ($record) use ($type, $date, $slug) {
          if ( $record['type'] === $type
            and $record['slug'] === $slug
            and strftime('%Y-%m-%d', $record['created']) === $date )
          {
            return TRUE;
          }
        },
        'column' => $column,
      ), TRUE);
      $article = $articles[0];
    }
    if ( $type === 'index' ) {
      $slug = $path;
      $articles = $this->mDB->Select('solog_contents', array(
        'where' => function ($record) use ($type, $slug) {
          if ( $record['type'] === $type and $record['slug'] === $slug ) {
            return TRUE;
          }
        },
        'column' => $column,
      ), TRUE);
      $article = $articles[0];
    }
    $result = $article ?: array();;
    $cache[$cache_index] = $result;
    return $result;
  }
  // 辅助函数：获取（文章的）所有目录
  public function GetCategories($cid = NULL)
  {
    static $cache = array();
    $cache_index = serialize(func_get_args());
    if ( isset($cache[$cache_index]) ) {
      return $cache[$cache_index];
    }
    if ( !is_null($cid) ) {
      $mids = $this->mDB->Select('solog_relationships', array(
        'action' => 'UNI',
        'where' => function ($record) use ($cid) {
          return $record['cid'] === $cid;
        },
        'column' => 'mid',
      ), TRUE);
      $result = $this->mDB->Select('solog_metas', array(
        'action' => 'UNI',
        'where' => function ($record) use ($mids) {
          return in_array($record['mid'], $mids, TRUE)
                 and $record['type'] === 'category';
        },
        'order' => array('name' => SORT_ASC),
        'column' => 'name',
      ), TRUE);
    } else {
      $result = $this->mDB->Select('solog_metas', array(
        'where' => function ($record) {
          return $record['type'] === 'category';
        },
        'order' => array('name' => SORT_ASC),
      ), TRUE);
    }
    $cache[$cache_index] = $result;
    return $result;
  }
  // 辅助函数：获取（文章的）所有标签
  public function GetTags($cid = NULL)
  {
    static $cache = array();
    $cache_index = serialize(func_get_args());
    if ( isset($cache[$cache_index]) ) {
      return $cache[$cache_index];
    }
    if ( !is_null($cid) ) {
      $mids = $this->mDB->Select('solog_relationships', array(
        'action' => 'UNI',
        'where' => function ($record) use ($cid) {
          return $record['cid'] === $cid;
        },
        'column' => 'mid',
      ), TRUE);
      $result = $this->mDB->Select('solog_metas', array(
        'action' => 'UNI',
        'where' => function ($record) use ($mids) {
          return in_array($record['mid'], $mids, TRUE)
                 and $record['type'] === 'tag';
        },
        'order' => array('name' => SORT_ASC),
        'column' => 'name',
      ), TRUE);
    } else {
      $result = $this->mDB->Select('solog_metas', array(
        'where' => function ($record) {
          return $record['type'] === 'tag';
        },
        'order' => array('name' => SORT_ASC),
      ), TRUE);
    }
    $cache[$cache_index] = $result;
    return $result;
  }
  // 辅助函数：获取文章内容
  public function GetContent($file, $length = NULL)
  {
    static $cache = array();
    $cache_index = serialize(func_get_args());
    if ( isset($cache[$cache_index]) ) {
      return $cache[$cache_index];
    }
    $filename = $this->mCfg['dir_database'] . $file;
    if ( FALSE === ($fh = @fopen($filename, 'rb')) ) {
      $cache[$cache_index] = FALSE;
      return FALSE;
    }
    if ( FALSE === @flock($fh, LOCK_SH) ) {
      @fclose($fh);
      $cache[$cache_index] = FALSE;
      return FALSE;
    }
    if ( is_integer($length) ) {
      $content = @file_get_contents($filename, FALSE, NULL, 0, $length);
    } else {
      $content = @file_get_contents($filename, FALSE, NULL);
    }
    @flock($fh, LOCK_UN);
    @fclose($fh);
    $result = FALSE !== $content ? $content : '';
    $cache[$cache_index] = $result;
    return $result;
  }
  // 辅助函数：从字符串中获取摘要
  public function GetExcerptFromContent($content)
  {
    // 以 <!-- more --> 或 <a name="more"> 为摘要的结束符。
    preg_match('/^\\s*(.*?)<!--\\s*more\\s*-->/si', $content, $fragments);
    if ( !isset($fragments[1]) ) {
      preg_match('/^\\s*(.*?)<a[^>]+name="more"[^>]*>/si', $content, $fragments);
    }
    if ( isset($fragments[1]) ) {
      $excerpt = $fragments[1];
    } else {
      $content = trim(strip_tags($content));
      $excerpt = mb_substr($content, 0, 150);
      if ( mb_strlen($excerpt) > 149 ) {
        $excerpt .= ' ...';
      }
    }
    return $excerpt;
  }
  // 辅助函数：从文件中获取文章摘要
  public function GetExcerpt($file, $length = NULL)
  {
    $content = $this->GetContent($file, $length);
    return $this->GetExcerptFromContent($content);
  }
  // 辅助函数：获取某篇文章的前一篇（同目录的）文章
  public function GetPrevArticle($post, $category = NULL, $column = NULL)
  {
    static $cache = array();
    $cache_index = serialize(func_get_args());
    if ( isset($cache[$cache_index]) ) {
      return $cache[$cache_index];
    }
    $result = NULL;
    $order = array('created' => SORT_DESC);
    if ( !is_null($category) ) {
      $articles = $this->GetArticlesByCategory($category, $order, $column);
    } else {
      $articles = $this->GetArticlesByType('post', $order, $column);
    }
    foreach ( $articles as $article ) {
      if ( $article['created'] < $post['created'] ) {
        $result = $article;
        break;
      }
    }
    $cache[$cache_index] = $result;
    return $result;
  }
  // 辅助函数：获取某篇文章的后一篇（同目录的）文章
  public function GetNextArticle($post, $category = NULL, $column = NULL)
  {
    static $cache = array();
    $cache_index = serialize(func_get_args());
    if ( isset($cache[$cache_index]) ) {
      return $cache[$cache_index];
    }
    $result = NULL;
    $order = array('created' => SORT_DESC);
    if ( !is_null($category) ) {
      $articles = $this->GetArticlesByCategory($category, $order, $column);
    } else {
      $articles = $this->GetArticlesByType('post', $order, $column);
    }
    $prev_index = NULL;
    foreach ( $articles as $index => $article ) {
      if ( $article['created'] === $post['created'] ) {
        if ( !is_null($prev_index) ) {
          $result = $articles[$prev_index];
        }
        break;
      }
      $prev_index = $index;
    }
    $cache[$cache_index] = $result;
    return $result;
  }

  // 渲染首页
  abstract protected function _GoHome($path, $pageNum);
  // 渲染目录
  abstract protected function _GoCategory($path, $pageNum);
  // 渲染标签
  abstract protected function _GoTag($path, $pageNum);
  // 渲染博文归档
  abstract protected function _GoArchive($path, $pageNum);
  // 渲染博文页面
  abstract protected function _GoPost($path, $pageNum);
  // 渲染特殊页面
  abstract protected function _GoIndex($path, $pageNum);
  // 渲染订阅内容
  abstract protected function _GoFeed($path, $pageNum);
  // 渲染不存在页面
  abstract protected function _GoError($path, $pageNum);
}
?>
