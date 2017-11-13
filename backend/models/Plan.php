<?php

namespace backend\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * This is the model class for table "plans".
 *
 * The followings are the available columns in table 'plans':
 * @property string $plan_id
 * @property string $plan_name
 * @property string $plan_facility_type
 * @property integer $plan_facility_id
 * @property string $plan_termination_country
 * @property string $plan_type
 * @property integer $plan_dids
 * @property string $plan_termination_cost_fixed
 * @property string $plan_termination_cost_mobile
 * @property string $plan_termination_cost_vip
 * @property integer $plan_termination_minutes
 * @property string $plan_connection_fee
 * @property string $plan_mrc
 * @property string $plan_currency
 * @property string $plan_datetime
 * @property integer $plan_admin_id
 * @property integer $plan_status
 * @property string $plan_description
 * @property integer $plan_public
 * @property string $service_name
 * @property string $voice_enabled
 * @property string $sms_enabled
 * @property string $voip_enabled
 * @property string $app_text_enabled
 * @property float $sms_fee
 * @property string $plan_alert_type
 * @property float $plan_trial_amount
 * @property integer $plan_trial_months
 * @property string $token
 * @property integer $show_in_portal
 *
 * @property Currency $currency
 */
class Plan extends ActiveRecord
{
    const STATUS_CANCELLED = 0;
    const STATUS_ACTIVE    = 1;
    const STATUS_BLOCKED   = 2;
    const STATUS_INACTIVE  = 3;
    const STATUS_PENDING   = 4;

    const TYPE_DOMESTIC      = 'domestic';
    const TYPE_INTERNATIONAL = 'international';
    //const TYPE_HYBRID = 'hybrid'; // not currently used

    const ALERT_TYPE_NONE    = '';
    const ALERT_TYPE_BALANCE = 'balance';
    const ALERT_TYPE_MINUTES = 'minutes';
    const ALERT_TYPE_BOTH    = 'both';

    public $clients;    // store user_plan count
    public $planCountry;
    public $planCountryGrid;

