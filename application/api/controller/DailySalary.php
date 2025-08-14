<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;
use think\Config;
use think\Db;

class DailySalary extends Api
{

    protected $model = null;

    protected $noNeedRight = '*';
    protected $noNeedLogin = [];
    protected $_allow_func = ['daily_salary', 'receive_salary', 'daily_reward_intro', 'record_daily_salary'];


    use \app\api\library\buiapi\traits\Api; 
       
    public function daily_salary()
    {
        $user_id = $this->auth->id;
        $user_game_level = $this->auth->game_level;
        $config = Db::name('game_level')->where('name', $user_game_level)->value('name');
        $daily_salary_config = config('site.daily_salary');
        $today_salary = $daily_salary_config[$config];
        $startTime = strtotime(date('Y-m-d 00:00:00'));
        $endTime = strtotime(date('Y-m-d 23:59:59'));

        $total_daily = Db::name('user_money_log')
            ->where('user_id', $user_id)
            ->where('type', 'daily_salary')
            ->sum('money');
        $check_log = Db::name('user_money_log')
            ->where('user_id', $user_id)
            ->where('type', 'daily_salary')
            ->where('createtime', 'between', [$startTime, $endTime])
            ->find();
        $daily_salary_note = Config::get('site.daily_note');
        $daily_reward_intro = config('site.daily_reward_intro');

        $data = [
            'user_id' => $user_id,
            'game_level' => $user_game_level,
            'today_salary' => $today_salary,
            'total_daily' => $total_daily,
            'noted' => $daily_salary_note,
            'daily_reward_intro' => $daily_reward_intro,
            'state' => empty($check_log) ? 0 : 1
        ];
        $this->success('ok', $data);
    }

    public function daily_reward_intro()
    {
        $daily_reward_intro = config('site.daily_reward_intro');
        $this->success('ok', $daily_reward_intro);
    }

    public function receive_salary()
    {
        $user_id = $this->auth->id;
        $user = db('user')->where('id', $user_id)->find();
        if (!$user) {
            $this->error('用户不存在');
        }

        //redis防重复点击
        $symbol = "daily_salary" . $this->auth->id;
        $submited = pushRedis($symbol);
        if (!$submited) {
            $this->error(__("操作频繁"));
        }

        $user_game_level = $this->auth->game_level;
        $config = Db::name('game_level')->where('name', $user_game_level)->value('name');
        if (!$config) {
            $this->error('等级不符合参与条件');
        }
        $daily_salary_config = config('site.daily_salary');
        $today_salary = $daily_salary_config[$config];
        $startTime = strtotime(date('Y-m-d 00:00:00'));
        $endTime = strtotime(date('Y-m-d 23:59:59'));
        $time = time();

        $check_log = Db::name('user_money_log')
            ->where('user_id', $user['id'])
            ->where('type', 'daily_salary')
            ->where('createtime', 'between', [$startTime, $endTime])
            ->find();

        if (!empty($check_log)) {
            $this->error('当日已领取');
        }

        $new_money = $user['money'] + $today_salary;
        $ins = [
            'user_id' => $user['id'],
            'money' => $today_salary,
            'before' => $user['money'],
            'after' => $new_money,
            'memo' => '日薪',
            'createtime' => $time,
            'type' => 'daily_salary',
        ];

        Db::startTrans();
        try {
            $res1 = Db::name('user_money_log')->insert($ins);
            // $res2 = Db::name('user')->where('id', $user['id'])->setInc('money', $today_salary);
            if (!$res1) {
                Db::rollback();
                $this->error("失败");
            }
            Db::commit();
            $this->success(__("提交成功"));
        } catch (Exception $e) {
            Db::rollback();
            lopRedis($symbol);
            $this->error(__("网络错误,请稍后再试!"));
        }
    }

    public function record_daily_salary()
    {
        $user_id = $this->auth->id;
        $data = db('user_money_log')
            ->field('id,memo,money,createtime')
            ->where('user_id', $user_id)
            ->where('type', 'daily_salary')
            ->order('id desc')
            ->paginate(10, false, ['query' => request()->param()]);
        foreach ($data as $k => $v) {
            $v['createtime'] = date('Y-m-d H:i', $v['createtime']);
            $data[$k] = $v;
        }
        $this->success('ok', $data);
    }
}
