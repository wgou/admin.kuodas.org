<?php

namespace app\admin\model;

use think\Model;


class Banner extends Model
{

    

    

    // 表名
    protected $name = 'banner';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'path_text'
    ];
    

    
    public function getPathList()
    {
        return ['1' => __('Path 1'), '2' => __('Path 2')];
    }


    public function getPathTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['path']) ? $data['path'] : '');
        $list = $this->getPathList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
