<?php

namespace backend\models;

use backend\helpers\Globals;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\behaviors\BlameableBehavior;
use yii\helpers\Url;

/**
 * This is the model class for table "users_referred".
 *
 * The followings are the available columns in table 'users_referred':
 * @property string $reference_id
 * @property integer $referrer_user_id
 * @property integer $referred_user_id
 * @property string $reference_set_datetime
 * @property string $reference_cancel_datetime
 * @property string $reference_admin_id
 * @property string $reference_cancel_admin_id
 * @property string $reference_active
 */
class ClientReferral extends ActiveRecord // TODO: Inherited from \protected\components\ActiveRecord.php
{

    /**
     * @var string
     */
    public $referrer_name;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%users_referred}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['referrer_user_id', 'referred_user_id'], 'required'],
            [['referrer_user_id', 'referred_user_id'], 'numerical'],
            [['reference_admin_id', 'reference_cancel_admin_id'], 'max' => 255],
            [['reference_active'], 'max' => 1],
            [['reference_set_datetime', 'reference_cancel_datetime'], 'safe'],
            [['user_id'], 'filters', 'filters' => 'checkReferral', 'on' => ['insert']],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['reference_id', 'referrer_name', 'referrer_user_id', 'referred_user_id', 'reference_set_datetime', 'reference_cancel_datetime', 'reference_admin_id', 'reference_cancel_admin_id', 'reference_active'], 'safe', 'on' => ['search']],
        ];
    }

    /**
     * @return array customized attribute labels (name => label)
     */
    public function attributeLabels()
    {
        return [
            'reference_id'              => 'RID',
            'referrer_user_id'          => 'CID',
            'referred_user_id'          => 'Client ID',
            'reference_set_datetime'    => 'Datetime',
            'reference_cancel_datetime' => 'Cancel Datetime',
            'reference_admin_id'        => 'Admin',
            'reference_cancel_admin_id' => 'Cancel Admin',
            'reference_active'          => 'Active',
        ];
    }

    /**
     * @deprecated
     * @return array
     */
    public function scopes()
    {
        return [
            'enabled'    => [
                'condition' => 'reference_active=1',
            ],
            'referredBy' => [
                'select'    => 'reference_id, referred_user_id',
                'condition' => 'reference_active!=0',
                'with'      => [
                    'referred' => [
                        'select' => 'user_first_name, user_last_name, user_inmate_first_name, user_inmate_last_name',
                    ],
                ],
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
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria = new CDbCriteria;

        $criteria->with = ['referrer'];

        $criteria->compare('reference_id', $this->reference_id);
        $criteria->compare('referrer_user_id', $this->referrer_user_id);
        if (!empty($this->referrer_name)) {
            $criteria->addcondition("(referrer.user_full_name LIKE '%" . $this->referrer_name . "%' OR referrer.user_inmate_full_name LIKE '%" . $this->referrer_name . "%')");
        }

        $criteria->compare('referred_user_id', $this->referred_user_id);
        $criteria->compare('reference_set_datetime', $this->reference_set_datetime, true);
        $criteria->compare('reference_active', $this->reference_active);

        return new CActiveDataProvider($this, [
            'criteria'   => $criteria,
            'sort'       => [
                'defaultOrder' => 'referrer.user_last_name',   // reference_set_datetime DESC
                'attributes'   => [
                    'referrer_name' => [
                        'asc'  => 'referrer.user_last_name ASC',
                        'desc' => 'referrer.user_last_name DESC',
                    ],
                    '*', // this adds all of the other columns as sortable
                ],

            ],
            'pagination' => false,
        ]);
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * This is used on consumer sites
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function searchClientView()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria = new CDbCriteria;

        $criteria->with = [
            'referrer' => [
                'with' => 'facility',
            ],
        ];

        $criteria->compare('reference_id', $this->reference_id);
        $criteria->compare('referrer_user_id', $this->referrer_user_id);
        $criteria->compare('referred_user_id', $this->referred_user_id);
        $criteria->compare('reference_active', $this->reference_active);

        return new CActiveDataProvider($this, [
            'criteria'   => $criteria,
            'pagination' => false,
            'sort'       => [
                'defaultOrder' => 'reference_set_datetime DESC',
                'attributes'   => [
                    'referrer.user_status'    => [
                        'asc'  => 'referrer.user_status ASC',
                        'desc' => 'referrer.user_status DESC',
                    ],
                    'referrer.user_last_name' => [
                        'asc'  => 'referrer.user_last_name ASC',
                        'desc' => 'referrer.user_last_name DESC',
                    ],
                    'referrer.user_facility'  => [
                        'asc'  => 'referrer.user_facility ASC',
                        'desc' => 'referrer.user_facility DESC',
                    ],
                    '*', // this adds all of the other columns as sortable
                ],
            ],
        ]);
    }

    /**
     * @deprecated
     *
     * @param $clientId
     * @param bool $asLink
     *
     * @return null
     */
    public function fetchReferredBy($clientId, $asLink = true)
    {
        $model = $this->referredBy()->find('referrer_user_id=:clientId', [':clientId' => $clientId]);
        if (empty($model) || empty($model->referred)) {
            return null;
        }

        if ($asLink) {
            $text = $model->referred->user_last_name . ', ' . $model->referred->user_first_name . ' / ' . $model->referred->user_inmate_last_name . ', ' . $model->referred->user_inmate_first_name;
            $url  = Url::to(['/client/update', ['id' => $model->referred_user_id]]);

            return Html::a($text, $url);
        } else {
            return $model;
        }
    }

    /**
     * Render the name value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridName']
     *
     * @param object $data
     *
     * @return string
     */
    public function gridName($data)
    {
        if (empty($data->referrer)) {
            return '** Warning: Client #' . $data->referrer_user_id . ' Not Found **';
        }
        $text = $data->referrer->user_last_name . ', ' . $data->referrer->user_first_name . ' / ' . $data->referrer->user_inmate_last_name . ', ' . $data->referrer->user_inmate_first_name;
        $url  = Url::to(['/client/update', ['id' => $data->referrer_user_id]]);

        return Html::a($text, $url, ['target' => '_blank']);
    }

    /**
     * Render the name value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridClientViewName']
     *
     * @param object $data
     *
     * @return string
     */
    public function gridClientViewName($data)
    {
        if (empty($data->referrer)) {
            return '** Client Not Found (#' . $data->referrer_user_id . ') **';
        }

        return $data->referrer->user_last_name . ', ' . $data->referrer->user_first_name . ' / ' . $data->referrer->user_inmate_last_name . ', ' . $data->referrer->user_inmate_first_name . ' (#' . $data->referrer_user_id . ')';
    }

    /**
     * Render the name value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridClientViewStatus']
     *
     * @param object $data
     *
     * @return string
     */
    public function gridClientViewStatus($data)
    {
        if (empty($data->referrer)) {
            return '** Client Not Found (#' . $data->referrer_user_id . ') **';
        }

        return $data->referrer->getStatusOptions($data->referrer->user_status);
    }

    /**
     * Render the name value for grid output.
     * This should be used in a grid using 'value' => [$model, 'gridClientViewFacility']
     *
     * @param object $data
     *
     * @return string
     */
    public function gridClientViewFacility($data)
    {
        if (empty($data->referrer)) {
            return '** Client Not Found (#' . $data->referrer_user_id . ') **';
        }
        if (empty($data->referrer->facility)) {
            return '** Facility Not Found (#' . $data->referrer->user_facility . ') **';
        }

        return $data->referrer->facility->facility_name;
    }

    /**
     * @deprecated
     * Custom validation for creating the association
     * in lionels code, referred_user_id = $referral and referrer_user_id = $user_id
     *
     * @param string $attribute
     */
    public function checkReferral($attribute)
    {
        $this->referred_user_id = numbersOnly($this->referred_user_id);
        if ($this->referred_user_id == $this->referrer_user_id) {
            $this->addError($attribute, 'You cannot add a referral to the same client');
        } else {
            $exists = $this->count('(referred_user_id=:referrerUserId AND referred_user_id=:referredUserId AND reference_active!=0)
                                    OR (referred_user_id=:referredUserId AND referrer_user_id=:referrerUserId AND reference_active!=0)
                                    OR (referred_user_id=:referrerUserId AND reference_active!=0)', [':referrerUserId' => $this->referrer_user_id, ':referredUserId' => $this->referred_user_id]);
            if ($exists) {
                $this->addError($attribute, "Referrals with this client ({$this->referred_user_id}) already exist");
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class'              => TimestampBehavior::className(),
                'createdAtAttribute' => 'reference_set_datetime',
                'updatedAtAttribute' => false,
                'value'              => date('Y-m-d H:i:s'),
            ],
            [
                'class'              => BlameableBehavior::className(),
                'createdByAttribute' => 'reference_admin_id',
                'updatedByAttribute' => false,
                'value'              => Globals::user()->id,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeSave()
    {
        if ($this->isNewRecord) {
            $this->reference_active = 1;
        }

        return parent::beforeSave();
    }

    /**
     * @deprecated
     *
     * @param bool $permanently
     *
     * @return bool|false|int
     */
    public function delete($permanently = false)
    {
        if ($permanently) {
            return parent::delete();
        }
        if (!$this->getIsNewRecord()) {
            Yii::trace(get_class($this) . '.delete()', 'system.db.ar.CActiveRecord');
            if ($this->beforeDelete()) {
                $this->reference_active          = 0;
                $this->reference_cancel_datetime = date('Y-m-d H:i:s');
                $this->reference_cancel_admin_id = Globals::user()->id;
                $result                          = $this->save();
                $this->afterDelete();

                return $result;
            } else {
                return false;
            }
        } else {
            throw new CDbException(Yii::t('yii', 'The active record cannot be deleted because it is new.'));
        }
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReferrer()
    {
        return $this->hasOne(Client::className(), ['referrer_user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getReferred()
    {
        return $this->hasOne(Client::className(), ['referred_user_id' => 'id']);
    }
}
