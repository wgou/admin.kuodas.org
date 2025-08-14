<?php

namespace app\api\model;

use think\Model;

class RechargeHistory extends Model
{
    // 表名
    protected $name = 'recharge';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'addtime';
    protected $updateTime = false;
    
    // 追加属性
    protected $append = [
        'status_text',
    ];
    
    public function getStatusTextAttr($value, $data)
    {
        $status = $data['status'] ?? 0;
        $statusList = [
            0 => '已取消',
            1 => '充值成功',
            2 => '处理中',
        ];
        return $statusList[$status] ?? '未知状态';
    }
    
    // 关联用户
    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id');
    }
    
    // 关联支付方式
    public function payment()
    {
        return $this->belongsTo('Payment', 'payment_id', 'id');
    }
}
