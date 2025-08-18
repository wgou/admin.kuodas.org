<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
use think\Db;


class OrderPatch extends Command
{

    protected function configure()
    {
        $this->setName('order_patch')
            ->setDescription('OrderPatch');
    }

     //订单补丁
    protected function execute(Input $input, Output $output)
    {
        $order_list = \app\api\model\Order::where('status', 2)
        ->where('id','between',[96816,96949])
        ->select();
        echo (count($order_list).'->');
        foreach($order_list as $key=>$order){
            echo($order['id'].'|');
            $project_data = $order;
            $qty =  $project_data['qty'];
            $config = $project_data['data'];
            $user = Db::name('user')->where('id', $order['user_id'])->find();
            //上级获得5%扩内专属消费券
            $up_user = \app\api\model\User::where('id', $user['upid'])->find();
            if(\app\api\model\Order::where('user_id', $up_user['id'])->where('status', 2)->sum('payprice') > 10000){
                $price_score = ceil(($config['money'] * $qty / 100) * 5);
                $detailed_data = [
                    'user_id' => $up_user['id'],
                    'money' => $price_score,
                    'before' => $up_user['neixuquan'] ?? 0,
                    'after' => ($up_user['neixuquan'] ?? 0) + $price_score,
                    'memo' => '[补]下级认购'.$project_data['price'].'项目专属内需券',
                    'createtime' => time(),
                    'type' => 'neixuquan',
                ];
                $zijin_price = ceil(($config['money'] * $qty / 100) * 1000);
                $zijin_data = [
                    'user_id' => $up_user['id'],
                    'money'   => $zijin_price,
                    'before'  => $up_user['money'] ?? 0,
                    'after'   => ($up_user['money'] ?? 0) + $zijin_price,
                    'memo' => '[补]下级认购'.$project_data['price'].'项目内需补贴资金',
                    'createtime' => time(),
                    'type' => 'neixu',
                ];
                Db::startTrans();
                try {
                    // 5%扩内专属消费券
                    \app\api\model\User::where('id', $up_user['id'])->setInc('neixuquan', $price_score);
                    \app\api\model\UserMoneyLog::create($detailed_data);
                    // 10%的内需补贴资金
                    \app\api\model\User::where('id', $up_user['id'])->setInc('money',$zijin_price);
                    \app\api\model\UserMoney::where('user_id', $up_user['id'])->setInc('money', $zijin_price);
                    \app\api\model\UserMoney::where('user_id', $up_user['id'])->setInc('money_neixu', $zijin_price);
                    \app\api\model\UserMoneyInLog::create($zijin_data);
                    \app\api\model\UserMoneyLog::create($zijin_data);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    echo($e->getMessage());
                }
            }
            //下级获得5%扩内专属消费券
            $sub_user = \app\api\model\User::where('upid', $user['id'])->field('id,neixuquan')->select();
            foreach ($sub_user  as  $sub){
                $payprice = \app\api\model\Order::where('user_id', $sub['id'])->where('status', 2)->sum('payprice');
                if($payprice > 10000){
                    $price_score = ceil(($config['money'] * $qty / 100) * 5);
                    $detailed_data = [
                        'user_id' => $sub['id'],
                        'money' => $price_score,
                        'before' => $sub['neixuquan'] ?? 0,
                        'after' => ($sub['neixuquan'] ?? 0) + $price_score,
                        'memo' => '[补]上级认购'.$project_data['price'].'项目专属内需券',
                        'createtime' => time(),
                        'type' => 'neixuquan',
                    ];
                    $zijin_price = ceil(($config['money'] * $qty / 100) * 1000);
                    $zijin_data = [
                        'user_id' => $sub['id'],
                        'money'   => $zijin_price,
                        'before'  => $sub['money'] ?? 0,
                        'after'   => ($sub['money'] ?? 0) + $zijin_price,
                        'memo' => '[补]上级认购'.$project_data['price'].'项目内需补贴资金',
                        'createtime' => time(),
                        'type' => 'neixu',
                    ];
                    Db::startTrans();
                    try {
                        // 5%扩内专属消费券
                        \app\api\model\User::where('id', $sub['id'])->setInc('neixuquan', $price_score);
                        \app\api\model\UserMoneyLog::create($detailed_data);
                        // 10%的内需补贴资金
                        \app\api\model\User::where('id', $sub['id'])->setInc('money',$zijin_price);
                        \app\api\model\UserMoney::where('user_id', $sub['id'])->setInc('money', $zijin_price);
                        \app\api\model\UserMoney::where('user_id', $sub['id'])->setInc('money_neixu', $zijin_price);
                        \app\api\model\UserMoneyInLog::create($zijin_data);
                        \app\api\model\UserMoneyLog::create($zijin_data);
                        Db::commit();
                    } catch (Exception $e) {
                        Db::rollback();
                        echo($e->getMessage());
                    }
                }
            }
        }
        echo("Successed!");
    }

    public function profit_log($profit_user){
        $log = date('Y-m-d H:i:s') . ' --- ' . json_encode($profit_user) . PHP_EOL;
        $path = ROOT_PATH . 'public' . DIRECTORY_SEPARATOR . 'order_patch';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        file_put_contents($path . DIRECTORY_SEPARATOR . date('Y-m-d') . 'order_patch.log', $log, FILE_APPEND);
    }    

}
