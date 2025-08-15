<?php

namespace app\api\model;

use think\Model;

class Withdraw extends Model
{

    protected $name = 'withdraw';

    protected function getcreatetimeattr($value, $data)
    {
        return date('Y-m-d H:i',$value);
    }
}
