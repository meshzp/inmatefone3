<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
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
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria = new CDbCriteria;

        $criteria->compare('service_id', $this->service_id, true);
        $criteria->compare('service_name', $this->service_name, true);
        $criteria->compare('service_status', $this->service_status);

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
        ]);
    }

    public function getServiceList()
    {
        $models  = $this->enabled()->byName()->findAll();
        $list    = CHtml::listData($models, 'service_id', 'service_name');
        $list[0] = 'Other';

        return $list;
    }

}