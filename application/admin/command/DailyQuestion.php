<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;

class DailyQuestion extends Command
{

    protected function configure()
    {
        $this->setName('daily_question')
            ->setDescription('Daily Questions Reset');
    }

     //每日答题 重置
    protected function execute(Input $input, Output $output)
    {
        \app\api\model\User::where('id', 'neq', 0)->update(['dt'=>2]);
        \app\api\model\User::where('id', 'neq', 0)->update(['day_share_num'=> 0]);
        $output->info("Successed!");
    }

}
