<?php

namespace backend\helpers;

use Yii;
use yii\helpers\Console;


/**
 * Class Globals
 * @package backend\helpers
 */
class Globals
{

    /**
     * @param string $key
     * @param mixed $value
     * @param bool $removeAfterAccess
     */
    public static function setFlash($key, $value = true, $removeAfterAccess = true)
    {
        if (Yii::$app->getRequest()->isConsoleRequest) {
            switch ($key) {
                case 'danger':
                    $attr = Console::FG_RED;
                    break;
                case 'error':
                    $attr = Console::FG_RED;
                    break;
                case 'success':
                    $attr = Console::FG_GREEN;
                    break;
                case 'info':
                    $attr = Console::FG_CYAN;
                    break;
                default:
                    $attr = Console::FG_YELLOW;
                    break;
            }
            $value  = "{$value}";
            $string = Console::ansiFormat($value, [$attr]);
            Console::stdout($string);
        } else {
            Yii::$app->session->setFlash($key, $value, $removeAfterAccess);
        }
    }

    /**
     * @deprecated
     * Shortcut to strip everything but numbers from a string
     * @param string $number The string
     * @return string The formatted string containing numbers only
     */
    public static function numbersOnly($number,$extraCharacters = '') {
        return preg_replace("/[^0-9$extraCharacters]/", "", $number);
    }

    /**
     * @deprecated
     * @param $value
     * @param string $key1
     * @param string $key2
     *
     * @return bool|string
     */
    public static function encrypt($value, $key1 = 'Inm8fone', $key2 = 'Inm8fone')
    {
        if (!$value || !$key1 || !$key2) {
            return false;
        }

        $crypttext = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key1), $value, MCRYPT_MODE_ECB, md5($key2));

        return trim(base64_encode($crypttext));
    }

    /**
     * @deprecated
     * @param $value
     * @param string $key1
     * @param string $key2
     *
     * @return bool|string
     */
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