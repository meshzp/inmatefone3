<?php

namespace backend\helpers;

class Globals
{
    /**
     * Shortcut to strip everything but numbers from a string
     * @param string $number The string
     * @return string The formatted string containing numbers only
     */
    public static function numbersOnly($number,$extraCharacters = '') {
        return preg_replace("/[^0-9$extraCharacters]/", "", $number);
    }

    public static function encrypt($value, $key1 = 'Inm8fone', $key2 = 'Inm8fone')
    {
        if (!$value || !$key1 || !$key2) {
            return false;
        }

        $crypttext = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key1), $value, MCRYPT_MODE_ECB, md5($key2));

        return trim(base64_encode($crypttext));
    }

    public static function decrypt($value, $key1 = 'Inm8fone', $key2 = 'Inm8fone')
    {
        if (!$value || !$key1 || !$key2) {
            return false;
        }

        $crypttext   = base64_decode($value);
        $decrypttext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($key1), $crypttext, MCRYPT_MODE_ECB, md5($key2));

        return trim($decrypttext);
    }
}