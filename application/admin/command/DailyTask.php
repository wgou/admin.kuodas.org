<?php

namespace app\admin\command;

use app\api\model\UserMoney;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
use app\api\model\Level;
use think\Db;

class DailyTask extends Command
{

    protected function configure()
    {
        $this->setName('daily_task')
            ->setDescription('task daily rebase');
    }

    //周期性收益  直接到可提现余额        每天, 23点50分 执行
    protected function execute(Input $input, Output $output)
    {
        $Tasks = \app\api\model\ProjectTask::Order('id desc')->where('status', 1)->whereTime('fanli_time', 'd')->select();
        //$Tasks = \app\api\model\ProjectTask::Order('id desc')->where('status', 1)->whereTime('fanli_time', 'yesterday')->select();
        //$Tasks = \app\api\model\ProjectTask::Order('id desc')->where('status', 1)->whereTime('fanli_time', 'between', ['2024-07-17 00:00:00', '2024-07-19 23:59:59'])->select();
        $this->profit_log(\app\api\model\ProjectTask::getLastSql());
        foreach ($Tasks as $i => $v) {
            echo $i;
            $Task = $Tasks[$i];
            $this->profit_log($Task['id']);
            $this->profit_log($Task);
            \app\api\model\User::where('id', $Task['user_id'])->setInc('money', $Task['fanli_money']);
            $ins = [
                'user_id' => $Task['user_id'],
                'money' => $Task['fanli_money'],
                'before' => 0,
                'after' => 0,
                'memo' => $Task['msg'] ? $Task['msg']: '释放分红',
                'createtime' => time(),
                'type' => 'release_divid',
            ];
            //新的资金记录表
            UserMoney::money_in($Task['user_id'], 'shifang', $Task['fanli_money'],$ins['memo']);
            \app\api\model\UserMoneyLog::create($ins);
            $this->profit_log($ins);
            //echo(\app\api\model\UserMoneyLog::getLastSql());
            \app\api\model\ProjectTask::where('id', $Task['id'])->update(['status' => 2]);
            
        //======================= event_profit =======================//
            
            if ($Task['event'] == 1) {
                $order = Db::name('order')->where('id', $Task['order_id'])->find();
                if(!empty($order)){
                $config = json_decode($order['data'], true);
                $price_event = 0;
                if ($Task['project_data_id'] == 1) {
                    $price_event =  ($config['day_fh'] *   $Task['qty']  * 10) / 100;
                }
                if ($Task['project_data_id'] == 2) {
                    $price_event =  ($config['day_fh']  *   $Task['qty'] * 30) / 100;
                }
                if ($Task['project_data_id'] == 3) {
                    $price_event =  ($config['day_fh']  *   $Task['qty'] * 50) / 100;
                }
        
                if ($order['event_state']  == 1) {
                    $event_profit = $price_event * 10;
                } elseif ($order['event_state'] == 2) {
                    $event_profit = $price_event * 20;
                } elseif ($order['event_state'] == 3) {
                    $event_profit = $price_event * 30;
                } else {
                    $event_profit = $price_event * 30;
                }
                
                Db::name('order')->where('id', $Task['order_id'])->setInc('event_state', 1);
                \app\api\model\User::where('id', $Task['user_id'])->setInc('allmoney', $event_profit);
                
                $profit_user = [
                    'id'=>$Task['id'],
                    'profit' => $event_profit ?? 0,
                    'order_id' => $Task['order_id'],
                    'user_id' => $Task['user_id'],
                ];
                $this->profit_log($profit_user);
                }
            }
            
        //==========================end==============================//
        }
        $output->info("执行完成");
    }
    
    public function profit_log($profit_user){
        $log = date('Y-m-d H:i:s') . ' --- ' . json_encode($profit_user) . PHP_EOL;
        $path = ROOT_PATH . 'public' . DIRECTORY_SEPARATOR . 'daily_task';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        file_put_contents($path . DIRECTORY_SEPARATOR . date('Y-m-d') . 'daily_task.log', $log, FILE_APPEND);
    }

}
