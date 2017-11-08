<?php

namespace backend\models;

use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\Html;

/**
 * This is the model class for table "hlr".
 *
 * The followings are the available columns in table 'hlr':
 * @property string $number
 * @property string $QueryID
 * @property string $MSISDN
 * @property string $Status
 * @property string $NetworkCode
 * @property string $ErrorCode
 * @property string $ErrorDescription
 * @property string $Location
 * @property string $CountryName
 * @property string $CountryCode
 * @property string $Organisation
 * @property string $NetworkName
 * @property string $NetworkType
 * @property string $Ported
 * @property string $PortedFrom
 * @property string $PortedFrom2
 * @property string $region_code
 * @property integer $number_type
 * @property string $sent_at
 * @property string $response_at
 */
class Hlr extends ActiveRecord
{
    public static $key = 'uhfgj8378f2jnasjhv';  // this should match up with the key in HlrController - at some point remove that one and rely on this

    /**
     * @var array used in the HLR results table to hold client id information
     */
    public $clientIds = [];

    /**
     * @var array Response fields when multiple results sent to the callback
     */
    public static $responseFields = [
        'QueryID',
        'MSISDN',
        'Status',
        'NetworkCode',
        'ErrorCode',
        'ErrorDescription',
        'Location',
        'CountryName',
        'CountryCode',
        'Organisation',
        'NetworkName',
        'NetworkType',
        'Ported',
        'PortedFrom',
        'PortedFrom2',
    ];

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%hlr}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['number', 'QueryID', 'sent_at'], 'required'],
            [['number_type'], 'integer'],
            [['number'], 'max' => 32],
            [['QueryID', 'MSISDN', 'Status', 'NetworkCode', 'ErrorCode', 'ErrorDescription', 'Location', 'CountryName', 'CountryCode', 'Organisation', 'NetworkName', 'NetworkType', 'Ported', 'PortedFrom', 'PortedFrom2'], 'max' => 255],
            [['region_code'], 'max' => 3],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            ['number', 'QueryID', 'MSISDN', 'Status', 'NetworkCode', 'ErrorCode', 'ErrorDescription', 'Location', 'CountryName', 'CountryCode', 'Organisation', 'NetworkName', 'NetworkType', 'Ported', 'PortedFrom', 'PortedFrom2', 'region_code', 'number_type', 'sent_at', 'response_at', 'safe'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'number'           => 'Number',
            'QueryID'          => 'Query ID',
            'MSISDN'           => 'MSISDN',
            'Status'           => 'Status',
            'NetworkCode'      => 'Network Code',
            'ErrorCode'        => 'Error Code',
            'ErrorDescription' => 'Error Description',
            'Location'         => 'Location',
            'CountryName'      => 'Country Name',
            'CountryCode'      => 'Country Code',
            'Organisation'     => 'Organisation',
            'NetworkName'      => 'Network Name',
            'NetworkType'      => 'Network Type',
            'Ported'           => 'Ported',
            'PortedFrom'       => 'Ported From',
            'PortedFrom2'      => 'Ported From2',
            'region_code'      => 'Region Code',
            'number_type'      => 'Number Type',
            'sent_at'          => 'Sent At',
            'response_at'      => 'Response At',
        ];
    }

    /**
     * Similar to the lookup function but will wait for a definite response and return it if found
     * (Taken from cv-portal Hlr model)
     * @todo put re-usable code into separate functions
     *
     * @param type $number
     * @param type $countryCode
     * @param bool $forceLookup
     *
     * @return Hlr|bool
     */
    public static function fetchResponse($number, $countryCode = null, $forceLookup = false)
    {
        if (empty($number)) {
            return false;
        }

        $defaultCountryCode = $countryCode === null ? 'US' : $countryCode;

        // make sure we are using a plus sign if country code not specified
        $number = trim($number);
        if ($countryCode === null && !strstr($number, '+')) {
            $number = '+' . $number;
        }

        $libPhone = self::validatePhoneNumber($number, $defaultCountryCode);
        if (is_array($libPhone)) {
            // see if the number has already been checked within the cachetime (3 months) and add to the models array instead
            $model = $forceLookup ? false : self::findLastRequest($libPhone['e164']);
            if (!$model) {
                // perform a HLR request
                $queryIds = self::request($libPhone['unsigned']);
                if (is_array($queryIds)) {
                    // although unnecessary here, query ids will always be in an array to allow multiple
                    foreach ($queryIds as $i => $queryId) {
                        if ($queryId < 0) {
                            // this indicates an error code
                            // @todo handle this properly
                            continue;
                        }

                        // create a new row in the hlr table
                        $model              = new Hlr();
                        $model->QueryID     = $queryId;
                        $model->number      = $libPhone['e164'];
                        $model->region_code = $libPhone['region_code'];
                        $model->number_type = $libPhone['number_type'];
                        $model->sent_at     = gmdate('Y-m-d H:i:s');
                        $model->save();
                    }
                }
            }

            // if we still don't have a model or it didn't save, return false
            if (empty($model) || $model->isNewRecord) {
                return false;
            }

            // check for a valid response
            $tries = 0;
            while (is_null($model->response_at) && $tries < 10) {
                $tries++;
                sleep(2);
                $model = self::findLastRequest($libPhone['e164']);
            }

            if (!is_null($model->response_at)) {
                return $model;
            }
        }

        // if we haven't returned anything by now then something went wrong
        return false;
    }

    /**
     * @deprecated
     *
     * @param int $number
     *
     * @return \yii\db\ActiveQuery
     */
    public static function findLastRequest($number)
    {
        // warning: always use sent_at rather than response_at!!! If using response_at a new row will be written every time
        $query = new Query();
        $query->select('*');
        $query->where('number = :number AND sent_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 MONTH)', [':number' => $number]);
        $query->orderBy(['sent_at' => SORT_DESC]);

        return Hlr::find($query);
    }

    /**
     * @deprecated
     * Send a HLR request
     * @todo might be better to allow an array of number => countryCode
     *
     * @param string|array $numbers The number(s) to lookup. Can be a comma separated string or an array for multiple
     * @param null $countryCode
     * @param array $errors
     *
     * @return array|bool An array of models or false if no numbers given
     */
    public static function lookup($numbers, $countryCode = null, &$errors = [])
    {
        if (empty($numbers)) {
            return false;
        }

        // force array of numbers
        $numbers = is_string($numbers) && strstr($numbers, ',') ? explode(',', $numbers) : (array)$numbers;

        // initialize the return models array
        $models = [];

        $defaultCountryCode = $countryCode === null ? 'US' : $countryCode;

        // validate all numbers
        $validatedNumbers = [];
        foreach ($numbers as $number) {
            // make sure we are using a plus sign if country code not specified
            $number = trim($number);
            if ($countryCode === null && !strstr($number, '+')) {
                $number = '+' . $number;
            }

            $libPhone = self::validatePhoneNumber($number, $defaultCountryCode);
            if (is_array($libPhone)) {
                // @todo see if the number has already been checked within the cachetime (3 months) and add to the models array instead
                //$model = Hlr::model()->find(array('number' => $libPhone['e164']), array('order' => 'sent_at DESC', 'condition' => 'sent_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 MONTH)'));
                $query = new Query();
                // warning: always use sent_at rather than response_at!
                $query->where('number = :number AND sent_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 MONTH)', [':number' => $libPhone['e164']]);
                $query->orderBy(['sent_at' => SORT_DESC]);
                $model = Hlr::find($query);
                if ($model) {
                    $models[] = $model;
                } else {
                    // use the number as a key so we don't have any duplicates
                    $validatedNumbers[$libPhone['e164']] = $libPhone;
                }
            } else {
                // return numbers that didn't validate with the error message
                $errors[$number] = $libPhone;
            }
        }

        unset($numbers);

        if (empty($validatedNumbers)) {
            return $models;
        }

        // the lookup can handle up to 100 numbers per request
        // so we must split into groups of 100 if provided more than that
        $numberGroups = array_chunk($validatedNumbers, 100);
        unset($validatedNumbers);

        foreach ($numberGroups as $validatedNumbers) {
            $numbers = [];
            foreach ($validatedNumbers as $validatedNumber) {
                $numbers[] = $validatedNumber['unsigned'];  // use the unsigned version for the request
            }

            // convert to comma separated list & make request
            $queryIds = self::request(implode(',', $numbers));
            if (is_array($queryIds)) {
                // query ids should be in the same order they were sent in
                foreach ($queryIds as $i => $queryId) {
                    if ($queryId < 0) {
                        // this indicates an error code
                        // @todo handle this properly - just add to the $errors array
                        continue;
                    }

                    if (!isset($validatedNumbers[$i])) {
                        continue;
                    }

                    $validatedNumber = $validatedNumbers[$i];

                    // create a new row in the hlr table
                    $hlr              = new Hlr();
                    $hlr->QueryID     = $queryId;
                    $hlr->number      = $validatedNumber['e164'];
                    $hlr->region_code = $validatedNumber['region_code'];
                    $hlr->number_type = $validatedNumber['number_type'];
                    $hlr->sent_at     = gmdate('Y-m-d H:i:s');
                    if ($hlr->save()) {
                        $models[] = $hlr;
                    }
                }
            }
        }

        return $models;
    }

    /**
     * @param int $numbers
     * @param array $params
     *
     * @return array|bool
     */
    public static function request($numbers, $params = [])
    {
        // if being called from the public site, we will need to import the controller class for the key property
        // @todo might be better to move the key to the model?
        // if on public site we need to send the hlr callback
        // @todo harcoding it for now so it's a bit 'hacky'. Think of a better solution.
        // Perhaps move the controller and callback to the public site?
        if (Yii::$app->name == 'InmateFone') {
            $returnUrl = 'https://portal.clearvoipinc.com/hlr/callback?key=' . self::$key;
        } else {
            $returnUrl = Yii::$app->urlManager->createAbsoluteUrl(['/hlr/callback', ['key' => self::$key]]);
        }

        $defaultParams = [
            'USERNAME'      => 'clearvoipinc2',
            'PASSWORD'      => '5ayonaraLuis',
            'MSISDN'        => $numbers,
            'RETURNTYPE'    => 'h', // e = email, h = HTTP
            'RETURNADDRESS' => $returnUrl,
        ];

        $params = array_merge($defaultParams, $params);

        $url      = 'http://hlrlookup.cardboardfish.com/HLRQuery?' . http_build_query($params);
        $response = @file_get_contents($url);
        $success  = is_string($response) && strstr($response, 'OK');
        if (!$success) {
            return false;
        }

        // @todo handle error responses i.e. ERR [ErrorType] [ErrorDescription]

        // Response will be in format of ... OK [UniqueID1] [UniqueIdn] e.g. OK 70801920 70801921
        // up to 100 QueryIds will be returned if using multiple lookup
        return explode(' ', ltrim($response, 'OK '));
    }

    /**
     * @deprecated
     *
     * @param string $data
     *
     * @return bool
     */
    public static function response($data)
    {
        $rows         = explode('#', $data);
        $count        = array_shift($rows);
        $successCount = 0;
        foreach ($rows as $row) {
            $values  = explode(':', $row);
            $attribs = [];
            foreach (self::$responseFields as $i => $responseField) {
                $attribs[$responseField] = $values[$i] === '' ? null : urldecode($values[$i]);
            }
            $model = Hlr::find()->where(['QueryID' => $attribs['QueryID']])->one();
            if ($model) {
                $model->attributes  = $attribs;
                $model->response_at = gmdate('Y-m-d H:i:s');
                if ($model->save()) {
                    $successCount++;
                }
            }
        }

        return $count == $successCount;
    }

    /**
     * @deprecated
     *
     * @param string $number
     * @param string $countryCode
     *
     * @return string|array If a string is returned, it is an error message and the lookup failed. Always check for an array if successful
     */
    public static function validatePhoneNumber($number, $countryCode = 'US')
    {
        // require the composer autoloader
        $autoloadFile = dirname(Yii::getAlias('application')) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (!file_exists($autoloadFile)) {
            $autoloadFile = str_replace('inmatefone' . DIRECTORY_SEPARATOR, '', $autoloadFile);
        }
        // TODO: Думаю что автозагрузчик нужно удалить потому что все будет подгружаться composer, но на время оставлю
        require_once "$autoloadFile";

        // validate a phone number and return the formatted number or false if not valid
        // TODO: Подключен неизвестный класс, скорее всего из строки: require_once "$autoloadFile";
        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        try {
            $numberProto = $phoneUtil->parse($number, $countryCode);
            if (!$phoneUtil->isValidNumber($numberProto) && !$phoneUtil->isPossibleNumber($numberProto)) {
                return 'The number (' . $number . ') is not valid';
            }

            $e164   asdsadsadas    = $phoneUtil->format($numberProto, \libphonenumber\PhoneNumberFormat::E164);
            $regionCode = $phoneUtil->getRegionCodeForNumber($numberProto);
            $type       = $phoneUtil->getNumberType($numberProto);

            return [
                'requested'   => $number,
                'unsigned'    => numbersOnly($e164), // remove the plus sign and any other non-numeric characters
                'e164'        => $e164,
                'region_code' => $regionCode,
                'number_type' => $type,
            ];
        } catch (\libphonenumber\NumberParseException $e) {
            return $e->getMessage() . " ($number)\n";
            //$this->log($e, 'mail');
        }
    }

    /**
     * Render the status value for grid output.
     * This should be used in a grid using 'value'=>array($model,'gridDlr')
     *
     * @param array $data
     *
     * @return string
     */
    public function gridClients($data)
    {

        $rows = [];

        sort($data->clientIds);

        foreach ($data->clientIds as $clientId) {
            $rows[] = Html::a('#' . $clientId, ['client/update', 'id' => $clientId]) . ' - ' .
                Html::a('Texts', ['/sms/texts/all', 'clientId' => $clientId, 'contact' => $data->number]);
        }

        return implode('<br />', $rows);
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @param string $params
     *
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search($params)
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $query        = self::find();
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'number'           => $this->number,
            'QueryID'          => $this->QueryID,
            'MSISDN'           => $this->MSISDN,
            'Status'           => $this->Status,
            'NetworkCode'      => $this->NetworkCode,
            'ErrorCode'        => $this->ErrorCode,
            'ErrorDescription' => $this->ErrorDescription,
            'Location'         => $this->Location,
            'CountryName'      => $this->CountryName,
            'CountryCode'      => $this->CountryCode,
            'Organisation'     => $this->Organisation,
            'NetworkName'      => $this->NetworkName,
            'NetworkType'      => $this->NetworkType,
            'Ported'           => $this->Ported,
            'PortedFrom'       => $this->PortedFrom,
            'PortedFrom2'      => $this->PortedFrom2,
            'region_code'      => $this->region_code,
            'number_type'      => $this->number_type,
            'sent_at'          => $this->sent_at,
            'response_at'      => $this->response_at,
        ]);

        return $dataProvider;
    }
}
