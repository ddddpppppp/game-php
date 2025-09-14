<?php


namespace app\common\helper;


class StrHelper
{

    /**
     * 隐藏手机号
     * @param $certno
     * @return string
     * @author lufee
     * @date 2021-02-22
     */
    public static function hidePhone($phone)
    {
        if (empty($phone)) {
            return '';
        }
        return  preg_replace('/(1[1-9]{1}[0-9])[0-9]{4}([0-9]{4})/i', '$1****$2', $phone);
    }

    /**
     * 隐藏身份证
     * @param $certno
     * @return string
     * @author lufee
     * @date 2021-02-22
     */
    public static function hideCertno($certno)
    {
        if (empty($certno)) {
            return '';
        }
        return substr($certno, 0, 4) . '********' . substr($certno, 12);
    }

    public static function hideEmail($email)
    {
        if (empty($email)) {
            return '';
        }
        return substr($email, 0, 2) . '****' . substr($email, strpos($email, '@'));
    }
}
