<?php
/** Wayback Machine version analysis tool
 * care https://archive.org/help/wayback_api.php to know the latest api, prepare the correct URL form
 * targeturl:https://wiki.apache.org/nutch/NutchTutorial
 * @copyright PSIKYO Corp. 2018
 */
include("config.php");
$url=START_URL;
//***basic directory process
$save_dir=sprintf("%s/%s",dirname(__FILE__),DATA_DIR);
define("BASE_DIR",$save_dir); //in case the directory variable is changed
echo "base directory : ".BASE_DIR."\n";
if(!file_exists(BASE_DIR))//make sure the base directory exists
    mkdir(BASE_DIR,0700);
if(!file_exists(DATA_HASH_DIR))//make sure the data hash directory exists
    mkdir(DATA_HASH_DIR,0700);
//***end of basic directory process

//php cli command parse,total 3 phase
//echo $_SERVER["argc"]."\n";//parameter count //echo $_SERVER["argv"][1];
if($_SERVER["argc"]==1)//show the menu
{
    echo "show the menu"."\n";
}
else if ($_SERVER["argc"]>1)//parameter follow 
{
    if($_SERVER["argv"][1]=="-down")
    {
        //***download phase
        $ch=wx_curl_init($errmsg);
        //fetch_by_timestamp($ch,$url,"2018","20180719032557");//debug for www.baidu.com
        $ymarr=year_month($ch,$url);
        $years_arr=year_process($ymarr);
        select_years($ch,$url,$years_arr,BASE_DIR);
        //***end of download phase        
    }
/** analysis phase
 * 1.列出目录下所有文件
 * 2.去除所有html标签;或者转成pdf
 * 3.md5所有的文档,得到一个list;
 * 4.list查重.提供一个单机版map reduce程序.
*/
    if($_SERVER["argv"][1]=="-clean")
    {
        //***clean html tag and wayback js phase
        local_file_clean(BASE_DIR,WAYBACK_REWRITE_JS,//WAYBACK_REWRITE_JS="DOMContentLoaded";
        WAYBACK_REWRITE_JS_END,
        CLEAN_SCRIPT_CONTENT_SIGN,SCRIPT_START_KEYWORD,SCRIPT_END_KEYWORD);
        //***end of clean html tag and wayback js phase       
    }
    if($_SERVER["argv"][1]=="-hash")
    {
        //***file hash phase
        local_file_hash(BASE_DIR,DATA_HASH_DIR);
        //放入hadoop的map reduce分析
        //***end of file hash phase
    }
    if($_SERVER["argv"][1]=="-mapreduce")
    {
        local_file_map_reduce(DATA_HASH_DIR,MAP_REDUCE_FILENAME);//MAP_REDUCE_FILENAME="part-r-00000"
    }
}
?>
<?php
//local file process functions
/** 单机版map reduce过程
 */ 
function local_file_map_reduce($hash_dir,$mr_filename)
{
    $rt=list_file_for($hash_dir);//rt is an array
    map_reduce($rt,$mr_filename);
}

/** ToDo:未来使用redis实现更好的集合性能
 */ 
function map_reduce($rt,$mr_filename)
{
    $cnt_file=count($rt);
    $md5_array=array();
    $date_array=array();//所有日期全部记录在一起,以空格分隔
    $cnt_array=array();
    //以上3个数组同索引
    for($i=0;$i<$cnt_file;$i++)
    {
        $file_with_path=$rt[$i];
        echo $file_with_path."\n";
        $str_content=file_get_contents($file_with_path);
        $one_line_array=explode("\t",$str_content);
        $md5=$one_line_array[0];
        $the_date=$one_line_array[1];
        $flag=0;
        $j=0;
        for($j=0;$j<count($md5_array);$j++)
        {
            if($md5==$md5_array[$j])
            {
                $flag=1;
                break;
            }
        }
        if($flag==1)//如果已经存在,则累加计数
        {
            //$md5_array[$j]==$md5;
            $cnt_array[$j]+=1;
            $single=sprintf("%s %s",$date_array[$j],$the_date);
            $date_array[$j]=$single;
        }
        else        
        {
            array_push($md5_array,$md5);
            array_push($cnt_array,1);
            array_push($date_array,$the_date);            
        }
    }
    //存入文件
    $save_content="";
    for($i=0;$i<count($md5_array);$i++)
    {
        $single_line=sprintf("%s\t%s %s\n",$md5_array[$i],$cnt_array[$i],$date_array[$i]);
        $save_content=$save_content.$single_line;
    }
    file_put_contents($mr_filename,$save_content);
}

/** 列目录.并且去除html标签,
 * 如果$clean_script_sign标志为0,则只去除wayback嵌入的函数的script,其余script内容不清除;
 * 如果$clean_script_sign标志为1,则清除所有的script内容.
 */ 
