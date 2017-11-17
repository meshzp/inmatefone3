<?php

namespace backend\models;

use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * This is the model class for table "facilities".
 *
 * The followings are the available columns in table 'facilities':
 * @property string $facility_id
 * @property string $facility_name
 * @property string $facility_type
 * @property string $facility_street
 * @property string $facility_city
 * @property string $facility_state
 * @property string $facility_state_2
 * @property string $facility_country
 * @property string $facility_zip
 * @property string $facility_phone
 * @property string $facility_fax
 * @property string $facility_ice
 * @property string $facility_additional_notes
 * @property integer $facility_client
 * @property integer $facility_available
 * @property string $facilityFullName
 *
 * @property Client $clients
 * @property CountryCode $country
 * @property Client $userCount
 * @property RateCenter[] $rateCenters
 */
class Facility extends ActiveRecord
{

    const FACILITY_TYPE_FEDERAL_PRISON = 'FEDERAL PRISON';
    const FACILITY_TYPE_STATE_PRISON   = 'STATE PRISON';
    const FACILITY_TYPE_COUNTY_JAIL    = 'COUNTY JAIL';
    // new types added 2013-05-02 (Civil Commitment Centers and Halfway Houses)
    const FACILITY_TYPE_CIVIL_COMMITMENT_CENTER = 'CIVIL COMMITMENT CENTER';
    const FACILITY_TYPE_HALFWAY_HOUSE           = 'HALFWAY HOUSE';

    public $clientCount;
    public $clientTextPlanCount;
    public $textUserCount;

