<?php

namespace app\admin\model;

use think\Model;


class Prizelist extends Model
{

    

    

    // 表名
    protected $name = 'prizelist';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        });
    }

    
    public function getFieldsList()
    {
        return ['' => __('实体'), 'nxxfj' => __('内需消费金'), 'neixuquan' => __('内需卷')];
    }






    public function lotterylist()
    {
        return $this->belongsTo('Lotterylist', 'lotterylist_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
