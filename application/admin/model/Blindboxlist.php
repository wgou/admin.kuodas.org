<?php

namespace app\admin\model;

use think\Model;


class Blindboxlist extends Model
{

    

    

    // 表名
    protected $name = 'blindboxlist';
    
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
        return ['' => __('实体'), 'nxxfj' => __('内需消费金'), 'neixuquan' => __('内需券'),'bbxjhb'=>'现金红包','game_level_jy'=>'商城经验'];
    }





    public function blindboxtype()
    {
        return $this->belongsTo('Blindboxtype', 'blindboxtype_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
