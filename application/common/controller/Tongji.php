<?php

namespace app\common\controller;

class Tongji
{

    public static function inc($field)
    {
        $date = self::getDate();
        \app\common\model\Tongji::where('date', $date)->setInc($field);
    }
    public static function getDate()
    {
        $date = date('Y-m-d');
        if (!\app\common\model\Tongji::where('date', $date)->find()) {
            \app\common\model\Tongji::create(['date' => $date]);
        }
        return $date;
    }
}
