<?php

namespace backend\models;

use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "app_account_settings".
 *
 * The followings are the available columns in table 'app_account_settings':
 * @property string $id
 * @property string $app_account_id
 * @property string $user_id
 * @property integer $voice_status
 * @property integer $text_status
 * @property integer $status
 *
 * The followings are the available model relations:
 * @property AppAccount $appAccount
 */
class AppAccountSettings extends ActiveRecord
{
    const STATUS_INACTIVE = 0;  // use global default
    const STATUS_ACTIVE   = 1;

    const TEXT_STATUS_DEFAULT           = -1;
    const TEXT_STATUS_NO                = 0;
    const TEXT_STATUS_YES_WITH_FALLBACK = 1; // reserved for use in the future
    const TEXT_STATUS_YES               = 2;

    const VOICE_STATUS_DEFAULT           = -1;
    const VOICE_STATUS_NO                = 0;
    const VOICE_STATUS_YES_WITH_FALLBACK = 1;
    const VOICE_STATUS_YES               = 2;

    public static $statuses = [
        self::STATUS_INACTIVE => 'Inactive (Use Global Default)',
        self::STATUS_ACTIVE   => 'Active',
    ];

    // note: currently no functionality to allow SMS fallback but it's reserved for future use if it becomes available
    public static $textStatuses = [
        self::TEXT_STATUS_DEFAULT => 'Default (Only Use App)',
        self::TEXT_STATUS_NO      => 'No (Standard SMS Only)',
        //self::TEXT_STATUS_YES_WITH_FALLBACK => 'Yes, With Fallback To Standard SMS On Failure',
        self::TEXT_STATUS_YES     => 'Yes, Only Use App',
    ];

    public static $voiceStatuses = [
        self::VOICE_STATUS_DEFAULT           => 'Default (Only Use App)',
        self::VOICE_STATUS_NO                => 'No (Standard Call Only)',
        self::VOICE_STATUS_YES_WITH_FALLBACK => 'Yes, With Fallback To Standard Call On Failure',
        self::VOICE_STATUS_YES               => 'Yes, Only Use App',
    ];

    /**
     * @inheritdoc
     */
    public function beforeSave()
    {
        if (parent::beforeSave()) {
            // make sure statuses are null if less than 0
            // this is due to having to use -1 for null value in x-editable
            if ($this->text_status < 0) {
                $this->text_status = null;
            }

            if ($this->voice_status < 0) {
                $this->voice_status = null;
            }

            return true;
        }

        return false;
    }

    /**
     * Are the settings being overridden by a particular user?
     * @return boolean
     */
    public function isOverridenByUser()
    {
        return $this->user_id !== null;
    }

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%app_account_settings}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['app_account_id'], 'required'],
            [['voice_status', 'text_status', 'status'], 'integer'],
            [['app_account_id'], 'max' => 11],
            [['user_id'], 'max' => 20],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['id', 'app_account_id', 'user_id', 'voice_status', 'text_status', 'status'], 'safe'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id'             => 'ID',
            'app_account_id' => 'App Account',
            'user_id'        => 'User',
            'voice_status'   => 'Voice Status',
            'text_status'    => 'Text Status',
            'status'         => 'Status',
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
            'id' => $this->id,
            'app_account_id' => $this->app_account_id,
            'user_id' => $this->user_id,
            'voice_status' => $this->voice_status,
            'text_status' => $this->text_status,
            'status' => $this->status,
        ]);

        return $dataProvider;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAppAccount()
    {
        return $this->hasOne(AppAccount::className(), ['app_account_id' => 'id']);
    }
}
