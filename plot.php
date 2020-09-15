<?php
/** map_reduce���ݸ���ͼ
 * ��Ҫ�������ļ���������С����
 * @author ����
 * @copyright 2020
 */
set_time_limit(0);
require_once ('jpgraph/jpgraph.php');
require_once ('jpgraph/jpgraph_gantt.php');
$filename="part-r-00000";

$graph=graph_init();

//process_date_time("20100816050046");
    
read_file($filename,$graph);

?>
<?php
//�����ļ�
function read_file($filename,$graph)
{
    $fp = fopen($filename, "r");
    $contents = fread($fp, filesize($filename));
    $lines_array=explode("\n",$contents);//������

    $data=array();
    $global_flag=0;
    $max_dt=process_date_time("19700101000000");
    $min_dt=$max_dt;//�����ļ��������С����ֵ
    for($i=0;$i<count($lines_array);$i++)
    {
        if(empty($lines_array[$i])) continue;   //�����򲻴���
        $one_line_array=explode("\t",$lines_array[$i]);
        $one_line_md5=$one_line_array[0];//md5ֵ
        
        $cnt_and_date=$one_line_array[1];//����������,һ���ı�
        $cd_array=explode(" ",$cnt_and_date);
        $one_line_date_cnt=$cd_array[0];//$cd_array[1]��֮��������
        
        array_shift($cd_array);//�Ƴ�0Ԫ��,����¼������
        //����$cd_array��0Ԫ����һ������
        $local_max_dt=process_date_time($cd_array[0]);$local_min_dt=$local_max_dt;//���е������С����
        if($global_flag==0)
        {
            $max_dt=process_date_time($cd_array[0]);
            $min_dt=$max_dt;
            $global_flag=1;
        }
        for($j=0;$j<count($cd_array);$j++)//����һ�е�����
        {
            if($cd_array[$j]!="")
            {
                $current_dt=process_date_time($cd_array[$j]);
                 
                if($max_dt<$current_dt) $max_dt=$current_dt;
                if($min_dt>$current_dt) $min_dt=$current_dt;
                if($local_max_dt<$current_dt)   $local_max_dt=$current_dt;
                if($local_min_dt>$current_dt)   $local_min_dt=$current_dt;
            }
        }
        $dt_interval=process_dt_class_diff($local_min_dt,$local_max_dt);//����,����
        $duration_text=sprintf("%d / %d",$one_line_date_cnt,$dt_interval);
                
        $data[$i][0]=$i;
        $data[$i][1]=array($one_line_md5,$duration_text,$local_min_dt->format('Y-m-d'),$local_max_dt->format('Y-m-d'));
        $data[$i][2]=$local_min_dt->format('Y-m-d');
        $data[$i][3]=$local_max_dt->format('Y-m-d');
        $data[$i][4]=FF_ARIAL;
        $data[$i][5]=FS_NORMAL;
        $data[$i][6]=8;
        if($dt_interval==0) 
            $fenmu=1;
        else
            $fenmu=$dt_interval;
        $data[$i][7]=$one_line_date_cnt/$fenmu;

        /*array_push($data,
        array($i,array($one_line_md5,$duration_text,$local_min_dt->format('Y-m-d'),$local_max_dt->format('Y-m-d'))
	      ,$local_min_dt->format('Y-m-d'),$local_max_dt->format('Y-m-d'),FF_ARIAL,FS_NORMAL,8) );
          */
    }
    graph_plot($graph,$data);
    //echo $max_dt->format('Y-m-d H:i:s')."\n";
    //echo date_format($min_dt,'Y-m-d H:i:s');
    $graph->SetDateRange($min_dt->format('Y-m-d'),$max_dt->format('Y-m-d'));
    fclose($fp);

    // Output line
    $graph->Stroke();
    //var_dump($data[0][1]);
}

/** ���$str_dt������20100816050046
 */ 
