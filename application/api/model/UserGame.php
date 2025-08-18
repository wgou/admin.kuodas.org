<?php

namespace app\api\model;

use think\Model;

class UserGame extends Model{

	protected $name = 'user_game';

    protected function getstarttimeattr($value, $data)
    {
        return date('Y-m-d H:i',$value);
    }
    protected function getendtimeattr($value, $data)
    {
        return date('Y-m-d H:i',$value);
    }
}
