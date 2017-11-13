<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "user_connect".
 *
 * The followings are the available columns in table 'user_connect':
 * @property string $connect_id
 * @property integer $connect_user_id
 * @property integer $connect_admin_id
 * @property string $connect_token
 * @property string $connect_datetime
 * @property integer $connect_status
 */
class ClientConnect extends ActiveRecord // Наследуется от \protected\components\ActiveRecord
{
    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%user_connect}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['connect_token', 'connect_user_id'], 'required'],
            [['connect_user_id', 'connect_admin_id', 'connect_status'], 'integer'],
            [['connect_token'], 'max' => 255],
            [['connect_datetime'], 'safe'],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['connect_id', 'connect_user_id', 'connect_admin_id', 'connect_token', 'connect_datetime', 'connect_status'], 'safe', 'on' => ['search']],
        ];
    }

    /**
     * @return array customized attribute labels (name => label)
     */
    public function attributeLabels()
    {
        return [
            'connect_id'       => 'Connect',
            'connect_user_id'  => 'Connect User',
            'connect_admin_id' => 'Connect Admin',
            'connect_token'    => 'Connect Token',
            'connect_datetime' => 'Connect Datetime',
            'connect_status'   => 'Connect Status',
        ];
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @param string $params
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
            'connect_id'       => $this->connect_id,
            'connect_user_id'  => $this->connect_user_id,
            'connect_admin_id' => $this->connect_admin_id,
            'connect_token'    => $this->connect_token,
            'connect_datetime' => $this->connect_datetime,
            'connect_status'   => $this->connect_status,
        ]);

        return $dataProvider;
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate()
    {
        // create token and datetime for new records
        if ($this->isNewRecord) {
            $this->connect_token    = unique_md5();
            $this->connect_datetime = date('Y-m-d H:i:s');
            $this->connect_admin_id = user()->id;
        }

        return parent::beforeValidate();
    }

}
