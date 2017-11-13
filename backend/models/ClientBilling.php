<?php

namespace backend\models;

use Yii;
use backend\helpers\Globals;
use yii\base\Exception;
use yii\db\ActiveRecord;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use backend\helpers\Util;
use yii\behaviors\TimestampBehavior;
use yii\behaviors\BlameableBehavior;

/**
 * This is the model class for table "user_billing_details".
 *
 * The followings are the available columns in table 'user_billing_details':
 * @property string $billing_id
 * @property integer $user_id
 * @property string $cc_type
 * @property string $cc_number
 * @property string $cc_exp_date
 * @property string $cc_cvv
 * @property string $cc_last_4
 * @property string $billing_first_name
 * @property string $billing_last_name
 * @property integer $billing_country
 * @property string $billing_address
 * @property string $billing_state
 * @property string $billing_city
 * @property string $billing_zip
 * @property integer $billing_status
 * @property string $billing_datetime
 * @property integer $billing_by
 * @property integer $billing_priority
 * @property integer $allow_auto_billing
 * @property integer $user_display
 * @property integer $flagged
 * @property integer $flagged_by
 * @property string $flagged_reason
 * @property string $flagged_datetime
 * @property integer $flagged_count
 *
 * @property User $admin
 * @property CountryCode $country
 * @property Client $client
 * @property Client $clientModel
 * @property bool $isPaymentScenario
 */
class ClientBilling extends ActiveRecord // Наследуется от \protected\components\ActiveRecord
{

    const CC_TYPE_AMEX       = 'AMEX';
    const CC_TYPE_DINERS     = 'DINERS';
    const CC_TYPE_DISCOVER   = 'DISCOVER';
    const CC_TYPE_JCB        = 'JCB';
    const CC_TYPE_MASTERCARD = 'MASTERCARD';
    const CC_TYPE_VISA       = 'VISA';

    // used in quickpay...
    public $chargeAmount;
    public $clientModel;
    public $existing;   // used in consumer site
    public $existingCard;   // used in consumer site
    public $confirm;   // used in consumer site
    public $saveCard = 0;   // used in consumer site

    public $journalEntry = 0;

    public $cc_masked;

