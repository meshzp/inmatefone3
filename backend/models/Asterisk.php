<?php

namespace backend\models;

use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use backend\helpers\Globals;
use yii\base\Exception;

/**
 * This is the model class for table "tbl_lookup".
 *
 * The followings are the available columns in table 'tbl_lookup':
 * @property string $in_number
 * @property string $out_number
 * @property string $out_number_2
 * @property string $seconds_remaining
 * @property string $cli_to_show
 * @property string $cli_name
 */
class Asterisk extends ActiveRecord
{

    /**
     * @return CDbConnection database connection
     */
    public function getDbConnection()
    {
        // TODO: Подключение компонента, дальше код будет с использованием этого метода будет отдавать ошибку
        return Yii::$app->asteriskdb;
    }

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'tbl_lookup';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['in_number', 'seconds_remaining', 'cli_to_show', 'cli_name'], 'required'],
            [['in_number', 'out_number', 'out_number_2', 'seconds_remaining', 'cli_to_show', 'cli_name'], 'max' => 32],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['in_number', 'out_number', 'out_number_2', 'seconds_remaining', 'cli_to_show', 'cli_name'], 'safe'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'in_number'         => 'In Number',
            'out_number'        => 'Out Number',
            'out_number_2'      => 'Out Number 2',
            'seconds_remaining' => 'Seconds Remaining',
            'cli_to_show'       => 'Cli To Show',
            'cli_name'          => 'Cli Name',
        ];
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @param array $params
     *
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search($params)
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $query = self::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'in_number'         => $this->in_number,
            'out_number'        => $this->out_number,
            'out_number_2'      => $this->out_number_2,
            'seconds_remaining' => $this->seconds_remaining,
            'cli_to_show'       => $this->cli_to_show,
            'cli_name'          => $this->cli_name,
        ]);

        return $dataProvider;
    }

    // TODO: convert this function to something more Yii based

    /**
     * @deprecated
     *
     * @param int $in_number
     * @param int $out_number
     * @param string $cli
     * @param int $status
     * @param int $id
     * @param string $allowance
     */
    public function process($in_number, $out_number, $cli, $status, $id, $allowance)
    {
        $in_number = Globals::numbersOnly($in_number);
        if (strstr(strtolower($out_number), 'pbx')) {
            $out_number = trim($out_number);
        } else {
            $out_number = Globals::numbersOnly($out_number);
        }
        $successCount = 0;

        $connection  = $this->getDbConnection();
        $transaction = $connection->beginTransaction();
        try {
            if (!$status) {
                // delete any in numbers already existing
                // TODO: Нужно что-то сделать, используется компонент из старого проекта
                $connection->createCommand()->delete($this->tableName(), 'in_number=:in_number', [':in_number' => $in_number]);

                $successCount = 1;
            } else {
                if ($status != 1) {
                    $out_number = '';
                }
                $count = self::find()->where(['in_number' => $in_number])->count();
                if ($count) {
                    $connection->createCommand()->update($this->tableName(), [
                        'out_number'        => $out_number,
                        'seconds_remaining' => $allowance,
                        'cli_to_show'       => $cli,
                    ], 'in_number=:in_number', [':in_number' => $in_number]);
                } else {
                    // TODO: why are we not inserting the cli_to_show here?
                    $connection->createCommand()->insert($this->tableName(), [
                        'in_number'         => $in_number,
                        'out_number'        => $out_number,
                        'seconds_remaining' => $allowance,
                    ]);
                }
                // check the number is in there ok and update the client Did
                $successCount = self::find()->where(['in_number' => $in_number, 'out_number' => $out_number])->count();
            }
            $transaction->commit();
        } catch (Exception $e) { // an exception is raised if a query fails
            $transaction->rollback();
            echo $e->getMessage();
            $successCount = 0;
        }

        if ($successCount == 1) {
            Yii::$app->db->createCommand()->update(ClientDid::tableName(), ['asterisk' => '1'], ['id' => $id]);
        }
    }
}
