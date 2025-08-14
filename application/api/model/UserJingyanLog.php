<?php

namespace app\api\model;

use think\Model;

class UserJingyanLog extends Model
{

    protected $name = 'user_jingyan_log';

    protected function getcreatetimeattr($value, $data)
    {
        return date('Y-m-d H:i',$value);
    }
}
