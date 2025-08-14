<?php

namespace app\admin\model\dreamfunding;

use think\Model;

class Record extends Model
{
    // 表名
    protected $name = 'dream_funding_record';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    
    // 追加属性
    protected $append = [
        'invest_time_text'
    ];
    
    public function getInvestTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['invest_time']) ? $data['invest_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setInvestTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    public function project()
    {
        return $this->belongsTo('Project', 'project_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
