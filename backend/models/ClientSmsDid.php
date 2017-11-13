<?php

namespace backend\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\helpers\Html;

/**
 * This is the model class for table "user_sms_did".
 *
 * The followings are the available columns in table 'user_sms_did':
 * @property string $id
 * @property integer $user_id
 * @property integer $did_id
 * @property string $created_at
 * @property integer $created_by
 * @property string $deleted_at
 * @property integer $deleted_by
 * @property integer $status
 *
 * The followings are the available model relations:
 * @property SmsIn[] $smsIns
 * @property SmsOut[] $smsOuts
 * @property Client $client
 * @property Did $did
 */
class ClientSmsDid extends ActiveRecord
{
    public $rateCenterId;
    public $providerId;
    public $npa;
    public $nxx;

    public $defective = 0;  // used when removing a client did and will set did_available flag to defective if necessary

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'user_sms_did';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['user_id', 'required'],
            ['status, rateCenterId, created_by, deleted_by', 'numerical', 'integerOnly' => true],
            ['user_id, did_id', 'length', 'max' => 11],
            ['created_at, deleted_at, defective', 'safe'],
            ['providerId, npa, nxx', 'safe', 'on' => 'insert'],
            ['id, user_id, did_id, created_at, deleted_at, created_by, deleted_by, status', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id'           => 'ID',
            'user_id'      => 'User',
            'did_id'       => 'Did',
            'created_at'   => 'Created At',
            'created_by'   => 'Created By',
            'deleted_at'   => 'Deleted At',
            'deleted_by'   => 'Deleted By',
            'status'       => 'Status',
            'rateCenterId' => 'Rate Center',
            'providerId'   => 'Provider',
            'npa'          => 'NPA',
            'nxx'          => 'NXX',
        ];
    }

    /**
     * @return ActiveQuery|SmsIn[]
     */
    public function getSmsIns()
    {
        return $this->hasMany(SmsIn::className(), ['user_sms_did_id' => 'did_id']);
    }

    /**
     * @return ActiveQuery|SmsOut[]
     */
    public function getSmsOuts()
    {
        return $this->hasMany(SmsOut::className(), ['user_sms_did_id' => 'did_id']);
    }

    /**
     * @return ActiveQuery|Client
     */
    public function getClient()
    {
        return $this->hasOne(Client::className(), ['user_id' => 'user_id']);
    }

    /**
     * @return ActiveQuery|Did
     */
    public function getDid()
    {
        return $this->hasOne(Client::className(), ['did_id' => 'did_id']);
    }

    public function afterSave()
    {
        /**
         * @var Did $did
         */
        // check if status was changed and update related dids
        if ($this->isAttributeDirty('status')) {
            if ((int)$this->status === 0) {
                // update associated did
                $did = Did::findOne($this->did_id);
                if ($did !== null) {
                    // note: currently, marking as defective is treated the same as removing - need to work out a better solution
                    $did->did_in_use = 0;
                    $did->did_available = 1;    // allow voice calls even if defective for SMS
                    $did->did_user_id   = 0;
                    $did->sms_status = 0;   // remove from SMS use whether defective or not for now until we can decide how best to add DIDs back after a certain period of time
                    $did->save();
                }
            }
            // the following was causing orphaned dids when a user did was re-activated to a status other than active
            else {
                // update associated did
                $did = Did::findOne($this->did_id);
                if ($did !== null && $did->did_in_use == 0 && $did->did_available > 0) {
                    $did->did_in_use    = 1;
                    $did->did_available = 1;
                    $did->did_user_id   = $this->user_id;
                    $did->sms_status    = 2;
                    $did->save();
                } elseif ((int)$this->originalAttributes['status'] === 0) {
                    // revert back to the original status if it was cancelled
                    $this->status = 0;
                    $this->save(false, ['status']);
                }
            }
        }

        parent::afterSave();
    }

    /**
     * @param bool $permanently
     *
     * @return bool|false|int
     * @throws Exception
     */
    public function delete($permanently = false)
    {
        if ($permanently) {
            return parent::delete();
        }

        if (!$this->getIsNewRecord()) {
            Yii::trace(get_class($this) . '.delete()', 'system.db.ar.CActiveRecord');
            if ($this->beforeDelete()) {
                // note: deleted_at and by are handled by before save
                $this->status = 0;
                $result       = $this->save();
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
     * @return bool
     */
    public function beforeSave()
    {
        if (parent::beforeSave()) {

            if ($this->isNewRecord) {
                $this->created_at = date('Y-m-d H:i:s');
                $this->created_by = Yii::$app->getUser()->getId();
                if (empty($this->did_id)) {
                    $this->did_id = (new Did())->getAvailableDidId($this->rateCenterId, [], $this->providerId, $this->npa, $this->nxx, null, true);
                }
                if (empty($this->did_id)) {
                    return false;
                }
            } elseif ($this->getDirtyAttributes('status')) {
                if ((int)$this->status === 0) {
                    $this->deleted_at = date("Y-m-d H:i:s");
                    $this->deleted_by = Yii::$app->getUser()->getId();
                } elseif ((int)$this->getDirtyAttributes()['status'] === 0 && (int)$this->status > 0) {
                    $this->deleted_at = null;
                    $this->deleted_by = null;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @deprecated
     * @return array
     */
    public function scopes()
    {
        return [
            'enabled'  => [
                'condition' => 'status!=0',
            ],
            'byClient' => [
                'with'  => 'client',
                'group' => 'client.user_id',
            ],
            'recover'  => [
                'with'      => 'did',
                'condition' => 't.status=0 AND did.did_in_use = 0 AND did.did_available = 1',
            ],
        ];
    }

    /**
     * @deprecated
     * @return string
     */
    public function getDidInfo()
    {
        $ret = '<div style="text-align:center;">';
        if (!empty($this->did->provider)) {
            $ret .= Html::encode($this->did->provider->provider_name) . '<br />';
        }
        if (!empty($this->did->country)) {
            $did     = in_array($this->did->country_id, [37, 223]) ? $this->did->did_area_code . '-' . $this->did->did_prefix . '-' . $this->did->did_line : $this->did->did;
            $fullDid = '+' . Html::encode($this->did->country->country_phone_code . ' ' . $did);
            $ret     .= Html::dialogLink($fullDid, "clientDid/showHistory", "", "", ["didId" => $this->did_id]) . '<br />';
        }
        if (!empty($this->did->rateCenter))
        {
            $ret .= Html::encode($this->did->rateCenter->rate_center . ', ' . $this->did->rateCenter->rate_center_state);
        }
        $ret .= '</div>';

        return $ret;
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @param bool $recover
     *
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search($recover = false)
    {

        $query = new ActiveQuery();

        $query->with = [
            'client',
            'did' => [
                'with' => [
                    'country',
                    'rateCenter',
                    'provider',
                ],
            ],
        ];

        $query->filterWhere(['like', 't.id', $this->id]);
        $query->andFilterWhere(['=', 't.user_id', $this->user_id]);
        $query->andFilterWhere(['=', 't.did_id', $this->did_id]);
        $query->andFilterWhere(['like', 't.created_at', $this->created_at]);
        $query->andFilterWhere(['=', 't.status', $this->status]);

        return new ActiveDataProvider([
            'query'   => $query,
            'sort'       => [
                'defaultOrder' => 't.created_at DESC',
            ],
            'pagination' => false,
        ]);
    }
}
