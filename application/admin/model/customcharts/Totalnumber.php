<?php

namespace app\admin\model\customcharts;

use think\Model;

class Totalnumber extends Model
{

    // 表名
    protected $name = 'customcharts_total_number';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_total_text',
        'type_time_text'
    ];

    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        });
    }
    
    public function getTypeTotalList()
    {
        return ['sum' => __('Sum'), 'count' => __('Count')];
    }

    public function getTypeTotalTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type_total']) ? $data['type_total'] : '');
        $list = $this->getTypeTotalList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getTypeTimeList()
    {
        return ['today' => __('Today'), 'week' => __('Week'), 'month' => __('Month'), 'all' => __('All')];
    }

    public function getTypeTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type_time']) ? $data['type_time'] : '');
        $list = $this->getTypeTimeList();
        return isset($list[$value]) ? $list[$value] : '';
    }
}
