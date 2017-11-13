<?php

namespace backend\models;

use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "country_codes".
 *
 * The followings are the available columns in table 'country_codes':
 * @property string $country_id
 * @property string $country_code_alpha_2
 * @property string $country_code_alpha_3
 * @property string $country_code_numeric
 * @property string $country_name
 * @property string $country_phone_code
 * @property string $termination_rate_fixed
 * @property string $termination_rate_mobile
 *
 * @property Facility $facilities
 * @property RateCenter $rateCenters
 */
class CountryCode extends ActiveRecord
{

    /**
     * @return ActiveQuery
     */
    public function getFacilities()
    {
        return $this->hasMany(Facility::className(), ['facility_country' => 'country_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getRateCenters()
    {
        return $this->hasMany(RateCenter::className(), ['country_id' => 'country_id']);
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        $query = new ActiveQuery();

        $query->filterWhere(['country_id', $this->country_id]);
        $query->andFilterWhere(['like', 'country_code_alpha_2', $this->country_code_alpha_2]);
        $query->andFilterWhere(['like', 'country_code_alpha_3', $this->country_code_alpha_3]);
        $query->andFilterWhere(['like', 'country_code_numeric', $this->country_code_numeric]);
        $query->andFilterWhere(['like', 'country_name', $this->country_name]);
        $query->andFilterWhere(['like', 'country_phone_code', $this->country_phone_code]);
        $query->andFilterWhere(['like', 'termination_rate_fixed', $this->termination_rate_fixed]);
        $query->andFilterWhere(['like', 'termination_rate_mobile', $this->termination_rate_mobile]);

        return new ActiveDataProvider($this, [
            'query' => $query,
        ]);
    }

    public function getCountryList($includePhonePrefix = false, $addSipOption = false)
    {
        // this is cached for 1 day
        $cacheId   = $includePhonePrefix ? 'countryListPhonePrefix' : 'countryList';
        $cacheTime = 86400; // 24hrs
        $list      = Yii::$app->cache->get($cacheId);
        if ($list === false) {
            // (re)generate $list
            $name = $includePhonePrefix ? 'CONCAT(country_name," (+",country_phone_code,")") AS country_name' : 'country_name';
            $list = ArrayHelper::map($this::findAll([
                'select' => "country_id, $name",
                'order'  => 'country_name ASC',
            ]), 'country_id', 'country_name');
            Yii::$app->cache->set($cacheId, $list, $cacheTime);
        }
        if ($addSipOption) {
            // note: don't use array_unshift as it re-orders the values!
            $list = [0 => 'SIP'] + $list;
        }

        return $list;
    }

    public function getCountryPhoneCode($countryId)
    {
        // this is stored in cache for 24 hours
        return Yii::$app->db->cache(function() use ($countryId) {
            Yii::$app->db->createCommand()
                ->select('country_phone_code')
                ->from(self::tableName())
                ->where('country_id=:countryId', [':countryId' => $countryId])
                ->queryScalar();
        }, 86400);
    }

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'country_codes';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['country_code_alpha_2, country_code_alpha_3, country_code_numeric, country_name, country_phone_code', 'required'],
            ['country_code_alpha_2', 'length', 'max' => 2],
            ['country_code_alpha_3, country_code_numeric', 'length', 'max' => 3],
            ['country_name, country_phone_code', 'length', 'max' => 255],
            ['termination_rate_fixed, termination_rate_mobile', 'length', 'max' => 10],
            ['country_id, country_code_alpha_2, country_code_alpha_3, country_code_numeric, country_name, country_phone_code, termination_rate_fixed, termination_rate_mobile', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'country_id'              => 'Country',
            'country_code_alpha_2'    => 'Country Code Alpha 2',
            'country_code_alpha_3'    => 'Country Code Alpha 3',
            'country_code_numeric'    => 'Country Code Numeric',
            'country_name'            => 'Country Name',
            'country_phone_code'      => 'Country Phone Code',
            'termination_rate_fixed'  => 'Termination Rate Fixed',
            'termination_rate_mobile' => 'Termination Rate Mobile',
        ];
    }

    public function getCountryName($countryId, $includePhonePrefix = false)
    {
        // this is stored in cache for 24 hours
        $select = $includePhonePrefix ? 'CONCAT(country_name," (+",country_phone_code,")") AS country_name' : 'country_name';

        return Yii::$app->db->cache(function() use ($select, $countryId) {
            Yii::$app->db->createCommand()
                ->select($select)
                ->from(self::tableName())
                ->where('country_id=:countryId', [':countryId' => $countryId])
                ->queryScalar();
        },86400);
    }
}