    private $_ccTypeOptions;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return '{{%user_billing_details}}';
    }

    /**
     * ClientBilling uses 5 x scenarios insert,update,insertAndCharge,updateAndCharge,charge(quickpay i.e. charge but don't save card)
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            [['cc_type', 'cc_number', 'cc_exp_date', 'cc_cvv', 'cc_last_4', 'billing_first_name', 'billing_last_name', 'billing_address', 'billing_city', 'billing_country', 'user_id', 'allow_auto_billing', 'user_display'], 'required', 'on' => ['insert', 'update']],
            [['cc_type', 'cc_number', 'cc_exp_date', 'cc_cvv', 'cc_last_4', 'billing_first_name', 'billing_last_name', 'billing_address', 'billing_city', 'billing_country', 'user_id', 'allow_auto_billing', 'user_display', 'chargeAmount'], 'required', 'on' => ['insertAndCharge', 'updateAndCharge']],
            [['cc_type', 'cc_number', 'cc_exp_date', 'cc_cvv', 'cc_last_4', 'billing_first_name', 'billing_last_name', 'billing_address', 'billing_city', 'billing_country', 'user_id'], 'required', 'on' => ['quickpay', 'clientQuickpay', 'phonepay', 'phoneQuickpay']],
            [['chargeAmount'], 'integer', 'min' => 10, 'message' => 'The amount to be charged must be $10 or greater', 'on' => ['quickpay', 'clientQuickpay', 'insertAndCharge', 'updateAndCharge']], // @todo should this include phonepay,phoneQuickpay
            [['cc_number', 'user_id', 'billing_country', 'billing_status', 'billing_by', 'billing_priority', 'allow_auto_billing', 'cc_exp_date', 'cc_cvv', 'user_display', 'flagged', 'flagged_by', 'flagged_count'], 'integer'],
            [['cc_type', 'cc_number', 'billing_first_name', 'billing_last_name', 'billing_address', 'billing_state', 'billing_city', 'billing_zip'], 'max' => 255],
            [['flagged_reason'], 'max' => 100],
            [['cc_last_4', 'cc_exp_date', 'cc_cvv'], 'max' => 4],
            [['billing_datetime', 'existing', 'confirm', 'flagged_datetime'], 'safe'],

            [['saveCard'], 'safe', 'on' => ['clientQuickpay']],

            [['chargeAmount'], 'safe', 'on' => ['insert', 'update']],
            [['journalEntry'], 'safe', 'on' => ['quickpay', 'insertAndCharge', 'updateAndCharge']],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            [['billing_id', 'user_id', 'cc_type', 'cc_number', 'cc_exp_date', 'cc_cvv', 'cc_last_4', 'billing_first_name', 'billing_last_name', 'billing_country', 'billing_address', 'billing_state', 'billing_city', 'billing_zip', 'billing_status', 'billing_datetime', 'billing_by', 'billing_priority'], 'safe', 'on' => ['search']],
        ];
    }

    /**
     * @return array customized attribute labels (name => label)
     */
    public function attributeLabels()
    {
        return [
            'billing_id'         => 'ID',
            'user_id'            => 'Client',
            'cc_type'            => 'Card Type',
            'cc_number'          => 'Card Number',
            'cc_exp_date'        => 'Card Exp. Date',
            'cc_cvv'             => 'Card Cvv',
            'cc_last_4'          => 'Card Last 4 Digits',
            'billing_first_name' => 'First Name',
            'billing_last_name'  => 'Last Name',
            'billing_country'    => 'Country',
            'billing_address'    => 'Address',
            'billing_state'      => 'State',
            'billing_city'       => 'City',
            'billing_zip'        => 'Zip',
            'billing_status'     => 'Status',
            'billing_datetime'   => 'Datetime',
            'billing_by'         => 'By',
            'billing_priority'   => 'Priority',
            'allow_auto_billing' => 'Allow Automatic Billing?',
            'user_display'       => 'Visible On Consumer Site?',

            'existing'  => '',
            'cc_masked' => 'Card Number',
            //'journalEntry' => 'Journal Entry?',

            'saveCard' => 'Save Card?',
        ];
    }

    // TODO: Не понятно как возможно это организовать в Yii2
    public function scopes()
    {
        return [
            'enabled' => [
                'condition' => 'billing_status=1',
            ],
        ];
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @param string $params
     *
     * @return DataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search($params)
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.


        $query = self::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => [
                    'billing_priority' => SORT_ASC,
                ],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'billing_id'         => $this->billing_city,
            'user_id'            => $this->billing_id,
            'cc_type'            => $this->user_id,
            'cc_number'          => $this->cc_type,
            'cc_exp_date'        => $this->cc_number,
            'cc_cvv'             => $this->cc_exp_date,
            'cc_last_4'          => $this->cc_cvv,
            'billing_first_name' => $this->cc_last_4,
            'billing_last_name'  => $this->billing_first_name,
            'billing_country'    => $this->billing_last_name,
            'billing_address'    => $this->billing_country,
            'billing_state'      => $this->billing_address,
            'billing_city'       => $this->billing_state,
            'billing_zip'        => $this->billing_city,
            'billing_status'     => $this->billing_zip,
            'billing_datetime'   => $this->billing_status,
            'billing_by'         => $this->billing_datetime,
            'billing_priority'   => $this->billing_by,
            'allow_auto_billing' => $this->billing_priority,
        ]);

        return $dataProvider;
    }

    /**
     * @return string
     */
    public function getDetails()
    {
        $countryName      = empty($this->country->country_name) ? '' : $this->country->country_name;
        $countryFlag      = Html::img(Yii::$app->request->baseUrl . '/img/country_flags/' . $this->country->country_code_alpha_3 . '.png', ['alt' => $this->country->country_name]);
        $autoBillingLabel = $this->allow_auto_billing ? '<span class="label label-success">Auto Bill</span>' : '<span class="label label-important">No Auto Bill</span>';
        if (!Util::validExpiryDate($this->cc_exp_date)) {
            // expired
            $expiryDate = '<span class="label label-important">Expired ' . $this->cc_exp_date . '</span>';
        } elseif (trim($this->cc_exp_date) == date('my')) {
            // expiring this month
            $expiryDate = '<span class="label label-warning">Expiring ' . $this->cc_exp_date . '</span>';
        } else {
            $expiryDate = 'Expires ' . $this->cc_exp_date;
        }

        $flagged = '';
        if ($this->flagged > 0) {
            // flagged value should be 1 or 2. Value of 2 means unusable.
            $use     = $this->flagged_count < 2 ? 'use was' : $this->flagged_count . ' uses were';
            $flagged = $this->flagged == 1 ? '<br /><span class="label label-warning">Last $use declined!<br />@ ' . $this->flagged_datetime . '<br />' . $this->flagged_reason . '</span>'
                : '<br /><span class="label label-important">Flagged as unusable!<br />@ ' . $this->flagged_datetime . '<br />' . $this->flagged_reason . '</span>';
        }

        return '<strong>' . $this->cc_type . ' - ' . $this->cc_last_4 . '</strong> ' . $autoBillingLabel . '<br />'
                . $this->billing_first_name . ' ' . $this->billing_last_name . '<br />'
                . $this->billing_address . ', ' . $this->billing_city . ' ' . $this->billing_zip . ' ' . $this->billing_state . '<br />'
                . $countryFlag . ' ' . $countryName . '<br />'
                . $expiryDate . ' '
                . $flagged;
    }

    /**
     * @param null|int $value
     *
     * @return string|array
     */
    public function getCCTypeOptions($value = null)
    {
        if (empty($this->_ccTypeOptions)) {
            $optionNames          = [
                self::CC_TYPE_AMEX       => 'AMERICAN EXPRESS',
                self::CC_TYPE_DINERS     => 'DINERS',
                self::CC_TYPE_DISCOVER   => 'DISCOVER',
                self::CC_TYPE_JCB        => 'JCB',
                self::CC_TYPE_MASTERCARD => 'MASTERCARD',
                self::CC_TYPE_VISA       => 'VISA',
            ];
            $this->_ccTypeOptions = self::getConstants('CC_TYPE_', __CLASS__, $optionNames);
        }

        return $value !== null && isset($this->_ccTypeOptions[$value]) ? $this->_ccTypeOptions[$value] : $this->_ccTypeOptions;
    }

    /**
     * @param bool $webDisplay
     *
     * @return array
     */
    public function getCardList($webDisplay = true)
    {
        if (!$this->user_id) {
            return [];
        }
        $displayOnly = $webDisplay ? ' AND user_display = 1' : '';

        return ArrayHelper::map(self::findAll([ // TODO: Не уверен что будет работать этот кусок кода, думаю что его стоит заменить
            'select'    => "billing_id, CONCAT(cc_type,' - ',cc_last_4) AS cc_type",
            'order'     => 'billing_priority ASC',
            'condition' => 'user_id = :clientId AND billing_status=1' . $displayOnly,
            'params'    => [':clientId' => $this->user_id],
        ]), 'billing_id', 'cc_type');
    }

    /**
     * @param bool $permanently
     *
     * @return bool|false|int
     */
    public function delete($permanently = false)
    {
        if ($permanently) {
            return parent::delete();
        }

        if (!$this->getIsNewRecord()) {
            Yii::trace(get_class($this) . '.delete()', 'system.db.ar.CActiveRecord');
            if ($this->beforeDelete()) {
                $this->billing_status = 0;
                $result               = $this->save(false, ['billing_status']);
                $this->afterDelete();

                return $result;
            } else {
                return false;
            }
        } else {
            // TODO: Нужно подключить другой класс для исключения
            throw new CDbException(Yii::t('yii', 'The active record cannot be deleted because it is new.'));
        }
    }

    /**
     * @deprecated
     * @return bool
     */
    public function beforeValidate()
    {
        // check user id is present
        if (empty($this->user_id)) {
            $this->addError('user_id', 'The client ID has not been found');
        }

        // note: existing is used in consumer site only at the moment
        if (empty($this->existing)) {
            // strip non-digit characters from cc_number and expiry date
            $this->cc_exp_date = preg_replace('/[^0-9]/', '', $this->cc_exp_date);
            $this->cc_number   = preg_replace('/[^0-9]/', '', $this->cc_number);
            $this->cc_last_4   = substr($this->cc_number, -4);

            // check for valid credit card number
            // TODO: Нужно заменить этот кусок кода
            Yii::import('ext.validators.ECCValidator');
            $cc = new ECCValidator(); // TODO: Нужно заменить класс
            // validate the number (this will add the format to the class format property)
            // TODO: use an error array in the validator class to store specific errors that we can output here
            if (!$cc->validateNumber($this->cc_number)) {
                $this->addError('cc_number', 'The card number is not valid.');
            }
            $this->cc_type = $cc->format;

            // check the date is correct
            if (strlen($this->cc_exp_date) != 4) {
                $this->addError('cc_exp_date', 'Invalid expiry date format. Please use MMYY as digits.');
            }
        }

        // check amount in payment scenarios
        //$paymentScenarios = array('insertAndCharge','updateAndCharge','quickpay','clientQuickpay');
        if ($this->isPaymentScenario) {
            $this->chargeAmount = preg_replace('/[^0-9.]/', '', $this->chargeAmount);
            if ($this->chargeAmount <= 0) {
                $this->addError('chargeAmount', 'Please enter a valid amount above 0.');
            }
        }

        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'createdAtAttribute' => 'billing_datetime',
                'updatedAtAttribute' => false,
                'value' => date('Y-m-d H:i:s'),
            ],
            [
                'class' => BlameableBehavior::className(),
                'createdByAttribute' => 'billing_by',
                'updatedByAttribute' => false,
                'value' => Globals::user()->id,
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
            $priority               = Yii::$app->db->createCommand() // TODO: Нужно заменить, оставил что бы не поломать
                ->select('max(billing_priority)')
                ->from($this->tableName())
                ->where('user_id=:user_id AND billing_status=1', [':user_id' => $this->user_id])
                ->queryScalar();
            $this->billing_priority = empty($priority) ? 1 : $priority + 1;
        }

        return parent::beforeSave();
    }

    /**
     * @return bool
     */
    public function getIsPaymentScenario()
    {
        $paymentScenarios = ['insertAndCharge', 'updateAndCharge', 'quickpay', 'clientQuickpay', 'phonepay', 'phoneQuickpay'];

        return in_array($this->scenario, $paymentScenarios);
    }

    /**
     * @param string $comment
     *
     * @return bool
     */
    public function quickPay($comment = 'Online Credit Purchase')
    {
        // make sure we have the client model at hand
        if (empty($this->clientModel) && !empty($this->user_id)) {
            $this->clientModel = Client::find()->with('currency')->findOne($this->user_id);
        }

        if ($this->validate() && !empty($this->clientModel)) {
            $params = [
                'profile_id'  => $this->billing_id,   // no card associated when dealing with new model
                'amount'      => $this->chargeAmount,
                'currency'    => $this->clientModel->user_currency,
                'cc_type'     => $this->cc_type,
                'cc_number'   => $this->cc_number,
                'cc_exp_date' => $this->cc_exp_date,
                'cc_cvv'      => $this->cc_cvv,
                'first_name'  => $this->billing_first_name,
                'last_name'   => $this->billing_last_name,
                'address_1'   => $this->billing_address,
                'city'        => $this->billing_city,
                'state'       => $this->billing_state,
                'zip'         => $this->billing_zip,
                'country'     => @$this->country->country_name,
                'reason'      => $this->journalEntry ? 'Journal Entry Payment' : 'Credit Purchase',
                'comment'     => $comment,
            ];

            // do the charge
            // TODO: re-format this function so we just need to send this model rather than all those params?
            return Transaction::chargeCC($this->clientModel->user_id, $params);
        } else {
            return false;
        }
    }

    /**
     * @deprecated
     * New function to charge existing cards - only used for phonepay at the moment
     *
     * @param integer $payment_type The payment type - see ClientTransaction for constants to use
     *
     * @return bool
     * @throws Exception
     */
    public function chargeThis($payment_type = null)
    {
        // check if card is valid
        if ($this->isNewRecord) {
            throw new Exception('The card cannot be charged because it is new.');
        }
        if (empty($this->billing_status)) {
            throw new Exception('The card cannot be charged because it is not enabled.');
        }
        if ($this->flagged == 2) {
            throw new Exception('The card cannot be charged because it has been flagged as unusable.');
        }

        // make sure we have the client model at hand
        if (empty($this->clientModel) && !empty($this->user_id)) {
            $this->clientModel = Client::find()->with('currency')->findOne($this->user_id);
        }

        $comment = 'Online Credit Purchase';

        // get correct payment_type
        if ($payment_type === null) {
            switch ($this->scenario) {
                case 'insertAndCharge':
                case 'updateAndCharge':
                    $payment_type = ClientTransaction::PAYMENT_TYPE_PORTAL_CARD_ON_FILE;
                    break;
                case 'quickpay':
                    $payment_type = ClientTransaction::PAYMENT_TYPE_PORTAL_CARD_QUICKPAY;
                    break;
                case 'clientQuickpay':
                    $payment_type = ClientTransaction::PAYMENT_TYPE_CLIENT_CARD_QUICKPAY;
                    break;
                case 'phonepay':
                    $comment      = 'Phone Credit Purchase';
                    $payment_type = ClientTransaction::PAYMENT_TYPE_CLIENT_CARD_PHONEPAY;
                    break;
                case 'phoneQuickpay':
                    // this isn't really used by this at the moment - see the apicontroller
                    $payment_type = ClientTransaction::PAYMENT_TYPE_CLIENT_CARD_PHONEQUICKPAY;
                    break;
            }
        }

        if ($this->validate() && !empty($this->clientModel)) {
            $params = [
                'profile_id'   => $this->billing_id,   // no card associated when dealing with new model
                'amount'       => $this->chargeAmount,
                //'currency' => $this->clientModel->user_currency,  // don't worry about client currency for the moment - should always be USD for now (default)
                'cc_type'      => $this->cc_type,
                'cc_number'    => $this->cc_number,
                'cc_exp_date'  => $this->cc_exp_date,
                'cc_cvv'       => $this->cc_cvv,
                'first_name'   => $this->billing_first_name,
                'last_name'    => $this->billing_last_name,
                'address_1'    => $this->billing_address,
                'city'         => $this->billing_city,
                'state'        => $this->billing_state,
                'zip'          => $this->billing_zip,
                'country'      => @$this->country->country_name,
                'reason'       => 'Credit Purchase',
                'comment'      => $comment,
                'payment_type' => $payment_type,
            ];

            // do the charge
            // TODO: re-format this function so we just need to send this model rather than all those params?
            return TransactionFd::chargeCC($this->clientModel->user_id, $params);
        } else {
            throw new Exception('The card cannot be charged because it failed validation.');
        }
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAdmin()
    {
        return $this->hasOne(User::className(), ['billing_by' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCountry()
    {
        return $this->hasOne(CountryCode::className(), ['billing_country' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getClient()
    {
        return $this->hasOne(Client::className(), ['user_id' => 'id']);
    }
}
