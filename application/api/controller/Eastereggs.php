<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;
use app\api\model\UserMoney;

/**
 * 示例接口
 */
class Eastereggs extends Api
{
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['aaa'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];
    public function aaa(){

        UserMoney::money_in(343423, 'bbxjhb', 1000, '手动');
        \app\api\model\User::where('id', 343423)->setInc('money', 1000);
    }
    // public function aaa(){
    //     $order=Db::name('order')->where(['id'=>['<',57964],'createtime'=>['>',1749414055],'status'=>1])->select();
    //     // var_dump($order);
    //     foreach ($order as $project_data) {
    //         $task2=Db::name('project_task2')->where(['order_id'=>$project_data['id']])->count();
    //         if ($task2) {
    //             $user = Db::name('user')->where('id', $project_data['user_id'])->find();
    //             if($user['is_buy']!=1&&$project_data['project_data_id']!=8){
    //                 \app\api\model\User::where('id', $project_data['user_id'])->update(['is_buy' => 1,'buy_time'=>time()]);//直推激活
    //                 if (Config('site.is_nxxfj')&&$user['createtime']>1746633600) {
    //                     $time=time();
    //                     $updatetime=bcadd($time,172800);
    //                     $year = date('Y'); // 当前年份
    //                     $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
    //                         $nxxfj=Config('site.new_send_nxxfj');
    //                         $msg='新人注册激活认购套餐';
    //                     if ($nxxfj) {
    //                         \app\common\model\User::nxxfj($nxxfj,$project_data['user_id'],$msg,'nxxfj');
    //                         $annualization=Config('site.annualization');
    //                         $dailyInterest=$this->getinterest($year,$nxxfj,$annualization[1]);
    //                         Db::name('nxxfjlist')->insert([
    //                             'money'=>$new_registerp_nxxfj,
    //                             'user_id'=>$project_data['user_id'],
    //                             'yeartime'=>1,
    //                             'year_rate'=>$annualization[1],
    //                             'expirytime'=>$nextYearTimestamp,
    //                             'status'=>1,
    //                             'interest'=>$dailyInterest,
    //                             'createtime'=>$time,
    //                             'updatetime'=>$updatetime,
    //                         ]);
    //                     }

    //                     $this->duanwu($project_data['user_id'],$this->auth->upid);
    //                 }
    //             }
    //             $ins = [
    //                 'user_id' => $project_data['user_id'],
    //                 'money' => -$project_data['price'],
    //                 'before' => $user['allmoney'] ?? 0,
    //                 'after' =>  $user['allmoney'] ?? 0 + $project_data['price'],
    //                 'memo' => '项目认购 ' . $project_data['id'],
    //                 'createtime' => time(),
    //                 'type' => 'buy',
    //             ];
    //             \app\api\model\UserMoneyLog::create($ins);

    //             //团队分佣
    //             Cmd::checkyong($project_data);
    //             /*************  活动时间：11月12日-12月5日  活动结束****************************/
    //             //更新订单数据
    //             \app\api\model\ProjectData::where('id', $project_data['project_data_id'])->setInc('thismoney', $project_data['price']);
    //             //更新订单状态
    //             $data = [
    //                 'status' => 2,
    //                 'payprice' => $project_data['price'],
    //                 'paytime' => time(),
    //             ];
    //             Db::table('fa_order')->where('id', $project_data['id'])->update($data);
    //         }


    //     }

    // }

