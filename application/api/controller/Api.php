<?php

namespace app\api\controller;

use app\api\enum\Order;
use app\api\enum\Imap;
use app\common\controller\Controller;
use app\common\enum\Bot;
use app\common\helper\ArrayHelper;
use app\common\helper\MicrosoftGraph;
use app\common\helper\TgHelper;
use app\common\model\EmailAutoAuth;
use app\common\model\PaymentChannel;
use app\common\service\Email;
use app\common\helper\OssHelper;
use think\Db;
use think\facade\Log;
use think\facade\Validate as FacadeValidate;

class Api extends Controller
{

    /**
     * Get system settings
     */
    public function getSystemSettings()
    {
        $type = $this->request->param('type', '');

        if (empty($type)) {
            return $this->error('Parameter error: type cannot be empty');
        }

        $allowedTypes = ['recharge_setting', 'withdraw_setting'];

        if (!in_array($type, $allowedTypes)) {
            return $this->error('Unsupported configuration type');
        }

        $setting = Db::name('system_setting')
            ->where('name', $type)
            ->where('status', 1)
            ->find();

        if (!$setting) {
            return $this->error('Configuration not found or disabled');
        }

        $config = json_decode($setting['config'], true);
        return $this->success([
            'name' => $setting['name'],
            'title' => $setting['title'],
            'description' => $setting['description'],
            'config' => $config
        ]);
    }


    public function uploadFile()
    {
        // 获取上传文件
        $file = $this->request->file('file');

        if (empty($file)) {
            return $this->error('请选择上传文件');
        }

        // 检查文件是否有效
        if (!$file->isValid()) {
            return $this->error('文件上传失败');
        }

        // 验证规则
        $validate = FacadeValidate::rule([
            'file' => [
                'fileSize' => 10485760, // 10MB
                'fileExt'  => 'jpg,jpeg,png,gif,pdf,doc,docx',
                'fileMime' => 'image/jpeg,image/png,image/gif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ]
        ]);

        if (!$validate->check(['file' => $file])) {
            return $this->error($validate->getError());
        }

        $originalName = $file->getInfo('name');
        $fileSize = $file->getInfo('size');
        $tmpFile = $file->getPathname(); // 获取临时文件路径
        $fileMd5 = md5_file($tmpFile);
        $fileSha1 = sha1_file($tmpFile);
        $mimeType = $file->getMime();
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);

        // 按日期生成子目录
        $subDir = date('Ymd') . '/';
        $saveFilename = uniqid() . '.' . $extension;

        // 判断是否使用OSS
        if (OssHelper::isEnabled()) {
            // 构建OSS对象名称
            $objectName = 'uploads/' . $subDir . $saveFilename;

            // 尝试上传到OSS
            $ossResult = OssHelper::upload($tmpFile, $objectName);

            if ($ossResult) {
                // 准备返回数据
                $fileInfo = [
                    'original_name' => $originalName,
                    'save_name'     => $saveFilename,
                    'save_path'     => $ossResult['save_path'],
                    'size'          => $fileSize,
                    'mime_type'     => $mimeType,
                    'extension'     => $extension,
                    'md5'           => $fileMd5,
                    'sha1'          => $fileSha1,
                    'url'           => $ossResult['url'],
                    'storage'       => 'oss'
                ];

                return $this->success($fileInfo);
            } else {
                // OSS上传失败，回退到本地上传
                // 这里可以记录日志
            }
        }

        // 本地上传（OSS禁用或OSS上传失败时执行）
        // 设置上传目录（相对于public目录）
        $uploadPath = 'static/uploads/' . $subDir;

        // 确保目录存在
        if (!is_dir(ROOT_PATH . '/public/' . $uploadPath)) {
            mkdir(ROOT_PATH . '/public/' . $uploadPath, 0755, true);
        }

        // 移动文件到指定目录
        $info = $file->rule(function () use ($saveFilename) {
            return $saveFilename;
        })->move(ROOT_PATH . '/public/' . $uploadPath);

        if (!$info) {
            return $this->error($file->getError());
        }

        // 准备返回数据
        $fileInfo = [
            'original_name' => $originalName,
            'save_name'     => $info->getFilename(),
            'save_path'     => $uploadPath . $info->getFilename(),
            'size'          => $fileSize,
            'mime_type'     => $mimeType,
            'extension'     => $info->getExtension(),
            'md5'           => $fileMd5,
            'sha1'          => $fileSha1,
            'url'           => SITE_ROOT . '/' . $uploadPath . $info->getFilename(),
            'storage'       => 'local'
        ];

        return $this->success($fileInfo);
    }


    public function tgWebhook()
    {
        $data = request()->param();
        Log::info($data);
    }

    public function test() {}
}
