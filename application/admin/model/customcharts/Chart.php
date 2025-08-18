<?php

namespace app\admin\model\customcharts;

use think\Model;

class Chart extends Model
{
    // 表名
    protected $name = 'customcharts_chart';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_total_text',
        'chart_type_text'
    ];


    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        });

        self::beforeUpdate(function ($row) {
            $params = request()->post('row/a');
            if (!isset($params['is_distinct'])) {
                $row->is_distinct = '';
            }
        });
    }

    
    public function getTypeTotalList()
    {
        return ['sum' => __('Sum'), 'count' => __('Count')];
    }

    public function getChartTypeList()
    {
        return ['pie' => __('Pie'), 'graph' => __('Graph'), 'histogram' => __('Histogram')];
    }


    public function getTypeTotalTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type_total']) ? $data['type_total'] : '');
        $list = $this->getTypeTotalList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getChartTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['chart_type']) ? $data['chart_type'] : '');
        $list = $this->getChartTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }
}