    private function duanwu($user_id,$pid){
        $duanwu_time_Interval=config('site.duanwu_time_Interval');
        // 拆分时间段
        list($start, $end) = explode(' - ', $duanwu_time_Interval);

        // 转换为时间戳
        $startTime = strtotime($start);
        $endTime = strtotime($end);
        $now = time(); // 当前时间戳
        // 验证活动时间
        if ($now >= $startTime && $now <= $endTime) {

            //注册送内需消费金
            $duanwu_add_act=Config('site.duanwu_add_act');
            $annualization=Config('site.annualization');
            $nxxfjlist=[];
            //今年的最后一天
            $year = date('Y'); // 当前年份
            $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
            $time=time();
            $updatetime=bcadd($time,172800);
            if ($duanwu_add_act) {
                \app\common\model\User::nxxfj($duanwu_add_act,$user_id,'粽有乾坤活动激活奖励内需消费补贴金','nxxfj');

                $dailyInterest=$this->getinterest($year,$duanwu_add_act,$annualization[1]);
                $nxxfjlist[]=[
                    'money'=>$duanwu_add_act,
                    'user_id'=>$user_id,
                    'yeartime'=>1,
                    'year_rate'=>$annualization[1],
                    'expirytime'=>$nextYearTimestamp,
                    'status'=>1,
                    'interest'=>$dailyInterest,
                    'createtime'=>$time,
                    'updatetime'=>$updatetime,
                ];
            }
            if ($pid) {
                $new_registerp_nxxfj=Config('site.duanwu_add_upid');
                $dailyInterest=$this->getinterest($year,$new_registerp_nxxfj,$annualization[1]);
                $nxxfjlist[]=[
                    'money'=>$new_registerp_nxxfj,
                    'user_id'=>$pid,
                    'yeartime'=>1,
                    'year_rate'=>$annualization[1],
                    'expirytime'=>$nextYearTimestamp,
                    'status'=>1,
                    'interest'=>$dailyInterest,
                    'createtime'=>$time,
                    'updatetime'=>$updatetime,
                ];
                \app\common\model\User::nxxfj($new_registerp_nxxfj,$pid,'粽有乾坤活动推荐奖励内需消费补贴金','nxxfj');
            }
            if ($nxxfjlist) {
                Db::name('nxxfjlist')->insertAll($nxxfjlist);
            }


            $rcount=Db::name('user')->where(['createtime'=>['>',$startTime],'upid'=>$pid])->count();
            $buycount=Db::name('user')->where(['createtime'=>['>',$startTime],'upid'=>$pid,'is_buy'=>1])->count();
            $memor=0;
            $memob=0;
            $field='';
            if ($rcount>=39&&$buycount>=11) {
                $memor=20;
                $memob=5;
                $field='level7996';
            }elseif ($rcount>=19&&$buycount>=6) {
                $memor=10;
                $memob=3;
                $field='level4996';
            }elseif ($rcount>=9&&$buycount>=3) {
                $memor=6;
                $memob=2;
                $field='level1796';
            }elseif ($rcount>=3&&$buycount>=1) {
                $memor=3;
                $memob=1;
                $field='level896';
            }
            if ($memor>0) {
                $memo='粽有乾坤活动达成邀请'.$memor.'人激活'.$memob.'人';
                $lcount=Db::name('user_lotterylog')->where(['user_id'=>$pid,'memo'=>$memo])->count();
                if (!$lcount) {
                    \app\common\model\User::lottery(1,$pid,$memo,$field);
                }
            }

        }
    }
    /**中奖记录接口
     */
    public function eastereggslist()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $page = $this->request->post('page',1);
            $page = $page<=0?0:--$page;
            $where=['user_id'=>$user_id];
            $data['list']=Db::name('user_eastereggslist')->where($where)->order('id','desc')->limit($page*10,10)->select();
            foreach ($data['list'] as &$value) {
                $value['prize_name']=Db::name('eastereggslist')->where(['id'=>$value['eastereggslist_id']])->value('name');
            }
            $data['total']=Db::name('user_eastereggslist')->where($where)->count();

