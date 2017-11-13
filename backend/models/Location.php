<?php

namespace backend\models;

use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "locations".
 *
 * The followings are the available columns in table 'locations':
 * @property string $id
 * @property integer $country_id
 * @property string $country_code
 * @property string $postal_code
 * @property string $place_name
 * @property string $name1
 * @property string $code1
 * @property string $name2
 * @property string $code2
 * @property string $name3
 * @property string $code3
 */
class Location extends ActiveRecord
{

    /**
     * @return ActiveDataProvider
     */
    public function search()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $query = self::find();
        $query->andFilterWhere(['=', 'id', $this->id]);
        $query->andFilterWhere(['=', 'country_id', $this->country_id]);
        $query->andFilterWhere(['=', 'country_code', $this->country_code]);
        $query->andFilterWhere(['=', 'postal_code', $this->postal_code]);
        $query->andFilterWhere(['like', 'place_name', $this->place_name]);
        $query->andFilterWhere(['like', 'name1', $this->name1]);
        $query->andFilterWhere(['=', 'code1', $this->code1]);
        $query->andFilterWhere(['like', 'name2', $this->name2]);
        $query->andFilterWhere(['=', 'code2', $this->code2]);
        $query->andFilterWhere(['like', 'name3', $this->name3]);
        $query->andFilterWhere(['=', 'code3', $this->code3]);

        return new ActiveDataProvider($this, [
            'query' => $query,
        ]);
    }

    /**
     * @param null $countryId
     *
     * @return array|mixed
     */
    public function getStateList($countryId = null)
    {
        $cacheId = 'stateList' . $countryId . 'v1';
        $list    = Yii::$app->cache->get($cacheId);
        if ($list === false) {
            if ($countryId === null) {
                $sql       = "SELECT code1,name1 FROM locations WHERE name1 != '' GROUP BY code1 ORDER BY country_id,name1";
                $locations = Yii::$app->db->createCommand($sql)->queryAll();
                $list      = CHtml::listData($locations, 'code1', 'name1');
            } else {
                $countryId = (int)$countryId;
                switch ($countryId) {
                    case 222 :
                        // UK
                        $sql       = "SELECT code2,name1,name2 FROM locations WHERE country_id = $countryId AND name1 != '' GROUP BY code2 ORDER BY name1,name2";
                        $locations = Yii::$app->db->createCommand($sql)->queryAll();
                        $list      = ArrayHelper::map($locations, 'code2', 'name2', 'name1');
                        break;
                    default :
                        $sql       = "SELECT code1,name1 FROM locations WHERE country_id = $countryId AND name1 != '' GROUP BY code1 ORDER BY name1";
                        $locations = Yii::$app->db->createCommand($sql)->queryAll();
                        $list      = ArrayHelper::map($locations, 'code1', 'name1');
                }
            }
            Yii::$app->cache->set($cacheId, $list, 2592000);   // cache for 30 days
        }

        return $list;
    }

    /**
     * @param $postCode
     * @param null $countryId
     *
     * @return mixed
     */
    public function postCodeLookup($postCode, $countryId = null)
    {
        if ($countryId === null) {
            $countryId = [223, 168, 169];
        }
        if (!is_array($countryId)) {
            $countryId = [$countryId];
        }

        return Yii::$app->db->cache(function() use ($countryId, $postCode) {
            Yii::$app->db->createCommand()
                ->select('*')
                ->from(self::tableName())
                ->where('postal_code = :postCode', [':postCode' => $postCode])
                ->andWhere(['in', 'country_id', $countryId])
                ->queryRow();
        },86400);
    }

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'locations';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['country_id', 'numerical', 'integerOnly' => true],
            ['country_code, postal_code, place_name, name1, code1, name2, code2, name3, code3', 'length', 'max' => 255],
            ['id, country_id, country_code, postal_code, place_name, name1, code1, name2, code2, name3, code3', 'safe', 'on' => 'search'],
        ];
    }

    // country id 223 is US - added Puerto Rico too (168 & 169 although only 168 is actually used in location table)

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id'           => 'ID',
            'country_id'   => 'Country',
            'country_code' => 'Country Code',
            'postal_code'  => 'Postal Code',
            'place_name'   => 'Place Name',
            'name1'        => 'Name1',
            'code1'        => 'Code1',
            'name2'        => 'Name2',
            'code2'        => 'Code2',
            'name3'        => 'Name3',
            'code3'        => 'Code3',
        ];
    }

}