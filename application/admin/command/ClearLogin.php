<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
use think\Db;

class ClearLogin extends Command
{

    protected function configure()
    {
        $this->setName('clear_login')
            ->setDescription('Clear Login');
    }

     //每日答题 重置
    protected function execute(Input $input, Output $output)
    {
        // \app\api\model\User::where('id', 'neq', 0)->update(['is_login'=>0]);
        Db::execute('TRUNCATE TABLE fa_user_token');
        $output->info("Login Clear Successed!");
    }

}
