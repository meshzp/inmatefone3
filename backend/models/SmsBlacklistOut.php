<?php

namespace backend\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "sms_blacklist_out".
 *
 * The followings are the available columns in table 'sms_blacklist_out':
 * @property string $id
 * @property string $corrlinks_contact_id
 * @property string $number_to
 * @property integer $status
 *
 * @property CorrlinksContact $corrlinksContact
 */
class SmsBlacklistOut extends ActiveRecord
{
    const STATUS_UNBLOCKED = 0;
    const STATUS_BLOCKED   = 1;

    public static $statuses = [
        self::STATUS_UNBLOCKED => 'Unblocked',
        self::STATUS_BLOCKED   => 'Blocked',
    ];

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'sms_blacklist_out';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['number_to', 'required'],
            ['status', 'numerical', 'integerOnly' => true],
            ['corrlinks_contact_id', 'length', 'max' => 11],
            ['number_to', 'length', 'max' => 32],
            ['id, corrlinks_contact_id, number_to, status', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return ActiveQuery
     */
    public function getCorrlinksContact()
    {
        return $this->hasOne(CorrlinksContact::className(), ['corrlinks_contact_id' => 'corrlinks_contact_id']);
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id'                   => 'ID',
            'corrlinks_contact_id' => 'Corrlinks Contact',
            'number_to'            => 'Number To',
            'status'               => 'Status',
        ];
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria       = new CDbCriteria;
        $criteria->with = [
            'corrlinksContact',
        ];

        $criteria->compare('id', $this->id, true);
        $criteria->compare('corrlinks_contact_id', $this->corrlinks_contact_id, true);
        $criteria->compare('number_to', $this->number_to, true);
        $criteria->compare('status', $this->status);

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
        ]);
    }

}
