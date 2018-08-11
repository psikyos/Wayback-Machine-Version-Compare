Wayback Machine Version Compare
时光机版本分析器使用说明
故事：对于你的代码，有svn可以帮你维护版本信息。对于互联网上的一个URL的文档，如何知道它有多少个版本呢？本项目可解决这个问题。
目标：从时光机上取回某URL的所有抓取快照，分析出所有的不重复的版本。
语种：php5.0及以上，java(for hadoop)
操作:
	以分析Apache Nutch的官方教程的版本为例，URL是https://wiki.apache.org/nutch/NutchTutorial.
1.预处理
	修改config.php文件,找到START_URL行，改成如下：
define("START_URL","https://wiki.apache.org/nutch/NutchTutorial");
	常量DATA_DIR是下载目录所在的位置，也是清除html标签后所在的位置。常量DATA_HASH_DIR是清除html完成后，送入hadoop处理的目录。
1.下载
	修改完config.php后,在shell中输入以下命令，将从时光机上下载START_URL的所有快照。
php webarchive.php -down
	此下载如果中途断开或者失败，请寻求Github上waybackMachine Downloader项目来代替。下载完成后，将按照年份存储所有快照。目录结构应该像如下：
	DATA_DIR/2006/20060529210949
	其中，20060529210949为文档名，存储了下载的快照。
2.清除html标签
	在执行此命令前，如果需要单独保存已下载的快照，请复制DATA_DIR目录到其它目录。在shell中输入以下命令，以清除html标签和wayback的专用js。
php webarchive.php -clean
	命令完成后，DATA_DIR里的文档将被除去html标签和js。
3.计算文档hash
	在第2步的基础上，计算文档hash值。在shell中输入以下命令。
php webarchive.php -hash
	所有的文档hash将被存储到DATA_HASH_DIR目录下，如文档DATA_HASH_DIR/20060529210949的内容为：
	9de7738f267f4dd33cbc83fc325a879c	20060529210949
	为照顾使用Hadoop1.0等老版本的用户，DATA_HASH_DIR下存储了所有文档，没有子目录。
4.在Hadoop上汇总结果
	建立目录wordcount_classes。
mkdir wordcount_classes
	编译项目里的VersoinCount.java为jar包。
java -d wordcount_classes VersoinCount.java
	将目录wordcount_classes打包为jar。
jar -cvf VersionCount.jar -C wordcount_classes/ .
	将DATA_HASH_DIR上传到Hadoop的hdfs上.假设shell中位于hadoop项目的根目录。
bin/hadoop dfs -copyFromLocal data_md5 data_md5_inpupt
	运行map reduce过程。
bin/hadoop jar VersionCount.jar VersionCount data_md5_input dm5_output
	将map reduce过程的输出拿到本地分析。
bin/hadoop dfs -get dm5_output/part-r-00000 /root/
5.通过part-r-00000查看所有版本
	其中，文件内容如下：
hash值 具有该值的文档数量n  n个该文档的名字,以时间为文档名
2034113c2f07246bff3fe7fb30923c2e	2 1 20111126015326  1 20111203064946  
	即文档20111126015326和20111203064946具有相同的hash值2034113c2f07246bff3fe7fb30923c2e，所以数量是2。即这2个快照内容是一样的。
6.手工去wayback Machine上下载需要的版本
全文完。