function local_file_clean($basic_dir,$wayback_keyword,$wayback_keyword_end,
$clean_script_sign,$script_start_kw,$script_end_kw)
{
    $rt=array();    
    list_file_recursive($basic_dir,$rt);//得到目录下的所有文件
    $cnt_file=count($rt);
    if($clean_script_sign==1)
    {
        for($i=0;$i<$cnt_file;$i++)
        {   
            $file_with_path=$rt[$i];
            echo $file_with_path."\n";        
            trim_html_script($file_with_path,$file_with_path,$script_start_kw,$script_end_kw);//overwrite the file
        }
    }
    else
    {
        for($i=0;$i<$cnt_file;$i++)
        {   
            $file_with_path=$rt[$i];
            echo $file_with_path."\n";        
            trim_html($file_with_path,$file_with_path,$wayback_keyword,$wayback_keyword_end);//overwrite the file
            //if($i==0) break;
        }
    }
}

/** 对本地已经清除了html标签的文件计算hash
 */ 
function local_file_hash($basic_dir,$hash_dir)
{
    $rt=list_file_for($basic_dir);//rt is an array
    $cnt_file=count($rt);
    for($i=0;$i<$cnt_file;$i++)
    {   
        $file_with_path=$rt[$i];        
        $onlyfn=basename($file_with_path);
        $file_md5_value=md5_file($file_with_path);
        $content=sprintf("%s\t%s",$file_md5_value,$onlyfn);//the file content
        $hash_file_with_path=sprintf("%s/%s",$hash_dir,$onlyfn);//for hadoop 1.0.1,not support recursive subdirectory
        echo $hash_file_with_path."=".$content."\n";
        file_put_contents($hash_file_with_path,$content);//给一个文件也行,给hadoop的map reduce做准备
    }
}

/** 先去除wayback特殊标记,再使用strip_tags去除html标签
 * 此函数不删除script标签之间的代码,除了wayback的script内容
 */ 
function trim_html($file_with_path,$target_fwp,$wayback_keyword,$wayback_keyword_end)
{
    $converted_content=file_get_contents($file_with_path);
    //$converted_content=strip_tags($str_content);//converted_content是去除html标签后的字符串
    //remove original content:Wayback Rewrite JS Include
    //remove trimmed content:wayback's 'DOMContentLoaded line
    //clean方法:找到关键字所在位置,向前和向后搜索回车标志,删除其所在的行
    $converted_len=strlen($converted_content);    
    $firstpos=strpos($converted_content,$wayback_keyword);//$firstpos=strpos($converted_content,"DOMContentLoaded");
    $cleaned_string="";
    if($firstpos!==false)//find marker
    {        
        //previous return
        //strrpos, offset, If the value is negative, search will instead start from that many characters from the end of the string, searching backwards.
        //offset-1代表向后寻找,offset的绝对值代表从文件末尾开始计算的偏移量
        $former_carriage_return_pos=strrpos($converted_content,"\n",(-1)*($converted_len-$firstpos ) );
        //middle sign,找到<!-- End Wayback Rewrite JS Include -->
        $middle_sign_pos=strpos($converted_content,$wayback_keyword_end,$firstpos);
        //next return of middle sign
        $later_carriage_return_pos=strpos($converted_content,"\n",$middle_sign_pos);
        if($former_carriage_return_pos!==false && $later_carriage_return_pos!==false)
        {
            //fetch the string between former and next return
            $removed_string=substr($converted_content,$former_carriage_return_pos,$later_carriage_return_pos-$former_carriage_return_pos);
            //echo $removed_string;//removed_string带走了前面的一个回车
            //repalce,最终存储cleaned_string
            $cleaned_string=str_replace($removed_string,"",$converted_content);//clean_string是除去DOMContentLoaded所在行的字符串         
        }
    }
    $str_content=strip_tags($cleaned_string);//converted_content是去除html标签后的字符串
    file_put_contents($target_fwp,$str_content);
}

/** 1.先把文件内容转小写;
 * 2.去除所有的<script和</script>之间的内容,包括标签;
 * 3.使用phpstrip_tags函数去除html标签
 */ 
function trim_html_script($file_with_path,$target_fwp,$start_kw,$end_kw)
{
    $str_content=file_get_contents($file_with_path);
    $converted_content=strtolower($str_content);//convert all content in lower case
    //remove all content between script tags, in lower case
    $converted_len=strlen($converted_content);
    //$start_kw="<script";$end_kw="</script>";
    $firstpos=strpos($converted_content,$start_kw);//$firstpos=strpos($converted_content,"<script");
    $cleaned_string="";
    while($firstpos!==false)//find marker
    {        
        $nextpos=strpos($converted_content,$end_kw,$firstpos);//find the end position of </script>
        if($nextpos!==false)
        {
            $removed_string=substr($converted_content,$firstpos,$nextpos+strlen($end_kw)-$firstpos);
            //echo $removed_string."\n";
            $cleaned_string=str_replace($removed_string,"",$converted_content);
            $converted_content=$cleaned_string;
        }
        $firstpos=strpos($converted_content,$start_kw);
    }
    $str_content=strip_tags($converted_content);//str_content是去除html标签后的字符串
    file_put_contents($target_fwp,$str_content);
}

