<?php
/* 配置示例
title       : "博客开张"
slug        : new-blog   
categories  : 博客事宜
tags        : [ 不容易, Javascript, NodeJS, "a b" ]
created     : 2013-04-03 12:28:10 +08:00
modified    : 2013-04-04 07:19:51 +08:00
comment     : true
feed        : true
order       :
hi          : lol
type        : post
*/

// 文章配置信息解析器
function parse_meta($content)
{
  $metas = array();
  $valid_options = array(
    // 属于文章内容表
    'type', 'title', 'slug', 'created', 'modified', 'comment', 'feed', 'order',
    // 属于附加信息表
    'categories', 'tags'
  );
  // 只获取所有语法正确的信息
  preg_match_all('/^(\\w+)\\s*:(.*)$/m', $content, $options, PREG_PATTERN_ORDER);
  // $options [0]-行内容 [1]-选项名 [2]-选项值
  foreach ( $options[1] as $index => $item_name ) {
    $item_name = strtolower($item_name);
    if ( !in_array($item_name, $valid_options, TRUE) ) {
      continue;
    }
    $item_value = trim($options[2][$index]);
    if ( preg_match('/^"(.*)"$/', $item_value, $match) ) {
      $metas[$item_name] = $match[1];
      continue;
    }
    if ( strtolower($item_value) === 'true' ) {
      $metas[$item_name] = TRUE;
      continue;
    }
    if ( strtolower($item_value) === 'false' ) {
      $metas[$item_name] = FALSE;
      continue;
    }
    if ( preg_match('/^\\[(.*)\\]$/', $item_value, $match) ) {
      $item_value = array_map('trim', mb_split(',', $match[1]));
      foreach ( $item_value as $key => $value ) {
        if ( preg_match('/^"(.*)"$/', $value, $match) ) {
          $item_value[$key] = $match[1];
        }
      }
      $metas[$item_name] = $item_value;
      continue;
    }
    $metas[$item_name] = $item_value;
  }
  return $metas;
}
?>
