<?php

namespace app\admin\library\buiapi;

/**
 * API生成器模版
 */
class BuiapiTemp
{

    //替换数据初始化
    protected static $data = [
        'controller_name' => '',
        'model_path' => 'api',
        'model_name' => '',
        'table_name' => '',
        'controller_index' => '',
        'relation_with_list' => '',
        'visible_field_list' => '',
        'index_search_field' => '',
        'relation_visible_field_list' => '',
        'relation_method_list' => '',
        'need_login' => '[]',
        'allow_func' => '[]'
    ];
    //是否存在错误
    protected static $error_msg = [];

    /**
     * 循环处理内容并赋值
     */
    public static function handleTemplate($template = [])
    {

        $with_table = [];
        $pattern = 'normal';
        $file_model_path = '';
        $file_controller_path = '';
        $relation_visible_field_list = "";

        //提示路径信息
        $pattern_msg = "已创建";
        $model_path_msg = "";
        $validate_path_msg = "";
        $controller_path_msg = "";


        foreach ($template as $key => $temp) {

            //判断是否是全局
            if (empty($key) && strpos($temp, "local=0") !== false) {
                self::$data['model_path'] = 'common';
            }

            //是否覆盖模式
            if (empty($key) && strpos($temp, "force=1") !== false) {
                $pattern = 'force';
                $pattern_msg = "已覆盖";
            }

            //是否删除模式
            if (empty($key) && strpos($temp, "delete=1") !== false) {
                $pattern = 'delete';
                $pattern_msg = "已删除";
            }

            $relation = $relationmode = $relationforeignkey = $relationprimarykey = '';


            foreach (explode(" ", trim($temp)) as $commond) {
                list($fkey, $fval) = explode("=", $commond);
                if ($fkey == 'table') {
                    self::$data['table_name'] = $fval;
                    self::$data['model_name'] = self::convertUnderline($fval);
                    self::$data['controller_name'] = self::convertUnderline($fval);
                    $dir_model_path = APP_PATH . self::$data['model_path'] . DS . 'model';
                    $file_model_path = APP_PATH . self::$data['model_path'] . DS . 'model' . DS . self::$data['model_name'] . '.php';
                    $file_controller_path = APP_PATH . 'api' . DS . 'controller' . DS . self::$data['controller_name'] . '.php';
                    $file_validate_path = APP_PATH . self::$data['model_path'] . DS . 'validate' . DS . self::$data['model_name'] . '.php';


                    $model_path_msg = self::$data['model_path'] . DS . 'model' . DS . self::$data['model_name'] . '.php';
                    $validate_path_msg = 'api' . DS . 'controller' . DS . self::$data['controller_name'] . '.php';
                    $controller_path_msg = self::$data['model_path'] . DS . 'validate' . DS . self::$data['model_name'] . '.php';
                }

                if ($fkey == 'func' && !empty($fval)) {
                    self::$data['allow_func'] = "['" . implode("','", explode(",", $fval)) . "']";
                }

                if ($fkey == 'vlogin' && $fval == 'not') {
                    self::$data['need_login'] = "'*'";
                }

                $dir_validate = APP_PATH . self::$data['model_path'] . DS . 'validate';
                if (!is_dir($dir_validate)) {
                    $oldumask = umask(0);
                    mkdir($dir_validate, 0777);
                    umask($oldumask);
                }

                if ($fkey == 'fields') {
                    $visible_field_list = str_replace(",", "','", $fval);
                    self::$data['visible_field_list'] = "\$row->visible(['{$visible_field_list}']);";
                }

                if ($fkey == 'searchfields') {
                    $index_search_field = str_replace(",", "','", $fval);
                    self::$data['index_search_field'] = "protected \$_search_field = ['{$index_search_field}'];";
                }

                if ($fkey == 'relation') {
                    $relation = trim($fval);
                    if (!empty($relation)) {
                        self::relationModel($relation);
                    }
                }

                if ($fkey == 'relationmode') {
                    $relationmode = trim($fval);
                }

                if ($fkey == 'relationforeignkey') {
                    $relationforeignkey = trim($fval);
                }

                if ($fkey == 'relationprimarykey') {
                    $relationprimarykey = trim($fval);
                }


                if ($fkey == 'relationfields' && !empty($relation) && !empty($relationmode) && !empty($relationforeignkey) && !empty($relationprimarykey)) {


                    $table_name = self::$data['table_name'];
                    $model_name = self::$data['model_name'];
                    $relation_name = self::convertUnderline($relation);
                    $visible_value = str_replace(",", "','", $fval);
                    if (strpos($relation, '_') !== false) {
                        $relation = str_replace("_", "", $relation);
                    }
                    $with_table[] = $relation;
                    self::$data['relation_visible_field_list'] .= <<<EOT
						\$row->visible(['$relation']);
						\$row->getRelation('$relation')->visible(['$visible_value']);

EOT;
                    self::$data['relation_method_list'] .= <<<EOT
						public function $relation()
						{
							return \$this->$relationmode('$relation_name', '$relationforeignkey', '$relationprimarykey', [], 'LEFT')->setEagerlyType(0);
						}

EOT;
                }
            }

            if (!empty($with_table)) {
                self::$data['relation_with_list'] = "->with(['" . implode("','", $with_table) . "'])";
            }
        }


        //判断是否存在删除
        if ($pattern == "delete") {

            if (file_exists($file_model_path)) {
                unlink($file_model_path);
                self::$error_msg[] = sprintf("删除Model成功：%s", $model_path_msg);
            } else {
                self::$error_msg[] = sprintf("删除Model失败：%s", $model_path_msg);
            }
            if (file_exists($file_controller_path)) {
                unlink($file_controller_path);
                self::$error_msg[] = sprintf("删除Controller成功：%s", $controller_path_msg);
            } else {
                self::$error_msg[] = sprintf("删除Controller失败：%s", $controller_path_msg);
            }

            if (file_exists($file_validate_path)) {
                unlink($file_validate_path);
                self::$error_msg[] = sprintf("删除Validate验证规则成功：%s", $validate_path_msg);
            } else {
                self::$error_msg[] = sprintf("删除Validate验证规则失败：%s", $validate_path_msg);
            }

            return ['code' => 5001, 'msg' => implode("\n", self::$error_msg)];
        }

        //如果是普通模式 
        if ($pattern == "normal") {
            if (file_exists($file_model_path)) {
                self::$error_msg[] = "已存在Model文件，请勾选「强制覆盖模式」。";
                self::$error_msg[] = sprintf("已存在Model文件：%s", $model_path_msg);
            }
            if (file_exists($file_controller_path)) {
                self::$error_msg[] = "已存在Controller文件，请勾选「强制覆盖模式」。";
                self::$error_msg[] = sprintf("已存在Controller文件：%s", $controller_path_msg);
            }
        }

        if (!empty(self::$error_msg)) {
            return ['code' => 5003, 'msg' => implode("\n", self::$error_msg)];
        }

        //读取模版内容
        $index = self::getTempText('index');
        $model = self::getTempText('model');
        $controller = self::getTempText('controller');
        $validate = self::getTempText('validate');

        //替换模版内容
        foreach (self::$data as $key_data => $val_data) {
            $index = str_replace("{%" . $key_data . "%}", $val_data, $index);
            $model = str_replace("{%" . $key_data . "%}", $val_data, $model);
            $validate = str_replace("{%" . $key_data . "%}", $val_data, $validate);
            if ($key_data != 'controller_index') {
                $controller = str_replace("{%" . $key_data . "%}", $val_data, $controller);
            }
        }

        //替换模版内容
        if (!empty(self::$data['relation_visible_field_list']) || !empty(self::$data['visible_field_list'])) {
            $controller = str_replace("{%controller_index%}", $index, $controller);
        } else {
            $controller = str_replace("{%controller_index%}", '', $controller);
        }

        //写入控制器文件
        $controllerfp = fopen($file_controller_path, "w");
        fwrite($controllerfp, $controller);
        fclose($controllerfp);

        //如果API接口model不存在则创建
        if (!is_dir($dir_model_path)) {
            mkdir($dir_model_path, 0777, true);
        }

        //写入Model文件
        $modelfp = fopen($file_model_path, "w");
        fwrite($modelfp, $model);
        fclose($modelfp);

        //写入验证规则文件
        $validatefp = fopen($file_validate_path, "w");
        fwrite($validatefp, $validate);
        fclose($validatefp);

        $success_desc = [];
        $success_desc[] = sprintf("%sController文件：%s", $pattern_msg, $controller_path_msg);
        $success_desc[] = sprintf("%sModel文件：%s", $pattern_msg, $model_path_msg);
        $success_desc[] = sprintf("%sValidate验证规则文件：%s", $pattern_msg, $validate_path_msg);
        return ['code' => 0, 'msg' => implode("\n", $success_desc)];
    }

