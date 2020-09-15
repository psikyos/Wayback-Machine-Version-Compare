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
 * 1.�г�Ŀ¼�������ļ�
 * 2.ȥ������html��ǩ;����ת��pdf
 * 3.md5���е��ĵ�,�õ�һ��list;
 * 4.list����.�ṩһ��������map reduce����.
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
        //����hadoop��map reduce����
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
/** ������map reduce����
 */ 
function local_file_map_reduce($hash_dir,$mr_filename)
{
    $rt=list_file_for($hash_dir);//rt is an array
    map_reduce($rt,$mr_filename);
}

/** ToDo:δ��ʹ��redisʵ�ָ��õļ�������
 */ 
function map_reduce($rt,$mr_filename)
{
    $cnt_file=count($rt);
    $md5_array=array();
    $date_array=array();//��������ȫ����¼��һ��,�Կո�ָ�
    $cnt_array=array();
    //����3������ͬ����
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
        if($flag==1)//����Ѿ�����,���ۼӼ���
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
    //�����ļ�
    $save_content="";
    for($i=0;$i<count($md5_array);$i++)
    {
        $single_line=sprintf("%s\t%s %s\n",$md5_array[$i],$cnt_array[$i],$date_array[$i]);
        $save_content=$save_content.$single_line;
    }
    file_put_contents($mr_filename,$save_content);
}

/** ��Ŀ¼.����ȥ��html��ǩ,
 * ���$clean_script_sign��־Ϊ0,��ֻȥ��waybackǶ��ĺ�����script,����script���ݲ����;
 * ���$clean_script_sign��־Ϊ1,��������е�script����.
 */ 
function local_file_clean($basic_dir,$wayback_keyword,$wayback_keyword_end,
$clean_script_sign,$script_start_kw,$script_end_kw)
{
    $rt=array();    
    list_file_recursive($basic_dir,$rt);//�õ�Ŀ¼�µ������ļ�
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

/** �Ա����Ѿ������html��ǩ���ļ�����hash
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
        file_put_contents($hash_file_with_path,$content);//��һ���ļ�Ҳ��,��hadoop��map reduce��׼��
    }
}

/** ��ȥ��wayback������,��ʹ��strip_tagsȥ��html��ǩ
 * �˺�����ɾ��script��ǩ֮��Ĵ���,����wayback��script����
 */ 
function trim_html($file_with_path,$target_fwp,$wayback_keyword,$wayback_keyword_end)
{
    $converted_content=file_get_contents($file_with_path);
    //$converted_content=strip_tags($str_content);//converted_content��ȥ��html��ǩ����ַ���
    //remove original content:Wayback Rewrite JS Include
    //remove trimmed content:wayback's 'DOMContentLoaded line
    //clean����:�ҵ��ؼ�������λ��,��ǰ����������س���־,ɾ�������ڵ���
    $converted_len=strlen($converted_content);    
    $firstpos=strpos($converted_content,$wayback_keyword);//$firstpos=strpos($converted_content,"DOMContentLoaded");
    $cleaned_string="";
    if($firstpos!==false)//find marker
    {        
        //previous return
        //strrpos, offset, If the value is negative, search will instead start from that many characters from the end of the string, searching backwards.
        //offset-1�������Ѱ��,offset�ľ���ֵ������ļ�ĩβ��ʼ�����ƫ����
        $former_carriage_return_pos=strrpos($converted_content,"\n",(-1)*($converted_len-$firstpos ) );
        //middle sign,�ҵ�<!-- End Wayback Rewrite JS Include -->
        $middle_sign_pos=strpos($converted_content,$wayback_keyword_end,$firstpos);
        //next return of middle sign
        $later_carriage_return_pos=strpos($converted_content,"\n",$middle_sign_pos);
        if($former_carriage_return_pos!==false && $later_carriage_return_pos!==false)
        {
            //fetch the string between former and next return
            $removed_string=substr($converted_content,$former_carriage_return_pos,$later_carriage_return_pos-$former_carriage_return_pos);
            //echo $removed_string;//removed_string������ǰ���һ���س�
            //repalce,���մ洢cleaned_string
            $cleaned_string=str_replace($removed_string,"",$converted_content);//clean_string�ǳ�ȥDOMContentLoaded�����е��ַ���         
        }
    }
    $str_content=strip_tags($cleaned_string);//converted_content��ȥ��html��ǩ����ַ���
    file_put_contents($target_fwp,$str_content);
}

/** 1.�Ȱ��ļ�����תСд;
 * 2.ȥ�����е�<script��</script>֮�������,������ǩ;
 * 3.ʹ��phpstrip_tags����ȥ��html��ǩ
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
    $str_content=strip_tags($converted_content);//str_content��ȥ��html��ǩ����ַ���
    file_put_contents($target_fwp,$str_content);
}

function list_file_recursive($basic_dir,&$rt)//�ݹ�����Ŀ¼�������ļ�
{
    //��Ŀ¼
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

//��Ŀ¼,�ǵݹ鷽ʽ
function list_file_for($basic_dir)
{
    $dirs=array($basic_dir);//dirs��Ϊһ��ֻ��һ��Ԫ�ص�����
    $rt=array();// all files in it
    do
    {
        //��ջ
        $single_dir=array_pop($dirs);
        //echo $single_dir." single_dir\n";
        //����һ��Ŀ¼
        $tmp_dir_arr=array_reverse( scandir($single_dir) );
        //var_dump($tmp_dir);
        foreach($tmp_dir_arr as $file)
        {
            if ($file != "." && $file != "..") //strip out . and ..
            {
                $file_with_path=sprintf("%s/%s",$single_dir,$file);
                if(is_dir($file_with_path))
                {
                    //ѹջ
                    array_push($dirs,$file_with_path);
                }
                else
                {                    
                    $rt[]=$file_with_path;
                }
            }
        }
    }while($dirs);//ֱ��ջ��û��Ŀ¼
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
    
    curl_setopt($ch, CURLOPT_MAXREDIRS,20); //������ת�Ĵ���,curl��������curl -L ��ʵ��
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);//�Զ�������תץȡ
   
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

/** ������ǰһ�뽫select_year�������õ��ĵ�json�ַ���$source,�����ַ�������ts��ؿ�ʼ��λ�ã���Ϊʱ�����ע���е�ʱ�����ĳ��ɼ�������
 * ��ʱ�õ�$tsarr����,ע���������е�Ԫ�ش���$token_keyword�ļ���ʱ���
 * ��һ�뺯���������$token_keyword��ʱ���,�õ�$ts_final_arr
 * $token_keywordͨ���Զ���,Ϊ�ָ���
 * ����������󷵻ص�Ӧ���ǲ����û�ж��ŵ�ʱ�������$ts_final_arr
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
             if($lastpos!==false)   //��ֹ���ֻ�������,��ts��־��û����]��β
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

/** ��ʼ��curl
 * ����ֵ����curl_init��ʼ����ch,������һ�������,���ʧ��chΪfalse
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
    //date_default_timezone_set('Asia/Shanghai'); //���Ӵ����,���ո�������ʱ����ʾ
	//$fp=fopen(WEBSITE_ROOT."debug.txt","a+");
    $fp=fopen("debug.txt","a+");
	$finalmsg=sprintf("%s %s\r\n",date("Y-m-d H:i:s",time()),$msg);
	fwrite($fp,$finalmsg,strlen($finalmsg));
	fclose($fp);
}
?>