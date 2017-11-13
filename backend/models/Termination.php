<?php

namespace backend\models;

use backend\helpers\Globals;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "terminations".
 *
 * The followings are the available columns in table 'terminations':
 * @property string $termination_id
 * @property string $termination_code
 * @property string $termination_destination
 * @property string $termination_country
 * @property string $termination_type
 * @property integer $country_id
 *
 * @property CountryCode $countryCode
 */
class Termination extends ActiveRecord
{

    const TYPE_FIXED     = 'FIXED';
    const TYPE_MOBILE    = 'MOBILE';
    const TYPE_SATELLITE = 'SATELLITE';

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['termination_code, termination_destination, termination_country, termination_type', 'required'],
            ['country_id', 'numerical', 'integerOnly' => true],
            ['termination_code, termination_destination, termination_country, termination_type', 'length', 'max' => 255],
            ['termination_id, termination_code, termination_destination, termination_country, termination_type, country_id', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return ActiveQuery
     */
    public function getCountryCode()
    {
        return $this->hasOne(CountryCode::className(), ['country_id' => 'country_id']);
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'termination_id'          => 'Termination',
            'termination_code'        => 'Termination Code',
            'termination_destination' => 'Termination Destination',
            'termination_country'     => 'Termination Country',
            'termination_type'        => 'Termination Type',
            'country_id'              => 'Country',
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

        $criteria = new CDbCriteria;

        $criteria->compare('termination_id', $this->termination_id, true);
        $criteria->compare('termination_code', $this->termination_code, true);
        $criteria->compare('termination_destination', $this->termination_destination, true);
        $criteria->compare('termination_country', $this->termination_country, true);
        $criteria->compare('termination_type', $this->termination_type, true);
        $criteria->compare('country_id', $this->country_id);

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
        ]);
    }

    /**
     * @param $number
     * @param bool $addZeroPrefix
     *
     * @return mixed
     */
    public function getTerminationIdByNumber($number, $addZeroPrefix = true)
    {
        /* original query....
         * SELECT termination_id FROM terminations
         * WHERE termination_destination!='00'
         * AND '00".$country_phone_code.$number."' LIKE CONCAT(termination_destination,'%')
         * ORDER BY LENGTH (termination_destination) DESC
         * LIMIT 1
         */
        if ($addZeroPrefix) {
            $number = '00' . $number;
        }

        // this is stored in cache for 1 hour
        return Yii::$app->db->cache(3600)->createCommand()
            ->select('termination_id')
            ->from(self::tableName())
            ->where('termination_destination!="00" AND :number LIKE CONCAT(termination_destination,"%")', [':number' => Globals::numbersOnly($number)])
            ->order('LENGTH (termination_destination) DESC')
            ->limit(1)
            ->queryScalar();
    }

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'terminations';
    }

    /**
     * @param $number
     *
     * @return bool|null
     */
    public function isMobile($number)
    {
        $termination = $this->getTerminationByNumber($number);
        if (empty($termination)) {
            return null;
        }

        return $termination['termination_type'] == 'MOBILE';
    }

    /**
     * @param $number
     * @param bool $addZeroPrefix
     *
     * @return mixed
     */
    public function getTerminationByNumber($number, $addZeroPrefix = true)
    {
        /* original query....
         * SELECT termination_id FROM terminations
         * WHERE termination_destination!='00'
         * AND '00".$country_phone_code.$number."' LIKE CONCAT(termination_destination,'%')
         * ORDER BY LENGTH (termination_destination) DESC
         * LIMIT 1
         */
        // note: at present, a 00 prefix needs to be added to the number
        if ($addZeroPrefix) {
            $number = '00' . $number;
        }

        // this is stored in cache for 1 hour
        return Yii::$app->db->cache(function($db, $number) {
           return $db->createCommand()
                ->select('*')
                ->from(self::tableName())
                ->where('termination_destination!="00" AND :number LIKE CONCAT(termination_destination,"%")', [':number' => Globals::numbersOnly($number)])
                ->order('LENGTH (termination_destination) DESC')
                ->limit(1)
                ->queryRow();
        },3600);
    }
}
