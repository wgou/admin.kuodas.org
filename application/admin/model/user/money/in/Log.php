<?php

namespace app\admin\model\user\money\in;

use think\Model;


class Log extends Model
{

    

    

    // 表名
    protected $name = 'user_money_in_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text'
    ];
    

    
    public function getTypeList()
    {
        return ['tuijian' => __('Tuijian'), 'shifang' => __('Shifang'), 'qiandao' => __('Qiandao'), 'quanhuan' => __('Quanhuan'), 'choujiang' => __('Choujiang'), 'bbxjhb' => __('Bbxjhb'), 'neixu' => __('Neixu')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }




    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
