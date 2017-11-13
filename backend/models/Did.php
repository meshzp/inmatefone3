<?php
namespace backend\models;

use backend\helpers\Globals;
use Symfony\Component\BrowserKit\Client;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * This is the model class for table "dids".
 *
 * The followings are the available columns in table 'dids':
 * @property string $did_id
 * @property string $did
 * @property string $did_full
 * @property integer $provider_id
 * @property integer $provider_tier
 * @property integer $rate_center_id
 * @property integer $country_id
 * @property string $did_state
 * @property string $did_area_code
 * @property string $did_prefix
 * @property string $did_line
 * @property string $did_datetime_add
 * @property string $did_datetime_cancel
 * @property integer $did_in_use
 * @property integer $did_available
 * @property integer $admin_id_add
 * @property integer $admin_id_cancel
 * @property string $did_notes
 * @property integer $did_user_id
 * @property string $did_last_time_used
 * @property string $sms_status
 *
 * @property CountryCode $country
 * @property Provider $provider
 * @property RateCenter $rateCenter
 * @property Client $client
 * @property ClientDid $clientDid
 * @property User $adminAdded
 * @property User $adminCancelled
 *
 */
class Did extends ActiveRecord
{
    // note: these are custom statuses covering did_in_use and did_available and not used in db
    const STATUS_NOT_AVAILABLE     = 0;
    const STATUS_AVAILABLE         = 1;
    const STATUS_AVAILABLE_IN_USE  = 2;
    const STATUS_AVAILABLE_ON_HOLD = 3;
    const STATUS_DEFECTIVE         = 4;

    const SMS_STATUS_NOT_AVAILABLE = 0;
    const SMS_STATUS_AVAILABLE     = 1;
    const SMS_STATUS_USED_FOR_SMS  = 2;  // this indicates that the DID is currently being used for SMS rather than voice

    public static $smsStatuses = [
        self::SMS_STATUS_NOT_AVAILABLE => 'Not Available',
        self::SMS_STATUS_AVAILABLE     => 'Available',
        self::SMS_STATUS_USED_FOR_SMS  => 'In Use',
    ];

    public $status;
    public $numberLoader;
    // for the number loader when adding DID's
    public $numberArray;
    public $countryCode;    // note: the main number array should be as did => did_full (did_full has country code, did doesn't)
    public $clientName;

