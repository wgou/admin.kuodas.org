<?php

namespace app\admin\model;

use app\api\model\ProjectData;
use app\api\model\ProjectTask;
use think\Model;


class Order extends Model
{





    // 表名
    protected $name = 'order';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'paytime_text',
        'projecttaskok',
        'yiling',
        'dailing',
    ];



    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }
    public function getprojecttaskokAttr($value, $data)
    {

        return ProjectTask::where('project_data_id',$data['id'])->where('status',2)->count()??0;
    }
    public function getyilingAttr($value, $data)
    {

        return ProjectTask::where('project_data_id',$data['project_data_id'])->where('status',2)->sum('fanli_money')??0;
    }
    public function getdailingAttr($value, $data)
    {

        return ProjectTask::where('project_data_id',$data['project_data_id'])->where('status',1)->sum('fanli_money')??0;
    }

    public function getPaytimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['paytime']) ? $data['paytime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPaytimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function user()
    {
        return $this->belongsTo(\app\api\model\User::class, 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    public function projectdata()
    {
        return $this->belongsTo(ProjectData::class, 'project_data_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
