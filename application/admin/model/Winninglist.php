<?php

namespace app\admin\model;

use think\Model;


class Winninglist extends Model
{

    

    

    // 表名
    protected $name = 'winninglist';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    public function address()
    {
        return $this->belongsTo('app\admin\model\leescore\Address', 'user_id', 'uid', [], 'LEFT')->order('id', 'desc');;
    }

    public function prizelist()
    {
        return $this->belongsTo('Prizelist', 'prizelist_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
