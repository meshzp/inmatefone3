<?php

namespace backend\models;

use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "user_onetime_referral_removed".
 *
 * The followings are the available columns in table 'user_onetime_referral_removed':
 * @property string $id
 * @property string $user_id
 * @property string $referred_by
 * @property integer $created_by
 * @property string $created_at
 * @property string $credited_at
 * @property integer $credit_transaction_id
 * @property string $status
 * @property string $removed_by
 * @property string $removed_at
 */
class ClientOnetimeReferralRemoved extends ActiveRecord // TODO: Inherited from \protected\components\ActiveRecord.php
{
    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%user_onetime_referral_removed}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['user_id', 'referred_by', 'created_by', 'created_at', 'status', 'removed_by', 'removed_at'], 'required'],
            [['created_by', 'credit_transaction_id'], 'integer'],
            [['user_id', 'referred_by', 'removed_by'], 'max' => 11],
            [['status'], 'max' => 32],
            [['credited_at'], 'safe'],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['id', 'user_id', 'referred_by', 'created_by', 'created_at', 'credited_at', 'status', 'removed_by', 'removed_at'], 'safe', 'on' => ['search']],
        ];
    }

    /**
     * @return array customized attribute labels (name => label)
     */
    public function attributeLabels()
    {
        return [
            'id'          => 'ID',
            'user_id'     => 'User',
            'referred_by' => 'Referred By',
            'created_by'  => 'Created By',
            'created_at'  => 'Created At',
            'credited_at' => 'Credited At',
            'status'      => 'Status',
            'removed_by'  => 'Removed By',
            'removed_at'  => 'Removed At',
        ];
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @param $params
     *
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
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
            'id'          => $this->id,
            'user_id'     => $this->user_id,
            'referred_by' => $this->referred_by,
            'created_by'  => $this->created_by,
            'created_at'  => $this->created_at,
            'credited_at' => $this->credited_at,
            'status'      => $this->status,
            'removed_by'  => $this->removed_by,
            'removed_at'  => $this->removed_at,
        ]);

        return $dataProvider;
    }
}