function list_file_recursive($basic_dir,&$rt)//递归列子目录下所有文件
{
    //列目录
    if($rt==null)
        $rt=array();
    if ($handle = opendir($basic_dir)) 
    {
        //This is the correct way to loop over the directory.
        while (false !== ($file = readdir($handle))) //file variable is only the name of file or directory
        {
            if ($file != "." && $file != "..") //strip out . and ..
            {
                $file_with_path=sprintf("%s/%s",$basic_dir,$file);
                //identity directory and file
                if(is_dir($file_with_path))
                {
                    list_file_recursive($file_with_path,$rt);
                }
                else
                {                    
                    $rt[]=$file_with_path;                    
                }
            }
        }
        closedir($handle);
    }
}

//列目录,非递归方式
function list_file_for($basic_dir)
{
    $dirs=array($basic_dir);//dirs成为一个只有一个元素点数组
    $rt=array();// all files in it
    do
    {
        //弹栈
        $single_dir=array_pop($dirs);
        //echo $single_dir." single_dir\n";
        //处理一个目录
        $tmp_dir_arr=array_reverse( scandir($single_dir) );
        //var_dump($tmp_dir);
        foreach($tmp_dir_arr as $file)
        {
            if ($file != "." && $file != "..") //strip out . and ..
            {
                $file_with_path=sprintf("%s/%s",$single_dir,$file);
                if(is_dir($file_with_path))
                {
                    //压栈
                    array_push($dirs,$file_with_path);
                }
                else
                {                    
                    $rt[]=$file_with_path;
                }
            }
        }
    }while($dirs);//直到栈中没有目录
    return $rt;
}
?>
<?php
//download functions
function fetch_by_timestamp(&$ch,$targeturl,$one_year,$one_timestamp,$base_dir)
{
    $page_url=sprintf("http://web.archive.org/web/%sif_/%s",$one_timestamp,$targeturl);
    echo $page_url."\n";
    
    curl_setopt($ch,CURLOPT_URL,$page_url);
    curl_setopt($ch,CURLOPT_REFERER,'"http://web.archive.org/');
    
    curl_setopt($ch, CURLOPT_MAXREDIRS,20); //允许跳转的次数,curl命令中用curl -L 来实现
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);//自动进行跳转抓取
   
    curl_setopt($ch,CURLOPT_USERAGENT,"waybackMachine version analysis tool");
    //curl_setopt($ch, CURLOPT_HTTP_VERSION , CURL_HTTP_VERSION_1_0  ); //use http 1.0    
    $data=curl_exec($ch) or die(curl_error($ch));
    //echo $data."\n";
    
    //base_directory+year+timestamp
    $directory=sprintf("%s/%s",$base_dir,$one_year);
    //make sure the directory exists
    if(!file_exists($directory))
        mkdir($directory,0700);    
    $file_with_path=sprintf("%s/%s",$directory,$one_timestamp);
    file_put_contents($file_with_path,$data);
}

function select_years(&$ch,$targeturl,$years_arr,$base_dir)
{
    $cnt_years=count($years_arr);
    $current_year=$years_arr[$cnt_years-1];
    for($i=0;$i<$cnt_years;$i++)    //for($i=$cnt_years-1;$i<$cnt_years;$i++)
    {
        $current_year=$years_arr[$i];
        $ts_one_year_arr=time_stamp_one_year($ch,$targeturl,$current_year);
        $cnt_ts=count($ts_one_year_arr);
        for($j=0;$j<$cnt_ts;$j++)
        {
            $one_time_stamp=$ts_one_year_arr[$j];
            fetch_by_timestamp($ch,$targeturl,$current_year,$one_time_stamp,$base_dir);
            sleep(2);//2 seconds
        }
        sleep(2);
    }
}

