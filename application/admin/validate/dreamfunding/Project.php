<?php

namespace app\admin\validate\dreamfunding;

use think\Validate;

class Project extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'name' => 'require',
        'amount_options' => 'require',
    ];
    /**
     * 提示消息
     */
    protected $message = [
        'name.require' => '项目名称不能为空',
        'amount_options.require' => '金额选项不能为空',
    ];
    /**
     * 验证场景
     */
    protected $scene = [
        'add'  => ['name', 'amount_options'],
        'edit' => ['name', 'amount_options'],
    ];
}
