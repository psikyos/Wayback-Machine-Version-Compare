<?php
//���ļ�����ӦΪANSI,����java�������
date_default_timezone_set("Asia/Shanghai");
define("DATA_DIR","./iphone/data");
define("DATA_HASH_DIR","./iphone/data_md5");
define("START_URL","https://www.apple.com/cn");
define("MAP_REDUCE_FILENAME","part-r-00000");//����������map reduceʱ,�ļ�������.�ļ�����php����ͬ��Ŀ¼��.
//***�����ǩʹ��
define("WAYBACK_REWRITE_JS","DOMContentLoaded");
define("WAYBACK_REWRITE_JS_END","<!-- End Wayback Rewrite JS Include -->");
define("CLEAN_SCRIPT_CONTENT_SIGN",1);//1ɾ��<script��</script>��ǩ֮��Ĵ���,����еĻ�,�����Ͳ���Ҫ����WAYBACK�������ǩ;0������.
define("SCRIPT_START_KEYWORD","<script");
define("SCRIPT_END_KEYWORD","</script>");
//***end of�����ǩʹ��
//����sleepʱ��?
?>
