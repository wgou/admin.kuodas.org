<?php

namespace app\api\model;

use think\Model;

class Juan extends Model
{
    protected $name = 'juan';
    
    // 自动写入时间戳
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = false;

    // 追加字段
    protected $append = [
        'createtime_text',
        'paytime_text'
    ];

    public function getCreatetimeTextAttr($value, $data)
    {
        return date('Y-m-d H:i:s', $data['createtime']);
    }

    public function getPaytimeTextAttr($value, $data)
    {
        return $data['paytime'] ? date('Y-m-d H:i:s', $data['paytime']) : '';
    }

    // 关联爱心项目
    public function loveData()
    {
        return $this->belongsTo('LoveData', 'love_data_id', 'id')->setEagerlyType(0);
    }
}
