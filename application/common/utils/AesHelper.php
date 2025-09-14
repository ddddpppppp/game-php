<?php


namespace app\common\utils;

class AesHelper
{
    /**
     * 加密方式
     *
     * @var string
     */
    private static $method = AES_METHOD;

    /**
     * 加密秘钥
     *
     * @var string
     */
    private static $encryptionKey = AES_KEY;


    /**
     * AES加密
     *
     * @param string $plainText
     * @return string
     */
    public static function encrypt($plainText)
    {
        if (is_array($plainText)) {
            $plainText = json_encode($plainText);
        }
        $ivLen = openssl_cipher_iv_length(self::$method);
        $iv = openssl_random_pseudo_bytes($ivLen);
        $cipherTextRaw = openssl_encrypt($plainText, self::$method, self::$encryptionKey, $options = OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $cipherTextRaw, self::$encryptionKey, $asBinary = true);
        return base64_encode($iv . $hmac . $cipherTextRaw);
    }

    /**
     * AES解密
     *
     * @param string $cipherText
     * @return string
     */
    public static function decrypt($cipherText)
    {
        $cipher = base64_decode($cipherText);
        $ivLen = openssl_cipher_iv_length(self::$method);
        $iv = substr($cipher, 0, $ivLen);
        $hmac = substr($cipher, $ivLen, $sha2len = 32);
        $cipherTextRaw = substr($cipher, $ivLen + $sha2len);
        $orininalText = openssl_decrypt($cipherTextRaw, self::$method, self::$encryptionKey, $options = OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $cipherTextRaw, self::$encryptionKey, $asBinary = true);
        if (hash_equals($hmac, $calcmac)) {
            if (is_array(json_decode($orininalText, 1))) {
                $orininalText = json_decode($orininalText, 1);
            }
            return $orininalText;
        }
        return "";
    }
}