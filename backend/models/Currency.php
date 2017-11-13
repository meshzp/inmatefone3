<?php

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "currencies".
 *
 * The followings are the available columns in table 'currencies':
 * @property string $currency_id
 * @property string $currency_prefix
 * @property string $currency_name
 * @property string $currency_sign
 * @property integer $currency_status
 */
class Currency extends ActiveRecord
{

    private $_currencySymbols;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'currencies';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['currency_prefix, currency_name', 'required'],
            ['currency_status', 'numerical', 'integerOnly' => true],
            ['currency_prefix', 'length', 'max' => 3],
            ['currency_name', 'length', 'max' => 255],
            ['currency_sign', 'length', 'max' => 20],
            ['currency_id, currency_prefix, currency_name, currency_sign, currency_status', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'currency_id'     => 'Currency',
            'currency_prefix' => 'Currency Prefix',
            'currency_name'   => 'Currency Name',
            'currency_sign'   => 'Currency Sign',
            'currency_status' => 'Currency Status',
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
                'condition' => 'currency_status=1',
            ],
            'byName'  => [
                'order' => 'currency_name ASC',
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

        $query->filterWhere(['like', 'currency_id', $this->currency_id]);
        $query->andFilterWhere(['like', 'currency_prefix', $this->currency_prefix]);
        $query->andFilterWhere(['like', 'currency_name', $this->currency_name]);
        $query->andFilterWhere(['like', 'currency_sign', $this->currency_sign]);
        $query->andFilterWhere(['=', 'currency_status', $this->currency_status]);

        return new ActiveDataProvider($this, [
            'query' => $query,
        ]);
    }

    /**
     * @deprecated
     * @return array
     */
    public function getCurrencyList()
    {
        $models   = $this->enabled()->byName()->findAll();
        $listData = [];
        foreach ($models as $model) {
            $listData[$model->currency_prefix] = $model->currency_name . ' (' . $model->currency_prefix . ')';
        }

        return $listData;
    }

    /**
     * @deprecated
     * @param $currencyPrefix
     *
     * @return null
     */
    public function getCurrencySymbolByPrefix($currencyPrefix)
    {
        $currencyPrefix = strtoupper($currencyPrefix);
        if (empty($this->_currencySymbols)) {
            $this->getCurrencySymbols();
        }

        return isset($this->_currencySymbols[$currencyPrefix]) ? $this->_currencySymbols[$currencyPrefix] : null;
    }

    /**
     * @deprecated
     * @return mixed
     */
    public function getCurrencySymbols()
    {
        if (empty($this->_currencySymbols)) {
            $this->_currencySymbols = ArrayHelper::map($this->enabled()->findAll(), 'currency_prefix', 'currency_sign');
        }

        return $this->_currencySymbols;
    }

}