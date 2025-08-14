<?php

namespace app\api\controller;

use app\api\model\Level;
use app\api\model\UserMoney;
use think\Db;

class Cmd
{
    //月工资  到团队津贴  每月, 15日 0点30分执行
    public function monthMoney()
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
        echo "执行完成";
    }

    //周期性收益  直接到可提现余额        每天, 23点50分 执行
    public function dayMoney()
    {
        $Tasks = \app\api\model\ProjectTask::Order('id desc')->where('status', 1)->whereTime('fanli_time', 'd')->select();
        foreach ($Tasks as $i => $Task) {
            echo $i;
            \app\api\model\User::where('id', $Task['user_id'])->setInc('money', $Task['fanli_money']);
//            $ins = [
//                'user_id' => $Task['user_id'],
//                'money' => $Task['fanli_money'] + $Task['fanli_money2'],
//                'money1' => $Task['fanli_money'],
//                'money2' => $Task['fanli_money2'],
//                'before' => 0,
//                'after' => 0,
//                'memo' => '每日分红',
//                'createtime' => time(),
//                'type' => 'fanli',
//            ];
//            \app\api\model\UserMoneyLog::create($ins);
            \app\api\model\ProjectTask::where('id', $Task['id'])->update(['status' => 2]);
        }
        echo "执行完成";
    }
    //每日收益记录
    public function dayMoney2()
    {
        $Tasks = \app\api\model\ProjectTask2::Order('id desc')->where('status', 1)->whereTime('fanli_time', 'd')->select();
        foreach ($Tasks as $i => $Task) {
            echo $i;
            $ins = [
                'user_id' => $Task['user_id'],
                'money' => $Task['fanli_money'] + $Task['fanli_money2'],
                'money1' => $Task['fanli_money'],
                'money2' => $Task['fanli_money2'],
                'before' => 0,
                'after' => 0,
                'memo' => '每日分红',
                'createtime' => time(),
                'type' => 'fanli',
            ];
            \app\api\model\UserMoneyLog::create($ins);
            \app\api\model\ProjectTask2::where('id', $Task['id'])->update(['status' => 2]);
        }
        echo "执行完成";
    }


    //每日答题 重置
    public function dayDt()
    {
        \app\api\model\User::where('id', 'neq', 0)->update(['dt'=>2]);
        \app\api\model\User::where('id', 'neq', 0)->update(['day_share_num'=> 0]);
    }


    //购买后 进行上级分佣  直接到可提现余额
    public static function checkyong($order)
    {
        $uid = $order['user_id'];
        $user = \app\api\model\User::where('id', $uid)->find();
        $yong = [
            'upid' => [
                'user_id' => $user['upid'] ?? 0,
                'money' => $order['price'] * (config('site.upmoney') / 100),
            ],
            'upid2' => [
                'user_id' => $user['upid2'] ?? 0,
                'money' => $order['price'] * (config('site.upmoney2') / 100),
            ],
            'upid3' => [
                'user_id' => $user['upid3'] ?? 0,
                'money' => $order['price'] * (config('site.upmoney3') / 100),
            ],
        ];

        foreach ($yong as $i => $dat) {
            $ins = [
                'user_id' => $dat['user_id'],
                'money' => $dat['money'],
                'before' => 0,
                'after' => 0,
                'memo' => '团队佣金 ' . $user['nickname'],
                'createtime' => time(),
                'type' => 'tui',
            ];
            \app\api\model\UserMoneyLog::create($ins);
            \app\api\model\User::where('id', $dat['user_id'])->setInc('money', $dat['money']);
            \app\api\model\User::where('id', $dat['user_id'])->setInc('allmoney', $dat['money']);
            //新的资金记录表
            UserMoney::money_in($dat['user_id'], 'tuijian', $dat['money'], '团队佣金 ' . $user['nickname']);
        }
    }
}
