<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;
use app\api\model\UserMoney;

/**
 * 示例接口
 */
class Nxxfj extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['test', 'test1','aaaaccc','checkyong'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];
//购买后 进行上级分佣  直接到可提现余额
    // public function checkyong()
    // {
    //     $order=[
    //         'user_id'=>614446,
    //         'price'=>1796,
    //     ];
    //     $uid = $order['user_id'];
    //     $user = \app\api\model\User::where('id', $uid)->find();
    //     $yong = [
    //         // 'upid' => [
    //         //     'user_id' => $user['upid'] ?? 0,
    //         //     'money' => $order['price'] * (config('site.upmoney') / 100),
    //         // ],
    //         // 'upid2' => [
    //         //     'user_id' => $user['upid2'] ?? 0,
    //         //     'money' => $order['price'] * (config('site.upmoney2') / 100),
    //         // ],
    //         'upid3' => [
    //             'user_id' => $user['upid3'] ?? 0,
    //             'money' => $order['price'] * (config('site.upmoney3') / 100),
    //         ],
    //     ];

    //     foreach ($yong as $i => $dat) {
    //         $ins = [
    //             'user_id' => $dat['user_id'],
    //             'money' => $dat['money'],
    //             'before' => 0,
    //             'after' => 0,
    //             'memo' => '团队佣金 ' . $user['nickname'],
    //             'createtime' => time(),
    //             'type' => 'tui',
    //         ];
    //         \app\api\model\UserMoneyLog::create($ins);
    //         \app\api\model\User::where('id', $dat['user_id'])->setInc('money', $dat['money']);
    //         \app\api\model\User::where('id', $dat['user_id'])->setInc('allmoney', $dat['money']);
    //         //新的资金记录表
    //         UserMoney::money_in($dat['user_id'], 'tuijian', $dat['money'], '团队佣金 ' . $user['nickname']);
    //     }
    // }
    /**
     * 测试方法
     *
     * @ApiTitle    (测试名称)
     * @ApiSummary  (测试描述信息)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/demo/test/id/{id}/name/{name})
     * @ApiHeaders  (name=token, type=string, required=true, description="请求的Token")
     * @ApiParams   (name="id", type="integer", required=true, description="会员ID")
     * @ApiParams   (name="name", type="string", required=true, description="用户名")
     * @ApiParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
         'code':'1',
         'msg':'返回成功'
        })
     */
    public function nxxfjlog()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $page = $this->request->post('page',1);
            $page = $page<=0?0:--$page;
            $where=['user_id'=>$user_id];
            $data['list']=Db::name('user_nxxfjlog')->where($where)->order('id','desc')->limit($page*10,10)->select();
            $data['total']=Db::name('user_nxxfjlog')->where($where)->count();
            
            $this->success('success',$data);
        }
    }
    
    public function nxxfjlxlog()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $page = $this->request->post('page',1);
            $page = $page<=0?0:--$page;
            $where=['user_id'=>$user_id];
            $data['list']=Db::name('user_nxxfjlxlog')->where($where)->order('id','desc')->limit($page*20,20)->select();
            // $data['list']=Db::name('user_nxxfjlxlog')->where($where)->order('id','desc')->select();
            $data['total']=Db::name('user_nxxfjlxlog')->where($where)->count();
            // $data['total']=1;
            
            $this->success('success',$data);
        }
    }

    public function continuation()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $redis = new \think\cache\driver\Redis(['length' => 3]);
            if (!$redis->handler()->setnx('nxxfj'.$user_id, 1)) {
                // code...
                $this->error('操作频繁');
            }else{
                $redis->handler()->expire('nxxfj'.$user_id, 3);
            }
            $time=time();
            $where=['user_id'=>$user_id,'expirytime'=>['<',$time],'status'=>1];
            $nxxfjlist=Db::name('nxxfjlist')->where($where)->select();
            //明年的今天
            $year = date('Y'); // 当前年份
            $timesting=$year."-12-31 23:59:59";
            $nextYearTimestamp = strtotime($timesting);
            $msg='暂不可续存，请（'.$timesting.'）再次重试';
            if ($nxxfjlist) {
                $annualization=Config('site.annualization');
                foreach ($nxxfjlist as &$value) {
                    $value['expirytime']=$nextYearTimestamp;
                    $value['yeartime']=bcadd($value['yeartime'],1);
                    $dailyInterest=$this->getinterest($year,$value['money'],$annualization[$value['yeartime']]);
                    $value['interest']=$dailyInterest;
                }
                $msg='存续成功';
                if ($nxxfjlist) {
                    $Nxxfjlist=new \app\admin\model\Nxxfjlist();
                    $result1 = $Nxxfjlist->saveAll($nxxfjlist);
                }
            }
            $this->success($msg);
        }
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
    public function withdrawnxxfj(){
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $redis = new \think\cache\driver\Redis(['length' => 3]);
            if (!$redis->handler()->setnx('nxxfj'.$user_id, 1)) {
                // code...
                $this->error('操作频繁');
            }else{
                $redis->handler()->expire('nxxfj'.$user_id, 3);
            }
            $time=time();
            $where=['user_id'=>$user_id,'expirytime'=>['<',$time],'status'=>1];
            $money=Db::name('nxxfjlist')->where($where)->sum('money');
            $year = date('Y'); // 当前年份
            $timesting=$year."-12-31 23:59:59";
            if (!$money) {
                $this->error('未满一年暂不可提现，请（'.$timesting.'）再次重试');
            }
            if ($this->auth->is_buy!=1) {
                $this->error('您的账号尚未激活，请认购套餐后再进行提现！');
            }
            $dataUser=Db::name('user_money')->where(['user_id'=>$user_id])->find();
            $dataIns  = [
                'user_id' => $user_id,
                'money' => $money,
                'type' => 'shifang',
                'before' => $dataUser['money_shifang'],
                'after' => bcadd($dataUser['money_shifang'],$money,2),
                'memo' => '内需消费补贴金提现',
                'createtime' => $time
            ];
            // \app\common\model\MoneyLog::create($dataIns);
            \app\api\model\UserMoneyInLog::create($dataIns);
            \app\api\model\UserMoneyLog::create($dataIns);
            \app\common\model\User::nxxfj(-$money,$user_id,'内需消费补贴金提现','nxxfj');
            \app\api\model\User::where('id', $user_id)->setInc('money', $money);
            Db::name('user_money')->where(['user_id'=>$user_id])->inc('money', $money)->inc('money_shifang', $money)->update();
            Db::name('user_nxxfjlist')->where($where)->update(['status'=>2]);
            $this->success('转至账户余额成功');
        }
    }
    public function withdrawnxxfjlx(){
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $redis = new \think\cache\driver\Redis(['length' => 3]);
            if (!$redis->handler()->setnx('nxxfj'.$user_id, 1)) {
                // code...
                $this->error('操作频繁');
            }else{
                $redis->handler()->expire('nxxfj'.$user_id, 3);
            }
            $money=$this->auth->nxxfjlx;
            if ($money<=0) {
                $this->error('暂未有利息');
            }
            if (date('j') != 1) {
                $this->error('暂不能提现，请一号再来重试');
            }
            // if ($money<100) {
            //     $this->error('最低100元起提现！');
            // }
            if ($this->auth->is_buy!=1) {
                $this->error('您的账号尚未激活，请认购套餐后再进行提现！');
            }
            $time=time();
            $dataUser=Db::name('user_money')->where(['user_id'=>$user_id])->find();
            $dataIns  = [
                'user_id' => $user_id,
                'money' => $money,
                'type' => 'shifang',
                'before' => $dataUser['money_shifang'],
                'after' => bcadd($dataUser['money_shifang'],$money,2),
                'memo' => '内需消费补贴金利息提现至分红释放余额',
                'createtime' => $time
            ];
            // \app\common\model\MoneyLog::create($dataIns);
            \app\api\model\UserMoneyInLog::create($dataIns);
            \app\api\model\UserMoneyLog::create($dataIns);
            \app\common\model\User::nxxfj(-$money,$user_id,'内需消费补贴金利息提现至分红释放余额','nxxfjlx');
            \app\api\model\User::where('id', $user_id)->setInc('money', $money);
            // app\api\model\UserMoney::where('user_id', $user_id)->setInc('money', $money);
            // app\api\model\UserMoney::where('user_id', $user_id)->setInc('money_shifang', $money);
            Db::name('user_money')->where(['user_id'=>$user_id])->inc('money', $money)->inc('money_shifang', $money)->update();
            $this->success('提现成功,请到分红释放查看');
        }
    }
    public function withdrawnomoney(){
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $redis = new \think\cache\driver\Redis(['length' => 3]);
            if (!$redis->handler()->setnx('nxxfj'.$user_id, 1)) {
                // code...
                $this->error('操作频繁');
            }else{
                $redis->handler()->expire('nxxfj'.$user_id, 3);
            }
            $money=$this->auth->nxxfjlx;
            if ($money<=0) {
                $this->error('暂未有利息');
            }
            if (date('j') != 1) {
                $this->error('未到提现时间段，每月1号才可提现');
            }
            if ($this->auth->is_buy!=1) {
                $this->error('您的账号尚未激活，请认购套餐后再进行转账！');
            }
            $time=time();
            $dataIns  = [
                'user_id' => $user_id,
                'money' => $money,
                'type' => 'nomoney',
                'before' => $this->auth->nomoney,
                'after' => bcadd($this->auth->nomoney,$money,2),
                'memo' => '内需消费补贴金利息提现账户余额',
                'createtime' => $time
            ];
            // \app\common\model\MoneyLog::create($dataIns);
            \app\api\model\UserMoneyLog::create($dataIns);
            \app\common\model\User::nxxfj(-$money,$user_id,'内需消费补贴金利息提现账户余额','nxxfjlx');
            // \app\api\model\User::where('id', $user_id)->setIec('nomoney', $money);
            Db::name('user')->where(['id'=>$user_id])->inc('nomoney', $money)->update();
            $this->success('转至账户余额成功');
        }
    }
    /**
     * 无需登录的接口
     *
     */
    public function test1()
    {
        $this->success('返回成功', ['action' => 'test1']);
    }

    /**
     * 需要登录的接口
     *
     */
    public function test2()
    {
        $this->success('返回成功', ['action' => 'test2']);
    }

    /**
     * 需要登录且需要验证有相应组的权限
     *
     */
    public function test3()
    {
        $this->success('返回成功', ['action' => 'test3']);
    }
    public function aaaaccc(){
        //提现处理失败,退回内需消费补贴金利息
        // $user=Db::name('user')->where(['buy_time'=>['>',1748534400]])->where(['createtime'=>['<',1746633600]])->field('id,is_buy,upid,createtime')->limit(1800,200)->select();
        // var_dump(count($user));
        // foreach ($user as $value) {
        //     $msg='老用户无法参与激赠送活动  激活无效撤销激活补贴金';
        //     // var_dump($value['id']);
        //     //删除对应存单
        //     $a=Db::name('nxxfjlist')->where(['user_id'=>$value['id'],'money'=>15000])->find();
            
        //     $ab=Db::name('user_nxxfjlog')->where(['user_id'=>$value['id'],'money'=>15000,'memo'=>'粽有乾坤活动激活奖励内需消费补贴金'])->find();
        //     if ($ab&&$a) {
        //         \app\common\model\User::nxxfj(-15000,$value['id'],$msg,'nxxfj');
        //         Db::name('nxxfjlist')->where(['id'=>$a['id']])->delete();
        //         // code...
        //     }
        //     // var_dump($a['id']);
        // }
    }
}
