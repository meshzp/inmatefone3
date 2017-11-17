<?php

namespace backend\models;

use Yii;
use backend\helpers\Globals;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\behaviors\BlameableBehavior;

/**
 * This is the model class for table "user_plans".
 *
 * The followings are the available columns in table 'user_plans':
 * @property string $user_plan_id
 * @property integer $user_id
 * @property integer $plan_id
 * @property integer $used
 * @property integer $allowance
 * @property integer $allowance_m
 * @property string $countries
 * @property integer $status
 * @property string $datetime
 * @property integer $admin_id
 * @property integer $in_trial
 *
 * @property User $admin
 * @property Client $client
 * @property Plan $plan
 */
class ClientPlan extends ActiveRecord // TODO: Inherited from \protected\components\ActiveRecord.php
{
    /**
     * @var string
     */
    public $addMinutes;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%user_plans}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['addMinutes'], 'required', 'on' => ['addMinutes']],
            [['user_id', 'plan_id', 'used', 'allowance', 'allowance_m', 'status', 'admin_id', 'addMinutes'], 'integer'],
            [['countries'], 'max' => 255],
            [['datetime', 'countries'], 'safe'],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['user_plan_id', 'user_id', 'plan_id', 'used', 'allowance', 'allowance_m', 'countries', 'status', 'datetime', 'admin_id'], 'safe', 'on' => ['search']],
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
                'condition' => 'status=1',
            ],
        ];
    }

    /**
     * @return array customized attribute labels (name => label)
     */
    public function attributeLabels()
    {
        return [
            'user_plan_id' => 'User Plan',
            'user_id'      => 'User',
            'plan_id'      => 'Plan',
            'used'         => 'Used',
            'allowance'    => 'Left',
            'allowance_m'  => 'Allowance M',
            'countries'    => 'Countries',
            'status'       => 'Status',
            'datetime'     => 'Datetime',
            'admin_id'     => 'Admin',
            'addMinutes'   => 'Add Minutes',
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
            'query'      => $query,
            'sort'       => [
                'defaultOrder' => [
                    'datetime' => SORT_DESC,
                ],
            ],
            'pagination' => false,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'user_plan_id' => $this->user_plan_id,
            'user_id'      => $this->user_id,
            'plan_id'      => $this->plan_id,
            'used'         => $this->used,
            'allowance'    => $this->allowance,
            'allowance_m'  => $this->allowance_m,
            'countries'    => $this->countries,
            'status'       => $this->status,
            'datetime'     => $this->datetime,
            'admin_id'     => $this->admin_id,
        ]);

        return $dataProvider;
    }

    /**
     * @deprecated
     *
     * @param bool $permanently
     *
     * @return bool
     */
    public function delete($permanently = false)
    {
        if ($permanently) {
            if (parent::delete()) {
                if (!empty($this->plan)) {
                    $client = Client::findOne($this->user_id);
                    if ($client !== null) {
                        $client->suggestedMrc;
                        if (!$client->save(true, ['user_suggested_mrc'])) {
                            Yii::$app->session->setFlash('warning', 'Error Updating Suggested MRC');
                        }
                    }
                }

                return true;
            }

            return false;
        }

        if (!$this->getIsNewRecord()) {
            Yii::trace(get_class($this) . '.delete()', 'system.db.ar.CActiveRecord');
            if ($this->beforeDelete()) {
                $this->status = 0;
                $result       = $this->save();
                if ($result && !empty($this->plan)) {
                    // load client model and update suggested mrc
                    $client = Client::findOne($this->user_id);
                    if ($client !== null) {
                        $client->suggestedMrc;
                        if (!$client->save(true, ['user_suggested_mrc'])) {
                            Yii::$app->session->setFlash('warning', 'Error Updating Suggested MRC');
                        }
                    }
                }
                $this->afterDelete();

                return $result;
            } else {
                return false;
            }
        } else {
            throw new CDbException(Yii::t('yii', 'The active record cannot be deleted because it is new.'));
        }
    }

    /**
     * @return bool|null
     */
    public function hasExpired()
    {
        $trialEndDate = $this->trialEndDate;

        return $trialEndDate ? ($trialEndDate <= date('Y-m-d')) : null;
    }

    /**
     * @return bool
     */
    public function getInTrial()
    {
        if (!empty($this->plan) && $this->plan->hasTrial) {
            return ($this->in_trial > 0);
        }

        return false;
    }

    /**
     * @param bool $asTime
     *
     * @return false|int|null|string
     */
    public function getTrialEndDate($asTime = false)
    {
        if (!empty($this->plan) && $this->plan->hasTrial && $this->client) {
            $savedBillDay = (int)$this->client->user_bill_day;

            $startTime = strtotime($this->datetime);
            $startDay  = date('d', $startTime);

            // reduce the trial months by 1 if bill day is yet to come this month
            $months = $this->plan->plan_trial_months;
            if ($savedBillDay > (int)$startDay && $months > 0) {
                $months--;
            }

            $startMonth = date('Y-m-01', $startTime);   // use the 1st to avoid end of month problems
            $interval   = '+' . $months . ' months';
            // work out if we need to adjust the bill day due to a shorter month
            $endMonthLastDay = date('t', strtotime($interval, $startTime));
            $billDay         = $savedBillDay > $endMonthLastDay ? $endMonthLastDay : str_pad($savedBillDay, 2, '0', STR_PAD_LEFT);

            $endDate = date('Y-m-' . $billDay, strtotime($interval, strtotime($startMonth)));

            return $asTime ? strtotime($endDate) : $endDate;
        }

        return null;
    }

    /**
     * @deprecated
     * @return string
     */
    public function getMrc()
    {
        if (!empty($this->plan)) {
            $currency = $this->plan->plan_currency;
            $symbol   = Currency::model()->getCurrencySymbolByPrefix($currency);
            $mrc      = $this->inTrial ? $this->plan->plan_trial_amount : $this->plan->plan_mrc;

            return $symbol . ' ' . $mrc . ' ' . $currency;
        }
    }

    /**
     * @deprecated
     * @return string
     */
    public function getUsedLeft()
    {
        if (!empty($this->plan)) {
            // create add button
            $url       = Url::to(['clientPlan/addMinutes', ['asDialog' => 1, 'gridId' => 'client-plan-grid', 'clientId' => $this->user_id, 'clientPlanId' => $this->user_plan_id]]);
            $addButton = Yii::app()->controller->widget('bootstrap.widgets.TbButton', [
                'label'       => 'Add',
                'type'        => 'primary',
                'url'         => 'javascript:void(0)',
                'size'        => 'mini',
                'htmlOptions' => [
                    'onclick' => "$('#popup-frame').attr('src','$url '); $('#popup-dialog').dialog('option','title','Add Minutes'); $('#popup-dialog').dialog('open');",
                ],
            ], true);

            $minutes     = $this->plan->plan_termination_minutes;
            $cost_fixed  = $this->plan->plan_termination_cost_fixed;
            $cost_mobile = $this->plan->plan_termination_cost_mobile;
            $allowance   = $this->allowance < 0 ? 0 : $this->allowance;
            if ($minutes == 0 && ($cost_fixed != 0.00 || $cost_mobile != 0.00)) {
                $allowance = '--';
            } elseif ($minutes != 0 && ($cost_fixed != 0.00 || $cost_mobile != 0.00)) {
                if ($this->used >= $minutes) {
                    $allowance = '--';
                } else {
                    $allowance .= '&nbsp;' . $addButton;
                }
            } elseif ($minutes == 0 && $cost_fixed == 0.00 && $cost_mobile == 0.00) {
                $allowance = '--';
            } else {
                $allowance .= '&nbsp;' . $addButton;
            }

            return $this->used . ' / ' . $allowance;
        }

        return '--';
    }

    /**
     * @return string
     */
    public function getUsedLeftClientView()
    {
        if (!empty($this->plan)) {
            $minutes     = $this->plan->plan_termination_minutes;
            $cost_fixed  = $this->plan->plan_termination_cost_fixed;
            $cost_mobile = $this->plan->plan_termination_cost_mobile;
            $allowance   = $this->allowance < 0 ? 0 : $this->allowance;
            if ($minutes == 0 && ($cost_fixed != 0.00 || $cost_mobile != 0.00)) {
                $allowance = '--';
            } elseif ($minutes != 0 && ($cost_fixed != 0.00 || $cost_mobile != 0.00)) {
                if ($this->used >= $minutes) {
                    $allowance = '--';
                } else {
                    $allowance .= '&nbsp;';
                }
            } elseif ($minutes == 0 && $cost_fixed == 0.00 && $cost_mobile == 0.00) {
                $allowance = '--';
            } else {
                $allowance .= '&nbsp;';
            }

            return $this->used . ' / ' . $allowance;
        }

        return '--';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class'              => TimestampBehavior::className(),
                'createdAtAttribute' => 'datetime',
                'updatedAtAttribute' => false,
                'value'              => date('Y-m-d H:i:s'),
            ],
            [
                'class'              => BlameableBehavior::className(),
                'createdByAttribute' => 'admin_id',
                'updatedByAttribute' => false,
                'value'              => Globals::user()->id,
            ],
        ];
    }

    /**
     * @deprecated
     * @return bool
     */
    public function beforeSave()
    {
        if ($this->isNewRecord) {
            // add as free trial if necessary
            if (!empty($this->plan) && $this->plan->hasTrial) {
                $this->in_trial = 1;
            } else {
                $this->in_trial = 0;
            }
        }

        if ($this->scenario == 'addMinutes') {
            $this->allowance = new CDbExpression('( allowance + :addMinutes )', [':addMinutes' => $this->addMinutes]);
        }

        return parent::beforeSave();
    }

    /**
     * @deprecated
     */
    public function afterSave()
    {
        if (!$this->client->isAlertMinutes()) {
            $this->client->minutes_notification_send_date = null;
            $this->client->save(true, ['minutes_notification_send_date']);
        }

        parent::afterSave();
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAdmin()
    {
        return $this->hasOne(User::className(), ['admin_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getClient()
    {
        return $this->hasOne(Client::className(), ['user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPlan()
    {
        return $this->hasOne(Plan::className(), ['plan_id' => 'id']);
    }
}
