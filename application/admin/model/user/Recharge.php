<?php

namespace app\admin\model\user;

use addons\epay\library\Service;
use app\common\model\User;
use think\Exception;
use think\Model;
use Yansongda\Pay\Pay;

class Recharge extends Model
{


    // 表名
    protected $name = 'Recharge';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
    ];

    public static function init()
    {
       
    }

    public function getStatusList()
    {
        return ['2' => __('Status created'), '1' => __('Status successed'), '0' => __('Status rejected')];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

    public function user()
    {
        return $this->belongsTo("\\app\\common\\model\\User", "user_id", "id")->setEagerlyType(0);
    }
}
