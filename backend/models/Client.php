<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\Html;
use backend\helpers\Globals;

/**
 * This is the model class for table "user_datas".
 *
 * The followings are the available columns in table 'user_datas':
 * @property string $user_id
 * @property integer $user_service
 * @property string $user_first_name
 * @property string $user_last_name
 * @property string $user_full_name
 * @property string $user_full_name_reverse
 * @property integer $user_phone_country_id
 * @property string $user_phone
 * @property string $user_email
 * @property integer $user_facility
 * @property integer $user_facility_country_id
 * @property string $user_facility_state
 * @property string $user_reg_number
 * @property string $user_inmate_first_name
 * @property string $user_inmate_last_name
 * @property string $user_inmate_full_name
 * @property string $user_inmate_full_name_reverse
 * @property integer $user_bill_day
 * @property string $user_payment_method
 * @property integer $user_recurring
 * @property string $user_balance
 * @property string $user_limit
 * @property integer $balance_alert
 * @property float $alert_amount
 * @property int $alert_minutes
 * @property integer $alert_by_email
 * @property integer $alert_by_sms
 * @property integer $alert_by_corrlink
 * @property string $user_w3_username
 * @property string $user_w3_password
 * @property string $user_ivr_pin
 * @property string $user_promotional_code
 * @property integer $user_prepaid
 * @property integer $user_renewal
 * @property string $user_currency
 * @property string $user_suggested_mrc
 * @property string $user_imposed_mrc
 * @property string $user_amount_cc
 * @property string $mrc_month_sync
 * @property string $user_notes
 * @property string $user_referral_credit_fixed
 * @property string $user_referral_credit_percentage
 * @property integer $user_status
 * @property string $user_datetime
 * @property string $user_datetime_last_update
 * @property integer $user_admin
 * @property string $token
 * @property integer $user_active_call
 * @property string $signup_ip
 * @property integer $corrlinks_account_id
 * @property integer $corrlinks_contact_id
 * @property integer $corrlinks_support_account_id
 * @property integer $corrlinks_support_contact_id
 * @property integer $user_days_before_bill_day
 * @property integer $received_payment
 *
 * @property Currency $currency
 * @property Currency $currencySymbol
 * @property CorrlinksAccount $corrlinksAccount
 * @property CorrlinksContact $corrlinksContact
 * @property CorrlinksAccount $corrlinksSupportAccount
 * @property CorrlinksContact $corrlinksSupportContact
 * @property Facility $facility
 * @property Service $service
 * @property ClientPlan[] $clientPlans
 * @property string $suggestedMrc
 * @property string $statusText
 * @property string $credit_notification_send_date
 * @property string $minutes_notification_send_date
 */
class Client extends ActiveRecord // TODO: Inherited from \protected\components\ActiveRecord.php
{
    const STATUS_ACTIVE    = 1;
    const STATUS_BLOCKED   = 2;
    const STATUS_INACTIVE  = 3;
    const STATUS_PENDING   = 4;
    const STATUS_CANCELLED = 0;

    const BILLING_TYPE_PRE  = 1;
    const BILLING_TYPE_POST = 0;

    const PAYMENT_METHOD_NONE              = 'x';
    const PAYMENT_METHOD_CREDIT_CARD       = 'C/C';
    const PAYMENT_METHOD_MONEY_ORDER       = 'M.O.';
    const PAYMENT_METHOD_PAYPAL            = 'PayPal';
    const PAYMENT_METHOD_INSTITUTION_CHECK = 'I.C.';
    const PAYMENT_METHOD_MONEYGRAM         = 'MoneyGram';

    const RECURRING_YES = 1;
    const RECURRING_NO  = 0;

    // some extras for the campaign search
    public $excludePlans;

    public $newFacilityName;
    public $newFacilityId;
    public $facilityName;
    public $facilityCountry;
    public $facilityState;
    public $facilityZip;
    public $facilityType;
    public $planId;

    public $corrlinksAccountName;
    public $corrlinksContactName;

    public $usedCount;
    public $leftCount;
    public $statusAge;
    public $autoBill;
    public $mrc;
    public $defaultCardSelect = 'autoBill';    // default card selection

