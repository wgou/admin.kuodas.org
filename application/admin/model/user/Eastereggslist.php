<?php

namespace app\admin\model\user;

use think\Model;


class Eastereggslist extends Model
{

    

    

    // 表名
    protected $name = 'user_eastereggslist';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







    public function eastereggslists()
    {
        return $this->belongsTo('app\admin\model\Eastereggslist', 'eastereggslist_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    public function address()
    {
        return $this->belongsTo('app\admin\model\leescore\Address', 'user_id', 'uid', [], 'LEFT')->order('id', 'desc');;
    }
}
