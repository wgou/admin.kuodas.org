<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
use think\Db;

class Nxxfj extends Command
{

    protected function configure()
    {
        $this->setName('nxxfj')
            ->setDescription('nxxfjlx');
    }

    //定时任务每分钟执行
    protected function execute(Input $input, Output $output)
    {
        $time=time();
        $Tasks = \app\api\model\ProjectTask::Order('id desc')->where('status', 1)->whereTime('fanli_time', 'd')->select();
        $nxxfjlist=Db::name('nxxfjlist')->where(['status'=>1,'updatetime'=>['<',$time]])->select();
        // $this->profit_log(Db::name('nxxfjlist')->getLastSql());
        $nxxfjlists=[];
        $ymd=date("Y-m-d", strtotime("+1 day"));
        foreach ($nxxfjlist as $i => $v) {
            echo $i;
            $hours1 = date("H:i:s", $v['createtime']);
            $time=strtotime($ymd.' '.$hours1);
            // $this->profit_log($v);
            $nxxfjlists[]=[
                'id'=>$v['id'],
                'updatetime'=>$time,
            ];
            \app\common\model\User::nxxfj($v['interest'],$v['user_id'],'内需消费补贴金利息','nxxfjlx');
        }
        if ($nxxfjlists) {
            $Nxxfjlist=new \app\admin\model\Nxxfjlist();
            $result1 = $Nxxfjlist->saveAll($nxxfjlists);
        }
        $output->info("执行完成");
    }
    
    public function profit_log($profit_user){
        $log = date('Y-m-d H:i:s') . ' --- ' . json_encode($profit_user) . PHP_EOL;
        $path = ROOT_PATH . 'public' . DIRECTORY_SEPARATOR . 'nxxfj';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        file_put_contents($path . DIRECTORY_SEPARATOR . date('Y-m-d') . 'nxxfj.log', $log, FILE_APPEND);
    }

}
