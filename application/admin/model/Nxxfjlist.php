<?php

namespace app\admin\model;

use think\Model;


class Nxxfjlist extends Model
{

    

    

    // 表名
    protected $name = 'nxxfjlist';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'expirytime_text'
    ];
    

    
    public function getStatusList()
    {
        return ['1' => __('Status 1'),'2' => __('Status 2')];
    }




    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getExpirytimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['expirytime']) ? $data['expirytime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    protected function setExpirytimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
