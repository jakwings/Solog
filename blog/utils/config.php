<?php
$gSoCfg = array(
  // 博客首页链接（末尾不带斜杠 /）
  // 协议类型 http:// 或 https:// 会被自动添加到开头，请勿手动添加）
  'web_link' => 'example.com/blog',
  // 博主名称
  'web_author' => 'Soloist',
  // 博客标题
  'web_title' => '飲水思源，知恩圖報。',
  // 博客副标题
  'web_subtitle' => 'Welcome to my solo log!',
  // 博客在链接中的路径（若非根目录，末尾不带 /）
  'dir_root_rel' => '/blog',
  // 博客数据库在链接中的路径（末尾不带 / ，请勿使用根目录）
  'dir_database_rel' => '/blog/database',
  // 博客主题文件夹名称
  'web_theme' => 'example',
  // 博客时区，设定后请不要随意更改，否则会影响文章链接。
  // 可参考 http://www.php.net/manual/zh/timezones.php
  'web_timezone' => 'Asia/Shanghai',
  // 博客主要语言类型：code 或 code-subcode ，可留空。
  // code 可参考 http://www.loc.gov/standards/iso639-2/ISO-639-2_utf-8.txt
  // subcode 可参考 http://www.iso.org/iso/home/standards/country_codes/country_names_and_code_elements_txt.htm
  'web_language' => 'zh-cn',
  // 博客文字方向：ltr 或 rtl，可留空。
  'web_direction' => 'ltr',
  // 博客页面缓存时间（秒）
  'cache_lifetime' => 60 * 60 * 24 * 7,
  // 是否开启文章缓存机制
  'cache_enabled' => FALSE,
  // 是否开启调试模式（不懂别碰）
  'debug' => FALSE,
);
?>
