<?php

namespace backend\models;

use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "pbx_whitelist".
 *
 * The followings are the available columns in table 'pbx_whitelist':
 * @property string $order
 * @property string $number
 */
class PbxWhitelist extends ActiveRecord
{

    public $text = '';

    public function setTextFromDb()
    {
        $whitelistNumbers = Yii::$app->db->createCommand("SELECT number FROM " . self::tableName() ." ORDER By `order`")->queryColumn();
        $this->text       = implode("\n", $whitelistNumbers) . "\n";
    }

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'pbx_whitelist';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['number', 'required'],
            ['number', 'length', 'max' => 32],
            ['order, number', 'safe', 'on' => 'search'],
            ['text', 'safe', 'on' => 'list'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'order'  => 'Order',
            'number' => 'Number',
            'text'   => 'Whitelist (one number per line)',
        ];
    }

    public function save($runValidation = true, $attributes = null)
    {
        if ($this->scenario != 'list') {
            return parent::save($runValidation, $attributes);
        }

        // truncate the existing table
        Yii::$app->db->createCommand("TRUNCATE pbx_whitelist")->execute();

        $numbers = explode("\n", $this->text);

        foreach ($numbers as $number) {

            // no longer validating phone number
            $trimmedNumber = trim($number);
            if ($trimmedNumber == '') {
                continue;
            }
            $model         = new PbxWhitelist();
            $model->number = $trimmedNumber;
            if (!$model->save()) {
                $this->addError('text', "Error adding '$trimmedNumber'");
            }
        }

        return !$this->hasErrors();
    }

    /**
     * @return ActiveDataProvider
     */
    public function search()
    {
        $query = PbxWhitelist::find();

        $query->andFilterWhere(['=', 'order', $this->order]);
        $query->andFilterWhere(['=', 'number', $this->number]);

        return new ActiveDataProvider($this, [
            'query' => $query,
        ]);
    }

}
