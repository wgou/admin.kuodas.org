<?php
namespace app\admin\controller\csmip;
 
use Exception;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use addons\csmip\library\Csmip;
use addons\csmip\library\CsmBackend;
use app\admin\library\Auth;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

/**
 * IP地址
 *
 * @icon fa fa-circle-o
 */
class Dataline extends CsmBackend
{
    
    /**
     * Dataline模型对象
     * @var \app\admin\model\csmip\Dataline
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\csmip\Dataline;
        $this->view->assign("statusList", $this->model->getStatusList());
    }
    
 
    public function testipform(){
        if ($this->request->isPost()) {
            $ip = $this->csmreq("ip", true);
            $csmip = Csmip::getInstance();
            $region = $csmip->getRegion($ip);
            $param = [
                "country"=>$region->country,
                "region"=>$region->region,
                "province"=>$region->province,
                "city"=>$region->city,
                "isp"=>$region->isp,
                "ipregregion"=>$region->ipregregion,
                "code"=>$this->_getcode($ip)
            ];
            $this->success("","",$param);
        }
        $this->view->assign("ip", $ip = $this->request->ip());
        return $this->view->fetch();
    }
    
    public function _getcode($ip){
        $str = '
//IP转省区代码
$csmip = \addons\csmip\library\Csmip::getInstance();
$region = $csmip->getRegion("'.$ip.'");

echo $region->country;//打印国家
echo $region->region;//打印区域
echo $region->province;//打印省区
echo $region->city;//打印城市
        ';
        return $str;
    }
    /**
     * 查看（从邮件邀请跳转过来）
     */
    public function index()
    {
        // 当前页面必须从活动页面跳转过来
        $parentid = $this->csmreq("parentid", true);
        $parent = $this->csmGetDbRowByReqest(new \app\admin\model\csmip\Data(), "parentid");
        $this->assign('parent', $parent);
        
        // 设置过滤方法
        $this->request->filter([
            'strip_tags'
        ]);
        if ($this->request->isAjax()) {
            // 如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list ($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model->where($where)
            ->where("csmip_data_id", "=", $parentid)
            ->order($sort, $order)
            ->count();
            
            $list = $this->model->where($where)
            ->where("csmip_data_id", "=", $parentid)
            ->order($sort, $order)
            ->limit($offset, $limit)
            ->select();
            
            $list = collection($list)->toArray();
            $result = array(
                "total" => $total,
                "rows" => $list
            );
            
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                
                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $csmip = Csmip::getInstance();
                    $region = $csmip->getRegion($params['ip']);
                    
                    $params['country'] = $region->country;
                    $params['region'] = $region->region;
                    $params['province'] = $region->province;
                    $params['city'] = $region->city;
                    $params['isp'] = $region->isp;
                    $params['ipregcityid'] = $region->ipregcityid;
                    $params['ipregregion'] = $region->ipregregion;
                    
                    
                    $result = $this->model->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }
    
    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    //$params['accountkeyrule'] = strtolower($params['accountkeyrule']);
                    $csmip = Csmip::getInstance();
                    $region = $csmip->getRegion($params['ip']);
            
                    $params['country'] = $region->country;
                    $params['region'] = $region->region;
                    $params['province'] = $region->province;
                    $params['city'] = $region->city;
                    $params['isp'] = $region->isp;
                    $params['ipregcityid'] = $region->ipregcityid;
                    $params['ipregregion'] = $region->ipregregion;
                    
                    $result = $row->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }
    
    
    /**
     * 导入
     */
    public function import()
    {
        $parentid = $this->csmreq("parentid", true);
        $file = $this->request->request('file');
        if (!$file) {
            $this->error(__('Parameter %s can not be empty', 'file'));
        }
        $filePath = ROOT_PATH . DS . 'public' . DS . $file;
        if (!is_file($filePath)) {
            $this->error(__('No results were found'));
        }
        //实例化reader
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
            $this->error(__('Unknown data format'));
        }
        if ($ext === 'csv') {
            $file = fopen($filePath, 'r');
            $filePath = tempnam(sys_get_temp_dir(), 'import_csv');
            $fp = fopen($filePath, "w");
            $n = 0;
            while ($line = fgets($file)) {
                $line = rtrim($line, "\n\r\0");
                $encoding = mb_detect_encoding($line, ['utf-8', 'gbk', 'latin1', 'big5']);
                if ($encoding != 'utf-8') {
                    $line = mb_convert_encoding($line, 'utf-8', $encoding);
                }
                if ($n == 0 || preg_match('/^".*"$/', $line)) {
                    fwrite($fp, $line . "\n");
                } else {
                    fwrite($fp, '"' . str_replace(['"', ','], ['""', '","'], $line) . "\"\n");
                }
                $n++;
            }
            fclose($file) || fclose($fp);
            
            $reader = new Csv();
        } elseif ($ext === 'xls') {
            $reader = new Xls();
        } else {
            $reader = new Xlsx();
        }
        
        
        //加载文件
        $insert = [];
        try {
            if (!$PHPExcel = $reader->load($filePath)) {
                $this->error(__('Unknown data format'));
            }
            $currentSheet = $PHPExcel->getSheet(0);  //读取文件中的第一个工作表
            $allRow = $currentSheet->getHighestRow(); //取得一共有多少行
            
   
            for ($currentRow = 2; $currentRow <= $allRow; $currentRow++) {           
                $row = [
                    "ip"=>$currentSheet->getCellByColumnAndRow('0', $currentRow)->getValue()
                ];
                if ($row) {
                    $insert[] = $row;
                }
            }
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
        }
        if (!$insert) {
            $this->error(__('No rows were updated'));
        }
       
        
        try {
            foreach($insert as $row){
                $param = [
                    "csmip_data_id"=>$parentid,
                    "ip"=>$row['ip'],
                    "status"=>"normal",
                    "createtime"=>time()
                ];
                $this->model->create($param);
            }

        } catch (PDOException $exception) {
            $msg = $exception->getMessage();
            if (preg_match("/.+Integrity constraint violation: 1062 Duplicate entry '(.+)' for key '(.+)'/is", $msg, $matches)) {
                $msg = "导入失败，包含【{$matches[1]}】的记录已存在";
            };
            $this->error($msg);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
        
        $this->success();
    }
}
