<?php

namespace app\api\model;

use think\Model;

class User extends Model
{

    protected $name = 'user';

    protected $append = [
        'zhituinum',
        'allnum',
        'allbuy',
        'levename',
    ];

    protected function getjointimeattr($value, $data)
    {
        return date('Y-m-d H:i', $value);
    }

    //直推人数
    protected function getzhituinumattr($value, $data)
    {
        return User::where('upid', $data['id'])->count();
    }

    protected function getlevenameattr($value, $data)
    {
        return Level::where('id', $data['level'])->value('name') ?? '无身份';
    }

    //全部下级
    protected function getallnumattr($value, $data)
    {
        return User::where('upid|upid2|upid3', $data['id'])->count();
    }

    //累计消费
    protected function getallbuyattr($value, $data)
    {
        return Order::where('user_id', $data['id'])->sum('price');
    }




}
