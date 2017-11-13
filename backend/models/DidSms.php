<?php

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "did_sms".
 *
 * The followings are the available columns in table 'did_sms':
 * @property integer $id
 * @property string $did
 */
class DidSms extends ActiveRecord
{

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'did_sms';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['did', 'required'],
            ['did', 'length', 'max' => 20],
            ['id, did', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id'  => 'ID',
            'did' => 'Did',
        ];
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        $query = new ActiveQuery();

        $query->filterWhere(['=', 'id', $this->id]);
        $query->andFilterWhere(['like', 'did', $this->did]);

        return new ActiveDataProvider($this, [
            'query' => $query,
        ]);
    }
}