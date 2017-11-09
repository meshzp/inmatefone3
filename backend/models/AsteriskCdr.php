<?php

namespace backend\models;

use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "asterisk_cdrs".
 *
 * The followings are the available columns in table 'asterisk_cdrs':
 * @property string $accountcode
 * @property string $cli
 * @property string $dst
 * @property string $context
 * @property string $cli2
 * @property string $channel
 * @property string $obchannel
 * @property string $lastapp
 * @property string $data
 * @property string $start_stamp
 * @property string $answered_stamp
 * @property string $end_stamp
 * @property integer $duration
 * @property integer $billsec
 * @property string $disposition
 * @property string $bs
 * @property string $uuid
 * @property integer $sync
 */
class AsteriskCdr extends ActiveRecord // TODO: В оригинале используется \protected\components\ActiveRecord.php
{
    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%asterisk_cdrs}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['duration, billsec, sync'], 'integer'],
            [['accountcode', 'cli', 'dst', 'context', 'cli2', 'channel', 'obchannel', 'lastapp', 'data', 'disposition', 'bs', 'uuid'], 'max' => 255],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['accountcode', 'cli', 'dst', 'context', 'cli2', 'channel', 'obchannel', 'lastapp', 'data', 'start_stamp', 'answered_stamp', 'end_stamp', 'duration', 'billsec', 'disposition', 'bs', 'uuid', 'sync'], 'safe'],
        ];
    }

    /**
     * @return array customized attribute labels (name => label)
     */
    public function attributeLabels()
    {
        return [
            'accountcode'    => 'Accountcode',
            'cli'            => 'Cli',
            'dst'            => 'Dst',
            'context'        => 'Context',
            'cli2'           => 'Cli2',
            'channel'        => 'Channel',
            'obchannel'      => 'Obchannel',
            'lastapp'        => 'Lastapp',
            'data'           => 'Data',
            'start_stamp'    => 'Start Stamp',
            'answered_stamp' => 'Answered Stamp',
            'end_stamp'      => 'End Stamp',
            'duration'       => 'Duration',
            'billsec'        => 'Billsec',
            'disposition'    => 'Disposition',
            'bs'             => 'Bs',
            'uuid'           => 'Uuid',
            'sync'           => 'Sync',
        ];
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @param array $params
     *
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
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
            'accountcode'    => $this->accountcode,
            'cli'            => $this->cli,
            'dst'            => $this->dst,
            'context'        => $this->context,
            'cli2'           => $this->cli2,
            'channel'        => $this->channel,
            'obchannel'      => $this->obchannel,
            'lastapp'        => $this->lastapp,
            'data'           => $this->data,
            'start_stamp'    => $this->start_stamp,
            'answered_stamp' => $this->answered_stamp,
            'end_stamp'      => $this->end_stamp,
            'duration'       => $this->duration,
            'billsec'        => $this->billsec,
            'disposition'    => $this->disposition,
            'bs'             => $this->bs,
            'uuid'           => $this->uuid,
            'sync'           => $this->sync,
        ]);

        return $dataProvider;
    }
}