    // search properties
    public  $clientStatus;
    public  $rate_center_tier;
    public  $rate_center_state;
    public  $added_by;
    public  $cancelled_by;
    public  $gridCountryList;
    public  $gridClientList;
    private $_statusOptions;

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['provider_id, country_id, did_state, rate_center_id, numberLoader', 'required', 'on' => 'insert'],
            ['provider_id, rate_center_id, country_id, did_in_use, did_available, admin_id_add, admin_id_cancel, did_user_id,sms_status,provider_tier', 'numerical', 'integerOnly' => true],
            ['did, did_full, did_state, did_area_code, did_prefix, did_line', 'length', 'max' => 255],
            ['did_datetime_add, did_datetime_cancel, did_last_time_used, did_notes', 'safe'],
            ['numberLoader', 'checkNumbers', 'on' => 'insert'],
            ['did_notes', 'safe', 'on' => 'editable'],
            ['did_id,country_id,did,provider_id,rate_center_id,rate_center_tier,rate_center_state,clientName,clientStatus,did_notes,status,did_datetime_add,did_datetime_cancel,did_last_time_used,sms_status,provider_tier', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return ActiveQuery
     */
    public function getCountry()
    {
        return $this->hasOne(CountryCode::className(), ['country_id' => 'country_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getProvider()
    {
        return $this->hasOne(Provider::className(), ['provider_id' => 'provider_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getRateCenter()
    {
        return $this->hasOne(RateCenter::className(), ['rate_center_id' => 'rate_center_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getClient()
    {
        return $this->hasOne(Client::className(), ['did_user_id' => 'did_user_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getClientDid()
    {
        return $this->hasOne(ClientDid::className(), ['did_user_id' => 'did_user_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getAdminAdded()
    {
        return $this->hasOne(User::className(), ['admin_id_add' => 'admin_id_add']);
    }


    /**
     * @return ActiveQuery
     */
    public function getAdminCancelled()
    {
        return $this->hasOne(User::className(), ['admin_id_cancel' => 'admin_id_cancel']);
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'did_id'              => 'ID',
            'did'                 => 'DID',
            'did_full'            => 'Did Full',
            'provider_id'         => 'Provider',
            'rate_center_id'      => 'Rate Center',
            'country_id'          => 'Country',
            'did_state'           => 'State',
            'did_area_code'       => 'Area Code',
            'did_prefix'          => 'Prefix',
            'did_line'            => 'Line',
            'did_datetime_add'    => 'Added',
            'did_datetime_cancel' => 'Cancelled',
            'did_in_use'          => 'In Use',
            'did_available'       => 'Available',
            'admin_id_add'        => 'Admin Id Add',
            'admin_id_cancel'     => 'Admin Id Cancel',
            'did_notes'           => 'Notes',
            'did_user_id'         => 'User',
            'did_last_time_used'  => 'Last Time Used',
            'provider_tier'       => 'Provider Tier',
            'clientName'          => 'Assigned To',
            'clientStatus'        => 'Client Status',
            'rate_center_tier'    => 'RC Tier',
            'numberLoader'        => 'Numbers <small>(1 number/range per line - see below)</small>',
        ];
    }

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'dids';
    }

    /**
     * Custom validation for the number loader
     *
     * @param $attribute
     */
    public function checkNumbers($attribute)
    {
        /**
         * @var CountryCode $countryModel
         */
        $countryModel = CountryCode::findOne($this->country_id);
        if ($countryModel === null) {
            $this->addError('country_id', 'The selected country does not exist');
        }
        $this->countryCode = $countryModel->country_phone_code;

        // get provider leading digit
        $leadingDigit = Yii::$app->db->createCommand()
            ->select('provider_dids_leading_digit')
            ->from('providers')
            ->where('provider_id=:provider_id', [':provider_id' => $this->provider_id])
            ->queryScalar();

        // convert loaded numbers into an array
        $rows     = explode("\n", trim($this->$attribute));
        $numCount = count($rows);
        if (!$numCount) {
            $this->addError($attribute, 'Please add some numbers');
        } else {
            // split out rows and add to the number array
            // TODO: consider adding this to a util function for re-use
            foreach ($rows as $row) {
                $tempNumbers = []; // array to hold numbers temporarily before adding to main number array
                $row         = strtolower($row);
                // check if it's a range
                if (strstr($row, 'to')) {
                    // TODO: in lionel's code, it seems he allows ranges to be entered as xxx-xxx-1 to xxx-xxx-999 for example,
                    // which translates to xxx-xxx-1000 to xxx-xxx-9990 using zero padding. Do we want something like this?
                    $range = explode('to', $row);
                    $from  = trim(Globals::numbersOnly($range[0]));
                    $to    = trim(Globals::numbersOnly($range[1]));
                    if (strlen($from) != strlen($to)) {
                        $this->addError($attribute, 'One or more ranges is invalid (lengths don\'t match). Please check.');
                    }
                    $prefix = Util::findMatchingPrefix($from, $to);
                    if ($prefix === false) {
                        $this->addError($attribute, 'One or more ranges is invalid (prefixes don\'t match). Please check.');
                    } else {
                        $fromSuffix = (int)substr($from, strlen($prefix));
                        $toSuffix   = (int)substr($to, strlen($prefix));
                        // check for numbers that are too long
                        if ($fromSuffix == '2147483647' || $toSuffix == '2147483647') {
                            // number maxed out
                            $this->addError($attribute, 'One or more ranges is invalid (range is too big). Please check.');
                        } else {
                            // add range numbers
                            for ($i = $fromSuffix; $i <= $toSuffix; $i++) {
                                // using str_pad to make sure zeros are added where necessary
                                $tempNumbers[] = $prefix . str_pad($i, strlen($toSuffix), '0', STR_PAD_LEFT);
                            }
                        }
                    }
                } else {
                    $tempNumbers[] = trim(Globals::numbersOnly($row));
                }
                // check and strip country prefixes and add the numbers to main number array
                foreach ($tempNumbers as $number) {
                    // strip any leading 0 values TODO: check this is correct in all circumstances
                    $number = ltrim($number, '0');
                    // strip the country code if it exists
                    // if it's a North American number be careful as the country codes might include the area code
                    // e.g. Jamaica's country code is 1876
                    if (substr($number, 0, 1) == '1') {
                        $didNumber  = Util::stringPrefixReplace('1', $number);
                        $fullNumber = '1' . $didNumber;
                    } else {
                        $didNumber = Util::stringPrefixReplace($this->countryCode, $number);
                        if ($leadingDigit === '' && $this->country_id == 223) {
                            $fullNumber = $didNumber;
                        } else {
                            $fullNumber = $this->countryCode . $didNumber;
                        }
                    }
                    $this->numberArray[$didNumber] = $fullNumber;
                }
            }
        }
    }

    /**
     * Parametrized named scope for choosing records based on area code + prefix
     * note: only works with US numbers at the moment
     *
     * @param string $prefix
     * @param int $countryId
     *
     * @return Did
     */
    public function havingPrefix($prefix, $countryId = 223)
    {
        $query         = new ActiveQuery();
        $query->select = "GROUP_CONCAT(DISTINCT(did_area_code)) AS did_area_code,GROUP_CONCAT(DISTINCT(did_prefix)) AS did_prefix";
        $query->groupBy  = self::tableAlias() . '.rate_center_id';
        $query->with   = ['rateCenter' => ['select' => 'rate_center_id,rate_center']];
        $query->andWhere(['like', self::tableAlias() . '.did', $prefix]);
        $query->andWhere(['=', self::tableAlias() . '.country_id', $countryId]);

        return $this;
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {

        $query = new ActiveQuery();

        $query->with = [
            'country',
            'provider',
            'rateCenter',
            'client',
            'adminAdded'     => [
                'scopes' => 'name',
                'alias'  => 'adminAdded',
            ],
            'adminCancelled' => [
                'scopes' => 'name',
                'alias'  => 'adminCancelled',
            ],
        ];

        $query->filterWhere(['like', 't.did_id', $this->did_id]);
        $query->andFilterWhere('t.did', preg_replace("/[^0-9\*\%\?\_]/", "", $this->did), true, 'AND', false);   // allow use of %/* and ?/_ wildcards see also new DbCriteria component
        $query->andFilterWhere(['like', 'country.country_name', $this->country_id]);
        $query->andFilterWhere(['=', 't.provider_id', $this->provider_id]);
        $query->andFilterWhere(['=', 't.provider_tier', $this->provider_tier]);
        $query->andFilterWhere(['like', 'rateCenter.rate_center', $this->rate_center_id]);
        $query->andFilterWhere(['like', 'rateCenter.rate_center_state', $this->rate_center_state]);
        $query->andFilterWhere(['=', 'rateCenter.rate_center_tier', $this->rate_center_tier]);
        if (isset($this->clientStatus) && $this->clientStatus !== '') {
            $query->andWhere(['=', 'client.user_status', $this->clientStatus]);
        }
        if (!empty($this->clientName)) {
            $query->where("(client.user_full_name LIKE '%" . $this->clientName . "%' OR client.user_inmate_full_name LIKE '%" . $this->clientName . "%')");
        }
        $query->andWhere(['like', 't.did_notes', $this->did_notes]);
        if (!empty($this->did_datetime_add)) {
            $query->where("(t.did_datetime_add LIKE '%" . $this->did_datetime_add . "%' OR adminAdded.display_name LIKE '%" . $this->did_datetime_add . "%')");
        }
        if (!empty($this->did_datetime_cancel)) {
            $query->where("(t.did_datetime_cancel LIKE '%" . $this->did_datetime_cancel . "%' OR adminCancelled.display_name LIKE '%" . $this->did_datetime_cancel . "%')");
        }
        $query->andFilterWhere(['=', 't.did_last_time_used', $this->did_last_time_used]);

        // status comparison
        if (isset($this->status) && $this->status !== '') {
            switch ($this->status) {
                case self::STATUS_NOT_AVAILABLE :
                    $query->andFilterWhere(['=', 't.did_available', 0]);
                    break;
                case self::STATUS_AVAILABLE :
                    $query->andFilterWhere(['=', 't.did_in_use', 0]);
                    $query->andFilterWhere(['=', 't.did_available', 1]);
                    break;
                case self::STATUS_AVAILABLE_IN_USE :
                    $query->andFilterWhere(['=', 't.did_in_use', 1]);
                    $query->andFilterWhere(['=', 't.did_available', 1]);
                    break;
                case self::STATUS_AVAILABLE_ON_HOLD :
                    $query->andFilterWhere(['=', 't.did_in_use', 0]);
                    $query->andFilterWhere(['=', 't.did_available', 3]);
                    break;
                case self::STATUS_DEFECTIVE :
                    $query->andFilterWhere(['=', 't.did_in_use', 0]);
                    $query->andFilterWhere(['=', 't.did_available', 2]);
                    break;
            }
        }

        $query->compare('t.sms_status', $this->sms_status);

        $sort   = [
            'country_id'        => [
                'asc'  => 'country.country_name ASC',
                'desc' => 'country.country_name DESC',
            ],
            'provider_id'       => [
                'asc'  => 'provider.provider_name ASC',
                'desc' => 'provider.provider_name DESC',
            ],
            'rate_center'       => [
                'asc'  => 'rateCenter.rate_center ASC',
                'desc' => 'rateCenter.rate_center DESC',
            ],
            'rate_center_state' => [
                'asc'  => 'rateCenter.rate_center_state ASC',
                'desc' => 'rateCenter.rate_center_state DESC',
            ],
            'clientName'        => [
                'asc'  => 'client.user_full_name ASC',
                'desc' => 'client.user_full_name DESC',
            ],
            'clientStatus'      => [
                'asc'  => 'client.user_status ASC',
                'desc' => 'client.user_status DESC',
            ],
            '*', // this adds all of the other columns as sortable
        ];
        $sort['defaultOrder'] = ['did' => 'ASC'];

        return new ActiveDataProvider($this, [
            'query' => $query,
            'sort'     => $sort,
        ]);
    }

    /**
     * Render the country name value for grid output.
     * This should be used in a grid using 'value' => array($model, 'gridCountryName')
     *
     * @param Did $data
     *
     * @return string|null
     */
    public function gridCountryName($data)
    {
        //public function getFacilityFullName() {
        if (!empty($data->country)) {
            $flag = Html::img(Yii::$app->request->baseUrl . "/img/country_flags/" . $data->country->country_code_alpha_3 . ".png", $data->country->country_name) . '&nbsp;';

            return $flag . Html::encode($data->country->country_name . " (+" . $data->country->country_phone_code . ")");
        }

        return null;
    }

    /**
     * Render the client name value for grid output.
     * This should be used in a grid using 'value' => array($model, 'gridClientName')
     *
     * @param Did $data
     *
     * @return string|null
     */
    public function gridClientName($data)
    {
        // $data->user_first_name." ".$data->user_last_name." (".$data->user_inmate_first_name." ".$data->user_inmate_last_name.") - ".Client::model()->getStatusOptions($data->user_status)
        if (!empty($data->client)) {
            $clientId   = Html::encode($data->client->user_id);
            $clientName = Html::encode($data->client->user_first_name . ' ' . $data->client->user_last_name);
            $inmateName = Html::encode($data->client->user_inmate_first_name . ' ' . $data->client->user_inmate_last_name);
            $clientInfo = "#$clientId<br />
                            $clientName<br />
                            ($inmateName)";

            return Html::a($clientInfo, ["client/update", "id" => $data->client->user_id]);
        }

        return null;
    }

    /**
     * Render the status value for grid output.
     * This should be used in a grid using 'value' => array($model, 'gridStatus')
     *
     * @param Did $data
     *
     * @return string
     */
    public function gridStatus($data)
    {
        // Not Available
        if ($data->did_in_use == 0 && $data->did_available == 0) {
            return '<span class="label label-inverse">Not Available</span>';
        } // Available
        elseif ($data->did_in_use == 0 && $data->did_available == 1) {
            return '<span class="label label-success">Available</span>';
        } // Available & In Use
        elseif ($data->did_in_use == 1 && $data->did_available == 1) {
            return '<span class="label label-info">Available & In Use</span>';
        } // Available & On Hold
        elseif ($data->did_in_use == 0 && $data->did_available == 3) {
            return '<span class="label">Available & On Hold</span>';
        } // Defective
        elseif ($data->did_in_use == 0 && $data->did_available == 2) {
            return '<span class="label label-warning">Defective</span>';
        } // Conflict warning
        else {
            return '<span class="label label-important">Status Conflict! Please Contact Support</span>';
        }
    }

    /**
     * Render the SMS status value for grid output.
     * This should be used in a grid using 'value' => array($model, 'gridSmsStatus')
     *
     * @param Did $data
     *
     * @return string
     */
    public function gridSmsStatus($data)
    {
        switch ($data->sms_status) {
            case self::SMS_STATUS_NOT_AVAILABLE :
                return '<span class="label label-inverse">Not Available</span>';
            case self::SMS_STATUS_AVAILABLE :
                return '<span class="label label-success">Available</span>';
            case self::SMS_STATUS_USED_FOR_SMS :
                return '<span class="label label-info">Available & In Use</span>';
        }

        return '<span class="label label-important">Incorrect status! Please Contact Support</span>';
    }

    /**
     * Render the status value for grid output.
     * This should be used in a grid using 'value' => array($model, 'gridClientStatus')
     *
     * @param Did $data
     *
     * @return string|null
     */
    public function gridClientStatus($data)
    {
        // TODO: move this to Client model and refer from there
        if (!empty($data->client)) {
            switch ($data->client->user_status) {
                case Client::STATUS_CANCELLED :
                    return '<span class="label label-inverse">Cancelled</span>';
                case Client::STATUS_ACTIVE :
                    return '<span class="label label-success">Active</span>';
                case Client::STATUS_BLOCKED :
                    return '<span class="label label-important">Blocked</span>';
                case Client::STATUS_INACTIVE :
                    return '<span class="label">Inactive</span>';
                case Client::STATUS_PENDING :
                    return '<span class="label label-info">Pending</span>';
            }
        }

        return null;
    }

    public function delete($permanently = false)
    {
        if ($permanently) {
            return parent::delete();
        }
        // DID records should not be fully deleted
        if (!$this->getIsNewRecord()) {
            Yii::trace(get_class($this) . '.delete()', 'system.db.ar.CActiveRecord');
            if ($this->beforeDelete()) {
                $this->did_available       = 0;
                $this->did_user_id         = 0;
                $this->did_datetime_cancel = date('Y-m-d H:i:s');
                $this->admin_id_cancel     = user()->id;
                $result                    = $this->save();
                $this->afterDelete();

                return $result;
            } else {
                return false;
            }
        } else {
            throw new Exception(Yii::t('yii', 'The active record cannot be deleted because it is new.'));
        }
    }

    public function getNPAList($nxx = null, $rateCenterId = null, $providerId = null, $smsEnabled = false)
    {
        $list = [];
        if (!empty($rateCenterId)) {
            $rateCenterId   = (int)$rateCenterId;
            $fieldName      = 'did_area_code';
            $providerFilter = empty($providerId) ? '' : "AND provider_id = " . (int)$providerId;
            $nxxFilter      = empty($nxx) ? '' : "AND did_prefix = " . (int)$nxx;
            $smsCondition   = $smsEnabled ? 'AND sms_status = 1' : '';
            // 2016-08-23 temporarily disallowing Verizon numbers from being assigned
            $sql   = "SELECT $fieldName AS val,CONCAT($fieldName,' (',COUNT(did_id),' available)') AS txt 
                    FROM dids WHERE did_available = 1 AND did_in_use = 0 AND rate_center_id = $rateCenterId $providerFilter $nxxFilter $smsCondition 
                        AND provider_id != 51
                    GROUP BY $fieldName";
            $codes = Yii::$app->db->createCommand($sql)->queryAll();
            $list  = ArrayHelper::map($codes, 'val', 'txt');
        }

        return $list;
    }

    /**
     * this is currently used for generating select2 lists in clientDid add form
     *
     * @param int|null $npa
     * @param int|null $rateCenterId
     * @param int|null $providerId
     * @param bool $smsEnabled
     *
     * @return array
     */
    public function getNXXList($npa = null, $rateCenterId = null, $providerId = null, $smsEnabled = false)
    {
        $list = [];
        if (!empty($rateCenterId)) {
            $rateCenterId   = (int)$rateCenterId;
            $fieldName      = 'did_prefix';
            $providerFilter = empty($providerId) ? '' : "AND provider_id = " . (int)$providerId;
            $npaFilter      = empty($npa) ? '' : "AND did_area_code = " . (int)$npa;
            $smsCondition   = $smsEnabled ? 'AND sms_status = 1' : '';
            // 2016-08-23 temporarily disallowing Verizon numbers from being assigned
            $sql   = "SELECT $fieldName AS val,CONCAT($fieldName,' (',COUNT(did_id),' available)') AS txt 
                    FROM dids WHERE did_available = 1 AND did_in_use = 0 AND rate_center_id = $rateCenterId $providerFilter $npaFilter $smsCondition 
                        AND provider_id != 51
                    GROUP BY $fieldName";
            $codes = Yii::$app->db->createCommand($sql)->queryAll();
            $list  = ArrayHelper::map($codes, 'val', 'txt');
        }

        return $list;
    }

    /**
     * this is currently used for generating select2 lists in clientDid add form
     *
     * @param int|null $rateCenterId
     * @param int|null $npa
     * @param int|null $nxx
     * @param bool $smsEnabled
     *
     * @return array
     */
    public function getProviderList($rateCenterId = null, $npa = null, $nxx = null, $smsEnabled = false)
    {
        $list = [];
        if (!empty($rateCenterId)) {
            $rateCenterId = (int)$rateCenterId;
            $npaFilter    = empty($npa) ? '' : "AND d.did_area_code = " . (int)$npa;
            $nxxFilter    = empty($nxx) ? '' : "AND d.did_prefix = " . (int)$nxx;
            $smsCondition = $smsEnabled ? 'AND sms_status = 1' : '';
            // 2016-08-23 temporarily disallowing Verizon numbers from being assigned
            $sql   = "SELECT d.provider_id AS val,CONCAT(p.provider_name,' (',COUNT(d.did_id),' available)') AS txt 
                    FROM dids d 
                    INNER JOIN providers p ON d.provider_id = p.provider_id
                    WHERE d.did_available = 1 AND d.did_in_use = 0 AND d.rate_center_id = $rateCenterId $npaFilter $nxxFilter $smsCondition 
                        AND d.provider_id != 51
                    GROUP BY d.provider_id";
            $codes = Yii::$app->db->createCommand($sql)->queryAll();
            $list  = ArrayHelper::map($codes, 'val', 'txt');
        }

        return $list;
    }

    // this is currently used for generating select2 lists in clientDid add form

    public function getAvailableDidId($rateCenterId, $not = [], $providerId = null, $npa = null, $nxx = null, $exceptDid = null, $smsEnabled = false)
    {
        $where  = 'rate_center_id=:rateCenterId AND did_in_use=0 AND did_available=1 AND provider_id != 51';
        $params = [
            ':rateCenterId' => $rateCenterId,
        ];
        if (!empty($providerId)) {
            $where                 .= ' AND provider_id = :providerId';
            $params[':providerId'] = $providerId;
        }
        if (!empty($npa)) {
            $where          .= ' AND did_area_code = :npa';
            $params[':npa'] = $npa;
        }
        if (!empty($nxx)) {
            $where          .= ' AND did_prefix = :nxx';
            $params[':nxx'] = $nxx;
        }
        // allows stopping redirect numbers being added that are the same as the did
        if (!empty($exceptDid)) {
            $where             .= ' AND did_full != :except';
            $params[':except'] = $exceptDid;
        }

        if ($smsEnabled) {
            $where .= ' AND sms_status = 1';
        }

        if (count($not)) {
            return Yii::$app->db->createCommand()
                ->select('did_id')
                ->from(self::tableName())
                ->where($where, $params)
                ->andWhere(['not in', 'did_id', $not])
                ->order('RAND()')
                ->limit(1)
                ->queryScalar();
        }

        return Yii::$app->db->createCommand()
            ->select('did_id')
            ->from(self::tableName())
            ->where($where, $params)
            ->order('RAND()')
            ->limit(1)
            ->queryScalar();
    }

    public function getAvailableDidIdsForFacility($facilityId, $limit = 1)
    {
        // get the rate centers associated with the facility
        $rateCenters = Yii::$app->db->createCommand('SELECT rate_center_id FROM jail_by_rc WHERE jail_id = :facilityId AND active=1')
            ->queryColumn([':facilityId' => $facilityId]);
        if (!count($rateCenters)) {
            return [];
        }

        return Yii::$app->db->createCommand()
            ->select('did_id')
            ->from(self::tableName())
            ->where(['in', 'rate_center_id', $rateCenters])
            ->andWhere('did_in_use=0')
            ->andWhere('did_available=1')
            ->andWhere('provider_id != 51')// 2016-08-23 temporarily disallowing Verizon numbers from being assigned
            ->order('RAND()')
            ->limit($limit)
            ->queryColumn();
    }

    /**
     * @return array
     */
    public function getDidList()
    {
        return ArrayHelper::map($this->findAll([
            'select'    => 't.did_id, CASE WHEN ud.user_id IS NULL THEN t.did_full ELSE CONCAT(t.did_full," #",ud.user_id," (",ud.redirect,")") END AS did',
            'join'      => 'LEFT JOIN user_dids ud ON ud.did_id = t.did_id AND ud.status > 0',
            'condition' => 't.did_available > 0',
        ]), 'did_id', 'did');
    }

    /**
     * @param null $status
     *
     * @return mixed
     */
    public function getStatusOptions($status = null)
    {
        if (empty($this->_statusOptions)) {
            $this->_statusOptions = self::getConstants('STATUS_', __CLASS__, self::CONSTANT_FORMAT_KEY_LABEL);
        }

        return $status !== null && isset($this->_statusOptions[$status]) ? $this->_statusOptions[$status] : $this->_statusOptions;
    }

    /**
     * @deprecated
     * @param null $attributes
     *
     * @return bool
     * @throws Exception
     */
    public function insert( $attributes = null)
    {
        // TODO: add some warning messages if things don't work
        if (!$this->getIsNewRecord()) {
            throw new Exception(Yii::t('yii', 'The active record cannot be inserted to database because it is not new.'));
        }

        if ($this->beforeSave()) {
            Yii::trace(get_class($this) . '.insert()', 'system.db.ar.CActiveRecord');

            // check we have some numbers
            if (!count($this->numberArray)) {
                Globals::setFlash('error', 'Error Finding Numbers');

                return false;
            }

            /**
             * @var RateCenter $rateCenterModel
             */
            // check we can load the rate center model
            $rateCenterModel = RateCenter::model()->findByPk($this->rate_center_id);
            if ($rateCenterModel === null) {
                Globals::setFlash('error', 'Error Finding Rate Center');

                return false;
            }

            // find existing DID's, remove them if necessary and flag warning
            $existingDids = Yii::$app->db->createCommand()
                ->select('did')
                ->from(self::tableName())
                ->where(['in', 'did', array_keys($this->numberArray)])
                ->queryColumn();
            if (count($existingDids)) {
                foreach ($existingDids as $existingDid) {
                    unset($this->numberArray[$existingDid]);
                    Globals::setFlash('warning', $existingDid . ' already exists and was not added.');
                }
            }

            // setup a region instance so we can add the state code in
            // but for now we need to convert the region id to state code
            if (is_numeric($this->did_state)) {
                $this->did_state = (new Region)->getRegionCodeFromId($this->did_state);
            }

            $transaction = Yii::$app->db->beginTransaction();
            try {

                // set the rate center status to 1 if not already set
                if ($rateCenterModel->rate_center_status != 1) {
                    $rateCenterModel->rate_center_status = 1;
                    $rateCenterModel->save(false, ['rate_center_status']);
                }

                $didRows = [];    // array to hold did rows for multiple insertion - note: insert relies on RDbConnection and RDbCommand components
                foreach ($this->numberArray as $number => $fullNumber) {
                    $npa = substr($number, 0, 3);
                    $nxx = substr($number, 3, 3);
                    // find the rc exch code
                    $exch = Yii::$app->db->createCommand()
                        ->select("exch")
                        ->from('lcg_prefix')
                        ->where('npa=:npa AND nxx=:nxx', [':npa' => $npa, ':nxx' => $nxx])
                        ->queryScalar();
                    // @todo check the rc exch code matches the one found here
                    $didRows[] = [
                        'did'              => $number,
                        'did_full'         => $fullNumber,
                        'provider_id'      => $this->provider_id,
                        'rate_center_id'   => $this->rate_center_id,
                        'country_id'       => $this->country_id,
                        'did_state'        => $this->did_state,
                        'did_area_code'    => $npa,
                        'did_prefix'       => $nxx,
                        'did_line'         => substr($number, 6, strlen($number)),
                        'did_datetime_add' => date('Y-m-d H:i:s'),
                        'admin_id_add'     => user()->id,
                        'exch'             => ($exch ? $exch : null),
                    ];
                }
                // insert dids
                if (count($didRows)) {
                    Yii::$app->db->createCommand()->insert(self::tableName(), $didRows);
                }

                $transaction->commit();
            } catch (Exception $e) {
                $transaction->rollBack();
                Globals::setFlash('error', "{$e->getMessage()}");

                return false;
            }

            return true;
        } else {
            return false;
        }
    }
}
