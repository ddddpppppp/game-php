<?php


namespace app\shop\controller;


use app\common\controller\Controller;
use app\common\enum\Common;
use app\common\helper\ArrayHelper;
use app\common\helper\OssHelper;
use app\common\helper\ServerHelper;
use app\common\helper\TimeHelper;
use app\shop\enum\Admin;
use app\shop\enum\Agent;
use app\shop\enum\Merchant;
use app\shop\model\Role;
use think\facade\Cache;
use think\facade\Request;
use think\facade\Validate;
use think\facade\Config;

class Api extends Controller
{
    protected $params = [];
    /**
     * 后台初始化
     */
    public function initialize()
    {
        $this->params = request()->param();
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
        $validate = Validate::rule([
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

    public function getVerificationCode()
    {
        $captcha = new \app\common\utils\VerificationCode();
        $result = $captcha->generate();

        // 获取验证码图片URL和UUID
        $imageUrl = SITE_ROOT . '/' . $result['image_url'];
        $uuid = $result['uuid'];

        return $this->success([
            'imageUrl' => $imageUrl,
            'uuid' => $uuid
        ]);
    }

    public function initMenu()
    {
        Role::where('type', 1)->update(['access' => implode(',', Admin::MENU_ACCESS)]);
        Role::where('type', 2)->update(['access' => implode(',', Merchant::MENU_ACCESS)]);
        Role::where('type', 2)->update(['access' => implode(',', Agent::MENU_ACCESS)]);
    }
}
