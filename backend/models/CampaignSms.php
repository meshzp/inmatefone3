<?php

namespace backend\models;

use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "campaign_sms".
 *
 * The followings are the available columns in table 'campaign_sms':
 * @property string $id
 * @property string $campaign_id
 * @property string $user_id
 * @property integer $contact_type
 * @property string $to_number
 * @property string $to_e164
 * @property string $region_code
 * @property integer $number_type
 * @property integer $status
 * @property string $created_at
 * @property string $sent_at
 *
 * The followings are the available model relations:
 * @property User $user
 * @property Campaign $campaign
 */
class CampaignSms extends ActiveRecord
{

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%campaign_sms}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['campaign_id', 'to_number', 'created_at'], 'required'],
            [['contact_type', 'number_type', 'status'], 'integer'],
            [['campaign_id'], 'max' => 11],
            [['user_id'], 'max' => 20],
            [['to_number', 'to_e164'], 'max' => 32],
            [['region_code'], 'max' => 3],
            [['sent_at'], 'safe'],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['id', 'campaign_id', 'user_id', 'contact_type', 'to_number', 'to_e164', 'region_code', 'number_type', 'status', 'created_at', 'sent_at'], 'safe'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id'           => 'ID',
            'campaign_id'  => 'Campaign',
            'user_id'      => 'User',
            'contact_type' => 'Contact Type',
            'to_number'    => 'To Number',
            'to_e164'      => 'To e164',
            'region_code'  => 'Region Code',
            'number_type'  => 'Number Type',
            'status'       => 'Status',
            'created_at'   => 'Created At',
            'sent_at'      => 'Sent At',
        ];
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @paramn array $params
     *
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search($params)
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $query = self::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id'           => $this->id,
            'campaign_id'  => $this->campaign_id,
            'user_id'      => $this->user_id,
            'contact_type' => $this->contact_type,
            'to_number'    => $this->to_number,
            'to_e164'      => $this->to_number,
            'region_code'  => $this->region_code,
            'number_type'  => $this->number_type,
            'status'       => $this->status,
            'created_at'   => $this->created_at,
            'sent_at'      => $this->sent_at,
        ]);

        return $dataProvider;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(UserDatas::className(), ['user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCampaign()
    {
        return $this->hasOne(Campaign::className(), ['campaign_id' => 'id']);
    }
}
