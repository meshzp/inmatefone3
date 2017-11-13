<?php

namespace backend\models;

use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "country".
 *
 * The followings are the available columns in table 'country':
 * @property string $code
 * @property string $name
 */
class Country extends ActiveRecord
{

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'country';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {

        return [
            ['code, name', 'required'],
            ['code', 'length', 'max' => 2],
            ['name', 'length', 'max' => 255],
            ['code, name', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'code' => 'Code',
            'name' => 'Name',
        ];
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        $query = new ActiveQuery();

        $query->andFilterWhere(['like', 'code', $this->code]);
        $query->andFilterWhere(['like', 'name', $this->name]);

        return new ActiveDataProvider([
            'query' => $query,
        ]);
    }
}