    private $_statusOptions;
    private $_statusClassOptions;
    private $_billingTypeOptions;
    private $_paymentMethodOptions;
    private $_recurringOptions;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%user_datas}}';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'activerecord-relation' => [
                // TODO: Need replace class
                'class' => 'ext.behaviors.activerecord-relation.EActiveRecordRelationBehavior',
            ],
        ];
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['user_bill_day', 'user_service', 'user_first_name', 'user_last_name'], 'required'],
            [['user_currency'], 'required', 'on' => ['update']],
            [['received_payment', 'user_service', 'user_phone_country_id', 'user_facility', 'user_facility_country_id', 'user_bill_day', 'user_recurring', 'balance_alert', 'alert_by_email', 'alert_by_sms', 'alert_by_corrlink', 'user_prepaid', 'user_renewal', 'user_status', 'user_admin', 'user_active_call', 'alert_minutes', 'corrlinks_account_id', 'corrlinks_contact_id', 'corrlinks_support_account_id', 'corrlinks_support_contact_id'], 'integer'],
            [['user_first_name', 'user_last_name', 'user_full_name', 'user_full_name_reverse', 'user_phone', 'user_email', 'user_facility_state', 'user_reg_number', 'user_inmate_first_name', 'user_inmate_last_name', 'user_inmate_full_name', 'user_inmate_full_name_reverse', 'user_payment_method', 'user_w3_username', 'user_w3_password', 'user_promotional_code', 'mrc_month_sync', 'token'], 'max' => 255],
            [['user_balance', 'user_limit', 'alert_amount', 'alert_minutes', 'user_suggested_mrc', 'user_imposed_mrc', 'user_amount_cc', 'user_referral_credit_fixed', 'user_referral_credit_percentage'], 'max' => 10],
            [['user_ivr_pin'], 'max' => 20],
            [['user_days_before_bill_day'], 'double'],
            [['user_currency'], 'max' => 3],
            [['user_datetime', 'user_datetime_last_update', 'credit_notification_send_date', 'minutes_notification_send_date', 'user_notes', 'signup_ip', 'user_days_before_bill_day'], 'safe'],
            // safe attributes for editable field scenario (from an editable grid column)
            [['user_status'], 'safe', 'on' => ['editable']],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['user_id', 'user_status', 'user_full_name_reverse', 'user_reg_number', 'user_service', 'facilityType', 'facilityCountry', 'facilityState', 'facilityZip', 'facilityName', 'user_inmate_full_name_reverse', 'user_payment_method', 'user_bill_day', 'user_balance', 'user_limit', 'didCount', 'sipCount', 'usedCount', 'statusAge', 'user_facility', 'corrlinks_account_id', 'corrlinks_support_account_id', 'corrlinksContactName', 'planId'], 'safe', 'on' => ['search']],
            // search select safe list ...
            [['user_id', 'user_status', 'user_full_name_reverse', 'user_reg_number', 'user_service', 'facilityType', 'facilityCountry', 'facilityState', 'facilityZip', 'facilityName', 'user_inmate_full_name_reverse', 'user_payment_method', 'user_bill_day', 'user_balance', 'user_limit', 'didCount', 'sipCount', 'usedCount', 'statusAge'], 'safe', 'on' => ['searchSelect']],
            [['user_id', 'user_status', 'user_full_name_reverse', 'user_reg_number', 'user_service', 'facilityType', 'facilityCountry', 'facilityState', 'facilityZip', 'facilityName', 'user_inmate_full_name_reverse', 'user_payment_method', 'user_bill_day', 'user_balance', 'user_limit', 'didCount', 'sipCount', 'usedCount', 'leftCount', 'statusAge', 'user_facility', 'autoBill', 'defaultCardSelect', 'mrc'], 'safe', 'on' => ['searchChargeView']],
            [['user_id', 'user_status', 'user_full_name_reverse', 'user_reg_number', 'user_service', 'facilityType', 'facilityCountry', 'facilityState', 'facilityZip', 'facilityName', 'user_inmate_full_name_reverse', 'user_payment_method', 'user_bill_day', 'user_balance', 'user_limit', 'didCount', 'sipCount', 'usedCount', 'statusAge', 'excludePlans'], 'safe', 'on' => ['campaignSearch']],
        ];
    }

    /**
     * @return array customized attribute labels (name => label)
     */
    public function attributeLabels()
    {
        return [
            'user_id'                         => 'ID',
            'user_service'                    => 'Service Type',
            'user_first_name'                 => 'Client First Name',
            'user_last_name'                  => 'Client Last Name',
            'user_full_name'                  => 'Client Full Name',
            'user_full_name_reverse'          => 'Client Full Name Reverse',
            'user_phone_country_id'           => 'Client Phone Country',
            'user_phone'                      => 'Client Phone',
            'user_email'                      => 'Client Email',
            'user_facility'                   => 'Facility',
            'user_facility_country_id'        => 'Facility Country',
            'user_facility_state'             => 'Facility State',
            'user_reg_number'                 => 'Reg. Number',
            'user_inmate_first_name'          => 'User First Name',
            'user_inmate_last_name'           => 'User Last Name',
            'user_inmate_full_name'           => 'User Full Name',
            'user_inmate_full_name_reverse'   => 'User Full Name Reverse',
            'user_bill_day'                   => 'Bill Day',
            'user_payment_method'             => 'Payment Method',
            'user_recurring'                  => 'Recurring',
            'user_balance'                    => 'Balance',
            'user_limit'                      => 'Limit',
            'balance_alert'                   => 'Balance/Minutes Alert',
            'alert_amount'                    => 'Alert Amount (for International Plans)',
            'alert_minutes'                   => 'Alert Minutes (for Domectic Plans)',
            'alert_by_email'                  => 'Alert By Email',
            'alert_by_sms'                    => 'Alert By Sms',
            'alert_by_corrlink'               => 'Alert By Corrlinks',
            'user_w3_username'                => 'W3 Username',
            'user_w3_password'                => 'W3 Password',
            'user_ivr_pin'                    => 'Ivr Pin',
            'user_promotional_code'           => 'Promotional Code',
            'user_prepaid'                    => 'Billing Type',
            'user_renewal'                    => 'Renew Next Month',
            'user_currency'                   => 'Currency',
            'user_suggested_mrc'              => 'Suggested MRC',
            'user_imposed_mrc'                => 'Imposed MRC',
            'user_amount_cc'                  => 'Amount C/C',
            'mrc_month_sync'                  => 'MRC Month Sync',
            'user_notes'                      => 'Notes',
            'user_referral_credit_fixed'      => 'Referral Credit (Fixed)',
            'user_referral_credit_percentage' => 'Referral Credit (Percentage)',
            'user_status'                     => 'Status',
            'user_datetime'                   => 'Datetime',
            'user_datetime_last_update'       => 'Datetime Last Update',
            'user_admin'                      => 'Admin',
            'token'                           => 'Token',
            'user_active_call'                => 'Active Call',
            'corrlinks_account_id'            => 'Corrlinks Texting Account',
            'corrlinks_contact_id'            => 'Corrlinks Texting Contact',
            'corrlinks_support_account_id'    => 'Corrlinks Support Account',
            'corrlinks_support_contact_id'    => 'Corrlinks Support Contact',
            'facilityName'                    => 'Facility',
            'facilityFullName'                => 'Facility',
            'didCount'                        => 'DIDs',
            'sipCount'                        => 'SIPs',
            'usedCount'                       => 'Used (Min)',
            'leftCount'                       => 'Left (Min)',
            'autoBill'                        => 'Has Auto Bill?',
            'defaultCardSelect'               => 'Card Selection',
            'mrc'                             => 'MRC',
            'user_days_before_bill_day'       => 'Next Payment Due',
            'received_payment'                => 'Received Payment',
        ];
    }

    /**
     * TODO: Need to rewrite the method
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria = new CDbCriteria;
        // no longer using sip and did count

        $usedCountSql     = '(SELECT SUM(p.used) FROM user_plans AS p WHERE p.user_id = t.user_id AND p.status = 1)';
        $statusAgeSql     = '(SELECT DATEDIFF("' . date('Y-m-d') . '",l.log_datetime) FROM user_logs AS l WHERE l.user_id = t.user_id AND l.user_status = t.user_status 
                            AND l.log_id > COALESCE((SELECT l2.log_id FROM user_logs AS l2 WHERE l2.user_id = t.user_id AND l2.user_status != t.user_status 
                                            ORDER BY l2.log_datetime DESC, l2.log_id DESC LIMIT 1),0)
                            ORDER BY l.log_datetime ASC
                            LIMIT 1)';
        $criteria->select = [
            '*',
            $usedCountSql . " as usedCount",
            $statusAgeSql . " as statusAge",
        ];
        $criteria->with   = [
            'facility',
            'service',
            'corrlinksAccount',
            'corrlinksContact',
            'clientPlans' => [
                'with' => ['plan'],
            ],
        ];

        // make sure this is false, otherwise pagination starts giving strange results
        // 'many' relations should be done like this and filters done as select sub queries (see below)
        $criteria->together = false;

        $criteria->compare('t.user_id', $this->user_id);
        $criteria->compare('user_status', $this->user_status);
        $criteria->compare('CONCAT(t.user_last_name,", ",t.user_first_name)', $this->user_full_name_reverse, true);
        $criteria->compare('user_reg_number', $this->user_reg_number, true);
        // note: some user service entries are -1 so we must take these into account too by using the following
        if (is_numeric($this->user_service) && $this->user_service == 0) {
            $this->user_service = '< 1';
        }
        $criteria->compare('user_service', $this->user_service);
        $criteria->compare('facility.facility_type', $this->facilityType);
        $criteria->compare('facility.facility_name', $this->facilityName, true);
        $criteria->compare('facility.facility_state', $this->facilityState);
        $criteria->compare('facility.facility_zip', $this->facilityZip, true);
        $criteria->compare('CONCAT(t.user_inmate_last_name,", ",t.user_inmate_first_name)', $this->user_inmate_full_name_reverse, true);
        $criteria->compare('user_payment_method', $this->user_payment_method);
        $criteria->compare('user_bill_day', $this->user_bill_day);
        $criteria->compare('user_balance', $this->user_balance, true);
        $criteria->compare($usedCountSql, $this->usedCount);
        $criteria->compare($statusAgeSql, $this->statusAge);
        $criteria->compare('user_facility', $this->user_facility);

        if (!empty($this->planId)) {
            // the following allows us to filter by plan but still show the other plans the user has too
            $criteria->addCondition('t.user_id IN(SELECT user_id FROM user_plans WHERE status > 0 AND plan_id = :planId)');
            $criteria->params[':planId'] = $this->planId;
        }

        if ($this->corrlinks_account_id == 'x') {
            $criteria->addCondition('t.corrlinks_account_id > 0 AND t.corrlinks_contact_id IS NULL');
        } else {
            $criteria->compare('corrlinksAccount.id', $this->corrlinks_account_id);
        }

        $criteria->compare('CONCAT(corrlinksContact.name, " (",corrlinksContact.number,")"', $this->corrlinksContactName, true);

        $sort               = new CSort();
        $sort->attributes   = [
            'user_service'                  => [
                'asc'  => 'service.service_name ASC',
                'desc' => 'service.service_name DESC',
            ],
            'facilityType'                  => [
                'asc'  => 'facility.facility_type ASC',
                'desc' => 'facility.facility_type DESC',
            ],
            'facilityName'                  => [
                'asc'  => 'facility.facility_name ASC',
                'desc' => 'facility.facility_name DESC',
            ],
            'facilityState'                 => [
                'asc'  => 'facility.facility_state ASC',
                'desc' => 'facility.facility_state DESC',
            ],
            'facilityZip'                   => [
                'asc'  => 'facility.facility_zip ASC',
                'desc' => 'facility.facility_zip DESC',
            ],
            'usedCount'                     => [
                'asc'  => 'usedCount ASC',
                'desc' => 'usedCount DESC',
            ],
            'statusAge'                     => [
                'asc'  => 'statusAge ASC',
                'desc' => 'statusAge DESC',
            ],
            'user_full_name_reverse'        => [
                'asc'  => 'CONCAT(t.user_last_name,", ",t.user_first_name) ASC',
                'desc' => 'CONCAT(t.user_last_name,", ",t.user_first_name) DESC',
            ],
            'user_inmate_full_name_reverse' => [
                'asc'  => 'CONCAT(t.user_inmate_last_name,", ",t.user_inmate_first_name) ASC',
                'desc' => 'CONCAT(t.user_inmate_last_name,", ",t.user_inmate_first_name) DESC',
            ],
            '*', // this adds all of the other columns as sortable
        ];
        $sort->defaultOrder = 't.user_id DESC';

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
            'sort'     => $sort,
        ]);
    }

    /**
     * TODO: Need to rewrite the method
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * Primarily for use by forms that need to select a client.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function searchSelect()
    {
        $criteria       = new CDbCriteria;
        $criteria->with = [
            'facility' => [
                'with' => ['country'],
            ],
        ];

        $criteria->compare('user_id', $this->user_id);
        $criteria->compare('user_status', $this->user_status);
        $criteria->compare('CONCAT(t.user_last_name,", ",t.user_first_name)', $this->user_full_name_reverse, true);
        $criteria->compare('user_reg_number', $this->user_reg_number, true);
        $criteria->compare('facility.facility_name', $this->facilityName, true);
        $criteria->compare('facility.facility_state', $this->facilityState);
        $criteria->compare('facility.facility_zip', $this->facilityZip);
        $criteria->compare('facility.facility_country', $this->facilityCountry);
        $criteria->compare('CONCAT(t.user_inmate_last_name,", ",t.user_inmate_first_name)', $this->user_inmate_full_name_reverse, true);
        $criteria->compare('user_payment_method', $this->user_payment_method);
        $criteria->compare('user_bill_day', $this->user_bill_day);
        $criteria->compare('user_balance', $this->user_balance, true);
        $criteria->compare('user_limit', $this->user_limit, true);

        $sort               = new CSort();
        $sort->attributes   = [
            'facilityName'                  => [
                'asc'  => 'facility.facility_name ASC',
                'desc' => 'facility.facility_name DESC',
            ],
            'facilityCountry'               => [
                'asc'  => 'country.country_name ASC',
                'desc' => 'country.country_name DESC',
            ],
            'facilityState'                 => [
                'asc'  => 'facility.facility_state ASC',
                'desc' => 'facility.facility_state DESC',
            ],
            'facilityZip'                   => [
                'asc'  => 'facility.facility_zip ASC',
                'desc' => 'facility.facility_zip DESC',
            ],
            'user_full_name_reverse'        => [
                'asc'  => 'CONCAT(t.user_last_name,", ",t.user_first_name) ASC',
                'desc' => 'CONCAT(t.user_last_name,", ",t.user_first_name) DESC',
            ],
            'user_inmate_full_name_reverse' => [
                'asc'  => 'CONCAT(t.user_inmate_last_name,", ",t.user_inmate_first_name) ASC',
                'desc' => 'CONCAT(t.user_inmate_last_name,", ",t.user_inmate_first_name) DESC',
            ],
            '*', // this adds all of the other columns as sortable
        ];
        $sort->defaultOrder = 'CONCAT(t.user_last_name,", ",t.user_first_name) ASC'; // 't.user_full_name_reverse ASC'

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
            'sort'     => $sort,
        ]);
    }

    /**
     * TODO: Need to rewrite the method
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions - specifically for charging cards.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function searchChargeView()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria = new CDbCriteria;

        $mrcSql           = '(CASE WHEN t.user_imposed_mrc != 0.00 THEN t.user_imposed_mrc ELSE t.user_suggested_mrc END)';
        $usedCountSql     = '(SELECT SUM(p.used) FROM user_plans AS p WHERE p.user_id = t.user_id AND p.status = 1)';
        $leftCountSql     = '(SELECT COALESCE(SUM(p.allowance),0) FROM user_plans AS p INNER JOIN plans pl ON p.plan_id = pl.plan_id AND plan_alert_type = "minutes" WHERE p.user_id = t.user_id AND p.status = 1)';
        $autoBillSql      = '(SELECT CASE WHEN COUNT(billing_id) > 0 THEN 1 ELSE 0 END FROM user_billing_details AS cc WHERE cc.user_id = t.user_id AND cc.billing_status = 1 AND cc.allow_auto_billing = 1 AND cc.flagged != 2)';
        $statusAgeSql     = '(SELECT DATEDIFF("' . date('Y-m-d') . '",l.log_datetime) FROM user_logs AS l WHERE l.user_id = t.user_id AND l.user_status = t.user_status 
                            AND l.log_id > COALESCE((SELECT l2.log_id FROM user_logs AS l2 WHERE l2.user_id = t.user_id AND l2.user_status != t.user_status 
                                            ORDER BY l2.log_datetime DESC, l2.log_id DESC LIMIT 1),0)
                            ORDER BY l.log_datetime ASC
                            LIMIT 1)';
        $criteria->select = [
            '*',
            $usedCountSql . " as usedCount",
            $leftCountSql . " as leftCount",
            $autoBillSql . " as autoBill",
            $mrcSql . " as mrc",
            $statusAgeSql . " as statusAge",
        ];
        $criteria->with   = [
            'facility' => [
                'with' => ['country'],
            ],
            'service',
        ];

        $criteria->compare('user_id', $this->user_id);
        $criteria->compare('user_status', $this->user_status);
        $criteria->compare('CONCAT(t.user_last_name," ",t.user_first_name," ",t.user_inmate_last_name," ",t.user_inmate_first_name)', $this->user_full_name_reverse, true);
        $criteria->compare('user_reg_number', $this->user_reg_number, true);
        if (is_numeric($this->user_service) && $this->user_service == 0) {
            $this->user_service = '< 1';
        }
        $criteria->compare('user_service', $this->user_service);
        $criteria->compare('facility.facility_type', $this->facilityType);
        $criteria->compare('facility.facility_name', $this->facilityName, true);
        $criteria->compare('facility.facility_state', $this->facilityState);
        $criteria->compare('user_payment_method', $this->user_payment_method);
        $criteria->compare('user_bill_day', $this->user_bill_day);
        $criteria->compare('user_balance', $this->user_balance, true);
        $criteria->compare($usedCountSql, $this->usedCount);
        $criteria->compare($leftCountSql, $this->leftCount);
        $criteria->compare($autoBillSql, $this->autoBill);
        $criteria->compare($mrcSql, $this->mrc);
        $criteria->compare($statusAgeSql, $this->statusAge);
        $criteria->compare('user_facility', $this->user_facility);

        $sort               = new CSort();
        $sort->attributes   = [
            'user_service'                  => [
                'asc'  => 'service.service_name ASC',
                'desc' => 'service.service_name DESC',
            ],
            'facilityType'                  => [
                'asc'  => 'facility.facility_type ASC',
                'desc' => 'facility.facility_type DESC',
            ],
            'facilityName'                  => [
                'asc'  => 'facility.facility_name ASC',
                'desc' => 'facility.facility_name DESC',
            ],
            'facilityCountry'               => [
                'asc'  => 'country.country_name ASC',
                'desc' => 'country.country_name DESC',
            ],
            'facilityState'                 => [
                'asc'  => 'facility.facility_state ASC',
                'desc' => 'facility.facility_state DESC',
            ],
            'facilityZip'                   => [
                'asc'  => 'facility.facility_zip ASC',
                'desc' => 'facility.facility_zip DESC',
            ],
            'usedCount'                     => [
                'asc'  => 'usedCount ASC',
                'desc' => 'usedCount DESC',
            ],
            'leftCount'                     => [
                'asc'  => 'leftCount ASC',
                'desc' => 'leftCount DESC',
            ],
            'autoBill'                      => [
                'asc'  => 'autoBill ASC',
                'desc' => 'autoBill DESC',
            ],
            'mrc'                           => [
                'asc'  => 'mrc ASC',
                'desc' => 'mrc DESC',
            ],
            'statusAge'                     => [
                'asc'  => 'statusAge ASC',
                'desc' => 'statusAge DESC',
            ],
            'user_full_name_reverse'        => [
                'asc'  => 'CONCAT(t.user_last_name,", ",t.user_first_name) ASC',
                'desc' => 'CONCAT(t.user_last_name,", ",t.user_first_name) DESC',
            ],
            'user_inmate_full_name_reverse' => [
                'asc'  => 'CONCAT(t.user_inmate_last_name,", ",t.user_inmate_first_name) ASC',
                'desc' => 'CONCAT(t.user_inmate_last_name,", ",t.user_inmate_first_name) DESC',
            ],
            '*', // this adds all of the other columns as sortable
        ];
        $sort->defaultOrder = 'CONCAT(t.user_last_name,", ",t.user_first_name) ASC'; // 't.user_full_name_reverse ASC'

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
            'sort'     => $sort,
        ]);
    }

    /**
     * TODO: Need to rewrite the method
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * Primarily for use by the campaign section to filter clients to send to
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function campaignSearch()
    {
        $criteria       = new CDbCriteria;
        $criteria->with = [
            'facility' => [
                'with' => ['country'],
            ],
        ];

        if (!empty($this->excludePlans)) {
            $excludedPlanIds     = is_array($this->excludePlans) ? implode(',', array_map('intval', $this->excludePlans)) : $this->excludePlans;
            $criteria->condition = "(SELECT user_plan_id FROM user_plans up WHERE up.user_id = t.user_id AND up.status > 0 AND up.plan_id IN($excludedPlanIds) LIMIT 1) IS NULL";
        }

        $criteria->compare('user_id', $this->user_id);
        $criteria->compare('user_status', $this->user_status);
        $criteria->compare('CONCAT(t.user_last_name,", ",t.user_first_name)', $this->user_full_name_reverse, true);
        $criteria->compare('user_reg_number', $this->user_reg_number, true);
        $criteria->compare('facility.facility_type', $this->facilityType);
        $criteria->compare('facility.facility_name', $this->facilityName, true);
        $criteria->compare('facility.facility_state', $this->facilityState);
        $criteria->compare('facility.facility_zip', $this->facilityZip);
        $criteria->compare('facility.facility_country', $this->facilityCountry);
        $criteria->compare('CONCAT(t.user_inmate_last_name,", ",t.user_inmate_first_name)', $this->user_inmate_full_name_reverse, true);
        $criteria->compare('user_payment_method', $this->user_payment_method);
        $criteria->compare('user_bill_day', $this->user_bill_day);
        $criteria->compare('user_balance', $this->user_balance, true);
        $criteria->compare('user_limit', $this->user_limit, true);

        $sort               = new CSort();
        $sort->attributes   = [
            'facilityType'                  => [
                'asc'  => 'facility.facility_type ASC',
                'desc' => 'facility.facility_type DESC',
            ],
            'facilityName'                  => [
                'asc'  => 'facility.facility_name ASC',
                'desc' => 'facility.facility_name DESC',
            ],
            'facilityCountry'               => [
                'asc'  => 'country.country_name ASC',
                'desc' => 'country.country_name DESC',
            ],
            'facilityState'                 => [
                'asc'  => 'facility.facility_state ASC',
                'desc' => 'facility.facility_state DESC',
            ],
            'facilityZip'                   => [
                'asc'  => 'facility.facility_zip ASC',
                'desc' => 'facility.facility_zip DESC',
            ],
            'user_full_name_reverse'        => [
                'asc'  => 'CONCAT(t.user_last_name,", ",t.user_first_name) ASC',
                'desc' => 'CONCAT(t.user_last_name,", ",t.user_first_name) DESC',
            ],
            'user_inmate_full_name_reverse' => [
                'asc'  => 'CONCAT(t.user_inmate_last_name,", ",t.user_inmate_first_name) ASC',
                'desc' => 'CONCAT(t.user_inmate_last_name,", ",t.user_inmate_first_name) DESC',
            ],
            '*', // this adds all of the other columns as sortable
        ];
        $sort->defaultOrder = 'CONCAT(t.user_last_name,", ",t.user_first_name) ASC'; // 't.user_full_name_reverse ASC'

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
            'sort'     => $sort,
        ]);
    }

    /**
     * @param null $clientId
     * @param bool $updateThis
     *
     * @return bool|string
     */
    public function getSuggestedMrc($clientId = null, $updateThis = true)
    {
        if ($clientId === null) {
            if ($this->getIsNewRecord()) {
                throw new CDbException(Yii::t('yii', 'Cannot retrieve suggested MRC value because the record has not yet been saved.'));
            }
            $clientId = $this->user_id;
        } else {
            $updateThis = false;
        }

        if (empty($clientId)) {
            return false;
        }

        $sql = 'SELECT COALESCE(SUM(CASE WHEN up.in_trial > 0 THEN p.plan_trial_amount ELSE p.plan_mrc END),0) AS user_suggested_mrc
                FROM user_datas u
                INNER JOIN user_plans up ON up.user_id = u.user_id AND up.status > 0
                INNER JOIN plans p ON up.plan_id = p.plan_id AND p.plan_status = 1
                WHERE u.user_id = :clientId
                GROUP BY u.user_id';
        $value = @number_format(Yii::$app->db->createCommand($sql, [':clientId' => $clientId])->queryScalar(), 2, '.', '');
        if ($updateThis) {
            $this->user_suggested_mrc = $value;
        }

        return $value;
    }

    /**
     * TODO: Need to rewrite the method
     * @deprecated
     * @param bool $activeOnly
     *
     * @return mixed
     */
    public function getClientList($activeOnly = true)
    {
        if ($activeOnly) {
            $list = CHtml::listData($this->findAll([
                'select'    => 'user_id, CONCAT("#",user_id," ",user_last_name,", ",user_first_name) AS user_last_name',
                'order'     => 'user_last_name ASC',
                'condition' => 'user_status > 0',
            ]), 'user_id', 'user_last_name');
        } else {
            $list = CHtml::listData($this->findAll([
                'select' => 'user_id, CONCAT("#",user_id," ",user_last_name,", ",user_first_name) AS user_last_name',
                'order'  => 'user_last_name ASC',
            ]), 'user_id', 'user_last_name');
        }

        return $list;
    }

    /**
     * @return string
     */
    public function getClientFullName()
    {
        return $this->user_last_name . ', ' . $this->user_first_name;
    }

    /**
     * @return string
     */
    public function getInmateFullName()
    {
        return $this->user_inmate_last_name . ', ' . $this->user_inmate_first_name;
    }

    /**
     * @return null|string
     */
    public function getFacilityFullName()
    {
        if (!empty($this->facility)) {
            return $this->facility->facilityFullName;
        }

        return null;
    }

    /**
     * @return string
     */
    public function getBalanceHtml()
    {
        $color          = $this->user_balance < 0 ? 'red' : 'black';
        $currencySymbol = isset($this->currency->currency_sign) ? $this->currency->currency_sign : $this->getCurrencySymbol($this->user_currency);
        $content        = $currencySymbol . ' ' . $this->user_balance . ' ' . $this->user_currency;

        return Html::tag('span', Html::encode($content), ['style' => 'color:' . $color]);
    }

    /**
     * Render the facility type value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridFacilityType']
     *
     * @param Client $data
     *
     * @return string
     */
    public function gridServiceType($data)
    {
        return empty($data->service) ? 'Other' : Html::encode($data->service->service_name);
    }

    /**
     * Render the facility type value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridFacilityType']
     *
     * @param Client $data
     *
     * @return string|null
     */
    public function gridFacilityType($data)
    {
        return empty($data->facility) ? null : Html::encode($data->facility->facility_type);
    }

    /**
     * Render the facility name value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridFacilityName']
     *
     * @param Client $data
     *
     * @return string|null
     */
    public function gridFacilityName($data)
    {
        if (!empty($data->facility)) {
            return Html::encode($data->facility->facility_name);
        }

        return null;
    }

    /**
     * Render the facility name value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridFacilityName']
     *
     * @param Client $data
     *
     * @return string|null
     */
    public function gridFacilityCountry($data)
    {
        if (!empty($data->facility) && !empty($data->facility->country)) {
            $flag = Html::img(Yii::$app->request->baseUrl . '/img/country_flags/' . $data->facility->country->country_code_alpha_3 . '.png', $data->facility->country->country_name) . '&nbsp;';

            return $flag . Html::encode($data->facility->country->country_name);
        }

        return null;
    }

    /**
     * Render the facility name value for grid output.
     * This should be used in a grid using 'value' => array($model, 'gridFacilityName')
     *
     * @param Client $data
     *
     * @return string|null
     */
    public function gridFacilityState($data)
    {
        if (!empty($data->facility)) {
            $state = empty($data->facility->facility_state_2) ? $data->facility->facility_state : $data->facility->facility_state_2;

            return Html::encode($state);
        }

        return null;
    }

    /**
     * Render the facility name value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridFacilityName']
     *
     * @param Client $data
     *
     * @return string|null
     */
    public function gridFacilityZip($data)
    {
        if (!empty($data->facility)) {
            return Html::encode($data->facility->facility_zip);
        }

        return null;
    }

    /**
     * Render the balance value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridBalance']
     * This is so that the currency symbol doesn't get called multiple times
     *
     * @param Client $data
     *
     * @return string
     */
    public function gridBalance($data)
    {
        $color   = $data->user_balance < 0 ? 'red' : 'black';
        $content = $this->getCurrencySymbol($data->user_currency) . ' ' . $data->user_balance . ' ' . $data->user_currency;

        return Html::tag('span', Html::encode($content), ['style' => 'color:' . $color]);
    }

    /**
     * @deprecated
     * Render the balance value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridBalance']
     * This is so that the currency symbol doesn't get called multiple times
     *
     * @param Client $data
     *
     * @return string
     */
    public function gridCardSelect($data)
    {
        $cards           = (new Query())
            ->select(['billing_id', 'cc_type', 'cc_last_4', 'cc_exp_date', 'billing_priority', 'allow_auto_billing'])
            ->from(['user_billing_details'])
            ->where('user_id = :clientId AND billing_status = 1 AND flagged != 2', [':clientId' => $data->user_id])
            ->orderBy(['billing_priority' => SORT_ASC])
            ->all();
        $autoBillCount   = 0;
        $noAutoBillCount = 0;
        if (count($cards)) {
            $cardOptions = [
                'any'        => 'Any',
                'autoBill'   => 'Auto Bill Only',
                'noAutoBill' => 'No Auto Bill Only',
            ];
            foreach ($cards as $card) {
                if ($card['allow_auto_billing']) {
                    $autoBillCount++;
                } else {
                    $noAutoBillCount++;
                }
                $cardOptions[$card['billing_id']] = $card['cc_type'] . '-' . $card['cc_last_4'] . '(' . $card['cc_exp_date'] . ') ' . ($card['allow_auto_billing'] ? 'Auto' : 'No Auto');
            }
            $cardOptions['any']        .= ' (' . ($autoBillCount + $noAutoBillCount) . ')';
            $cardOptions['autoBill']   .= ' (' . $autoBillCount . ')';
            $cardOptions['noAutoBill'] .= ' (' . $noAutoBillCount . ')';
            $select                    = $this->defaultCardSelect;
        } else {
            $cardOptions = [
                '0' => '** No Cards Available **',
            ];
            $select      = '0';
        }

        return Html::dropDownList('Client[cardSelect][' . $data->user_id . ']', $select, $cardOptions, ['id' => 'cardSelect_' . $data->user_id, 'class' => 'cardSelect']);
    }

    /**
     * Render the limit value for grid output.
     * This should be used in a grid using 'value' => array($model, 'gridLimit')
     * This is so that the currency symbol doesn't get called multiple times
     *
     * @param Client $data
     *
     * @return string
     */
    public function gridLimit($data)
    {
        $color   = $data->user_limit < 0 ? 'red' : 'black';
        $content = $this->getCurrencySymbol($data->user_currency) . ' ' . $data->user_limit . ' ' . $data->user_currency;

        return Html::tag('span', Html::encode($content), ['style' => 'color:' . $color]);
    }

    /**
     * Render the facility type value for grid output.
     * This should be used in a grid using 'value' => array($model, 'gridPlans')
     *
     * @param Client $data
     *
     * @return string|null
     */
    public function gridPlans($data)
    {
        if (empty($data->clientPlans)) {
            return null;
        }

        $plans = [];
        foreach ($data->clientPlans as $clientPlan) {
            if (empty($clientPlan->plan) || empty($clientPlan->status)) {
                continue;
            }

            $plans[$clientPlan->plan_id] = $clientPlan->plan->plan_name;
        }

        return implode('<br />', $plans);
    }

    /**
     * Render the balance value for grid output.
     * This should be used in a grid using 'value' => array($model, 'gridRegNotes')
     * This is so that the currency symbol doesn't get called multiple times
     *
     * @param Client $data
     *
     * @return string
     */
    public function gridRegNotes($data)
    {
        $linkText = empty($data->user_reg_number) ? Html::tag('span', 'N/A', ['class' => 'text-error']) : $data->user_reg_number;

        return Html::a($linkText, 'javascript:void(0)', [
            'data-title'     => 'User Notes',
            'data-placement' => 'right',
            'data-trigger'   => 'hover',
            'data-content'   => nl2br($data->user_notes),
            'rel'            => 'popover',
        ]);
    }

    /**
     * @param int $attribute
     *
     * @return string
     */
    public function getValueColor($attribute)
    {
        if ($this->$attribute < 0) {
            return '#ff0000';
        } else {
            return '#000000';
        }
    }

    /**
     * @param null|string $status
     *
     * @return mixed
     */
    public function getStatusClassOptions($status = null)
    {
        if (empty($this->_statusClassOptions)) {
            $statusClassNames          = [
                self::STATUS_ACTIVE    => 'status_active',
                self::STATUS_BLOCKED   => 'status_blocked',
                self::STATUS_INACTIVE  => 'status_inactive',
                self::STATUS_PENDING   => 'status_pending',
                self::STATUS_CANCELLED => 'status_cancelled',
            ];
            $this->_statusClassOptions = self::getConstants('STATUS_', __CLASS__, $statusClassNames);
        }

        return $status !== null && isset($this->_statusClassOptions[$status]) ? $this->_statusClassOptions[$status] : $this->_statusClassOptions;
    }

    /**
     * @return string
     */
    public function getStatusText()
    {
        return $this->getStatusOptions($this->user_status);
    }

    /**
     * @param null|string $status
     *
     * @return array|string
     */
    public function getStatusOptions($status = null)
    {
        if (empty($this->_statusOptions)) {
            $statusNames          = [
                self::STATUS_ACTIVE    => 'Active',
                self::STATUS_BLOCKED   => 'Blocked',
                self::STATUS_INACTIVE  => 'Inactive',
                self::STATUS_PENDING   => 'Pending',
                self::STATUS_CANCELLED => 'Cancelled',
            ];
            $this->_statusOptions = self::getConstants('STATUS_', __CLASS__, $statusNames);
        }

        return $status !== null && isset($this->_statusOptions[$status]) ? $this->_statusOptions[$status] : $this->_statusOptions;
    }

    /**
     * @param null|string $value
     *
     * @return array|string
     */
    public function getBillingTypeOptions($value = null)
    {
        if (empty($this->_billingTypeOptions)) {
            $optionNames               = [
                self::BILLING_TYPE_PRE  => 'Pre-Paid',
                self::BILLING_TYPE_POST => 'Post-Paid',
            ];
            $this->_billingTypeOptions = self::getConstants('BILLING_TYPE_', __CLASS__, $optionNames);
        }

        return $value !== null && isset($this->_billingTypeOptions[$value]) ? $this->_billingTypeOptions[$value] : $this->_billingTypeOptions;
    }

    /**
     * from original Lionel code ....
     * <option value="x">Select one...</option>
     * <option value="C/C" <?php if($user_payment_method == 'C/C'){ echo 'selected="selected"'; } ?>>Credit Card</option>
     * <option value="M.O." <?php if($user_payment_method == 'M.O.'){ echo 'selected="selected"'; } ?>>Money Order</option>
     * <option value="PayPal" <?php if($user_payment_method == 'PayPal'){ echo 'selected="selected"'; } ?>>PayPal</option>
     * <option value="I.C." <?php if($user_payment_method == 'I.C.'){ echo 'selected="selected"'; } ?>>Institution Check</option>
     *
     * @param null|string $value
     *
     * @return array|string
     */
    public function getPaymentMethodOptions($value = null)
    {
        if (empty($this->_paymentMethodOptions)) {
            $optionNames                 = [
                self::PAYMENT_METHOD_NONE              => '**No Payment Method**',
                self::PAYMENT_METHOD_CREDIT_CARD       => 'Credit Card',
                self::PAYMENT_METHOD_MONEY_ORDER       => 'Money Order',
                self::PAYMENT_METHOD_PAYPAL            => 'PayPal',
                self::PAYMENT_METHOD_INSTITUTION_CHECK => 'Institution Check',
                self::PAYMENT_METHOD_MONEYGRAM         => 'MoneyGram',
            ];
            $this->_paymentMethodOptions = self::getConstants('PAYMENT_METHOD_', __CLASS__, $optionNames);
        }

        return $value !== null ? (isset($this->_paymentMethodOptions[$value]) ? $this->_paymentMethodOptions[$value] : '**No Payment Method**') : $this->_paymentMethodOptions;
    }

    /**
     * @param null|string $value
     *
     * @return array|string
     */
    public function getRecurringOptions($value = null)
    {
        if (empty($this->_recurringOptions)) {
            $optionNames             = [
                self::RECURRING_YES => 'Yes',
                self::RECURRING_NO  => 'No',
            ];
            $this->_recurringOptions = self::getConstants('RECURRING_', __CLASS__, $optionNames);
        }

        return $value !== null && isset($this->_recurringOptions[$value]) ? $this->_recurringOptions[$value] : $this->_recurringOptions;
    }

    /**
     * TODO: Need to rewrite the method
     * @deprecated
     * @return mixed
     */
    public function getAssociatedWith()
    {
        // TODO: just getting one record here, correct?
        return ClientAssociation::model()->fetchAssociatedWith($this->user_id);
    }

    /**
     * TODO: Need to rewrite the method
     * @deprecated
     * @param bool $asLink
     *
     * @return mixed
     */
    public function getReferredBy($asLink = true)
    {
        // TODO: just getting one record here, correct?
        return ClientReferral::model()->fetchReferredBy($this->user_id, $asLink);
    }

    /**
     * TODO: Need to rewrite the method
     * @deprecated
     * @param bool $asLink
     *
     * @return mixed
     */
    public function getOnetimeReferredBy($asLink = true)
    {
        return ClientOnetimeReferral::model()->fetchReferredBy($this->user_id, $asLink);
    }

    /**
     * Checks whether Alert Minutes (for Domectic Plans) should be fired or not
     *
     * @return bool
     */
    public function isAlertMinutes()
    {
        if ($this->balance_alert && count($this->clientPlans) > 0) {
            foreach ($this->clientPlans as $clientPlan) {
                if ($clientPlan->plan->plan_alert_type == Plan::ALERT_TYPE_MINUTES && $clientPlan->plan->plan_status == Plan::STATUS_ACTIVE && $clientPlan->allowance < $this->alert_minutes) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks whether Alert Amount (for International Plans) should be fired or not
     *
     * @return bool
     */
    public function isAlertAmount()
    {
        if ($this->balance_alert && count($this->clientPlans) > 0 && $this->user_balance < $this->alert_amount) {
            foreach ($this->clientPlans as $clientPlan) {
                if ($clientPlan->plan->plan_alert_type == Plan::ALERT_TYPE_BALANCE && $clientPlan->plan->plan_status == Plan::STATUS_ACTIVE) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @deprecated
     * @inheritdoc
     */
    public function beforeSave()
    {
        // create token and datetime for new records
        if ($this->isNewRecord) {
            $this->token         = Globals::uniqueMd5();
            $this->user_datetime = date('Y-m-d H:i:s');
        } else {
            // update suggested mrc
            $this->suggestedMrc;
        }

        // update user_admin
        $this->user_admin = Globals::user()->id;

        // update datetime
        $this->user_datetime_last_update = date('Y-m-d H:i:s');

        // trim names
        $this->user_first_name        = trim($this->user_first_name);
        $this->user_last_name         = trim($this->user_last_name);
        $this->user_inmate_first_name = trim($this->user_inmate_first_name);
        $this->user_inmate_last_name  = trim($this->user_inmate_last_name);
        $this->user_reg_number        = trim($this->user_reg_number);

        // update full name columns for clients
        if (!empty($this->user_first_name) || !empty($this->user_last_name)) {
            $this->user_full_name         = $this->user_first_name . ' ' . $this->user_last_name;
            $this->user_full_name_reverse = $this->user_last_name . ' ' . $this->user_first_name;
        }
        // update full name columns for inmates
        if (!empty($this->user_inmate_first_name) || !empty($this->user_inmate_last_name)) {
            $this->user_inmate_full_name         = $this->user_inmate_first_name . ' ' . $this->user_inmate_last_name;
            $this->user_inmate_full_name_reverse = $this->user_inmate_last_name . ' ' . $this->user_inmate_first_name;
        }

        /**
         * @var Facility $facility
         */
        if ($this->user_facility) {
            $facility = Facility::findOne($this->user_facility);
            if ($facility !== null) {
                $this->user_facility_country_id = $facility->facility_country;
                $this->user_facility_state      = $facility->facility_state;
            }
        }

        return parent::beforeSave();
    }

    /**
     * TODO: Need to rewrite the method
     * @deprecated
     * @inheritdoc
     */
    public function afterSave()
    {
        parent::afterSave();

        if (!$this->user_id) {
            return false;
        }

        if ($this->isAttributeDirty('user_status')) {
            // add log entry
            // original code ... INSERT INTO user_logs (user_id, user_status, log_datetime, log_by) VALUES ('$user_id','$user_status','$today','$admin_id')
            $log               = new ClientLog();
            $log->user_id      = $this->user_id;
            $log->user_status  = $this->user_status;
            $log->log_datetime = date('Y-m-d H:i:s');
            $log->log_by       = Globals::user()->id;
            $log->save();
        }

        // The following should only be done if we have had a change of status
        if ($this->isAttributeDirty('user_status')) {
            // update client did's
            // original UPDATE user_dids SET datetime_cancel='$datetime_cancel', datetime_last_update='$today',
            // status='$user_status', admin_id_last_update='$admin_id', asterisk='0' WHERE user_id='$user_id' AND status!='0'
            $datetime_cancel = ($this->user_status == self::STATUS_CANCELLED) ? date('Y-m-d H:i:s') : '0000-00-00 00:00:00';
            ClientDid::updateAll([
                'datetime_cancel'      => $datetime_cancel,
                'datetime_last_update' => date('Y-m-d H:i:s'),
                'status'               => $this->user_status,
                'admin_id_last_update' => user()->id,
                'asterisk'             => 0,
            ], 'user_id = :user_id AND status!=0', [':user_id' => $this->user_id]);

            // update client sms dids
            ClientSmsDid::updateAll([
                'deleted_at' => (($this->user_status == self::STATUS_CANCELLED) ? date('Y-m-d H:i:s') : null),
                'deleted_by' => (($this->user_status == self::STATUS_CANCELLED) ? user()->id : null),
                'status'     => (($this->user_status == self::STATUS_CANCELLED) ? 0 : 1), // only using active or cancelled
            ], 'user_id = :user_id AND status!=0', [':user_id' => $this->user_id]);

            // update did's if status = cancelled. This will make all associated DID's go on hold.
            // original code ... UPDATE dids SET did_in_use='0', did_available='1', did_user_id='0' WHERE did_user_id='$user_id'
            if ($this->user_status == self::STATUS_CANCELLED) {
                Did::updateAll([
                    'did_in_use'          => 0,
                    'did_available'       => 1,
                    'did_user_id'         => 0,
                    'did_datetime_cancel' => $datetime_cancel,
                    'admin_id_cancel'     => user()->id,
                ], 'did_user_id = :user_id', [':user_id' => $this->user_id]);
            }
        }

        // check if we need to update the user phone table
        if ($this->isAttributeDirty('user_phone')) {
            $this->updateUserPhone();
        }

        ClientDid::blockDid($this->user_id);

        return true;
    }

    /**
     * @deprecated
     */
    public function updateUserPhone()
    {
        // select all users and phone numbers
        $values  = [];
        $sql     = 'SELECT u.user_id,u.user_phone_country_id,u.user_phone,c.country_phone_code
                FROM user_datas u
                LEFT JOIN country_codes c ON c.country_id = u.user_phone_country_id
                WHERE u.user_phone != \'\' AND u.user_id = :clientId';
        $userRow = Yii::$app->db->createCommand($sql, [':clientId' => $this->user_id])->queryOne();

        self::getClientPhoneValues($userRow, $values);

        if (count($values)) {
            // delete the previous phone numbers for this client then insert new
            Yii::$app->db->createCommand()->delete('user_phone', 'user_id = :clientId', [':clientId' => $this->user_id]);
            Yii::$app->db->createCommand()->insertIgnore('user_phone', $values); // TODO: Couldn't find an analogy insertIgnore()
        }
    }

    /**
     * Used by user_phone functions to create rows for inserting into user_phone
     *
     * @param array $userRow
     * @param array &$values
     *
     * @return array
     */
    public static function getClientPhoneValues($userRow, &$values)
    {
        // see if we are dealing wih multiple phone numbers
        if (strstr($userRow['user_phone'], ',')) {
            $numbers = explode(',', $userRow['user_phone']);
        } elseif (strstr($userRow['user_phone'], '/')) {
            $numbers = explode('/', $userRow['user_phone']);
        } elseif (strstr($userRow['user_phone'], 'or')) {
            $numbers = explode('or', $userRow['user_phone']);
        } else {
            $numbers = [$userRow['user_phone']];
        }
        foreach ($numbers as $number) {
            $ext = null;
            // check for extension
            if (strstr($number, 'ext')) {
                $tmp    = explode('ext', $number);
                $number = $tmp[0];
                $ext    = $tmp[1];
            }
            // sort out number
            $number = Globals::numbersOnly($number);
            if ($userRow['country_phone_code'] == 1) {
                $number = ltrim($number, '1');
            }

            // ignore empty numbers
            if (empty($number)) {
                continue;
            }

            $value    = [
                'user_id'               => $userRow['user_id'],
                'user_phone_country_id' => $userRow['user_phone_country_id'],
                'user_phone'            => $number,
                'user_phone_ext'        => $ext,
            ];
            $values[] = $value;
        }

        return $values;
    }

    /**
     * Proceed sending message (email/sms to client) by template alias
     *
     * @param $templateAlias
     *
     * @return bool
     */
    public function proceedServiceMessageToClientByAlias($templateAlias)
    {
        if (!empty($templateAlias)) {
            /** @var MsgTemplate $template */
            $template = MsgTemplate::find()->where(['alias' => $templateAlias])->all();
            if (!empty($template)) {
                return $this->proceedServiceMessageToClient($template->id);
            }
        }

        return false;
    }

    /**
     * Proceed sending message (email/sms to client) by template id
     *
     * @param $templateID
     *
     * @return bool
     */
    public function proceedServiceMessageToClient($templateID)
    {
        // Creating new message
        $message          = new MsgSent();
        $message->user_id = $this->user_id;
        // Setting proper message template ID (create IT in admin-panel/messaging/templates)
        $message->msg_template_id = $templateID;
        // Setting correct email properties
        $message->to_email   = $this->user_email;
        $message->from_email = MsgFrom::getCorrectFromMail($this->user_id);
        // Setting correct sms properties
        $message->to_number   = Util::cleanPhoneNumber($this->user_phone, $this->user_phone_country_id);
        $message->from_number = MsgFrom::getCorrectFromNumber($this->user_id);
        // Fetching another template data
        if (!$message->fetchTemplateData($this->user_id)) {
            return false;
        }
        // Setting send type
        if ($this->alert_by_email && !empty($message->email_html)) {
            $message->send_type |= MsgSent::SEND_TYPE_EMAIL;
        }
        if ($this->alert_by_sms && !empty($message->sms)) {
            $message->send_type |= MsgSent::SEND_TYPE_SMS;
        }
        if ($this->alert_by_corrlink && !empty($message->corrlinks_support_text)) {
            $message->send_type |= MsgSent::SEND_TYPE_CORRLINKS_SUPPORT;
        }
        // Sending the message
        if (!$message->send(true, false)) {
            return false;
        }

        return true;
    }

    /**
     * Proceed sending corrlinks message (to Inmate) by template alias
     *
     * @param $templateAlias
     * @param string $subj
     * @param string $text
     *
     * @return bool|int
     */
    public function proceedServiceMsgToInmateByAlias($templateAlias, $subj = '', $text = '')
    {
        if (!empty($templateAlias)) {
            /** @var MsgTemplate $template */
            $template = MsgTemplate::find()->where(['alias' => $templateAlias])->all();
            if (!empty($template)) {
                return $this->proceedServiceMsgToInmate($template->id, $subj, $text);
            }
        }

        return false;
    }

    /**
     * Proceed sending corrlinks message (to Inmate) by template id
     *
     * @param int $templateId
     * @param string $subj
     * @param string $text
     *
     * @return bool
     */
    public function proceedServiceMsgToInmate($templateId, $subj = '', $text = '')
    {
        /**
         * @var MsgTemplate $template
         */
        $cAccountId = isset($this->corrlinks_support_account_id) ? $this->corrlinks_support_account_id : '';
        $cContactId = isset($this->corrlinks_support_contact_id) ? $this->corrlinks_support_contact_id : '';
        $subjTmp    = '';
        $textTmp    = '';
        if ($templateId) {
            $template = MsgTemplate::findOne($templateId);
            if (!empty($template)) {
                $subjTmp = $template->subject;
                $textTmp = $template->corrlinks_support;
            }
        }

        $subject = !empty($subj) ? $subj : $subjTmp;
        $message = !empty($text) ? $text : $textTmp;
        if (!empty($cAccountId) && !empty($cContactId) && !empty($message)) {
            $sql = 'INSERT INTO corrlinks_in (account_id, contact_id, subject, message, status) VALUES (' . $cAccountId . ',' . $cContactId . ',\'' . $subject . '\',\'' . $message . '\', 0);';
            if (Yii::$app->db->createCommand($sql)->execute()) {
                return true;
            };
        }

        return false;
    }

    /**
     * Proceed sending corrlinks message (to Inmate)
     *
     * @param int $templateId
     * @param string $subj
     * @param string $text
     *
     * @return bool
     */
    public function proceedMsgToInmate($templateId, $subj = '', $text = '')
    {
        /**
         * @var MsgTemplate $template
         */
        $cAccountId = isset($this->corrlinks_account_id) ? $this->corrlinks_account_id : '';
        $cContactId = isset($this->corrlinks_contact_id) ? $this->corrlinks_contact_id : '';
        $subjTmp    = '';
        $textTmp    = '';
        if ($templateId) {
            $template = MsgTemplate::findOne($templateId);
            if (!empty($template)) {
                $subjTmp = $template->subject;
                $textTmp = $template->corrlinks;
            }
        }

        $subject = !empty($subj) ? $subj : $subjTmp;
        $message = !empty($text) ? $text : $textTmp;
        if (!empty($cAccountId) && !empty($cContactId) && !empty($message)) {
            $sql = 'INSERT INTO corrlinks_in (account_id, contact_id, subject, message, status) VALUES (' . $cAccountId . ',' . $cContactId . ',\'' . $subject . '\',\'' . $message . '\', 0);';
            if (Yii::$app->db->createCommand($sql)->execute()) {
                return true;
            };
        }

        return false;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFacility()
    {
        return $this->hasOne(Facility::className(), ['user_facility' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getService()
    {
        return $this->hasOne(Service::className(), ['user_service' => 'id']);
    }

    /**
     * @return $this
     */
    public function getCurrency()
    {
        return $this->hasOne(Currency::className(), ['user_currency' => 'id'])->onCondition([$this->tableAlias . '.user_currency' => 'currency.currency_prefix']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCorrlinksAccount()
    {
        return $this->hasOne(CorrlinksAccount::className(), ['corrlinks_account_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCorrlinksContact()
    {
        return $this->hasOne(CorrlinksContact::className(), ['corrlinks_contact_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCorrlinksSupportAccount()
    {
        return $this->hasOne(CorrlinksAccount::className(), ['corrlinks_support_account_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCorrlinksSupportContact()
    {
        return $this->hasOne(CorrlinksContact::className(), ['corrlinks_support_contact_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getClientPlans()
    {
        return $this->hasMany(ClientPlan::className(), ['user_id' => 'id']);
    }
}