    private $_statusOptions;
    private $_statusClassOptions;
    private $_typeOptions;
    private $_alertTypeOptions;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'plans';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['plan_name, plan_facility_type, plan_currency, plan_type', 'required'],
            ['plan_facility_id, plan_dids, plan_termination_minutes, plan_admin_id, plan_status', 'numerical', 'integerOnly' => true],
            ['plan_name, plan_facility_type, plan_termination_country, plan_type, plan_alert_type, token', 'length', 'max' => 255],
            ['plan_termination_cost_fixed, plan_termination_cost_mobile, plan_connection_fee, plan_mrc', 'length', 'max' => 10],
            ['plan_currency', 'length', 'max' => 3],
            ['plan_datetime, planCountry, planCountryGrid', 'safe'],
            ['plan_status', 'safe', 'on' => 'editable'],
            ['plan_id, plan_name, plan_facility_type, plan_facility_id, plan_termination_country, plan_type, plan_dids, plan_termination_cost_fixed, plan_termination_cost_mobile, plan_termination_minutes, plan_connection_fee, plan_mrc, plan_currency, plan_datetime, plan_admin_id, plan_status, token', 'safe', 'on' => 'search'],
            ['plan_id, plan_name, plan_facility_type, plan_facility_id, plan_termination_country, plan_type, plan_dids, plan_termination_cost_fixed, plan_termination_cost_mobile, plan_termination_minutes, plan_connection_fee, plan_mrc, plan_currency, plan_datetime, plan_admin_id, plan_status, token', 'safe', 'on' => 'searchSelect'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'plan_id'                      => 'ID',
            'plan_name'                    => 'Name',
            'plan_facility_type'           => 'Facility Type',
            'plan_facility_id'             => 'Facility',
            'plan_termination_country'     => 'Termination Country',
            'plan_type'                    => 'Type',
            'plan_alert_type'              => 'Alert Type',
            'plan_dids'                    => '# of Dids',
            'plan_termination_cost_fixed'  => 'Cost/Min Fixed',
            'plan_termination_cost_mobile' => 'Cost/Min Mobile',
            'plan_termination_minutes'     => 'Minutes Included',
            'plan_connection_fee'          => 'Connection Fee',
            'plan_mrc'                     => 'MRC',
            'plan_currency'                => 'Currency',
            'plan_datetime'                => 'Datetime',
            'plan_admin_id'                => 'Admin',
            'plan_status'                  => 'Status',
            'token'                        => 'Token',
        ];
    }

    /**
     * @return bool
     */
    public function beforeSave()
    {
        // make sure any international rates are updated
        if (!$this->isNewRecord && $this->plan_type == self::TYPE_INTERNATIONAL && !empty($this->planCountryGrid) && is_array($this->planCountryGrid)) {
            // get the current plan rates
            $planRates = Yii::$app->db->createCommand()
                ->select('rate_id, country_id, fixed, mobile')
                ->from('plans_rates')
                ->where('plan_id=:planId', [':planId' => $this->plan_id])
                ->order('country_id')
                ->queryAll();

            foreach ($planRates as $planRate) {
                if (isset($this->planCountryGrid[$planRate['country_id']])) {
                    // check if the rate has changed and update if it has
                    $posted = $this->planCountryGrid[$planRate['country_id']];

                    $update = [];
                    if ((string)$planRate['fixed'] !== (string)$posted['fixed']) {
                        $update['fixed'] = $posted['fixed'];
                    }
                    if ((string)$planRate['mobile'] !== (string)$posted['mobile']) {
                        $update['mobile'] = $posted['mobile'];
                    }

                    if (count($update)) {
                        // add the latest datetime
                        $update['datetime'] = date('Y-m-d H:i:s');
                        Yii::$app->db->createCommand()->update(PlanRate::tableName(), $update,
                            'rate_id=:id', [':id' => $planRate['rate_id']]);
                    }
                }
            }
        }

        return parent::beforeSave();
    }

    /**
     * @return ActiveQuery
     */
    public function getCurrency()
    {
        return $this->hasOne(Currency::className(), ['currency_prefix' => 'plan_currency']);
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {

        $criteria = new CDbCriteria;

        $criteria->compare('plan_id', $this->plan_id, true);
        $criteria->compare('plan_name', $this->plan_name, true);
        $criteria->compare('plan_facility_type', $this->plan_facility_type, true);
        $criteria->compare('plan_facility_id', $this->plan_facility_id);
        $criteria->compare('plan_termination_country', $this->plan_termination_country, true);
        $criteria->compare('plan_type', $this->plan_type, true);
        $criteria->compare('plan_alert_type', $this->plan_alert_type, true);
        $criteria->compare('plan_dids', $this->plan_dids);
        $criteria->compare('plan_termination_cost_fixed', $this->plan_termination_cost_fixed, true);
        $criteria->compare('plan_termination_cost_mobile', $this->plan_termination_cost_mobile, true);
        $criteria->compare('plan_termination_minutes', $this->plan_termination_minutes);
        $criteria->compare('plan_connection_fee', $this->plan_connection_fee, true);
        $criteria->compare('plan_mrc', $this->plan_mrc, true);
        $criteria->compare('plan_currency', $this->plan_currency, true);
        $criteria->compare('plan_datetime', $this->plan_datetime, true);
        $criteria->compare('plan_admin_id', $this->plan_admin_id);
        $criteria->compare('plan_status', $this->plan_status);
        $criteria->compare('token', $this->token, true);

        $sort               = new CSort();
        $sort->attributes   = [
            '*', // this adds all of the other columns as sortable
        ];
        $sort->defaultOrder = 'plan_name ASC';

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
            'sort'     => $sort,
        ]);
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function searchSelect()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria = new CDbCriteria;

        // make sure we don't show cancelled plans
        $criteria->addCondition('plan_status > 0');
        // .. or plans that still need to work but shouldn't be visible
        $criteria->addCondition('show_in_portal = 1');

        $criteria->compare('plan_id', $this->plan_id, true);
        $criteria->compare('plan_name', $this->plan_name, true);
        $criteria->compare('plan_facility_type', $this->plan_facility_type, true);
        $criteria->compare('plan_facility_id', $this->plan_facility_id);
        $criteria->compare('plan_termination_country', $this->plan_termination_country, true);
        $criteria->compare('plan_type', $this->plan_type, true);
        $criteria->compare('plan_alert_type', $this->plan_alert_type, true);
        $criteria->compare('plan_dids', $this->plan_dids);
        $criteria->compare('plan_termination_cost_fixed', $this->plan_termination_cost_fixed, true);
        $criteria->compare('plan_termination_cost_mobile', $this->plan_termination_cost_mobile, true);
        $criteria->compare('plan_termination_minutes', $this->plan_termination_minutes);
        $criteria->compare('plan_connection_fee', $this->plan_connection_fee, true);
        $criteria->compare('plan_mrc', $this->plan_mrc, true);
        $criteria->compare('plan_currency', $this->plan_currency, true);
        $criteria->compare('plan_datetime', $this->plan_datetime, true);
        $criteria->compare('plan_admin_id', $this->plan_admin_id);
        //$criteria->compare('plan_status', $this->plan_status);
        $criteria->compare('token', $this->token, true);

        $sort               = new CSort();
        $sort->attributes   = [
            '*',
        ];
        $sort->defaultOrder = 'plan_name ASC';

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
            'sort'     => $sort,
        ]);
    }

    /**
     * @return bool
     */
    public function getHasTrial()
    {
        return ($this->plan_trial_amount !== null && !empty($this->plan_trial_months));
    }

    /**
     * @deprecated
     * @param null $status
     *
     * @return mixed
     */
    public function getStatusOptions($status = null)
    {
        if (empty($this->_statusOptions)) {
            $statusNames = [
                self::STATUS_CANCELLED => 'Cancelled',
                self::STATUS_ACTIVE    => 'Active',
                self::STATUS_BLOCKED   => 'Blocked',
                self::STATUS_INACTIVE  => 'Inactive',
                self::STATUS_PENDING   => 'Pending',
            ];

            $this->_statusOptions = self::getConstants('STATUS_', __CLASS__, $statusNames);
        }

        return $status !== null && isset($this->_statusOptions[$status]) ? $this->_statusOptions[$status] : $this->_statusOptions;
    }

    /**
     * @deprecated
     * @param null $alert_type
     *
     * @return mixed
     */
    public function getAlertTypeOptions($alert_type = null)
    {
        if (empty($this->_alertTypeOptions)) {
            $alertTypeNames = [
                self::ALERT_TYPE_NONE    => 'None',
                self::ALERT_TYPE_BALANCE => 'Balance',
                self::ALERT_TYPE_MINUTES => 'Minutes',
                self::ALERT_TYPE_BOTH    => 'Both',
            ];

            $this->_alertTypeOptions = self::getConstants('ALERT_TYPE_', __CLASS__, $alertTypeNames);
        }

        return $alert_type !== null && isset($this->_alertTypeOptions[$alert_type]) ? $this->_alertTypeOptions[$alert_type] : $this->_alertTypeOptions;
    }

    /**
     * @deprecated
     * @param null $status
     *
     * @return mixed
     */
    public function getStatusClassOptions($status = null)
    {
        if (empty($this->_statusClassOptions)) {
            $statusClassNames = [
                self::STATUS_CANCELLED => 'status_cancelled',
                self::STATUS_ACTIVE    => 'status_active',
                self::STATUS_BLOCKED   => 'status_blocked',
                self::STATUS_INACTIVE  => 'status_inactive',
                self::STATUS_PENDING   => 'status_pending',
            ];

            $this->_statusClassOptions = self::getConstants('STATUS_', __CLASS__, $statusClassNames);
        }

        return $status !== null && isset($this->_statusClassOptions[$status]) ? $this->_statusClassOptions[$status] : $this->_statusClassOptions;
    }

    /**
     * @deprecated
     * @param null $type
     *
     * @return mixed
     */
    public function getTypeOptions($type = null)
    {
        if (empty($this->_typeOptions)) {
            $this->_typeOptions = self::getConstants('TYPE_', __CLASS__, self::CONSTANT_FORMAT_UCFIRST);
        }

        return $type !== null && isset($this->_typeOptions[$type]) ? $this->_typeOptions[$type] : $this->_typeOptions;
    }

    /**
     * @return array
     */
    public function getFacilityTypes()
    {
        return explode(',', strtoupper($this->plan_facility_type));
    }

    /**
     * @param $value
     */
    public function setFacilityTypes($value)
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        // if ALL is in the list remove any others
        if (in_array('ALL', $value)) {
            $value = ['ALL'];
        }

        $this->plan_facility_type = implode(',', $value);
    }

    /**
     * @param null $type
     *
     * @return array|mixed|null
     */
    public function getFacilityTypeOptions($type = null)
    {
        $options = [
            'ALL'     => 'All',
            'FEDERAL' => 'Federal Prison',
            'STATE'   => 'State Prison',
            'COUNTY'  => 'County Jail',
        ];

        if ($type === null) {
            return $options;
        }

        return isset($options[$type]) ? $options[$type] : null;
    }

    /**
     * Render the MRC value for grid output.
     * This should be used in a grid using 'value'=>array($model,'gridMrc')
     * This is so that the currency symbol doesn't get called multiple times
     *
     * @param Plan $data
     *
     * @return string
     */
    public function gridMrc($data)
    {
        return Html::encode($this->getCurrencySymbol($data->plan_currency) . ' ' . $data->plan_mrc . ' ' . $data->plan_currency);
    }

    public function getPlanList()
    {
        $models = Yii::$app->db->createCommand()
            ->select('plan_id, plan_name')
            ->from('plans')
            ->where('plan_status=1')
            ->order('plan_name')
            ->queryAll();
        $list   = ArrayHelper::map($models, 'plan_id', 'plan_name');

        return $list;
    }

    /**
     * @param string $facilityType
     * @param bool $publicOnly
     * @param bool $returnList
     *
     * @return array
     */
    public function getPlanListForFacility($facilityType = 'ALL', $publicOnly = true, $returnList = true)
    {
        switch ($facilityType) {
            case Facility::FACILITY_TYPE_COUNTY_JAIL :
                $facilityType = 'COUNTY';
                break;
            case Facility::FACILITY_TYPE_FEDERAL_PRISON :
                $facilityType = 'FEDERAL';
                break;
            case Facility::FACILITY_TYPE_STATE_PRISON :
                $facilityType = 'STATE';
                break;
            default :
                $facilityType = 'ALL';
                break;
        }
        $publicSql = $publicOnly ? ' AND plan_public = 1' : '';
        $plans     = Yii::$app->db->createCommand()
            ->select('plan_id, plan_name')
            ->from('plans')
            ->where('(plan_facility_type like :facilityType OR plan_facility_type="ALL,") AND plan_status=1' . $publicSql, [':facilityType' => '%' . $facilityType . '%'])
            ->order('plan_name')
            ->queryAll();
        if ($returnList) {
            $plans = ArrayHelper::map($plans, 'plan_id', 'plan_name');
            // 400 plan has become 'Family Plan'
            foreach ($plans as $k => &$v) {
                if ($v == '400') {
                    $v = 'Family Plan';
                } elseif ($v == 'Voicemail') {
                    $v = 'Voice Mail (1 Month Free Trial)';
                }
            }

            return $plans;
        } else {
            return $plans;
        }
    }
}
