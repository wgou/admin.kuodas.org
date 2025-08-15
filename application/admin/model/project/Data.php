<?php

namespace app\admin\model\project;

use think\Model;


class Data extends Model
{

    

    

    // 表名
    protected $name = 'project_data';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'sell_text'
    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        });
    }

    
    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2')];
    }

    public function getSellList()
    {
        return ['1' => __('Sell 1'), '2' => __('Sell 2')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getSellTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['sell']) ? $data['sell'] : '');
        $list = $this->getSellList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
