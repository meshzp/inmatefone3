<?php

namespace backend\models;

use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "jail_by_rc".
 *
 * The followings are the available columns in table 'jail_by_rc':
 * @property string $primary_key
 * @property integer $rate_center_id
 * @property integer $jail_id
 * @property string $datetime
 * @property string $cancel_datetime
 * @property integer $active
 * @property integer $admin_id
 *
 * @property Facility $facility
 * @property RateCenter $rateCenter
 */
class FacilityRateCenter extends ActiveRecord
{

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'jail_by_rc';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['rate_center_id, jail_id', 'required'],
            ['rate_center_id, jail_id, active, admin_id', 'numerical', 'integerOnly' => true],
            ['datetime, cancel_datetime', 'safe'],
            ['primary_key, rate_center_id, jail_id, datetime, cancel_datetime, active, admin_id', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'primary_key'     => 'Primary Key',
            'rate_center_id'  => 'Rate Center',
            'jail_id'         => 'Jail',
            'datetime'        => 'Datetime',
            'cancel_datetime' => 'Cancel Datetime',
            'active'          => 'Active',
            'admin_id'        => 'Admin',
        ];
    }

    public function beforeSave()
    {
        // date and admin info
        if ($this->isNewRecord) {
            $this->datetime = date('Y-m-d H:i:s');
            $this->admin_id = Yii::$app->getUser()->getId();
            $this->active   = 1;
        }
        // is the correct way it should work?
        if (isset($this->active) && ($this->active === '0' || $this->active === 0)) {
            $this->cancel_datetime = date('Y-m-d H:i:s');
        } elseif (isset($this->active) && ($this->active === '1' || $this->active === 1)) {
            $this->cancel_datetime = 0;
        }

        return parent::beforeSave();
    }

    /**
     * @return ActiveQuery
     */
    public function getFacility()
    {
        return $this->hasOne(Facility::className(), ['facility_id' => 'jail_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getRateCenter()
    {
        return $this->hasOne(RateCenter::className(), ['facility_id' => 'rate_center_id']);
    }

    /**
     * @return ActiveDataProvider
     */
    public function search()
    {

        $query = self::find();

        $query->with('facility', 'rateCenter');

        $query->andFilterWhere(['=', 'primary_key', $this->primary_key]);
        $query->andFilterWhere(['=', 'rate_center_id', $this->rate_center_id]);
        $query->andFilterWhere(['=', 'jail_id', $this->jail_id]);
        $query->andFilterWhere(['like', 'datetime', $this->datetime, true]);
        $query->andFilterWhere(['like', 'cancel_datetime', $this->cancel_datetime, true]);
        $query->andFilterWhere(['=', 'active', $this->active]);
        $query->andFilterWhere(['=', 'admin_id', $this->admin_id]);

        return new ActiveDataProvider($this, [
            'query' => $query,
        ]);
    }

}