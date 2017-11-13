<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "app_account_registration".
 *
 * The followings are the available columns in table 'app_account_registration':
 * @property string $id
 * @property string $app_account_id
 * @property string $operating_system
 * @property integer $pin_status
 * @property integer $existing_account
 * @property string $created_at
 *
 * The followings are the available model relations:
 * @property AppAccount $appAccount
 */
class AppAccountRegistration extends ActiveRecord
{
    /**
     * Statuses list
     */
    const PIN_STATUS_NOT_SENT   = 0;
    const PIN_STATUS_SENT_OK    = 1;
    const PIN_STATUS_SENT_ERROR = 2;

    /**
     * @var array
     */
    public static $pinStatuses = [
        self::PIN_STATUS_NOT_SENT   => 'Not Sent',
        self::PIN_STATUS_SENT_OK    => 'Sent OK',
        self::PIN_STATUS_SENT_ERROR => 'Send Error',
    ];

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%app_account_registration}}';
    }

    /**
     * @inheritdoc
     */
    public function beforeSave()
    {
        if (parent::beforeSave()) {
            $dt = date('Y-m-d H:i:s');
            if ($this->isNewRecord && empty($this->created_at)) {
                $this->created_at = $dt;
            }

            return true;
        }

        return false;
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
            [['pin_status', 'existing_account'], 'integer'],
            [['app_account_id'], 'max' => 11],
            [['operating_system'], 'max' => 255],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['id', 'app_account_id', 'operating_system', 'pin_status', 'existing_account', 'created_at'], 'safe'],
        ];
    }

    /**
     * @return array customized attribute labels (name => label)
     */
    public function attributeLabels()
    {
        return [
            'id'               => 'ID',
            'app_account_id'   => 'App Account',
            'operating_system' => 'Operating System',
            'pin_status'       => 'Pin Status',
            'existing_account' => 'Existing Account?',
            'created_at'       => 'Created At',
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
            'id'               => $this->id,
            'app_account_id'   => $this->app_account_id,
            'operating_system' => $this->operating_system,
            'pin_status'       => $this->pin_status,
            'existing_account' => $this->existing_account,
            'created_at'       => $this->created_at,
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
