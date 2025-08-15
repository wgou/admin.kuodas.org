<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
use app\api\model\Level;
use think\Db;

class MonthTask extends Command
{

    protected function configure()
    {
        $this->setName('month_task')
            ->setDescription('monthly task rebase');
    }

    //月工资  到团队津贴  每月, 15日 0点30分执行
    protected function execute(Input $input, Output $output)
    {
        $Levels = Level::Order('id desc')->select();
        foreach ($Levels as $Level) {
            $ids = \app\api\model\User::where('level', $Level['id'])->column('id');
            foreach ($ids as $i => $id) {
                echo $i;
                \app\api\model\User::where('id', $id)->setInc('tdjt', $Level['money']);
                $ins = [
                    'user_id' => $id,
                    'money' => $Level['money'],
                    'before' => 0,
                    'after' => 0,
                    'memo' => '月工资',
                    'createtime' => time(),
                    'type' => 'gongzi',
                ];
                \app\api\model\UserMoneyLog::create($ins);
            }
        }
        $output->info("执行完成");
    }

}
