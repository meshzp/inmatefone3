<?php

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "user_sips".
 *
 * The followings are the available columns in table 'user_sips':
 * @property string $sip_id
 * @property integer $user_id
 * @property string $sip_name
 * @property string $sip_user_id
 * @property string $sip_password
 * @property string $sip_mac_address
 * @property string $sip_ip
 * @property string $sip_cli
 * @property string $sip_cli_name
 * @property integer $sip_status
 * @property string $sip_datetime
 * @property string $sip_datetime_cancel
 * @property string $sip_datetime_last_update
 * @property integer $admin_id
 * @property integer $admin_id_last_update
 * @property integer $termination_provider_id
 * @property integer $sip_update
 */
class ClientSip extends ActiveRecord
{

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'user_sips';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['sip_name, sip_user_id, sip_password, sip_mac_address, sip_ip, sip_cli, sip_cli_name', 'required'],
            ['user_id, sip_status, admin_id, admin_id_last_update, termination_provider_id, sip_update', 'numerical', 'integerOnly' => true],
            ['sip_name, sip_user_id, sip_password, sip_mac_address, sip_ip, sip_cli, sip_cli_name', 'length', 'max' => 255],
            ['sip_datetime, sip_datetime_cancel, sip_datetime_last_update', 'safe'],
            ['sip_id, user_id, sip_name, sip_user_id, sip_password, sip_mac_address, sip_ip, sip_cli, sip_cli_name, sip_status, sip_datetime, sip_datetime_cancel, sip_datetime_last_update, admin_id, admin_id_last_update, termination_provider_id, sip_update', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'sip_id'                   => 'Sip',
            'user_id'                  => 'User',
            'sip_name'                 => 'Sip Name',
            'sip_user_id'              => 'Sip User',
            'sip_password'             => 'Sip Password',
            'sip_mac_address'          => 'Sip Mac Address',
            'sip_ip'                   => 'Sip Ip',
            'sip_cli'                  => 'Sip Cli',
            'sip_cli_name'             => 'Sip Cli Name',
            'sip_status'               => 'Sip Status',
            'sip_datetime'             => 'Sip Datetime',
            'sip_datetime_cancel'      => 'Sip Datetime Cancel',
            'sip_datetime_last_update' => 'Sip Datetime Last Update',
            'admin_id'                 => 'Admin',
            'admin_id_last_update'     => 'Admin Id Last Update',
            'termination_provider_id'  => 'Termination Provider',
            'sip_update'               => 'Sip Update',
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
                'condition' => 'sip_status!=0',
            ],
        ];
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        $query         = new ActiveQuery();
        $query->where(['!=', 'enabled', 0]);

        $query->andFilterWhere(['=', 'sip_id', $this->sip_id]);
        $query->andFilterWhere(['=', 'user_id', $this->user_id]);
        $query->andFilterWhere(['like', 'sip_name', $this->sip_name]);
        $query->andFilterWhere(['like', 'sip_user_id', $this->sip_user_id]);
        $query->andFilterWhere(['like', 'sip_password', $this->sip_password]);
        $query->andFilterWhere(['like', 'sip_mac_address', $this->sip_mac_address]);
        $query->andFilterWhere(['like', 'sip_ip', $this->sip_ip]);
        $query->andFilterWhere(['like', 'sip_cli', $this->sip_cli]);
        $query->andFilterWhere(['like', 'sip_cli_name', $this->sip_cli_name]);
        $query->andFilterWhere(['=', 'sip_status', $this->sip_status]);
        $query->andFilterWhere(['like', 'sip_datetime', $this->sip_datetime]);
        $query->andFilterWhere(['like', 'sip_datetime_cancel', $this->sip_datetime_cancel]);
        $query->andFilterWhere(['like', 'sip_datetime_last_update', $this->sip_datetime_last_update]);
        $query->andFilterWhere(['=', 'admin_id', $this->admin_id]);
        $query->andFilterWhere(['=', 'admin_id_last_update', $this->admin_id_last_update]);
        $query->andFilterWhere(['=', 'termination_provider_id', $this->termination_provider_id]);
        $query->andFilterWhere(['=', 'sip_update', $this->sip_update]);

        return new ActiveDataProvider([
            'query' => $query,
        ]);
    }

}