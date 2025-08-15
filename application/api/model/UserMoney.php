<?php

namespace app\api\model;

use think\Db;
use think\Model;

class UserMoney extends Model
{

    protected $name = 'user_money';

    /**
     * 资金入账操作
     * @param $user_id  //用户ID
     * @param $money_type //资金类型
     * @param $money    //资金金额
     * @param $memo     //备注详情
     * @return void
     */
    public static function money_in($user_id, $money_type, $money, $memo){
        Db::startTrans();
        try {
            $dataUser = \app\api\model\UserMoney::where('user_id',$user_id)->find();
            if ($dataUser && $money != 0) {
                $dataIns  = [
                    'user_id' => $user_id,
                    'money' => $money,
                    'type' => $money_type,
                    'before' => $dataUser['money_'.$money_type],
                    'after' => $dataUser['money_'.$money_type] + $money,
                    'memo' => $memo,
                    'createtime' => time()
                ];
                UserMoneyInLog::create($dataIns);
                UserMoney::where('user_id', $user_id)->setInc('money', $money);
                UserMoney::where('user_id', $user_id)->setInc('money_'.$money_type, $money);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
    }
}
