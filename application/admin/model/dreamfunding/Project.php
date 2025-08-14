<?php

namespace app\admin\model\dreamfunding;

use think\Model;

class Project extends Model
{
    // 表名
    protected $name = 'dream_funding_project';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    
    // 追加属性
    protected $append = [
        'status_text'
    ];
    
    // 类型转换
    protected $type = [
        'amount_options' => 'json'
    ];
    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }
    
    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : $data['status'];
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }
    
    protected static function init()
    {
        self::beforeWrite(function ($row) {
            // 确保 amount_options 是数组
            if (isset($row['amount_options'])) {
                if (is_string($row['amount_options'])) {
                    $row['amount_options'] = json_decode($row['amount_options'], true) ?: [];
                }
                // 过滤空值
                if (is_array($row['amount_options'])) {
                    $row['amount_options'] = array_values(array_filter($row['amount_options'], function($value) {
                        return $value !== '';
                    }));
                }
            }
        });
    }
}
