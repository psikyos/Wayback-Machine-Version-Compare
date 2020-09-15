<?php
//此文件编码应为ANSI,以免java处理出错
date_default_timezone_set("Asia/Shanghai");
define("DATA_DIR","./iphone/data");
define("DATA_HASH_DIR","./iphone/data_md5");
define("START_URL","https://www.apple.com/cn");
define("MAP_REDUCE_FILENAME","part-r-00000");//单机版运行map reduce时,文件名设置.文件将在php程序同级目录下.
//***清理标签使用
define("WAYBACK_REWRITE_JS","DOMContentLoaded");
define("WAYBACK_REWRITE_JS_END","<!-- End Wayback Rewrite JS Include -->");
define("CLEAN_SCRIPT_CONTENT_SIGN",1);//1删除<script和</script>标签之间的代码,如果有的话,这样就不需要处理WAYBACK的特殊标签;0不处理.
define("SCRIPT_START_KEYWORD","<script");
define("SCRIPT_END_KEYWORD","</script>");
//***end of清理标签使用
//增加sleep时间?
?>
