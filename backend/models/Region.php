<?php

namespace backend\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "regions".
 *
 * The followings are the available columns in table 'regions':
 * @property string $id
 * @property integer $country_id
 * @property string $group_name
 * @property string $region_code
 * @property string $region_name
 *
 * @property CountryCode $countryCode
 */
class Region extends ActiveRecord
{

    private $_regionCodes;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'regions';
    }

    public function getCountryCode()
    {
        return $this->hasOne(CountryCode::className(), ['country_id' => 'country_id']);
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['country_id, region_name', 'required'],
            ['country_id', 'numerical', 'integerOnly' => true],
            ['group_name, region_code', 'length', 'max' => 100],
            ['region_name', 'length', 'max' => 255],
            ['id, country_id, group_name, region_code, region_name', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id'          => 'ID',
            'country_id'  => 'Country',
            'group_name'  => 'Group Name',
            'region_code' => 'Region Code',
            'region_name' => 'Region Name',
        ];
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {

        $query = new ActiveQuery();

        $query->filterWhere(['like', 'id', $this->id]);
        $query->andFilterWhere(['=', 'country_id', $this->country_id]);
        $query->andFilterWhere(['like', 'group_name', $this->group_name]);
        $query->andFilterWhere(['like', 'region_code', $this->region_code]);
        $query->andFilterWhere(['like', 'region_name', $this->region_name]);

        return new ActiveDataProvider([
            'query' => $query,
        ]);
    }

    /**
     * @param null $countryId
     *
     * @return mixed
     */
    public function getRegionList($countryId = null)
    {
        $cacheId = 'regionList' . $countryId . 'v1';    // change the cacheid slightly if the code/data changes
        $list    = Yii::$app->cache->get($cacheId);
        if ($list === false) {
            // regenerate $list because it is not found in cache
            //SELECT * FROM locations WHERE name1 != '' GROUP BY code1 ORDER BY country_id,name1
            if ($countryId === null) {
                $sql     = "SELECT r.id,r.region_name,c.country_name FROM regions r
                        INNER JOIN country_codes c ON c.country_id = r.country_id
                        ORDER BY c.country_name,r.region_name";
                $regions = Yii::$app->db->createCommand($sql)->queryAll();
                $list    = ArrayHelper::map($regions, 'id', 'region_name', 'country_name');
            } else {
                $countryId = (int)$countryId;
                $sql       = "SELECT id,region_name FROM regions WHERE country_id = $countryId ORDER BY region_name";
                $regions   = Yii::$app->db->createCommand($sql)->queryAll();
                $list      = ArrayHelper::map($regions, 'id', 'region_name');
            }
            Yii::$app->cache->set($cacheId, $list, 86400);   // cache for 24hrs
        }

        return $list;
    }

    /**
     * @param $id
     *
     * @return mixed|null
     */
    public function getRegionCodeFromId($id)
    {
        if (empty($this->_regionCodes)) {
            $this->_regionCodes = [];
            $rows               = Yii::$app->db->createCommand("SELECT id,region_code FROM regions")->queryAll();
            foreach ($rows as $row) {
                $this->_regionCodes[$row['id']] = $row['region_code'];
            }
        }

        return isset($this->_regionCodes[$id]) ? $this->_regionCodes[$id] : null;
    }

}