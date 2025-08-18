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
        // $nxxfjlist =Db::name('nxxfjlist')->where('status', 1)->whereTime('updatetime', 'd')->field('sum(interest) as interest,user_id')->group('user_id')->limit(0,1000)->select();
        // foreach ($nxxfjlist as $i => $v) {
        //     $money=$v['interest'];
        //     $user_id=$v['user_id'];
        //     $memo='内需消费补贴金利息';
        //     $field='nxxfjlx';
        //     Db::startTrans();
        //     try {
        //         $user = Db::name('user')->where(['id'=>$user_id])->find();
        //         if ($user && $money != 0) {
        //             $before = $user->$field;
        //             $after = function_exists('bcadd') ? bcadd($user[$field], $money, 2) : $user[$field] + $money;
        //             //更新会员信息
        //             Db::name('user')->where(['id'=>$user['id']])->setInc($field,$money);
        //             //写入日志
        //             Db::name('user_'.$field.'log')->insert(['user_id' => $user_id, 'money' => $money, 'before' => $before, 'after' => $after, 'memo' => $memo,'createtime'=>time()]);
        //         }
        //         Db::commit();
        //     } catch (\Exception $e) {
        //         Db::rollback();
        //     }
        //     Db::name('nxxfjlist')->where(['user_id'=>$v['user_id']])->whereTime('updatetime', 'd')->setInc('updatetime',86400);
        // }
        $nxxfjlist = Db::name('nxxfjlist')
            ->where('status', 1)
            ->whereTime('updatetime', 'd')
            ->field('sum(interest) as interest,user_id')
            ->group('user_id')
            ->limit(1000)
            ->select();

        if ($nxxfjlist) {
        

        $logs = [];
        Db::startTrans();
        try {
            foreach ($nxxfjlist as $v) {
                $money = $v['interest'];
                $user_id = $v['user_id'];
                if ($money == 0) continue;
                // echo $user_id.'.';
                $field = 'nxxfjlx';
                $user = Db::name('user')->where('id', $user_id)->lock(true)->find();
                if (!$user) continue;

                $before = $user[$field];
                $after = bcadd($before, $money, 2);

                // 更新会员信息
                Db::name('user')->where('id', $user_id)->setInc($field, $money);

                // 准备日志
                $logs[] = [
                    'user_id' => $user_id,
                    'money' => $money,
                    'before' => $before,
                    'after' => $after,
                    'memo' => '内需消费补贴金利息',
                    'createtime' => $time
                ];
            }

            // 批量插入日志
            if (!empty($logs)) {
                Db::name('user_nxxfjlxlog')->insertAll($logs);
            }

            // 批量更新 nxxfjlist.updatetime
            $userIds = array_column($nxxfjlist, 'user_id');
            if (!empty($userIds)) {
                Db::name('nxxfjlist')
                    ->whereIn('user_id', $userIds)
                    ->whereTime('updatetime', 'd')
                    ->setInc('updatetime', 86400);
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $output->error("执行出错: " . $e->getMessage());
        }
        }
        $output->info("执行完成",time());
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
