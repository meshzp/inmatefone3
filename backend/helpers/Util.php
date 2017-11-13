<?php

namespace backend\helpers;

use Yii;
use backend\models\Hlr;

/**
 * Helpers Util
 */
class Util
{
    /**
     * @deprecated
     *
     * @param string $from
     * @param string $to
     * @param string $message
     * @param int $dlrMask
     * @param null|string $dlrUrl
     * @param int $validity
     * @param null|int $providerId
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    public static function sendSms($from, $to, $message, $dlrMask = 7, $dlrUrl = null, $validity = 1440, $providerId = null, $username = 'inmatefone', $password = 'Zlionel0A')
    {
        $fromNumbersOnly = self::numbersOnly($from);
        $toNumbersOnly   = self::numbersOnly($to);
        $msgTrimmed      = trim($message);

        // validate the number we're sending to
        $toValidated = Hlr::validatePhoneNumber('+' . $toNumbersOnly);
        if (!is_array($toValidated) || empty($fromNumbersOnly) || empty($msgTrimmed)) {
            return false;
        }

        // always use plus sign for all phone numbers (requires url encoding before sending)
        $params = [
            'username' => $username,
            'password' => $password,
            'from'     => '+' . $fromNumbersOnly,
            'to'       => $toValidated['e164'],
            'text'     => $msgTrimmed,
        ];

        // hard coded provider IDs for now - 1 = bandwidth, 11 = Mblox, 21 = Mobiweb
        // don't bother checking US prefixes - always send as bandwidth
        if (empty($providerId) && $toValidated['region_code'] != 'US') {
            // hlr everything non-us for now
            // note: this isn't very efficient because the HLR request will also do a libphone lookup. Not too big a deal though.
            $hlr = Hlr::fetchResponse($toValidated['e164']);

            // @todo if the hlr response status is unavailable, force a new lookup (cached for an hour instead of 3 months) instead of sending the SMS - might need a new status value for this

            $networkCodeCondition = ($hlr && !empty($hlr->NetworkCode)) ? '(network_code = \'' . $hlr->NetworkCode . '\' OR network_code = \'0\')' : 'network_code = \'0\'';

            // to send via mblox rather than bandwidth the 'to' number must be prefixed with a 00
            // check if the number should have 00 added by checking the sms_prefix table
            // @todo this is only a temporary solution and could possibly be improved for efficiency
            // cache for 1 minute
            $routeSql   = 'SELECT provider_id FROM sms_route WHERE `status` = 1 AND \'' . $toNumbersOnly . '\' LIKE CONCAT(prefix,\'%\') AND ' . $networkCodeCondition . ' ORDER BY LENGTH(network_code) DESC, LENGTH(prefix) DESC';
            $providerId = Yii::$app->db->createCommand($routeSql)->queryScalar();
        }

        // add provider prefixes as necessary
        // @todo grab these from the db
        // note: default is Bandwidth which requires no prefix
        if ($providerId == 11) {
            $params['to'] = '00' . $params['to'];
        } elseif ($providerId == 21) {
            $params['to'] = '99' . $params['to'];
        } else {
            // make sure we set this to the default provider so that it can be added to the dlr callback below
            $providerId = 1;
        }

        // add optional params
        if (!is_null($dlrMask)) {
            $params['dlr-mask'] = $dlrMask;
        }
        if (!is_null($dlrUrl)) {
            // make sure any provider ID placeholders are replaced in the url
            $params['dlr-url'] = str_replace('{{providerId}}', $providerId, $dlrUrl);
        }
        if (!is_null($validity)) {
            $params['validity'] = $validity;
        }

        // send the SMS
        $url      = 'http://173.45.65.98:13031/cgi-bin/sendsms?' . http_build_query($params);
        $response = @file_get_contents($url);

        return (@strstr($response, 'Accepted for delivery') || @strstr($response, 'Queued for later delivery'));
    }

    /**
     * @deprecated
     * Shortcut to strip everything but numbers from a string
     *
     * @param string $number The string
     *
     * @return string The formatted string containing numbers only
     */
    public static function numbersOnly($number)
    {
        return preg_replace('/[^0-9]/', '', $number);
    }
}
