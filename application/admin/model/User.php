<?php

namespace app\admin\model;

use app\api\model\Level;
use app\api\model\Order;
use app\admin\model\user\Nxxfjlxlog;
use app\common\model\MoneyLog;
use app\common\model\ScoreLog;
use app\common\model\UserJingyanLog;
use think\Db;
use think\Model;

class User extends Model
{

    // 表名
    protected $name = 'user';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'prevtime_text',
        'logintime_text',
        'jointime_text',
        'levename',
        'zhituinum',
        'allnum',
        'allbuy',
        'ipname',
        'levename',
    ];


    protected function getjointimeattr($value, $data)
    {
        return date('Y-m-d H:i', $value);
    }
    protected function getprevtimeattr($value, $data)
    {
        return date('Y-m-d H:i', $value);
    }
    //直推人数
    protected function getzhituinumattr($value, $data)
    {
        // return \app\api\model\User::where('upid', $data['id'])->count();
        return \app\api\model\User::where(['upid' => $data['id'], 'is_buy' => 1])->count();
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

    protected function getipnameattr($value, $data)
    {
        //IP转省区代码
        $csmip = \addons\csmip\library\Csmip::getInstance();
        $region = $csmip->getRegion($data['loginip']);

//        echo $region->country;//打印国家
//        echo $region->region;//打印区域
//        echo $region->province;//打印省区
//        echo $region->city;//打印城市
        return $region->province.'-'.$region->city;
    }




    public function getOriginData()
    {
        return $this->origin;
    }

    protected static function init()
    {
        self::beforeUpdate(function ($row) {
            $changed = $row->getChangedData();
            //如果有修改密码
            if (isset($changed['password'])) {
                if ($changed['password']) {
                    $salt = \fast\Random::alnum();
                    $row->password = \app\common\library\Auth::instance()->getEncryptPassword($changed['password'], $salt);
                    $row->salt = $salt;
                } else {
                    unset($row->password);
                }
            }
        });


        self::beforeUpdate(function ($row) {
            $changedata = $row->getChangedData();
            $origin = $row->getOriginData();
            $user_money = UserMoney::where('user_id',$origin['id'])->field(['money','money_tuijian','money_shifang','money_qiandao','money_quanhuan','money_choujiang','money_neixu','money_zhengcejin'])->find();
            if (isset($changedata['money']) && (function_exists('bccomp') ? bccomp($changedata['money'], $origin['money'], 2) !== 0 : (double)$changedata['money'] !== (double)$origin['money'])) {
                MoneyLog::create(['user_id' => $row['id'], 'money' => $changedata['money'] - $origin['money'], 'before' => $origin['money'], 'after' => $changedata['money'], 'memo' =>  $changedata['memo'] ? $changedata['memo'] : '管理员变更可提现余额']);
            }

            if (isset($changedata['money_tuijian']) && (function_exists('bccomp') ? bccomp($changedata['money_tuijian'], $user_money['money_tuijian'], 2) !== 0 : (double)$changedata['money_tuijian'] !== (double)$user_money['money_tuijian'])) {
                MoneyLog::create(['user_id' => $row['id'], 'money' => $changedata['money_tuijian'] - $user_money['money_tuijian'], 'before' => $user_money['money_tuijian'], 'after' => $changedata['money_tuijian'], 'memo' =>  $changedata['memo'] ? $changedata['memo'] : '管理员变更推荐奖励款项']);
                User::where('id', $row['id'])->setInc('money', $changedata['money_tuijian'] - $user_money['money_tuijian']);
                \app\api\model\UserMoney::money_in($row['id'],'tuijian',$changedata['money_tuijian'] - $user_money['money_tuijian'],'管理员变更推荐奖励款项');
            }
            if (isset($changedata['money_shifang']) && (function_exists('bccomp') ? bccomp($changedata['money_shifang'], $user_money['money_shifang'], 2) !== 0 : (double)$changedata['money_shifang'] !== (double)$user_money['money_shifang'])) {
                MoneyLog::create(['user_id' => $row['id'], 'money' => $changedata['money_shifang'] - $user_money['money_shifang'], 'before' => $user_money['money_shifang'], 'after' => $changedata['money_shifang'], 'memo' =>  $changedata['memo'] ? $changedata['memo'] : '管理员变更分红释放款项']);
                User::where('id', $row['id'])->setInc('money', $changedata['money_shifang'] - $user_money['money_shifang']);
                \app\api\model\UserMoney::money_in($row['id'],'shifang',$changedata['money_shifang'] - $user_money['money_shifang'],'管理员变更分红释放款项');
            }
            if (isset($changedata['money_qiandao']) && (function_exists('bccomp') ? bccomp($changedata['money_qiandao'], $user_money['money_qiandao'], 2) !== 0 : (double)$changedata['money_qiandao'] !== (double)$user_money['money_qiandao'])) {
                MoneyLog::create(['user_id' => $row['id'], 'money' => $changedata['money_qiandao'] - $user_money['money_qiandao'], 'before' => $user_money['money_qiandao'], 'after' => $changedata['money_qiandao'], 'memo' =>  $changedata['memo'] ? $changedata['memo'] : '管理员变更每日签到款项']);
                User::where('id', $row['id'])->setInc('money', $changedata['money_qiandao'] - $user_money['money_qiandao']);
                \app\api\model\UserMoney::money_in($row['id'],'qiandao',$changedata['money_qiandao'] - $user_money['money_qiandao'],'管理员变更每日签到款项');
            }
            if (isset($changedata['money_quanhuan']) && (function_exists('bccomp') ? bccomp($changedata['money_quanhuan'], $user_money['money_quanhuan'], 2) !== 0 : (double)$changedata['money_quanhuan'] !== (double)$user_money['money_quanhuan'])) {
                MoneyLog::create(['user_id' => $row['id'], 'money' => $changedata['money_quanhuan'] - $user_money['money_quanhuan'], 'before' => $user_money['money_quanhuan'], 'after' => $changedata['money_quanhuan'], 'memo' =>  $changedata['memo'] ? $changedata['memo'] : '管理员变更券换资金款项']);
                User::where('id', $row['id'])->setInc('money', $changedata['money_quanhuan'] - $user_money['money_quanhuan']);
                \app\api\model\UserMoney::money_in($row['id'],'quanhuan',$changedata['money_quanhuan'] - $user_money['money_quanhuan'],'管理员变更券换资金款项');
            }
            if (isset($changedata['money_choujiang']) && (function_exists('bccomp') ? bccomp($changedata['money_choujiang'], $user_money['money_choujiang'], 2) !== 0 : (double)$changedata['money_choujiang'] !== (double)$user_money['money_choujiang'])) {
                MoneyLog::create(['user_id' => $row['id'], 'money' => $changedata['money_choujiang'] - $user_money['money_choujiang'], 'before' => $user_money['money_choujiang'], 'after' => $changedata['money_choujiang'], 'memo' =>  $changedata['memo'] ? $changedata['memo'] : '管理员变更抽奖资金款项']);
                User::where('id', $row['id'])->setInc('money', $changedata['money_choujiang'] - $user_money['money_choujiang']);
                \app\api\model\UserMoney::money_in($row['id'],'choujiang',$changedata['money_choujiang'] - $user_money['money_choujiang'],'管理员变更抽奖资金款项');
            }
            if (isset($changedata['money_neixu']) && (function_exists('bccomp') ? bccomp($changedata['money_neixu'], $user_money['money_neixu'], 2) !== 0 : (double)$changedata['money_neixu'] !== (double)$user_money['money_neixu'])) {
                MoneyLog::create(['user_id' => $row['id'], 'money' => $changedata['money_neixu'] - $user_money['money_neixu'], 'before' => $user_money['money_neixu'], 'after' => $changedata['money_neixu'], 'memo' =>  $changedata['memo'] ? $changedata['memo'] : '管理员变更内需奖励款项']);
                User::where('id', $row['id'])->setInc('money', $changedata['money_neixu'] - $user_money['money_neixu']);
                \app\api\model\UserMoney::money_in($row['id'],'neixu',$changedata['money_neixu'] - $user_money['money_neixu'],'管理员变更内需奖励款项');
            }
            
            if (isset($changedata['money_zhengcejin']) && (function_exists('bccomp') ? bccomp($changedata['money_zhengcejin'], $user_money['money_zhengcejin'], 2) !== 0 : (double)$changedata['money_zhengcejin'] !== (double)$user_money['money_zhengcejin'])) {
                MoneyLog::create(['user_id' => $row['id'], 'money' => $changedata['money_zhengcejin'] - $user_money['money_zhengcejin'], 'before' => $user_money['money_zhengcejin'], 'after' => $changedata['money_zhengcejin'], 'memo' =>  $changedata['memo'] ? $changedata['memo'] : '管理员变更政策金款项']);
                User::where('id', $row['id'])->setInc('money', $changedata['money_zhengcejin'] - $user_money['money_zhengcejin']);
                \app\api\model\UserMoney::money_in($row['id'],'zhengcejin',$changedata['money_zhengcejin'] - $user_money['money_zhengcejin'],'管理员变更政策金款项');
            }
            
            if (isset($changedata['nomoney']) && (function_exists('bccomp') ? bccomp($changedata['nomoney'], $origin['nomoney'], 2) !== 0 : (double)$changedata['nomoney'] !== (double)$origin['nomoney'])) {
                MoneyLog::create(['user_id' => $row['id'], 'money' => $changedata['nomoney'] - $origin['nomoney'], 'before' => $origin['nomoney'], 'after' => $changedata['nomoney'], 'memo' =>  $changedata['memo'] ?  $changedata['memo'] : '管理员变更账户余额']);
            }
            
            if (isset($changedata['nxxfjlx']) && (function_exists('bccomp') ? bccomp($changedata['nxxfjlx'], $origin['nxxfjlx'], 2) !== 0 : (double)$changedata['nxxfjlx'] !== (double)$origin['nxxfjlx'])) {
                Nxxfjlxlog::create(['user_id' => $row['id'], 'money' => bcsub($changedata['nxxfjlx'],$origin['nxxfjlx'],2), 'before' => $origin['nxxfjlx'], 'after' => $changedata['nxxfjlx'], 'memo' =>  $changedata['memo'] ?  $changedata['memo'] : '管理员变更账户余额']);
            }
            
            if (isset($changedata['score']) && (int)$changedata['score'] !== (int)$origin['score']) {
                MoneyLog::create(['user_id' => $row['id'], 'money' => $changedata['score'] - $origin['score'], 'before' => $origin['score'], 'after' => $changedata['score'], 'memo' =>  $changedata['memo'] ?  $changedata['memo'] : '管理员变更消费券', 'type'=>'coupon']);
                ScoreLog::create(['user_id' => $row['id'], 'score' => $changedata['score'] - $origin['score'], 'before' => $origin['score'], 'after' => $changedata['score'], 'memo' =>  $changedata['memo'] ?  $changedata['memo'] : '管理员变更消费券']);
            }
            if (isset($changedata['neixuquan']) && (int)$changedata['neixuquan'] !== (int)$origin['neixuquan']) {
                MoneyLog::create(['user_id' => $row['id'], 'money' => $changedata['neixuquan'] - $origin['neixuquan'], 'before' => $origin['neixuquan'], 'after' => $changedata['neixuquan'], 'memo' =>  $changedata['memo'] ?  $changedata['memo'] : '管理员变更内需券', 'type'=>'neixuquan']);
                ScoreLog::create(['user_id' => $row['id'], 'score' => $changedata['neixuquan'] - $origin['neixuquan'], 'before' => $origin['neixuquan'], 'after' => $changedata['neixuquan'], 'memo' =>  $changedata['memo'] ?  $changedata['memo'] : '管理员变更内需券']);
            }
            if (isset($changedata['game_level_jy']) && (int)$changedata['game_level_jy'] !== (int)$origin['game_level_jy']) {
                UserJingyanLog::create(['user_id' => $row['id'], 'score' => $changedata['game_level_jy'] - $origin['game_level_jy'], 'before' => $origin['game_level_jy'], 'after' => $changedata['game_level_jy'], 'memo' =>  $changedata['memo'] ?  $changedata['memo'] : '管理员变更经验']);
                //自动更新商城等级
                $game_level = Db::name('game_level')->where('jy','>' ,$changedata['game_level_jy'])->order('id ASC')->find();
                $up_level = $game_level ? $game_level['name'] - 1 : 20;
                Db::name('user')->where('id',$row['id'])->update(['game_level'=>$up_level]);
            }
        });
    }

    public function getGenderList()
    {
        return ['1' => __('Male'), '0' => __('Female')];
    }

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }
    
    public function getAccType()
    {
        return ['1' => __('用户'), '2' => __('机器人')];
    }


    public function getPrevtimeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['prevtime'] ?? "");
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getLogintimeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['logintime'] ?? "");
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getJointimeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['jointime'] ?? "");
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPrevtimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setLogintimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setJointimeAttr($value)
    {
        return $value && !is_numeric($value) ? strtotime($value) : $value;
    }

    protected function setBirthdayAttr($value)
    {
        return $value ? $value : null;
    }

    public function group()
    {
        return $this->belongsTo('UserGroup', 'group_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
