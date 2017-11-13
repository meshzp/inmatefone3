<?php

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Html;

/**
 * This is the model class for table "services".
 *
 * The followings are the available columns in table 'services':
 * @property string $service_id
 * @property string $service_name
 * @property integer $service_status
 */
class Service extends ActiveRecord
{

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'services';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['service_name', 'required'],
            ['service_status', 'numerical', 'integerOnly' => true],
            ['service_name', 'length', 'max' => 255],
            ['service_id, service_name, service_status', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'service_id'     => 'Service',
            'service_name'   => 'Service Name',
            'service_status' => 'Service Status',
        ];
    }

    /**
     * @deprecated
     * @return array
     */
    public function scopes()
    {
        return [
            'enabled' => [
                'condition' => 'service_status=1',
            ],
            'byName'  => [
                'order' => 'service_name ASC',
            ],
        ];
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        $query = new ActiveQuery();
        $query->compare('service_id', $this->service_id, true);
        $query->compare('service_name', $this->service_name, true);
        $query->compare('service_status', $this->service_status);

        return new ActiveDataProvider($this, [
            'query' => $query,
        ]);
    }

    /**
     * @deprecated
     * @return mixed
     */
    public function getServiceList()
    {
        $models  = $this->enabled()->byName()->findAll();
        $list    = Html::map($models, 'service_id', 'service_name');
        $list[0] = 'Other';

        return $list;
    }

}