    /**
     * 生成关联表的Model文件
     */
    protected static function relationModel($table_name = "")
    {
        $model_name = self::convertUnderline($table_name);
        $dir_model = APP_PATH . self::$data['model_path'] . DS . 'model';
        $model_file = $dir_model . DS . $model_name . '.php';
        if (!is_dir($dir_model)) {
            $oldumask = umask(0);
            mkdir($dir_model, 0777);
            umask($oldumask);
        }
        if (!file_exists($model_file)) {
            $model_temp = self::getTempText('model');
            $model_temp = str_replace("{%relation_method_list%}", "", $model_temp);
            $model_temp = str_replace("{%model_path%}", self::$data['model_path'], $model_temp);
            $model_temp = str_replace("{%model_name%}", $model_name, $model_temp);
            $model_temp = str_replace("{%table_name%}", $table_name, $model_temp);
            $modelfp = fopen($model_file, "w");
            fwrite($modelfp, $model_temp);
            fclose($modelfp);
        }
    }

    /**
     * 将下划线命名转换为驼峰式命名
     */
    protected static function convertUnderline($str, $ucfirst = true)
    {
        $str = explode('_', $str);
        foreach ($str as $key => $val) {
            $str[$key] = ucfirst($val);
        }
        if (!$ucfirst) {
            $str[0] = strtolower($str[0]);
        }
        return implode('', $str);
    }

    /**
     * 读取模版内容
     */
    protected static function getTempText($tempName = '')
    {
        $file_path = '';
        if (in_array($tempName, ['model', 'controller', 'index', 'validate'])) {
            $file_path = __DIR__ . DS . 'stubs' . DS . $tempName . '.stub';
        }
        if (empty($file_path) || !file_exists($file_path)) {
            return false;
        }
        return file_get_contents($file_path);
    }

}
