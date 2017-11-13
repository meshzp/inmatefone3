<?php

namespace backend\models;

use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "linphone".
 *
 * The followings are the available columns in table 'linphone':
 * @property string $id
 * @property string $number
 * @property string $username
 * @property integer $verify_code
 * @property string $email
 * @property integer $status
 * @property string $created_at
 * @property string $updated_at
 */
class Linphone extends ActiveRecord
{

    const STATUS_INACTIVE   = 0;
    const STATUS_ACTIVE     = 1;
    const STATUS_UNVERIFIED = 2;

    public static $statuses = [
        self::STATUS_INACTIVE   => 'Inactive',
        self::STATUS_ACTIVE     => 'Active',
        self::STATUS_UNVERIFIED => 'Unverified',
    ];

    public $tel;

    /**
     * @return ActiveDataProvider
     */
    public function search()
    {
        $query = Linphone::find();

        $query->andFilterWhere(['like', 'id', $this->id]);
        $query->andFilterWhere(['like', 'number', $this->number]);
        $query->andFilterWhere(['like', 'username', $this->username]);
        $query->andFilterWhere(['like', 'email', $this->email]);
        $query->andFilterWhere(['like', 'created_at', $this->created_at]);
        $query->andFilterWhere(['like', 'updated_at', $this->updated_at]);
        $query->andFilterWhere(['=', 'status', $this->status]);
        $query->andFilterWhere(['=', 'verify_code', $this->verify_code]);

        return new ActiveDataProvider($this, [
            'query' => $query,
            'sort'     => [
                'defaultOrder' => 'username',
            ],
        ]);
    }

    public function beforeSave()
    {

        if (parent::beforeSave()) {

            $dt = date('Y-m-d H:i:s');
            if ($this->isNewRecord) {
                $this->created_at = $dt;
            }
            $this->updated_at = $dt;

            return true;
        }

        return false;
    }

    public function afterSave()
    {
        parent::afterSave();

        $this->updateDids();
    }

    public function updateDids()
    {
        if (!empty($this->number)) {
            // run Block DID on any associated numbers to update the voip allowance
            $clientIds      = Yii::$app->db->createCommand()
                ->select('DISTINCT(user_id)')
                ->from('user_dids')
                ->where('`status` > 0 AND redirect_e164 = :number')
                ->queryColumn([':number' => $this->number]);
            $clientDidModel = new ClientDid();
            foreach ($clientIds as $clientId) {
                $clientDidModel->blockDid($clientId);
            }
        }
    }

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'linphone';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['number, verify_code', 'required'],
            ['verify_code, status', 'numerical', 'integerOnly' => true],
            ['number', 'length', 'max' => 32],
            ['username, email', 'length', 'max' => 255],
            ['id, number, username, verify_code, email, status, created_at, updated_at', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id'          => 'ID',
            'number'      => 'Number',
            'username'    => 'Username',
            'verify_code' => 'Verify Code',
            'email'       => 'Email',
            'status'      => 'Status',
            'created_at'  => 'Created At',
            'updated_at'  => 'Updated At',
        ];
    }

}