    private $_countryList;
    private $_facilityTypes;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'facilities';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['facility_name,facility_type', 'required'],
            ['facility_client, facility_available', 'numerical', 'integerOnly' => true],
            ['facility_name, facility_type, facility_street, facility_city, facility_state, facility_state_2, facility_country, facility_zip, facility_phone, facility_fax, facility_ice', 'length', 'max' => 255],
            ['facility_additional_notes', 'safe'],
            ['facility_additional_notes', 'safe', 'on' => 'editable'], // safe attributes for editable field scenario
            ['facility_id, facility_name, facility_type, facility_street, facility_city, facility_state, facility_state_2, facility_country, facility_zip, facility_phone, facility_fax, facility_ice, facility_additional_notes, facility_client, facility_available', 'safe', 'on' => 'search'],
            ['facility_id, facility_name, facility_type, facility_street, facility_city, facility_state, facility_state_2, facility_country, facility_zip, facility_phone, facility_fax, facility_ice, facility_additional_notes, facility_client, facility_available, clientCount, clientTextPlanCount, textUserCount', 'safe', 'on' => 'searchStats'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'facility_id'               => 'ID',
            'facility_name'             => 'Name',
            'facility_type'             => 'Type',
            'facility_street'           => 'Street',
            'facility_city'             => 'City',
            'facility_state'            => 'State',
            'facility_state_2'          => 'State 2',
            'facility_country'          => 'Country',
            'facility_zip'              => 'Zip',
            'facility_phone'            => 'Phone',
            'facility_fax'              => 'Fax',
            'facility_ice'              => 'ICE Detention Center?',
            'facility_additional_notes' => 'Notes',
            'facility_client'           => 'Client',
            'facility_available'        => 'Available',
            'clientCount'               => '# Active Clients',
            'clientTextPlanCount'       => '# Text Plans',
            'textUserCount'             => '# Active Text Users',
        ];
    }

    public function beforeSave()
    {
        // add state name as facility_state_2
        if (isset(Yii::$app->params['stateList'][$this->facility_state])) {
            $this->facility_state_2 = Yii::$app->params['stateList'][$this->facility_state];
        }

        return parent::beforeSave();
    }

    public function delete($permanently = false)
    {
        if ($permanently) {
            return parent::delete();
        }
        // Facility records should not be fully deleted
        // original = UPDATE facilities SET facility_available='0' WHERE facility_id='$facility_id'
        if (!$this->getIsNewRecord()) {
            Yii::trace(get_class($this) . '.delete()', 'system.db.ar.CActiveRecord');
            if ($this->beforeDelete()) {
                $this->facility_available = 0;
                $result                   = $this->save();
                $this->afterDelete();

                return $result;
            } else {
                return false;
            }
        } else {
            throw new Exception(Yii::t('yii', 'The active record cannot be deleted because it is new.'));
        }
    }

    /**
     * @return ActiveQuery
     */
    public function getClients()
    {
        return $this->hasMany(Client::className(), ['user_facility' => 'facility_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getCountry()
    {
        return $this->hasOne(CountryCode::className(), ['facility_country' => 'facility_country']);
    }

    /**
     * @return ActiveQuery
     */
    public function getRateCenter()
    {
        return $this->hasMany(RateCenter::className(), ['jail_id' => 'rate_center_id'])->onCondition(['acive' => 1]);
    }

    public function getUserCount()
    {
        return $this->getClients()->count();
    }

    /**
     * @deprecated
     * @return array
     */
    public function scopes()
    {
        return [
            'list'    => [
                'select' => 'DISTINCT facility_id, facility_type, facility_name, facility_state, facility_zip, facility_ice',
            ],
            'enabled' => [
                'condition' => 'facility_available=1',
            ],
            'byName'  => [
                'order' => 'facility_name ASC',
            ],
            'byType'  => [
                'order' => 'facility_type ASC, facility_name ASC, facility_state ASC',
            ],
        ];
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        $criteria = new DbCriteria;

        $criteria->with = [
            'country',
            'userCount',
        ];

        $criteria->compare('facility_id', $this->facility_id, true);
        $criteria->compare('facility_name', $this->facility_name, true);
        if ($this->facility_type != 'ice') {
            $criteria->compare('facility_type', $this->facility_type);
        } elseif ($this->facility_type == 'ice') {
            $criteria->compare('facility_ice', 1);
        }
        $criteria->compare('facility_street', $this->facility_street, true);
        $criteria->compare('facility_city', $this->facility_city, true);
        $criteria->compare('facility_state', $this->facility_state);
        $criteria->compare('facility_country', $this->facility_country, true);
        // use asterisk if provided
        $escape = strstr($this->facility_zip, '*') || strstr($this->facility_zip, '?') ? false : true;
        $criteria->compare('facility_zip', $this->facility_zip, true, 'AND', $escape);
        $criteria->compare('facility_phone', $this->facility_phone, true);
        $criteria->compare('facility_fax', $this->facility_fax, true);
        $criteria->compare('facility_ice', $this->facility_ice);
        $criteria->compare('facility_additional_notes', $this->facility_additional_notes, true);
        $criteria->compare('facility_client', $this->facility_client);
        $criteria->compare('facility_available', $this->facility_available);

        $sort               = new CSort();
        $sort->attributes   = [
            'facility_address' => [
                'asc'  => 'facility_address ASC',
                'desc' => 'facility_address DESC',
            ],
            'user_count'       => [
                'asc'  => 'user_count ASC',
                'desc' => 'user_count DESC',
            ],
            '*', // this adds all of the other columns as sortable
        ];
        $sort->defaultOrder = 'facility_name ASC';

        return new ActiveDataProvider($this, [
            'criteria' => $criteria,
            'sort'     => $sort,
        ]);
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * Primarily for use by the client update form to select a new facility.
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function searchSelect()
    {
        $criteria = new CDbCriteria;

        $criteria->with = [
            'country',
        ];

        $criteria->compare('facility_id', $this->facility_id, true);
        $criteria->compare('facility_name', $this->facility_name, true);
        if ($this->facility_type != 'ice') {
            $criteria->compare('facility_type', $this->facility_type);
        } elseif ($this->facility_type == 'ice') {
            $criteria->compare('facility_ice', 1);
        }
        $criteria->compare('facility_state', $this->facility_state, true);
        $criteria->compare('facility_country', $this->facility_country, true);
        $criteria->compare('facility_available', 1);

        $sort               = new CSort();
        $sort->attributes   = [
            'facility_address' => [
                'asc'  => 'facility_address ASC',
                'desc' => 'facility_address DESC',
            ],
            '*',
        ];
        $sort->defaultOrder = 'facility_name ASC';

        return new ActiveDataProvider($this, [
            'criteria' => $criteria,
            'sort'     => $sort,
        ]);
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function searchStats()
    {
        $query = self::find();

        $clientCountSql         = '(SELECT COUNT(u.user_id) FROM user_datas u WHERE u.user_status > 0 AND u.user_facility = t.facility_id)';
        $clientTextPlanCountSql = '(SELECT COUNT(DISTINCT u.user_id) FROM user_datas u '
            . 'INNER JOIN user_plans up ON up.user_id = u.user_id '
            . 'INNER JOIN plans p ON up.plan_id = p.plan_id '
            . 'WHERE u.user_status > 0 AND u.user_facility = t.facility_id AND up.status = 1 AND p.sms_enabled = 1)';
        $textUserCountSql       = '(SELECT COUNT(u.user_id) FROM user_datas u WHERE u.user_status > 0 AND u.user_facility = t.facility_id AND u.corrlinks_account_id > 0 AND u.corrlinks_contact_id > 0)';

        $query->select = [
            '*',
            $clientCountSql . " as clientCount",
            $clientTextPlanCountSql . " as clientTextPlanCount",
            $textUserCountSql . " as textUserCount",
        ];

        $query->with([
            'country',
        ]);

        $criteria->compare('facility_id', $this->facility_id, true);
        $criteria->compare('facility_name', $this->facility_name, true);
        if ($this->facility_type != 'ice') {
            $criteria->compare('facility_type', $this->facility_type);
        } elseif ($this->facility_type == 'ice') {
            $criteria->compare('facility_ice', 1);
        }
        $criteria->compare('facility_street', $this->facility_street, true);
        $criteria->compare('facility_city', $this->facility_city, true);
        $criteria->compare('facility_state', $this->facility_state);
        $criteria->compare('facility_country', $this->facility_country, true);
        // use asterisk if provided
        $escape = strstr($this->facility_zip, '*') || strstr($this->facility_zip, '?') ? false : true;
        $criteria->compare('facility_zip', $this->facility_zip, true, 'AND', $escape);
        $criteria->compare('facility_phone', $this->facility_phone, true);
        $criteria->compare('facility_fax', $this->facility_fax, true);
        $criteria->compare('facility_ice', $this->facility_ice);
        $criteria->compare('facility_additional_notes', $this->facility_additional_notes, true);
        $criteria->compare('facility_client', $this->facility_client);
        $criteria->compare('facility_available', $this->facility_available);
        $criteria->compare($clientCountSql, $this->clientCount);
        $criteria->compare($clientTextPlanCountSql, $this->clientTextPlanCount);
        $criteria->compare($textUserCountSql, $this->textUserCount);

        $sort               = new CSort();
        $sort->attributes   = [
            'facility_address'    => [
                'asc'  => 'facility_address ASC',
                'desc' => 'facility_address DESC',
            ],
            'clientCount'         => [
                'asc'  => 'clientCount ASC',
                'desc' => 'clientCount DESC',
            ],
            'clientTextPlanCount' => [
                'asc'  => 'clientTextPlanCount ASC',
                'desc' => 'clientTextPlanCount DESC',
            ],
            'textUserCount'       => [
                'asc'  => 'textUserCount ASC',
                'desc' => 'textUserCount DESC',
            ],
            '*', // this adds all of the other columns as sortable
        ];
        $sort->defaultOrder = 'facility_name ASC';

        return new ActiveDataProvider($this, [
            'criteria'   => $criteria,
            'sort'       => $sort,
            'pagination' => false,
        ]);
    }

    /**
     * @return string
     */
    public function getFacilityAddress()
    {
        return $this->facility_street . " " . $this->facility_city . " " . $this->facility_state . " " . $this->facility_zip;
    }

    /**
     * for use with select boxes (uses optgroup)
     * @param null $facilityType
     * @param bool $includeICE
     * @param bool $includeAll
     *
     * @return array|mixed
     */
    public function getFacilityTypes($facilityType = null, $includeICE = false, $includeAll = false)
    {
        if (empty($this->_facilityTypes)) {
            $this->_facilityTypes = self::getConstants('FACILITY_TYPE_', __CLASS__);
        }
        $types = $this->_facilityTypes;
        if ($includeICE) {
            $types['ice'] = 'ICE Detention Center';
        }
        if ($includeAll) {
            $types = ['all' => 'All'] + $types;
        }

        return $facilityType !== null && isset($types[$facilityType]) ? $types[$facilityType] : $types;
    }

    /**
     * @return string
     */
    public function getFacilityFullName()
    {
        $flag = empty($this->country) ? '' : Html::img(Yii::$app->request->baseUrl . "/img/country_flags/" . $this->country->country_code_alpha_3 . ".png", $this->country->country_name) . '&nbsp;';

        return $flag . Html::encode($this->facility_name . ", " . $this->facility_state);
    }

    /**
     * @param null $countryId
     *
     * @return array
     */
    public function getFacilityListByType($countryId = null)
    {
        $ret    = [];
        $models = $countryId ?
            $this->list()->enabled()->byType()->findAll('facility_country=:facility_country', [':facility_country' => $countryId]) :
            $this->list()->enabled()->byType()->findAll();
        foreach ($models as $model) {
            $ret[$model->facility_type][$model->facility_id] = $model->facility_name . ', ' . $model->facility_state . ', ' . $model->facility_zip . ($model->facility_ice ? ' (ICE)' : '');
        }

        return $ret;
    }

    /**
     * @param $state
     * @param $type
     * @param bool $returnList
     *
     * @return array
     */
    public function getFacilityListForStateAndType($state, $type, $returnList = true)
    {
        $facilities = Yii::$app->db->createCommand()
            ->select('facility_id, CONCAT(facility_name,", ",facility_zip) AS facility_name')
            ->from('facilities')
            ->where('facility_state = :facility_state AND facility_type = :facility_type', [':facility_state' => $state, ':facility_type' => $type])
            ->order('facility_name')
            ->queryAll();
        if ($returnList) {
            return ArrayHelper::map($facilities, 'facility_id', 'facility_name');
        } else {
            return $facilities;
        }
    }

    /**
     * @param $rateCenterId
     * @param bool $permanently
     *
     * @return false|int
     * @throws Exception
     */
    public function deleteRateCenter($rateCenterId, $permanently = false)
    {
        if ($permanently) {
            return parent::delete();
        }
        if (!$this->getIsNewRecord()) {
            // find rate center in jail_by_rc table
            $facilityRateCenter = FacilityRateCenter::model()->findByAttributes(['jail_id' => $this->facility_id, 'rate_center_id' => $rateCenterId, 'active' => 1]);
            if (empty($facilityRateCenter)) {
                throw new Exception(Yii::t('yii', 'The facility/rate center association could not be found.'));
            }
            $facilityRateCenter->active = 0;

            return $facilityRateCenter->save();
        } else {
            throw new Exception(Yii::t('yii', 'The active record cannot be deleted because it is new.'));
        }
    }

    /**
     * Render the country value for grid output.
     * This should be used in a grid using 'value'=>array($model,'gridCountry')
     */
    public function gridCountry($data, $row)
    {
        if (!empty($data->country)) {
            $flag = Html::img(Yii::$app->request->baseUrl . "/img/country_flags/" . $data->country->country_code_alpha_3 . ".png", $data->country->country_name) . '&nbsp;';

            return $flag . Html::encode($data->country->country_name);
        }

        return null;
    }

    /**
     * Render the state value for grid output.
     * This should be used in a grid using 'value'=>array($model,'gridState')
     */
    public function gridState($data, $row)
    {
        $state = empty($data->facility_state_2) ? $data->facility_state : $data->facility_state_2;

        return Html::encode($state);
    }

    /**
     * Render the state value for grid output.
     * This should be used in a grid using 'value'=>array($model,'gridState')
     */
    public function gridCityState($data, $row)
    {
        $state = empty($data->facility_state_2) ? $data->facility_state : $data->facility_state_2;

        return Html::encode($data->facility_city . ' ' . $state);
    }

    /**
     * Get a country list for countries attached to facilities
     */
    public function getCountryList()
    {
        if (empty($this->_countryList)) {
            $sql        = "SELECT c.country_id,c.country_name FROM facilities f
                    INNER JOIN country_codes c ON f.facility_country = c.country_id
                    GROUP BY f.facility_country
                    ORDER BY c.country_name";
            $this->_countryList = ArrayHelper::map(
                Yii::$app->createCommand($sql)->queryAll(),
                'country_id', 'country_name'
            );
        }

        return $this->_countryList;
    }

}