function time_stamp_one_year($ch,$targeturl,$one_year)
{
    //one year time stamp process
    $composited_url=sprintf("http://web.archive.org/__wb/calendarcaptures?url=%s&selected_year=%s",$targeturl,$one_year);    
    echo $composited_url."\n";
    curl_setopt($ch,CURLOPT_URL,$composited_url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
    $data=curl_exec($ch) or die(curl_error($ch));
    
    //new thought: search ts as keyword in data variable
    //"ts":[20180227203422,20180227220116]
    //"ts":[20180319193530]
    //since now,we get the latest year json,search "ts": as start keyword, the ] as end keyword    
    $start_keyword="\"ts\":[";  $end_keyword="]";   $token_keyword=",";
    //$ts_one_year_arr=get_time_stamp_in_year($data,$start_keyword,$end_keyword,$token_keyword);
    return get_time_stamp_in_year($data,$start_keyword,$end_keyword,$token_keyword);
}

/** 本函数前一半将select_year操作所得到的的json字符串$source,搜索字符串里以ts相关开始的位置，作为时间戳，注意有的时间戳是某天采集了许多次
 * 此时得到$tsarr数组,注意数组中有的元素带有$token_keyword的几个时间戳
 * 后一半函数即处理带$token_keyword的时间戳,得到$ts_final_arr
 * $token_keyword通常以逗号,为分隔符
 * 整个函数最后返回的应该是拆解后的没有逗号的时间戳数组$ts_final_arr
*/
function get_time_stamp_in_year($source,$start_keyword,$end_keyword,$token_keyword)
{
    $tsarr=array();
    $len_start_kw=strlen($start_keyword);    //$len_end_kw=strlen($end_keyword);
    $len_sournce=strlen($source);
    for($i=0,$offset=0;$offset<$len_sournce;$i++)
    {
        $firstpos=strpos($source,$start_keyword,$offset);
        if($firstpos!==false)
        {
             $lastpos=strpos($source,$end_keyword,$firstpos);
             if($lastpos!==false)   //防止出现畸形数据,即ts标志后没有以]结尾
             {
                 $lenth=$lastpos-$firstpos-$len_start_kw;
                 $single_ts=substr($source,$firstpos+$len_start_kw,$lenth);
                 $tsarr[$i]=$single_ts;
                 $offset=$lastpos;
             }
             else
                break;
        }
        else
            break;
    }    
    //writelog(var_export($tsarr,true));
        
    //split some ts seperated in comma
    $cnt_ts=count($tsarr);
    $ts_final_arr=array();
    for($i=0,$index=0;$i<$cnt_ts;$i++)//check all the array elements,$index for $ts_final_arr; $i for $tsarr
    {
        $line_element=$tsarr[$i];
        $firstpos=strpos($line_element,$token_keyword,0);
        if($firstpos===false)
        {
            $ts_final_arr[$index]=$line_element;
            $index++;
        }
        else    //similar: $tsarr[10]='20180404205451,20180404210109,20180404220035,20180404230404'
        {
            $temp_arr=explode($token_keyword,$line_element);
            $cnt_tmep=count($temp_arr);
            for($j=0;$j<$cnt_tmep;$j++,$index++)
            {
                $ts_final_arr[$index]=$temp_arr[$j];
            }
        }
    }
    //file_put_contents("test.txt",var_export($ts_final_arr,true),FILE_APPEND);
    return $ts_final_arr;
}

function year_process($ymarr)
{
    //$ymarr["first_ts"]="20130503140310"
    //$cnt_years=count($ymarr["years"]);//the year count
    $start_year=substr($ymarr["first_ts"],0,4);//truncate the first 4 digit is start year.
    $last_year=substr($ymarr["last_ts"],0,4);    
    $years_arr=array();
    for($i=$start_year,$index=0;$i<=$last_year;$i++,$index++)
    {
        $years_arr[$index]=$i;//one year
        //echo $i."\n";
    }
    return $years_arr;
}

function year_month(&$ch,$url)
{    
//    $ch=wx_curl_init($errmsg);
    $composited_url=sprintf("http://web.archive.org/__wb/sparkline?url=%s&collection=web&output=json",$url);    
    curl_setopt($ch,CURLOPT_URL,$composited_url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
    $data=curl_exec($ch) or die(curl_error($ch));    
    $ymarr=json_decode($data,true); //make sure return as array
    return $ymarr;
    //echo $dtarr["years"][2018][0];
    //var_dump($dtarr);
}

/** 初始化curl
 * 返回值是由curl_init初始化的ch,类似于一个浏览器,如果失败ch为false
*/
function wx_curl_init(&$errmsg)
{
    if(!function_exists("curl_init"))
    {
        $errmsg="curl function does not exists.";
        return false;
    }
    $ch=curl_init();
    if(curl_error($ch))
    {
        $errmsg=curl_error($ch);
        return false;
    }
    else
        return $ch;
}

function writelog($msg)
{
    //file_put_contents($outfile,var_dump($sql),FILE_APPEND);
    //date_default_timezone_set('Asia/Shanghai'); //不加此语句,则按照格林威治时间显示
	//$fp=fopen(WEBSITE_ROOT."debug.txt","a+");
    $fp=fopen("debug.txt","a+");
	$finalmsg=sprintf("%s %s\r\n",date("Y-m-d H:i:s",time()),$msg);
	fwrite($fp,$finalmsg,strlen($finalmsg));
	fclose($fp);
}
?>