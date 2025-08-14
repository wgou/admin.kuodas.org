<?php

namespace app\admin\library\buiapi;

use think\Db;
use think\Config;
use app\admin\model\BuiapiField;

/**
 * 模型管理管理
 */
class BuiApiFunc
{

    public static $rules = [
        'require' => '必添',
        'accepted' => '接受 1 yes on 验证',
        'date' => '是否是一个有效日期',
        'alpha' => '只允许字母',
        'chs' => '只允许汉字',
        'alphaNum' => '只允许字母和数字',
        'activeUrl' => '是否为有效的网址',
        'ip' => '是否为IP地址',
        'url' => '是否为一个URL地址',
        'number' => '是否为数字',
        'integer' => '是否为整型',
        'email' => '是否为邮箱地址',
        'boolean' => '是否为布尔值',
        'array' => '是否为数组',
        'file' => '文件验证',
        'image' => '图片验证',
        'token' => 'TOEKN验证'
        //'float'		  => '是否为float',
        //'chsAlpha'	  => '只允许汉字、字母',
        //'chsAlphaNum' => '只允许汉字、字母和数字',
        //'chsDash'	  => '只允许汉字、字母、数字和下划线_及破折号-',
        //'alphaDash'   => '只允许字母数字和下划线破折号',
    ];
    protected static $table_list = [];
    protected static $not_sync_table = ['admin', 'admin_log', 'auth_group', 'auth_group_access', 'auth_rule', 'config', 'ems', 'sms', 'user', 'user_group', 'user_money_log', 'user_rule', 'user_score_log', 'user_token', 'version', 'buiapi_field', 'buiapi_table'];

    /**
     * 获取当前数据库的表列表
     */
    public static function getTableList($debar = [])
    {
        $tableList = Db::query("SHOW TABLE STATUS");
        foreach ($tableList as $key => $row) {
            $table_name = str_replace(Config::get('database.prefix'), '', trim($row['Name']));
            if (in_array($table_name, $debar) || in_array($table_name, self::$not_sync_table)) {
                continue;
            }
            self::$table_list[$row['Name']] = !empty($row['Comment']) ? trim($row['Comment']) : "缺省";
        }
        return self::$table_list;
    }

    /**
     * 获取所有的数据
     */
    public static function getTableFormatData($list = [])
    {
        $tables = [];
        foreach ($list as $key => $row) {
            list($table_name, $table_desc) = explode("|", $row);
            if (empty($table_name) || empty($table_desc)) {
                continue;
            }

            //初始化
            list($modelName, $currTime) = ['', time()];
            $tableName = str_replace(Config::get('database.prefix'), '', trim($table_name));


            if (substr_count($tableName, '_')) {
                $exp = explode('_', $tableName);
                foreach ($exp as $val) {
                    $modelName .= ucfirst(strtolower($val));
                }
            } else {
                $modelName = ucfirst(strtolower($tableName));
            }
            $tables[] = ['name' => $modelName, 'table' => $tableName, 'desc' => $table_desc, 'createtime' => $currTime, 'updatetime' => $currTime, 'is_show' => 1];
        }
        return $tables;
    }

    /**
     * 同步当前表的所有字段数据
     */
    public static function getTableFieldFormatData(array $table = [])
    {
        $insert_data = [];
        foreach ($table as $key => $row) {
            $field_list = Db::query(sprintf("SHOW FULL COLUMNS FROM %s%s", Config::get('database.prefix'), $row['table']));
            if (empty($field_list)) {
                continue;
            }

            //处理字段
            foreach ($field_list as $field) {

                //默认值
                list($field_string, $field_json) = ['', []];
                list($type, $length, $comment, $default, $title) = ['', '', '缺省', '', '缺省'];

                //如果是主键ID略过
                if (strtolower($field['Field']) == 'id') {
                    continue;
                }

                //如果存在略过
                if (BuiapiField::where(['field' => $field['Field'], 'table' => $row['table']])->count()) {
                    continue;
                }

                //如果是TEXT类型
                if (in_array($field['Type'], ['text', 'date', 'datetime', 'time'])) {
                    list($type, $length, $title) = [$field['Type'], '', trim($field['Comment'])];
                    list($comment, $default) = [$field['Comment'], trim($field['Default'])];
                }

                //正则匹配
                $pattern = "/([\w\W]*)\(([\w\W]+)?\)/i"; //enum('0','1','2')
                preg_match($pattern, $field['Type'], $match);


                //如果类型是 varchar、int、decimal、tinyint
                if (isset($match[1]) && in_array($match[1], ['varchar', 'int', 'decimal', 'tinyint'])) {
                    list($type, $length, $title) = [$match[1], $match[2], trim($field['Comment'])];
                    list($comment, $default) = [$field['Comment'], trim($field['Default'])];
                }


                //如果类型是 set、enum
                if (isset($match[1]) && in_array($match[1], ['set', 'enum'])) {
                    $list = str_replace("'", '', explode(',', $match[2]));
                    $field_string = implode(",", $list);
                    foreach ($list as $key => $vals) {
                        if (empty($key) && empty($field['Comment'])) {
                            $default = $vals;
                        }
                        $list[$key] = sprintf("%s=%s", $vals, $vals);
                        $field_json[$vals] = $vals;
                    }
                    $liststr = implode(',', $list);
                    list($type, $length) = [$match[1], '255'];
                    $comment = (!empty($field['Comment'])) ? sprintf("%s:%s", $field['Comment'], $liststr) : sprintf("%s:%s", $comment, $liststr);
                    $title = (!empty($field['Comment'])) ? trim($field['Comment']) : $title;

                    if (strpos($field['Comment'], ':') !== false && strpos($field['Comment'], ',') !== false) {
                        $field_json = [];
                        list($comment1, $comment2) = explode(':', $field['Comment']);
                        foreach (explode(',', $comment2) as $vals) {
                            list($v1, $v2) = explode('=', $vals);
                            $field_json["$v1"] = $v2;
                        }
                    }
                }

                if (isset($match[1]) && !in_array($match[1], ['varchar', 'int', 'decimal', 'tinyint', 'set', 'enum'])) {
                    list($type, $length, $title) = [$match[1], $match[2], trim($field['Comment'])];
                    list($comment, $default) = [$field['Comment'], trim($field['Default'])];
                }

                if (strpos($title, ':') !== false) {
                    list($title, $defaultValue) = explode(":", $title);
                }

                if ($title == '缺省') {
                    $title = sprintf("%s(%s)", $title, trim($field['Field']));
                }

                //处理字段
                $curr_time = time();
                $insert_data[] = [
                    'table' => trim($row['table']),
                    'title' => $title,
                    'name' => trim($field['Field']),
                    'field' => trim($field['Field']),
                    'type' => $type,
                    'length' => $length,
                    'default' => $default,
                    'remark' => $comment,
                    'createtime' => $curr_time,
                    'updatetime' => $curr_time,
                    'field_string' => $field_string,
                    'field_json' => (!empty($field_json)) ? json_encode($field_json, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT) : '',
                ];
            }
        }
        return $insert_data;
    }

}
