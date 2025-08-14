<?php

namespace app\common\model;

use think\Model;

/**
 * 会员经验日志模型
 */
class UserJingyanLog extends Model
{

    // 表名
    protected $name = 'user_jingyan_log';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = '';
    // 追加属性
    protected $append = [
    ];
}
