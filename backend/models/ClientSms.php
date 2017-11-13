<?php

namespace backend\models;

use backend\helpers\Globals;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Exception;

/**
 * This is the model class for table "user_sms".
 *
 * The followings are the available columns in table 'user_sms':
 * @property integer $id
 * @property integer $user_id
 * @property string $hashtag
 * @property integer $destination_country_id
 * @property string $destination
 * @property integer $did_sms_id
 * @property integer $status
 * @property string $entry_admin_id
 * @property string $entry_modified
 *
 * @property User $admin
 * @property DidSms $didSms
 * @property Client $client
 * @property CountryCode $country
 */
class ClientSms extends ActiveRecord
{

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'user_sms';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['user_id, hashtag, destination_country_id, destination', 'required'],
            ['did_sms_id', 'required', 'message' => 'This destination number has been used too many times. Please ask support to add another SMS DID.'],
            ['did_sms_id, status', 'numerical', 'integerOnly' => true],
            ['user_id, destination_country_id, entry_admin_id', 'length', 'max' => 11],
            ['hashtag, destination', 'length', 'max' => 20],
            ['hashtag', 'ext.validators.alpha', 'allowNumbers' => true, 'message' => 'Alpha-numeric HashTag only.'],
            ['user_id', 'ext.validators.UniqueAttributesValidator', 'with' => 'hashtag', 'message' => 'Each HashTag must be unique to the client. Please use another.'],
            // unique destination, did
            ['did_sms_id', 'ext.validators.UniqueAttributesValidator', 'with' => 'destination_country_id,destination', 'message' => 'Someone else is using the same SMS DID for this destination. Contact support.'],
            ['id, user_id, hashtag, destination, did_sms_id, status, entry_admin_id, entry_modified', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id'                     => 'ID',
            'user_id'                => 'User',
            'hashtag'                => 'Hashtag',
            'destination_country_id' => 'Destination Country',
            'destination'            => 'Destination Number',
            'did_sms_id'             => 'Did Sms',
            'status'                 => 'Status',
            'entry_admin_id'         => 'Entry Admin',
            'entry_modified'         => 'Entry Modified',
        ];
    }

    public function beforeValidate()
    {
        if ($this->isNewRecord) {
            // get a valid and random did_sms_id
            $sql              = "SELECT d.id 
                    FROM did_sms AS d
                    LEFT JOIN (SELECT did_sms_id FROM user_sms WHERE destination_country_id = :destination_country_id AND destination = :destination) 
                        AS s ON s.did_sms_id = d.id
                    WHERE s.did_sms_id IS NULL
                    ORDER BY RAND() 
                    LIMIT 1";
            $this->did_sms_id = Yii::$app->db->createCommand($sql)->queryScalar([
                ':destination_country_id' => $this->destination_country_id,
                ':destination'            => $this->destination,
            ]);
        }

        $this->hashtag     = trim($this->hashtag);
        $this->destination = Globals::numbersOnly($this->destination);

        return parent::beforeValidate();
    }

    /**
     * @return bool
     */
    public function beforeSave()
    {
        $this->entry_admin_id = Yii::$app->getUser()->getId();
        $this->entry_modified = date('Y-m-d H:i:s');

        return parent::beforeSave();
    }

    public function delete($permanently = false)
    {
        if ($permanently) {
            return parent::delete();
        }
        // ClientSMS records should not be fully deleted until an entry has been made in the history table
        if (!$this->getIsNewRecord()) {
            Yii::trace(get_class($this) . '.delete()', 'system.db.ar.CActiveRecord');
            if ($this->beforeDelete()) {
                $result = false;
                // change status to -1 to indicate deletion, timestamp and entry admin id, then save (so it goes into history table) and delete
                $this->status = -1;
                if ($this->save()) {
                    $result = $this->delete();
                }
                $this->afterDelete();

                return $result;
            } else {
                return false;
            }
        } else {
            throw new Exception(Yii::t('yii', 'The active record cannot be deleted because it is new.'));
        }
    }

    /**
     * @return ActiveQuery|User
     */
    public function getAdmin()
    {
        return $this->hasOne(User::className(), ['entry_admin_id' => 'entry_admin_id']);
    }

    /**
     * @return ActiveQuery|DidSms
     */
    public function getDidSms()
    {
        return $this->hasOne(DidSms::className(), ['did_sms_id' => 'did_sms_id']);
    }

    /**
     * @return ActiveQuery|Client
     */
    public function getClient()
    {
        return $this->hasOne(Client::className(), ['user_id' => 'user_id']);
    }

    /**
     * @return ActiveQuery|CountryCode
     */
    public function getCountry()
    {
        return $this->hasOne(CountryCode::className(), ['destination_country_id' => 'destination_country_id']);
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {

        $query = new ActiveQuery();

        $query->filterWhere(['=', 't.id', $this->id]);
        $query->andFilterWhere(['=', 't.user_id', $this->user_id]);
        $query->andFilterWhere(['like', 't.hashtag', $this->hashtag]);
        $query->andFilterWhere(['=', 't.destination_country_id', $this->destination_country_id]);
        $query->andFilterWhere(['like', 't.destination', $this->destination]);
        $query->andFilterWhere(['=', 't.did_sms_id', $this->did_sms_id]);
        $query->andFilterWhere(['=', 't.status', $this->status]);
        $query->andFilterWhere(['=', 't.entry_admin_id', $this->entry_admin_id]);
        $query->andFilterWhere(['like', 't.entry_modified', $this->entry_modified]);

        return new ActiveDataProvider([
            'query'      => $query,
            'sort'       => [
                'defaultOrder' => 't.hashtag',
            ],
            'pagination' => false,
        ]);
    }

}