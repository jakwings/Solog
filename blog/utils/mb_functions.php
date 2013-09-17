<?php
// 不需要的函数。
function mb_language() {}
function mb_internal_encoding() {}
function mb_regex_encoding() {}
function mb_regex_set_options() {}

// 替换为只支持单字节的函数
function mb_strlen($str, $encoding = NULL)
{
  $args = array_slice(func_get_args(), 0, 1);
  return call_user_func_array('strlen', $args);
}
function mb_strtolower($str, $encoding = NULL)
{
  $args = array_slice(func_get_args(), 0, 1);
  return call_user_func_array('strtolower', $args);
}
function mb_substr($str, $start, $length = NULL, $encoding = NULL)
{
  $args = array_slice(func_get_args(), 0, 3);
  return call_user_func_array('substr', $args);
}
function mb_split($pattern, $string, int $limit = -1)
{
  $args = array_slice(func_get_args(), 0, 3);
  return call_user_func_array('explode', func_get_args());
}
function mb_ereg_replace($pattern, $replacement, $string, $option = 'msr')
{
  $args = array_slice(func_get_args(), 0, 3);
  return call_user_func_array('preg_replace', $args);
}
?>
