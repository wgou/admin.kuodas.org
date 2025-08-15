<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 示例接口
 */
class Lottery extends Api
{
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = [''];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];

    /**中奖记录接口
     */
    public function lotterylist()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $page = $this->request->post('page',1);
            $page = $page<=0?0:--$page;
            $where=['user_id'=>$user_id];
            $data['list']=Db::name('winninglist')->where($where)->order('id','desc')->limit($page*10,10)->select();
            foreach ($data['list'] as &$value) {
                $value['prize_name']=Db::name('prizelist')->where(['id'=>$value['prizelist_id']])->value('name');
            }
            $data['total']=Db::name('winninglist')->where($where)->count();
            
            $this->success('success',$data);
        }
    }
    
    
    /**滚动中奖历史记录
     */
    public function lotterylists()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $page = $this->request->post('page',1);
            $page = $page<=0?0:--$page;
            $total=Db::name('winninglist')->count();
            $page=rand(0,$total-20);
            $data['list']=Db::name('winninglist')->order('id','desc')->limit($page,20)->select();
            foreach ($data['list'] as &$value) {
                $value['prize_name']=Db::name('prizelist')->where(['id'=>$value['prizelist_id']])->value('name');
                $value['username']=Db::name('user')->where(['id'=>$value['user_id']])->value('mobile');
                $value['username'] = substr_replace($value['username'],'****',3,4);
            }
            
            $this->success('success',$data);
        }
    }
    /**
     * 抽奖次数
     */
    public function lotterynum()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $page = $this->request->post('page',1);
            $page = $page<=0?0:--$page;
            $where=['user_id'=>$user_id];
            $lotterynum=Db::name('lotterynum')->where($where)->find();
            if (!$lotterynum) {
                $time=time();
                Db::name('lotterynum')->insert(['user_id'=>$user_id,'createtime'=>$time,'updatetime'=>$time]);
                $lotterynum=Db::name('lotterynum')->where($where)->find();
            }
            
            $this->success('success',$lotterynum);
        }
    }
    
    /**
     * 抽奖接口
     */
    public function lottery()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $symbol = "register" . $user_id;
            $submited = pushRedis($symbol);
            if (!$submited) {
                $this->error(__("操作频繁"));
            }
            $lottery_id = $this->request->post('lottery_id',1);
            $lotterylist=Db::name('lotterylist')->where(['id'=>$lottery_id])->find();
            if (!$lotterylist) {
                $this->error('当前抽奖已关闭');
            }

            $num=[0,'level896','level1796','level4996','level7996'];
            $lotterynum=Db::name('lotterynum')->where(['user_id'=>$user_id])->find();
            if ($lotterynum[$num[$lottery_id]]<=0) {
                $this->error('抽奖次数不够');
            }
            \app\common\model\User::lottery(-1,$user_id,'抽奖',$lotterynum[$num[$lottery_id]]);
            $lotterylistcount=Db::name('prizelist')->where(['lotterylist_id'=>$lottery_id,'status'=>1])->sum('probability');
            if ($lotterylistcount<=0) {
                $this->error('抽奖功能异常,请联系客服！');
            }
            $lotterylist=Db::name('prizelist')->where(['lotterylist_id'=>$lottery_id,'status'=>1])->select();
            $lottery=$this->weighted_random($lotterylist,$lotterylistcount);
            if (!$lottery) {
                $this->error('抽奖功能异常,请联系客服！');
            }
            $time=time();
            $data=[
                'user_id'=>$user_id,
                'prizelist_id'=>$lottery['id'],
                'type'=>$lottery['type'],
                'status'=>bcadd($lottery['type'],1),
                'updatetime'=>$time,
                'createtime'=>$time,
            ];
            if ($data['type']&&$lottery['money_fields']) {
                if ($lottery['money_fields']=='nxxfj') {
                    $annualization=Config('site.annualization');
                    //今年的最后一天
                    $year = date('Y'); // 当前年份
                    $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
                    $updatetime=bcadd($time,172800);
                    if ($lottery['money']) {
                        $new_registerp_nxxfj=$lottery['money'];
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
                        \app\common\model\User::nxxfj($new_registerp_nxxfj,$user_id,'粽有乾坤活动抽奖','nxxfj');
                        Db::name('nxxfjlist')->insertAll($nxxfjlist);
                    }
                }else {
                    $neixu_data = [
                        'user_id' => $user_id,
                        'money' => $lottery['money'],
                        'before' => $this->auth->neixuquan,
                        'after' => $this->auth->neixuquan + $lottery['money'],
                        'memo' => '粽有乾坤活动抽奖扩内专属内需券',
                        'createtime' => $time,
                        'type' => 'neixuquan',
                    ];
                    \app\api\model\User::where('id', $user_id)->setInc('neixuquan',$lottery['money']);
                    \app\api\model\UserMoneyLog::create($neixu_data);
                }
            }
            Db::name('winninglist')->insert($data);
            \app\common\model\User::lottery(-1,$user_id,'抽奖',$num[$lottery_id]);
            $this->success('success',$lottery);
        }
    }
    function weighted_random($weights,$total_weight) {
        $rand = mt_rand(0, $total_weight - 1);
            var_dump($rand);
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
}
