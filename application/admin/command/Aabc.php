<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
use think\Db;

class Aabc extends Command
{

    protected function configure()
    {
        $this->setName('aabc')
            ->setDescription('aabc');
    }

    //定时任务每分钟执行
    protected function execute(Input $input, Output $output)
    {
                $targetDir = '/www/wwwroot/admin.kuodas.org/public/new_folder/';
        // 创建目标目录（如不存在）
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        
        // 查询 card_image 和 cardbm_image 不为空的记录
        $images = Db::name('user')
    ->where('card_image', '<>', '')
    ->where('card_image', 'not null')
    ->where('cardbm_image', '<>', '')
    ->where('cardbm_image', 'not null')
    ->where('is_rot', 'eq',1)
            ->select();
        foreach ($images as $key=>$img) {
            $fields = ['card_image', 'cardbm_image'];
            foreach ($fields as $field) {
                $originalPath = $img[$field];
                // 去掉域名（http:// 或 https:// 后的域名）
                $cleanPath = preg_replace('#^https?://[^/]+/#', '', ltrim($originalPath, '/'));
                
                // 构建源路径（如果实际路径带 public/ 可加上）
                $sourcePath = '/www/wwwroot/admin.kuodas.org/public/'.$cleanPath;
                $targetDira=$targetDir.$key.'/';
                if (!is_dir($targetDira)) {
                    mkdir($targetDira, 0777, true);
                }
                // 目标文件名保留原名，存入目标目录
                $destinationPath = $targetDira . basename($cleanPath);
                // 检查文件是否存在并复制
                // 判断是否是文件
    if (is_file($sourcePath)) {
        // 如果目标文件已存在则跳过
        if (file_exists($destinationPath)) {
            echo "🔁 已存在，跳过：$destinationPath\n";
            continue;
        }

        // 执行复制
        if (copy($sourcePath, $destinationPath)) {
            echo "✅ 已复制：$sourcePath -> $destinationPath\n";
        } else {
            echo "❌ 复制失败：$sourcePath\n";
        }
    } else {
        echo "⚠️ 源文件不存在或不是文件：$sourcePath\n";
    }
            }
        }
    }
    
    public function profit_log($profit_user){
        $log = date('Y-m-d H:i:s') . ' --- ' . json_encode($profit_user) . PHP_EOL;
        $path = ROOT_PATH . 'public' . DIRECTORY_SEPARATOR . 'nxxfj';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        file_put_contents($path . DIRECTORY_SEPARATOR . date('Y-m-d') . 'nxxfj.log', $log, FILE_APPEND);
    }

}
