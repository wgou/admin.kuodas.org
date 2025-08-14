<?php

namespace app\admin\model;

use think\Model;


class Develop extends Model
{

    

    

    // 表名
    protected $name = 'develop';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'investment_time_text',
        'status_text'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => __('未审核'),'1' => __('通过'),'2' => __('拒绝')];
    }


    public function getInvestmentTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['investment_time']) ? $data['investment_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setInvestmentTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