function process_date_time($str_dt)
{
    $year=substr($str_dt,0,4);    $mon=substr($str_dt,4,2);    $day=substr($str_dt,6,2);
    $hour=substr($str_dt,8,2); $minute=substr($str_dt,10,2);    $second=substr($str_dt,12,2);
    $str_formated_dt=sprintf("%s-%s-%s %s:%s:%s",$year,$mon,$day,$hour,$minute,$second);
    $the_datetime = new DateTime($str_formated_dt);
    //echo $the_datetime->format('Y-m-d H:i:s') . "\n";
    return $the_datetime;
}

/** ����ֵΪһ������,���ڼ������
 * ���$max_dt��$min_dtΪdatetime class.����process_date_time��ʼ��
 * $max_dt=process_date_time("19840128110000");$min_dt=process_date_time("19840127100000");
 * $max_dt����$min_dt
 */ 
function process_dt_class_diff($min_dt,$max_dt)
{
    //echo $max_dt->format("U");
    $date1=(int)$max_dt->format("U");//$date1=strtotime("1984-01-28 11:00:00");
    $date2=(int)$min_dt->format("U");//$date2=strtotime("1980-10-15 12:00:00");
    
    $diff=$date1-$date2;//�õ�unixʱ���,����Ϊ��λ
    $diff_days = (int)(($date1-$date2)/(24*3600));//�õ�����
    //echo $diff."\n";    echo "$ate1��$date2��ʱ���Ϊ��" . $diff_days . "��";
    return $diff_days;//�������߸���
}

function graph_plot($graph,$data)
{
    // Create the bars and add them to the gantt chart
    for($i=0; $i<count($data); ++$i) 
    {
        $percentage_text=sprintf("[%d%%]",$data[$i][7]*100);
    	$bar = new GanttBar($data[$i][0],$data[$i][1],$data[$i][2],$data[$i][3],$percentage_text,10);
    	if( count($data[$i])>4 )
    		$bar->title->SetFont($data[$i][4],$data[$i][5],$data[$i][6]);
    	$bar->SetPattern(BAND_RDIAG,"yellow");
    	$bar->SetFillColor("gray");        
        if($data[$i][7]<=1&&$data[$i][7]>=0)
    	   $bar->progress->Set($data[$i][7]);
        elseif($data[$i][7]>1)
            $bar->progress->Set(1);
        else
            $bar->progress->Set(0);
    	$bar->progress->SetPattern(GANTT_SOLID,"darkgreen");
    	$graph->Add($bar);
    }
}

function graph_init()
{
    $graph = new GanttGraph();
    
    $graph->title->Set("Only month & year scale");
    
    // Setup some "very" nonstandard colors
    $graph->SetMarginColor('lightgreen@0.8');
    $graph->SetBox(true,'yellow:0.6',2);
    $graph->SetFrame(true,'darkgreen',4);
    $graph->scale->divider->SetColor('yellow:0.6');
    $graph->scale->dividerh->SetColor('yellow:0.6');
    
    // Explicitely set the date range 
    // (Autoscaling will of course also work)
    //$graph->SetDateRange('2001-10-06','2010-4-10');
    
    // Display month and year scale with the gridlines
    $graph->ShowHeaders(GANTT_HMONTH | GANTT_HYEAR);
    $graph->scale->month->grid->SetColor('gray');
    $graph->scale->month->grid->Show(true);
    $graph->scale->year->grid->SetColor('gray');
    $graph->scale->year->grid->Show(true);
    
    
    // Setup activity info
    
    // For the titles we also add a minimum width of 100 pixels for the Task name column
    $graph->scale->actinfo->SetColTitles(
        array('Name','Points/Days','Start','Finish'),array(100));
    $graph->scale->actinfo->SetBackgroundColor('green:0.5@0.5');
    $graph->scale->actinfo->SetFont(FF_ARIAL,FS_NORMAL,10);
    $graph->scale->actinfo->vgrid->SetStyle('solid');
    $graph->scale->actinfo->vgrid->SetColor('gray');
    return $graph;
}
?>