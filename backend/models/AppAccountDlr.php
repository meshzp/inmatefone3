<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "app_account_registration_dlr".
 *
 * The followings are the available columns in table 'app_account_dlr':
 * @property string $id
 * @property string $app_account_id
 * @property string $app_account_registration_id
 * @property integer $sms_provider_id
 * @property string $sent_at
 * @property string $type
 * @property string $smsc_reply
 * @property string $created_at
 *
 * The followings are the available model relations:
 * @property SmsProvider $smsProvider
 * @property AppAccount $appAccount
 * @property AppAccountRegistration $appAccountRegistration
 */
class AppAccountDlr extends ActiveRecord
{
    /**
     * @var array
     */
    public static $types = [
        1  => 'Delivered to phone',
        2  => 'Non-delivered to Phone',
        4  => 'Queued on SMSC',
        8  => 'Delivered to SMSC',
        16 => 'Non-delivered to SMSC',
    ];

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%app_account_dlr}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['app_account_id', 'created_at'], 'required'],
            [['sms_provider_id'], 'integer'],
            [['app_account_id', 'app_account_registration_id'], 'max' => 11],
            [['type, smsc_reply'], 'max' => 255],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['id, app_account_id', 'app_account_registration_id', 'sms_provider_id', 'sent_at', 'type', 'smsc_reply', 'created_at'], 'safe'],
        ];
    }

    /**
     * @return array customized attribute labels (name => label)
     */
    public function attributeLabels()
    {
        return [
            'id'                          => 'ID',
            'app_account_id'              => 'App Account',
            'app_account_registration_id' => 'App Account Registration',
            'sms_provider_id'             => 'Provider',
            'sent_at'                     => 'Sent At',
            'type'                        => 'Type',
            'smsc_reply'                  => 'Smsc Reply',
            'created_at'                  => 'Created At',
        ];
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @param $params
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
            'id'                          => $this->id,
            'app_account_id'              => $this->app_account_id,
            'app_account_registration_id' => $this->app_account_registration_id,
            'sms_provider_id'             => $this->sms_provider_id,
            'sent_at'                     => $this->sent_at,
            'type'                        => $this->type,
            'smsc_reply'                  => $this->smsc_reply,
            'created_at'                  => $this->created_at,
        ]);

        return $dataProvider;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSmsProvider()
    {
        return $this->hasOne(SmsProvider::className(), ['sms_provider_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAppAccount()
    {
        return $this->hasOne(AppAccount::className(), ['app_account_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAppAccountRegistration()
    {
        return $this->hasOne(AppAccountRegistration::className(), ['app_account_registration_id' => 'id']);
    }
}
