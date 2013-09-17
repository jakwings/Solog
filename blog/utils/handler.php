<?php
class RequestHandler
{
  public function Support($type)
  {
    $valid_types = array(
      '', 'i', 'posts', 'feed.xml', 'archives', 'categories', 'tags'
    );
    return in_array($type, $valid_types, FALSE);
  }
  public function Handle($type, $path, $pageNum)
  {
    $theme_file = $GLOBALS['gSoCfg']['dir_theme'] . '/theme.php';
    if ( is_readable($theme_file) ) {
      include_once $GLOBALS['gSoCfg']['dir_root'] . '/utils/renderer.php';
      include_once $theme_file;
      $renderer = new Theme();
      $renderer->Render($type, $path, $pageNum);
    } else {
      catch_error(503, '博客主题配置错误！');
    }
  }
}
