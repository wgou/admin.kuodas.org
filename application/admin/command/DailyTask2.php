<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
use app\api\model\Level;
use think\Db;

class DailyTask2 extends Command
{

    protected function configure()
    {
        $this->setName('daily_task_2')
            ->setDescription('task daily rebase');
    }

    //每日收益记录
    protected function execute(Input $input, Output $output)
    {
        // $Tasks = \app\api\model\ProjectTask2::Order('id desc')->where('status', 1)->whereTime('fanli_time', 'd')->select();
        $Tasks = \app\api\model\ProjectTask2::Order('id desc')->where('status', 1)->where(['fanli_time'=>['<',time()]])->select();
        // $Tasks = \app\api\model\ProjectTask2::Order('id desc')->where('status', 1)->where(['fanli_time'=>['<',1748016000]])->select();
        // echo count($Tasks);exit();
        //$Tasks = \app\api\model\ProjectTask2::Order('id desc')->where('status', 1)->whereTime('fanli_time', 'yesterday')->select();
        // $Tasks = \app\api\model\ProjectTask2::Order('id desc')->where('status', 1)->whereTime('fanli_time',  'between', ['2025-05-23 00:00:00', '2025-05-23 23:59:59'])->select();
        //echo(\app\api\model\ProjectTask2::getLastSql());
        //var_dump(json_encode($Tasks));
        $this->profit_log(\app\api\model\ProjectTask2::getLastSql());
        foreach ($Tasks as $i => $v) {
            echo $i;
            $Task = $Tasks[$i];
            $this->profit_log($Task['id']);
            //$this->profit_log($Task);
            // if($Task['order_id']>16136 && $Task['order_id']<18349){
            //      $price_event = 0;
            //     if ($Task['project_data_id'] == 2) {
            //         $price_event = 103;
            //         $des = '建党节798项目额外每日分红福利';
            //     }
            //     if ($Task['project_data_id'] == 3) {
            //         $price_event = 206;
            //         $des = '建党节1698项目额外每日分红福利';
            //     }
            //     if ($Task['project_data_id'] == 4) {
            //         $price_event = 309;
            //         $des = '建党节3998项目额外每日分红福利';
            //     }
            //     if ($Task['project_data_id'] == 5) {
            //         $price_event =  615;
            //         $des = '建党节6898项目额外每日分红福利';
            //     }
            //     if ($Task['project_data_id'] == 6) {
            //         $price_event =  1030;
            //         $des = '建党节9988项目额外每日分红福利';
            //     }
            //     $event_log = [
            //         'user_id' => $Task['user_id'],
            //         'money' => $price_event*$Task['qty'],
            //         'before' => 0,
            //         'after' => 0,
            //         'memo' => $des,
            //         'createtime' => time(),
            //         'type' => 'fanli',
            //     ];

            //     \app\api\model\UserMoneyLog::create($event_log);
            //     //$this->profit_log($event_log);
            //     //echo(\app\api\model\UserMoneyLog::getLastSql());
            // }
            if ($Task['event'] == 1) {
                $price_event = 0;
                if ($Task['project_data_id'] == 1) {
                    $price_event =  ($Task['fanli_money'] * 10) / 100;
                    $des = '298项目每日分红福利';
                }
                if ($Task['project_data_id'] == 2) {
                    $price_event =  ($Task['fanli_money'] * 30) / 100;
                    $des = '798项目每日分红福利';
                }
                if ($Task['project_data_id'] == 3) {
                    $price_event =  ($Task['fanli_money'] * 50) / 100;
                    $des = '1698项目每日分红福利';
                }

                $event_log = [
                    'user_id' => $Task['user_id'],
                    'money' => $price_event,
                    'before' => 0,
                    'after' => 0,
                    'memo' => $des,
                    'createtime' => time(),
                    'type' => 'fanli',
                ];
                \app\api\model\UserMoneyLog::create($event_log);
                //$this->profit_log($event_log);strtotime(date("Y-m-d 23:59:59"))
            }
            
            $ins = [
                'user_id' => $Task['user_id'],
                'money' => $Task['fanli_money'] + $Task['fanli_money2'],
                'money1' => $Task['fanli_money'],
                'money2' => $Task['fanli_money2'],
                'before' => 0,
                'after' => 0,
                'memo' => $Task['msg'] ? $Task['msg']: '每日分红',
                'createtime' => time(),
                'type' => 'fanli',
            ];
            \app\api\model\ProjectTask2::where('id', $Task['id'])->update(['status' => 2]);
            \app\api\model\UserMoneyLog::create($ins);
            //$this->profit_log($ins);
        }
        $output->info("执行完成");
    }

    public function profit_log($profit_user){
        $log = date('Y-m-d H:i:s') . ' --- ' . json_encode($profit_user) . PHP_EOL;
        $path = ROOT_PATH . 'public' . DIRECTORY_SEPARATOR . 'daily_task';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        file_put_contents($path . DIRECTORY_SEPARATOR . date('Y-m-d') . 'daily_task_2.log', $log, FILE_APPEND);
    }
    
}
