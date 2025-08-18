<?php

namespace app\api\model;

use think\Model;

class UserMoneyLog extends Model
{

    protected $name = 'user_money_log';

    protected function getcreatetimeattr($value, $data)
    {
        return date('Y-m-d H:i',$value);
    }
}
