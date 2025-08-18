<?php

namespace app\api\library\buiapi;

use think\Db;
use think\Config;
use think\Validate;
use app\admin\model\BuiapiField;

/**
 * 动态验证规则
 */
class VerfiyRule
{

    //验证规则
    protected static $rule = [];

    //参数值
    protected static $data = [];

    //字段描述
    protected static $desc = [];

    /**
     * 执行逻辑
     */
    public static function exec($type = 'add', $table = '', $params = [])
    {

        if (!in_array($type, ['add', 'edit']) || empty($table)) {
            return [false, '参数错误'];
        }


        if (!empty($fouField = self::verfiyParam($table, $params))) {
            return [true, sprintf("%s 非法字段", $fouField)];
        }

        $rule_key = sprintf("rule_%s", $type);
        $rule_list = BuiapiField::where(['table' => $table, 'is_show' => 1])->where("{$rule_key} != ''")->select();
        if (empty($rule_list)) {
            return [false, '暂无数据'];
        }
        $rule_list = collection($rule_list)->toArray();
        foreach ($rule_list as $key => $val) {
            self::$desc[$val['field']] = $val['title'];
            self::$rule[$val['field']] = $val[$rule_key];
        }


        $validate = new Validate(self::$rule, [], self::$desc);
        $result = $validate->check($params);
        if (!$result) {
            return [true, $validate->getError()];
        }
        return [false, '验证通过'];
    }


    /**
     * 对不在字段内的参数进行效验
     */
    protected static function verfiyParam($table = '', $params = [])
    {
        $retult = Db::query(sprintf("show columns from %s%s", Config::get('database.prefix'), $table));
        $retult = array_column($retult, 'Field');
        foreach ($params as $field => $value) {
            if (!in_array($field, $retult)) {
                return $field;
            }
        }
        return '';
    }
}
