<?php

namespace app\admin\validate\dreamfunding;

use think\Validate;

class Record extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'user_id' => 'require|number',
        'real_name' => 'require',
        'name' => 'require',
        'phone' => 'require|mobile',
        'id_card' => 'require|idCard',
        'project_id' => 'require|number',
        'amount' => 'require|float|gt:0',
        'invest_time' => 'require',
    ];
    /**
     * 提示消息
     */
    protected $message = [
        'user_id.require' => '用户ID不能为空',
        'user_id.number' => '用户ID必须是数字',
        'real_name.require' => '实名认证姓名不能为空',
        'name.require' => '姓名不能为空',
        'phone.require' => '电话不能为空',
        'phone.mobile' => '电话格式不正确',
        'id_card.require' => '身份证不能为空',
        'id_card.idCard' => '身份证格式不正确',
        'project_id.require' => '项目不能为空',
        'project_id.number' => '项目ID必须是数字',
        'amount.require' => '投资金额不能为空',
        'amount.float' => '投资金额必须是数字',
        'amount.gt' => '投资金额必须大于0',
        'invest_time.require' => '投资时间不能为空',
    ];
    /**
     * 验证场景
     */
    protected $scene = [
        'add'  => ['user_id', 'real_name', 'name', 'phone', 'id_card', 'project_id', 'amount', 'invest_time'],
        'edit' => ['user_id', 'real_name', 'name', 'phone', 'id_card', 'project_id', 'amount', 'invest_time'],
    ];
}