            $this->success('success',$data);
        }
    }


    /**滚动中奖历史记录
     */
    public function eastereggslists()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $page = $this->request->post('page',1);
            $page = $page<=0?0:--$page;
            $total=Db::name('user_eastereggslist')->count();
            $page=rand(0,$total-20);
            $data['list']=Db::name('user_eastereggslist')->order('id','desc')->limit($page,20)->select();
            foreach ($data['list'] as &$value) {
                $value['prize_name']=Db::name('eastereggslist')->where(['id'=>$value['eastereggslist_id']])->value('name');
                $value['username']=Db::name('user')->where(['id'=>$value['user_id']])->value('mobile');
                $value['username'] = substr_replace($value['username'],'****',3,4);
            }

            $this->success('success',$data);
        }
    }
    /**
     * 抽奖次数
     */
    public function eastereggsnum()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $page = $this->request->post('page',1);
            $page = $page<=0?0:--$page;
            $where=['user_id'=>$user_id];
            $eastereggs=Db::name('user_eastereggs')->where($where)->find();
            if (!$eastereggs) {
                $time=time();
                Db::name('user_eastereggs')->insert(['user_id'=>$user_id,'createtime'=>$time,'updatetime'=>$time]);
                $eastereggs=Db::name('user_eastereggs')->where($where)->find();
            }

            $this->success('success',$eastereggs);
        }
    }

    /**
     * 抽奖接口
     */
    public function eastereggs()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $symbol = "eastereggs" . $user_id;
            $submited = pushRedis($symbol);
            if (!$submited) {
                $this->error(__("操作频繁"));
            }
            $eastereggs_id = $this->request->post('eastereggs_id',1);
            $eastereggslist=Db::name('eastereggstype')->where(['id'=>$eastereggs_id])->find();
            if (!$eastereggslist) {
                $this->error('当前彩蛋已关闭');
            }

            $num=['level896','level1796','level4996','level7996','level14996'];
            $user_eastereggs=Db::name('user_eastereggs')->where(['user_id'=>$user_id])->find();
            if ($user_eastereggs[$num[$eastereggs_id]]<=0) {
                $this->error('彩蛋次数不够');
            }
            \app\common\model\User::lottery(-1,$user_id,'抽奖',$user_eastereggs[$num[$eastereggs_id]]);
            $eastereggslistcount=Db::name('eastereggslist')->where(['eastereggstype_id'=>$eastereggs_id,'status'=>1])->sum('probability');
            if ($eastereggslistcount<=0) {
                $this->error('彩蛋功能异常,请联系客服！');
            }
            $eastereggslist=Db::name('eastereggslist')->where(['eastereggstype_id'=>$eastereggs_id,'status'=>1])->select();
            $eastereggs=$this->weighted_random($eastereggslist,$eastereggslistcount);
            if (!$eastereggs) {
                $this->error('彩蛋功能异常,请联系客服！');
            }
            $time=time();
            $data=[
                'user_id'=>$user_id,
                'eastereggslist_id'=>$eastereggs['id'],
                'type'=>$eastereggs['type'],
                'status'=>bcadd($eastereggs['type'],1),
                'updatetime'=>$time,
                'createtime'=>$time,
            ];
            if ($data['type']&&$eastereggs['money_fields']) {
                if ($eastereggs['money_fields']=='nxxfj') {
                    $annualization=Config('site.annualization');
                    //今年的最后一天
                    $year = date('Y'); // 当前年份
                    $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
                    $updatetime=bcadd($time,172800);
                    if ($eastereggs['money']) {
                        $new_registerp_nxxfj=$eastereggs['money'];
                        $dailyInterest=$this->getinterest($year,$new_registerp_nxxfj,$annualization[1]);
                        $nxxfjlist[]=[
                            'money'=>$new_registerp_nxxfj,
                            'user_id'=>$user_id,
                            'yeartime'=>1,
                            'year_rate'=>$annualization[1],
                            'expirytime'=>$nextYearTimestamp,
                            'status'=>1,
                            'interest'=>$dailyInterest,
                            'createtime'=>$time,
                            'updatetime'=>$updatetime,
                        ];
                        \app\common\model\User::nxxfj($new_registerp_nxxfj,$user_id,'彩蛋','nxxfj');
                        Db::name('nxxfjlist')->insertAll($nxxfjlist);
                    }
                }elseif ($eastereggs['money_fields']=='bbxjhb') {
                    UserMoney::money_in($user_id, 'bbxjhb', $eastereggs['money'], '彩蛋');
                    \app\api\model\User::where('id', $user_id)->setInc('money', $eastereggs['money']);
                }elseif ($eastereggs['money_fields']=='game_level_jy') {
                    //购买套餐增加经验
                    $jingyanData = [
                        'user_id' => $user_id,
                        'score' => $eastereggs['money'],
                        'before' => $this->auth->game_level_jy,
                        'after' =>  bcadd($eastereggs['money'],$this->auth->game_level_jy),
                        'memo' => '彩蛋增加经验',
                        'createtime' => time()
                    ];
                    \app\api\model\UserJingyanLog::create($jingyanData);
                    \app\api\model\User::where('id', $user_id)->setInc('game_level_jy', $eastereggs['money']);//经验
                }else {
                    $neixu_data = [
                        'user_id' => $user_id,
                        'money' => $eastereggs['money'],
                        'before' => $this->auth->neixuquan,
                        'after' => $this->auth->neixuquan + $eastereggs['money'],
                        'memo' => '彩蛋扩内专属内需券',
                        'createtime' => $time,
                        'type' => 'neixuquan',
                    ];
                    \app\api\model\User::where('id', $user_id)->setInc('neixuquan',$eastereggs['money']);
                    \app\api\model\UserMoneyLog::create($neixu_data);
                }
            }
            Db::name('user_eastereggslist')->insert($data);
            \app\common\model\User::eastereggs(-1,$user_id,'彩蛋',$num[$eastereggs_id]);
            $this->success('success',$eastereggs);
        }
    }
    function weighted_random($weights,$total_weight) {
        $rand = mt_rand(0, $total_weight - 1);
        $counted_weight = 0;
        foreach ($weights as $item => $weight) {
            $counted_weight += $weight['probability'];
            if ($rand < $counted_weight) {
                return $weight;
            }
        }
        return end($weights); // 应该不会执行到这里，除非$weights数组为空
    }
    private function getinterest($year,$principal,$annualRate){
        // 计算总天数
        $isLeap = date('L', strtotime("$year-01-01")); // 判断是否闰年
        $totalDays = $isLeap ? 366 : 365;

        // 计算总利息：本金 × 年利率
        $totalInterest = bcmul($principal, $annualRate,2);
        $totalDays=bcmul($totalDays,100);
        // 计算每天利息：总利息 ÷ 天数
        $dailyInterest = bcdiv($totalInterest, $totalDays, 2);
        return $dailyInterest;
    }
    public function geteastereggsnum(){
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $symbol = "eastereggs" . $user_id;
            $submited = pushRedis($symbol);
            if (!$submited) {
                $this->error(__("操作频繁"));
            }
            $eastereggs_time_Interval=config('site.eastereggs_time_Interval');
            // 拆分时间段
            list($start, $end) = explode(' - ', $eastereggs_time_Interval);
            // 转换为时间戳
            $startTime = strtotime($start);
            $endTime = strtotime($end);

            $now = time(); // 当前时间戳
            // 验证活动时间
            if ($now >= $startTime && $now <= $endTime) {
                $buycount=Db::name('user')->where(['createtime'=>['>',$startTime],'upid'=>$user_id,'is_buy'=>1])->count();
                $memor=0;
                $data=[
                    ['status'=>0,'where'=>100,'num'=>295,'money'=>5888],
                    ['status'=>0,'where'=>80,'num'=>195,'money'=>2888],
                    ['status'=>0,'where'=>50,'num'=>115,'money'=>2188],
                    ['status'=>0,'where'=>30,'num'=>65,'money'=>1288],
                    ['status'=>0,'where'=>20,'num'=>35,'money'=>588],
                    ['status'=>0,'where'=>10,'num'=>15,'money'=>288],
                    ['status'=>0,'where'=>5,'num'=>5,'money'=>188],
                ];

                foreach ($data as $k=>&$v){
                    if ($v['num']<=$buycount) {
                        $a=Db::name('user_money_in_log')->where(['user_id'=>$user_id,'type'=>'tuijian','memo'=>'直推激活达到'.$v['where'].'人奖励'])->count();
                        $v['status']=1;
                        if ($a) {
                            $v['status']=2;
                        }
                    }
                }
                $this->success('success',['data'=>$data,'actnum'=>$buycount]);
            }
            $this->success('不在活动时间');
        }
    }
    public function editeastereggsnum(){
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $symbol = "editeastereggsnum" . $user_id;
            $submited = pushRedis($symbol);
            if (!$submited) {
                $this->error(__("操作频繁"));
            }
            $eastereggs_time_Interval=config('site.team_open_time');
            // 拆分时间段
            list($start, $end) = explode(' - ', $eastereggs_time_Interval);
            // 转换为时间戳
            $startTime = strtotime($start);
            $endTime = strtotime($end);

            $now = time(); // 当前时间戳
            // 验证活动时间
            if ($now >= $startTime && $now <= $endTime) {
                $buycount=Db::name('user')->where(['createtime'=>['>',$startTime],'upid'=>$user_id,'is_buy'=>1])->count();
                $memor=0;
                $data=[
                    ['status'=>0,'where'=>100,'num'=>295,'money'=>5888],
                    ['status'=>0,'where'=>80,'num'=>195,'money'=>2888],
                    ['status'=>0,'where'=>50,'num'=>115,'money'=>2188],
                    ['status'=>0,'where'=>30,'num'=>65,'money'=>1288],
                    ['status'=>0,'where'=>20,'num'=>35,'money'=>588],
                    ['status'=>0,'where'=>10,'num'=>15,'money'=>288],
                    ['status'=>0,'where'=>5,'num'=>5,'money'=>188],
                ];
                $eastereggsnum = $this->request->post('eastereggsnum',1);
                $aaa=isset($data[$eastereggsnum])?$data[$eastereggsnum]:'';
                if (!$aaa) {
                    $this->error(__("已经领取过奖励了"));
                }
                $memo='直推激活达到'.$aaa['where'].'人奖励';
                $a=Db::name('user_money_in_log')->where(['user_id'=>$user_id,'type'=>'tuijian','memo'=>$memo])->count();
                if ($a) {
                    $this->error(__("已经领取过奖励了"));
                }

                UserMoney::money_in($user_id, 'tuijian', $aaa['money'], $memo);
                \app\common\model\User::money($aaa['money'], $user_id, $memo,'tui');
                $this->success('领取成功');
            }
            $this->success('不在活动时间');
        }
    }
}
