<?php

namespace app\admin\model\csmip;

use think\Model;


class Data extends Model
{

    

    

    // 表名
    protected $name = 'csmip_data';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'needuserlogin_text',
        'status_text'
    ];
    

    
    public function getNeeduserloginList()
    {
        return ['N' => __('Needuserlogin n'),'Y' => __('Needuserlogin y')];
    }

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }


    public function getNeeduserloginTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['needuserlogin']) ? $data['needuserlogin'] : '');
        $list = $this->getNeeduserloginList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
