<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
use think\Db;

class ReturnProfit extends Command
{

    protected function configure()
    {
        $this->setName('retur_profit1')
            ->setDescription('retur_profit');
    }

    //周期性收益  直接到可提现余额        每天, 23点50分 执行
    protected function execute(Input $input, Output $output)
    {
        exit();
        // $Tasks = \app\api\model\ProjectTask::Order('id desc')->where('status', 1)->where('user_id',100025)->where('id',66057)->select();
        // foreach ($Tasks as $i => $Task) {
        //     $order = Db::name('order')->where('id', $Task['order_id'])->find();
        //     $config = json_decode($order['data'], true);
        //     $price_event = 0;
        //     if ($Task['project_data_id'] == 1) {
        //         $price_event =  ($config['day_fh'] *   $Task['qty']  * 10) / 100;
        //     }
        //     if ($Task['project_data_id'] == 2) {
        //         $price_event =  ($config['day_fh']  *   $Task['qty'] * 30) / 100;
        //     }
        //     if ($Task['project_data_id'] == 3) {
        //         $price_event =  ($config['day_fh']  *   $Task['qty'] * 50) / 100;
        //     }
    
        //     if ($order['event_state']  == 1) {
        //         $event_profit = $price_event * 10;
        //     } elseif ($order['event_state'] == 2) {
        //         $event_profit = $price_event * 20;
        //     } elseif ($order['event_state'] == 3) {
        //         $event_profit = $price_event * 30;
        //     } else {
        //         $event_profit = $price_event * 30;
        //     }
            
        //     var_dump($event_profit);
        // }
        
        $output->info('Command executed successfully');
    }

}
