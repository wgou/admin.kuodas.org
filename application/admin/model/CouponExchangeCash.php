<?php

namespace app\admin\model;

use think\Model;


class CouponExchangeCash extends Model
{

    // 表名
    protected $name = 'coupon_exchange_cash';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text'
    ];

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0'), '2' => __('Status 2')];
    }
    public function approveList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0')];
    }
}
