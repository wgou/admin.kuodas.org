<?php

namespace app\admin\model\game;

use think\Model;


class Lottery extends Model
{

    

    

    // 表名
    protected $name = 'lottery_record';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text'
    ];



    public function getStatusList()
    {
        return ['1' => __('消费券'), '2' => __('商城经验'), '3' => __('项目收益'), '4' => __('现金奖'), '5' => __('实物奖')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['lottery_type']) ? $data['lottery_type'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    







}
