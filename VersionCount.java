import java.io.IOException;
import java.util.*;

import org.apache.hadoop.fs.Path;
import org.apache.hadoop.conf.*;
import org.apache.hadoop.io.*;
import org.apache.hadoop.mapreduce.*;
import org.apache.hadoop.mapreduce.lib.input.*;
import org.apache.hadoop.mapreduce.lib.output.*;
import org.apache.hadoop.util.*;

public class VersionCount extends Configured implements Tool
{
    //Map class
    public static class Map extends Mapper<LongWritable, Text, Text, Text>
    {
            public void map(LongWritable key,Text value, Context context) throws IOException, InterruptedException
            {
                    String line = value.toString();
                    //以“\n”为分隔符,将输入的文件分割成单个记录
                    StringTokenizer tokenizerArticle = new StringTokenizer(line,"\n");
                    //对每个记录处理
                    while(tokenizerArticle.hasMoreTokens())
                    {
                            //将每个记录分成MD5值和数量1两部分.
                            StringTokenizer tokenizerLine=new StringTokenizer(tokenizerArticle.nextToken());//默认分割符:空格、制表符\t，换行符\n，回车符\r
                            while(tokenizerLine.hasMoreTokens())
                            {
                                    String strHash= tokenizerLine.nextToken();
                                    if(tokenizerLine.hasMoreTokens())
                                    {
                                        String strDate=tokenizerLine.nextToken();
                                        Text hash=new Text(strHash); //MD5值
                                        Text the_date=new Text(strDate);
                                        //int scoreInt =Integer.parseInt((strScore));//MD5值的数量,应该是1 //context.write(name, new IntWritable(scoreInt));
                                        //context.write(hash, new IntWritable(1));
                                        context.write(hash,the_date);
                                    }
                            }
                    }
            }//end of map method
    }//end of class Map
    
    //Reduce class
    public static class Reduce extends Reducer<Text, Text, Text, Text>
    {
        public void reduce(Text key, Iterable<Text> values, Context context) throws IOException, InterruptedException
        {
            String singleDate;
            StringBuilder str_container=new StringBuilder();//组合用的string
            int count=0;
            Iterator<Text> iterator=values.iterator();
            while(iterator.hasNext())
            {
                singleDate=iterator.next().toString();
                //组合字符
                str_container.append(singleDate+" ");
                count++;
            }
            //int average=(int) sum/count;
            //context.write(key,new IntWritable(average));
            String str_output=String.format("%d %s",count,str_container.toString());
            Text result=new Text(str_output);
            context.write(key,result);
        }
    }//end of Reduce class
    
    //run function
    public int run(String []args)throws Exception
    {
        
	Configuration conf = getConf();
        //Job job=new Job(getConf());
        Job job=new Job(conf,"VersionCount");
        //conf.set("mapreduce.input.fileinputformat.input.dir.recursive",true);
        job.setJarByClass(VersionCount.class);
        //job.setJobName("VersionCount");
        
        job.setOutputKeyClass(Text.class);
        job.setOutputValueClass(Text.class);
        
        job.setMapperClass(Map.class);
        job.setCombinerClass(Reduce.class);
        job.setReducerClass(Reduce.class);
        
        job.setInputFormatClass(TextInputFormat.class);
        job.setOutputFormatClass(TextOutputFormat.class);
        
        FileInputFormat.setInputPaths(job, new Path(args[0]));
        //FileInputFormat.setInputDirRecursive
        FileOutputFormat.setOutputPath(job, new Path(args[1]));
        
        boolean success=job.waitForCompletion(true);
        return success?0:1;
    }//end of run function
    
    public static void main(String[] args)throws Exception
    {
        int ret=ToolRunner.run(new VersionCount(), args);
        System.exit(ret);
    }
}
