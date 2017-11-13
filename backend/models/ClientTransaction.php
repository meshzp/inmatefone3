<?php

namespace backend\models;

use backend\helpers\Globals;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Transaction;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * This is the model class for table "user_transactions".
 *
 * The followings are the available columns in table 'user_transactions':
 * @property string $transaction_id
 * @property string $transaction_currency
 * @property integer $user_id
 * @property integer $profile_id
 * @property integer $cdr_id
 * @property string $credit_update
 * @property string $new_balance
 * @property string $reason
 * @property string $comment
 * @property string $datetime
 * @property integer $year
 * @property integer $month
 * @property integer $day
 * @property integer $admin_id
 * @property string $ip_address
 * @property string $token
 * @property integer $quick_pay
 * @property integer $process_balance
 * @property integer $linked_to
 * @property integer $reversed_by
 * @property integer $payment_type
 * @property integer $dispute_id
 * @property integer $sms_out_id
 *
 * @property Client $client
 * @property User $admin
 * @property Dispute $dispute
 * @property Transaction[] $transaction
 * @property TransactionFd[] $transactionFd
 * @property Mgpurchase[] $mgpurchase
 *
 */
class ClientTransaction extends ActiveRecord
{
    const PAYMENT_TYPE_SYSTEM                    = 1;
    const PAYMENT_TYPE_PORTAL_CARD_ON_FILE       = 2;
    const PAYMENT_TYPE_PORTAL_CARD_QUICKPAY      = 3;
    const PAYMENT_TYPE_CLIENT_CARD_ON_FILE       = 4;
    const PAYMENT_TYPE_CLIENT_CARD_QUICKPAY      = 5;
    const PAYMENT_TYPE_CLIENT_CARD_PHONEPAY      = 6;
    const PAYMENT_TYPE_CLIENT_CARD_PHONEQUICKPAY = 7;
    const PAYMENT_TYPE_MONEYGRAM                 = 8;
    const PAYMENT_TYPE_PAPER                     = 9;
    public $updateAmount;
    public $clientModel;
    public $fromDate;   //  = 0.00
    public $toDate;
    // used by financial report ...
    public $date;
    public $dateRange;
    public $year;
    public $month;
    public $payment_amount;   // now using year and month on financial reports to make it easier
    public $payment_count;
    public $payment_count_declined;
    public $refund_amount;
    public $refund_count;
    public $net_amount;
    public $cumulative_amount;
    public $chartType    = 'totals-bar';
    public $showCdr      = 1;
    public $showDeclined = 1;
    // used to hide/show certain values
    public $moneygramRef;
    public $moneygramAccountId;
    // used by MoneyGram
    public $checkOrderType;
    public $checkOrderNo;
    // used by Paper Instruments
    public $forceAdminId = false;
    /**
     * @var $currency Currency The currency model
     */
    private $_currency;
    private $_transferClient;   // force a different admin id?
    private $_chartData;

    /**
     * @return ActiveQuery
     */
    public function getClient()
    {
        return $this->hasOne(Client::className(), ['user_id' => 'user_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getAdmin()
    {
        return $this->hasOne(User::className(), ['admin_id' => 'admin_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getDispute()
    {
        return $this->hasOne(Dispute::className(), ['dispute_id' => 'dispute_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getTransaction()
    {
        return $this->hasMany(Transaction::className(), ['user_transaction_id' => 'transaction_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getTransactionFd()
    {
        return $this->hasMany(TransactionFd::className(), ['user_transaction_id' => 'transaction_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getMgpurchase()
    {
        return $this->hasMany(Moneygram::className(), ['purchase_user_transaction_id' => 'transaction_id']);
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @param bool $clientView
     *
     * @return ActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search($clientView = false)
    {

        $query = new ActiveQuery();
        if ($clientView) {
            $query->with = [
                'transaction'   => [
                    'alias' => 'transaction',
                ],
                'transactionfd' => [
                    'alias' => 'transactionfd',
                ],
            ];
        } else {
            $query->with = [
                'admin'         => [
                    'scopes' => 'name',
                    'alias'  => 'admin',
                ],
                'dispute'       => [
                    'alias' => 'dispute',
                ],
                'transaction'   => [
                    'alias' => 'transaction',
                ],
                'transactionfd' => [
                    'alias' => 'transactionfd',
                ],
                'mgpurchase'    => [
                    'alias' => 'mgpurchase',
                    'with'  => 'account',
                ],
            ];
        }

        $query->andFilterWhere(['like', 't.transaction_id', $this->transaction_id]);
        $query->andFilterWhere(['like', 't.transaction_currency', $this->transaction_currency]);
        $query->andFilterWhere(['=', 't.user_id', $this->user_id]);
        $query->andFilterWhere(['=', 't.profile_id', $this->profile_id]);
        $query->andFilterWhere(['=', 't.cdr_id', $this->cdr_id]);
        $query->andFilterWhere(['like', 't.credit_update', $this->credit_update]);
        $query->andFilterWhere(['like', 't.new_balance', $this->new_balance]);
        $query->andFilterWhere(['like', 't.reason', $this->reason]);
        $query->andFilterWhere(['like', 't.comment', $this->comment]);
        $query->andFilterWhere(['like', 't.datetime', $this->datetime]);
        $query->andFilterWhere(['=', 't.year', $this->year]);
        $query->andFilterWhere(['=', 't.month', $this->month]);
        $query->andFilterWhere(['=', 't.day', $this->day]);
        if (!empty($this->admin_id)) {
            $query->filterWhere("(t.admin_id = '" . $this->admin_id . "' OR admin.display_name LIKE '%" . $this->admin_id . "%')");
        }
        $query->andFilterWhere(['like', 't.ip_address', $this->ip_address]);
        $query->andFilterWhere(['like', 't.token', $this->token]);
        $query->andFilterWhere(['=', 't.quick_pay', $this->quick_pay]);
        $query->andFilterWhere(['=', 't.dispute_id', $this->dispute_id]);

        if (!$clientView) {
            if ($this->showCdr == 0) {
                $query->filterWhere("t.comment NOT LIKE 'CDR #%'");
            }
            if ($this->showDeclined == 0) {
                $query->filterWhere("t.comment != 'DECLINED'");
            }
        }

        return new ActiveDataProvider([
            'query' => $query,
            'sort'     => [
                'defaultOrder' => 't.transaction_id DESC',
            ],
        ]);
    }

    /**
     * For validation when transferring an amount to another client
     */
    public function checkReason($attribute, $params)
    {
        if ($this->reason == 'Transfer' || $this->reason == 'Reseller Credit') {
            $this->profile_id = Globals::numbersOnly($this->profile_id);
            if (empty($this->profile_id)) {
                $this->addError($attribute, 'Invalid Client ID');
            } else {
                $this->_transferClient = Client::findOne($this->profile_id);
                if ($this->_transferClient === null) {
                    $this->addError($attribute, 'Could not find the client to transfer to. Please try another ID.');
                } elseif ($this->clientModel->user_currency != $this->_transferClient->user_currency) {
                    $this->addError($attribute, 'Transfer is not possible because the two clients do not use the same currency.');
                } elseif ($this->reason == 'Reseller Credit') {
                    // check that the reseller has enough credit (including credit limit)
                    $limit = $this->_transferClient->user_limit;
                    if (empty($limit)) {
                        $limit = 0;
                    }
                    $creditAllowance = $this->_transferClient->user_balance + $limit;
                    if (($creditAllowance - $this->updateAmount) < 0) {
                        $this->addError($attribute, 'The reseller does not have enough credit for this transaction.');
                    }
                }
            }
        }
    }

    public function updateBalance()
    {
        if ($this->validate()) {
            $reason = $this->reason;
            if (in_array($reason, ['Fees and Charges', 'Credit Reversal', 'Refund', 'Transfer'])) {
                $this->updateAmount = 0 - $this->updateAmount;
            }

            if ($reason == 'Transfer') {
                $this->reason  = 'Transfer (Debit)';
                $this->comment = "Transfer to #$this->profile_id - {$this->_transferClient->user_first_name} {$this->_transferClient->user_last_name}";
            }

            // new reseller credit option
            if ($reason == 'Reseller Credit') {
                // check that the reseller has enough credit (including credit limit)
                // this is now done in the checkReason function above

                $this->comment = "Reseller Credit From #$this->profile_id - {$this->_transferClient->user_first_name} {$this->_transferClient->user_last_name}" . (empty($this->comment) ? '' : ' | ' . $this->comment);
            }

            $this->scenario             = 'insert';
            $this->transaction_currency = $this->clientModel->user_currency;
            $this->credit_update        = $this->updateAmount;
            // evrything else is either set already or set automatically
            if (!$this->save()) {
                return false;
            }

            if ($reason == 'Transfer') {
                $xfer                       = new ClientTransaction;
                $xfer->transaction_currency = $this->clientModel->user_currency;
                $xfer->user_id              = $this->profile_id;
                $xfer->profile_id           = $this->user_id;
                $xfer->credit_update        = $this->updateAmount * (-1);
                $xfer->reason               = 'Transfer (Credit)';
                $xfer->comment              = "Transfer from #$this->user_id - {$this->clientModel->user_first_name} {$this->clientModel->user_last_name}";
                if (!$xfer->save()) {
                    return false;
                }
            }

            // we need to create a new client transaction for the reseller being transferred from (payee)
            if ($reason == 'Reseller Credit') {
                // Purchase charge for reseller
                $fee                       = new ClientTransaction;
                $fee->transaction_currency = $this->clientModel->user_currency;
                $fee->user_id              = $this->profile_id;
                $fee->profile_id           = $this->user_id;
                $fee->credit_update        = 0 - $this->updateAmount;
                $fee->linked_to            = $this->transaction_id;
                $fee->reason               = 'Fees and Charges';
                $fee->comment              = "Reseller Payment For #$this->user_id - {$this->clientModel->user_first_name} {$this->clientModel->user_last_name} TID: " . $this->transaction_id;
                if (!$fee->save()) {
                    return false;
                }

                // save link on this transaction
                $this->linked_to = $fee->transaction_id;
                $this->save(false, ['linked_to']);

                $this->processResellerReferral();
            }

            return true;
        } else {
            return false;
        }
    }

    public function processResellerReferral()
    {
        // reason must be a reseller credit
        if ($this->reason != 'Reseller Credit') {
            return false;
        }

        if ($this->credit_update <= 0) {
            return false;
        }

        // get client model
        /** @var Client $clientModel */
        $clientModel = Client::findOne($this->user_id);
        if (empty($clientModel)) {
            return false;
        }

        // check currencies match
        if ($clientModel->user_currency != $this->transaction_currency) {
            return false;
        }

        // check that this transaction hasn't already been added as a referral
        if ($this->exists('reason = "Referral Credit" AND linked_to = :linkedTo', [':linkedTo' => $this->transaction_id])) {
            return false;
        }

        $referredClientId = $this->profile_id;
        /** @var Client $referredClientModel */
        $referredClientModel = Client::findOne($referredClientId);
        if (empty($referredClientModel)) {
            return false;
        }

        // use fixed credit first, then percentage, then 10% as default
        if ($clientModel->user_referral_credit_fixed > 0) {
            $referralAmount = $clientModel->user_referral_credit_fixed;
        } elseif ($clientModel->user_referral_credit_percentage > 0) {
            $referralAmount = ($this->credit_update / 100) * $clientModel->user_referral_credit_percentage;
        } else {
            $referralAmount = ($this->credit_update / 100) * 10;
        }

        $clientTransactionModel                       = new ClientTransaction;
        $clientTransactionModel->transaction_currency = $referredClientModel->user_currency;
        $clientTransactionModel->user_id              = $referredClientId;
        $clientTransactionModel->profile_id           = $clientModel->user_id;
        $clientTransactionModel->credit_update        = number_format($referralAmount, 2);
        $clientTransactionModel->reason               = 'Referral Credit';
        $clientTransactionModel->comment              = 'Reseller Referral #' . $clientModel->user_id . ' / ' . $clientModel->user_last_name . ', ' . $clientModel->user_first_name;
        $clientTransactionModel->linked_to            = $this->transaction_id;
        if (!$clientTransactionModel->save()) {
            $msg = 'Error Saving Client Referral Transaction Record. ' . Html::errorSummary($clientTransactionModel);
            Globals::setFlash('error', $msg);
        }
        unset($clientTransactionModel);
        // at this point, the update user balance trigger should run and update the client's balance and the new_balance field

        // check referred user status
        ClientStatus::process($referredClientId, 'Reseller Referral For #' . $clientModel->user_id);
    }

    public function moneygramPayment()
    {
        if ($this->validate()) {
            $reason             = 'Credit Purchase';
            $optionalComment    = empty($this->comment) ? '' : ' ' . $this->comment;
            $this->comment      = 'MoneyGram #' . $this->moneygramRef . $optionalComment;
            $this->payment_type = self::PAYMENT_TYPE_MONEYGRAM;

            $this->scenario             = 'insert';
            $this->transaction_currency = $this->clientModel->user_currency;
            $this->credit_update        = $this->updateAmount;
            // evrything else is either set already or set automatically
            if (!$this->save()) {
                return false;
            }

            // add MoneyGram fee (hardcoded as $3)
            $fee                       = new ClientTransaction;
            $fee->transaction_currency = $this->clientModel->user_currency;
            $fee->user_id              = $this->user_id;
            $fee->credit_update        = -3.00;
            $fee->reason               = 'Fees and Charges';
            $fee->comment              = "MoneyGram Fee";
            $fee->payment_type         = $this->payment_type;
            if (!$fee->save()) {
                return false;
            }

            // add referral (hardcoded to Henry's account #9391)
            $referral                       = new ClientTransaction;
            $referral->transaction_currency = 'USD';
            $referral->user_id              = 76731;  // changed from 9391 (Henry) to 76731 (Rodi Perez) 2015-07-01
            $referral->credit_update        = 3.00; // changed from 2.00 to 3.00 2016-12-01 as per Ravivs instructions
            $referral->reason               = 'Referral Credit';
            $referral->comment              = "MoneyGram Payment From Client #" . $this->user_id;
            $referral->payment_type         = $this->payment_type;
            if (!$referral->save()) {
                return false;
            }

            // add to MoneyGram table
            $moneygram                               = new Moneygram;
            $moneygram->user_id                      = $this->user_id;
            $moneygram->ref                          = $this->moneygramRef;
            $moneygram->purchase_user_transaction_id = $this->transaction_id;
            $moneygram->fee_user_transaction_id      = $fee->transaction_id;
            $moneygram->referral_user_transaction_id = $referral->transaction_id;
            $moneygram->amount                       = $this->updateAmount;
            $moneygram->moneygram_account_id         = $this->moneygramAccountId;
            if (!$moneygram->save()) {
                return false;
            }

            return true;
        } else {
            return false;
        }
    }

    public function paperPayment()
    {
        if ($this->validate()) {
            $reason             = 'Credit Purchase';
            $optionalComment    = empty($this->comment) ? '' : ' ' . $this->comment;
            $this->comment      = 'Check #' . $this->checkOrderNo . $optionalComment;
            $this->payment_type = self::PAYMENT_TYPE_PAPER;

            $this->scenario             = 'insert';
            $this->transaction_currency = $this->clientModel->user_currency;
            $this->credit_update        = $this->updateAmount;
            // evrything else is either set already or set automatically
            if (!$this->save()) {
                return false;
            }

            // add to Paper table
            $paper                               = new Paper();
            $paper->user_id                      = $this->user_id;
            $paper->ref                          = $this->checkOrderNo;
            $paper->check_order_type             = $this->checkOrderType;
            $paper->purchase_user_transaction_id = $this->transaction_id;
            $paper->referral_user_transaction_id = null;
            $paper->amount                       = $this->updateAmount;
            if (!$paper->save()) {
                return false;
            }

            return true;
        } else {
            return false;
        }
    }

    public function commentListOptions()
    {
        return Yii::$app->db->createCommand()
            ->select('DISTINCT(comment)')
            ->from(self::tableName())
            ->where('comment != ""')
            ->queryColumn();
    }

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'user_transactions';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['reason', 'required'],
            ['moneygramRef, moneygramAccountId', 'required', 'on' => 'moneygramPayment'],
            ['moneygramAccountId', 'numerical', 'integerOnly' => true, 'on' => 'moneygramPayment'],
            ['checkOrderType, checkOrderType', 'required', 'on' => 'paperPayment'],
            ['checkOrderNo', 'numerical', 'integerOnly' => true, 'on' => 'paperPayment'],
            ['checkOrderNo', 'length', 'max' => 15, 'on' => 'paperPayment'],
            // rules for general inserting...
            ['transaction_currency, user_id, credit_update', 'required', 'on' => 'insert'],
            // rules for updating balance...
            ['updateAmount', 'numerical', 'min' => 0, 'allowEmpty' => false, 'on' => 'updateBalance,moneygramPayment,paperPayment'],
            ['reason', 'checkReason', 'on' => 'updateBalance'],
            ['clientModel', 'safe', 'on' => 'updateBalance,moneygramPayment,paperPayment'],
            ['user_id, profile_id, cdr_id, year, month, day, admin_id, quick_pay, showCdr, showDeclined, process_balance, linked_to, reversed_by, sms_out_id', 'numerical', 'integerOnly' => true],
            ['transaction_currency', 'length', 'max' => 3],
            ['credit_update, new_balance', 'length', 'max' => 10],
            ['reason, comment, ip_address, token', 'length', 'max' => 255],
            ['datetime, payment_type', 'safe'],
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            ['transaction_id, transaction_currency, user_id, profile_id, cdr_id, credit_update, new_balance, reason, comment, datetime, year, month, day, admin_id, ip_address, token, quick_pay, showCdr, showDeclined, dispute_id', 'safe', 'on' => 'search'],
            ['date, fromDate, toDate, payment_amount, payment_count,payment_count_declined,refund_amount,refund_count,net_amount,cumulative_amount,year,month,chartType', 'safe', 'on' => 'searchFinancial'],
        ];
    }

    public function beforeValidate()
    {
        if (!empty($this->updateAmount)) {
            $this->updateAmount = Globals::numbersOnly($this->updateAmount, '-.');
        }
        if ($this->scenario === 'moneygramPayment') {
            $this->reason = 'Credit Purchase';
        }
        if ($this->scenario === 'paperPayment') {
            $this->reason = 'Credit Purchase';
        }

        return parent::beforeValidate();
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'transaction_id'       => 'TID',
            'transaction_currency' => 'Currency',
            'user_id'              => 'Client',
            'profile_id'           => 'Card/Client Transfer ID',
            'cdr_id'               => 'CDR',
            'credit_update'        => 'Credit Update',
            'new_balance'          => 'New Balance',
            'reason'               => 'Reason',
            'comment'              => 'Comment',
            'datetime'             => 'Date & Time',
            'year'                 => 'Year',
            'month'                => 'Month',
            'day'                  => 'Day',
            'admin_id'             => 'By',
            'ip_address'           => 'Ip Address',
            'token'                => 'Token',
            'quick_pay'            => 'Quick Pay',
            'updateAmount'         => 'Update',
            // financial reports labels...
            'date'                 => 'Date',
            'dateRange'            => 'Date Range: ',
            'payment_amount'       => '# of Payments Received',
            'payment_count'        => 'Amount Received',
            'refund_amount'        => '# of Refunds',
            'refund_count'         => 'Amount Refunded',
            'net_amount'           => 'Net Amount',
            'cumulative_amount'    => 'Net Cumulative Amount',
            'moneygramRef'         => 'MoneyGram Ref',
            'moneygramAccountId'   => 'MoneyGram Account',
            'checkOrderNo'         => 'Check/Money Order #',
            'checkOrderType'       => 'Check/Money Order type',
        ];
    }

    public function beforeSave()
    {
        // create token and datetime for new records
        if ($this->isNewRecord) {
            $this->token      = Globals::unique_md5();
            $this->ip_address = Util::getRemoteIpAddress();
            $this->datetime   = date('Y-m-d H:i:s');
            $this->year       = date('Y');    // do we really need this? What a waste of time!
            $this->month      = date('m');   // do we really need this? What a waste of time!
            $this->day        = date('d');     // do we really need this? What a waste of time!
            $this->admin_id   = $this->forceAdminId === false ? Yii::$app->getUser()->getId() : $this->forceAdminId;
        }

        return parent::beforeSave();
    }

    public function afterSave()
    {
        // referrals processing
        if ($this->reason == 'Credit Purchase' && $this->credit_update > 0) {
            $this->processReferrals();
        }

        if ($this->credit_update > 0) {
            if (($this->reason == 'Credit Purchase' || ($this->reason == 'Reseller Credit' && empty($this->linked_to))) && $this->client->received_payment && $this->client->alert_by_corrlink) {
                $model                  = new MsgSent;
                $model->user_id         = $this->client->user_id;
                $model->msg_template_id = MsgTemplate::getTemplateIdByAlias(MsgTemplate::TMP_RECEIVED_PAYMENT);
                $model->send_type       = MsgSent::SEND_TYPE_CORRLINKS_SUPPORT;
                $model->fetchTemplateData($this->client->user_id);
                $model->send(true, false);
            }
            // checks to NULL credit_notification_send_date and minutes_notification_send_date
            // Check if client's Alert Minutes should be reseted
            if (!$this->client->isAlertMinutes()) {
                $this->client->minutes_notification_send_date = null;
                $this->client->save(true, ['minutes_notification_send_date']);
            }
            // Check if client's Alert Amount should be reseted
            if (!$this->client->isAlertAmount()) {
                $this->client->credit_notification_send_date = null;
                $this->client->save(true, ['credit_notification_send_date']);
            }
        }

        // note: card reverse referrals are still made separately in the transactionFd model
        // we aren't bothering with reversing manual refunds just yet as it's not easy

        parent::afterSave();
    }

    // this isn't really used anymore - was just a test

    public function processReferrals()
    {
        // reason must be a credit purchase
        if ($this->reason != 'Credit Purchase') {
            return false;
        }

        if ($this->credit_update <= 0) {
            return false;
        }

        /** @var Client $clientModel */
        $clientModel = Client::findOne($this->user_id);
        if (empty($clientModel)) {
            return false;
        }

        // check currencies match
        if ($clientModel->user_currency != $this->transaction_currency) {
            return false;
        }

        // check that this transaction hasn't already been added as a referral
        if ($this->exists('linked_to = :linkedTo', [':linkedTo' => $this->transaction_id])) {
            return false;
        }

        // get referred user id
        $referredClientId = Yii::$app->db->createCommand()
            ->select('referred_user_id')
            ->from(ClientReferral::tableName())
            ->where('referrer_user_id=:clientId AND reference_active != 0', [':clientId' => $clientModel->user_id])
            ->queryScalar();
        if (empty($referredClientId)) {
            return false;
        }

        $referredClientModel = Client::findOne($referredClientId);
        if (empty($referredClientModel)) {
            return false;
        }

        // use fixed credit first, then percentage, then 10% as default
        if ($clientModel->user_referral_credit_fixed > 0) {
            $referralAmount = $clientModel->user_referral_credit_fixed;
        } elseif ($clientModel->user_referral_credit_percentage > 0) {
            $referralAmount = ($this->credit_update / 100) * $clientModel->user_referral_credit_percentage;
        } else {
            $referralAmount = ($this->credit_update / 100) * 10;
        }

        $clientTransactionModel                       = new ClientTransaction;
        $clientTransactionModel->transaction_currency = $referredClientModel->user_currency;
        $clientTransactionModel->user_id              = $referredClientId;
        $clientTransactionModel->credit_update        = number_format($referralAmount, 2);
        $clientTransactionModel->reason               = 'Referral Credit';
        $clientTransactionModel->comment              = '#' . $clientModel->user_id . ' / ' . $clientModel->user_last_name . ', ' . $clientModel->user_first_name;
        $clientTransactionModel->linked_to            = $this->transaction_id;
        if (!$clientTransactionModel->save()) {
            $msg = 'Error Saving Client Referral Transaction Record. ' . Html::errorSummary($clientTransactionModel);
            Globals::setFlash('error', $msg);
        }
        unset($clientTransactionModel);
        // at this point, the update user balance trigger should run and update the client's balance and the new_balance field

        // check referred user status
        ClientStatus::process($referredClientId, 'ChargeCC Referral For #' . $clientModel->user_id);
    }

    // only use this for grids when using a grid... function like gridBalance() below.
    // Multiple currency symbol requests will be issued if not using the same model instance!

    /**
     * Render the credit update value for grid output.
     * This should be used in a grid using 'value'=>array($model,'gridCreditUpdate')
     * This is so that the currency symbol doesn't get called multiple times
     */
    public function gridCreditUpdate($data, $row)
    {
        $strikethrough = empty($data->reversed_by) ? '' : 'text-decoration: line-through;';
        $color         = $data->credit_update < 0 ? 'red;' : 'black;';
        $content       = $this->getCurrencySymbol($data->transaction_currency) . ' ' . $data->credit_update . ' ' . $data->transaction_currency;

        return Html::tag('span', ['style' => 'color:' . $color . $strikethrough], Html::encode($content));
    }

    public function getCurrencySymbol($currencyPrefix = null)
    {
        if ($currencyPrefix === null) {
            $currencyPrefix = $this->user_currency;
        }
        if ($this->_currency === null) {
            $this->_currency = new Currency;
        }

        return $this->_currency->getCurrencySymbolByPrefix($currencyPrefix);
    }

    /**
     * Render the new balance value for grid output.
     * This should be used in a grid using 'value'=>array($model,'gridNewBalance')
     * This is so that the currency symbol doesn't get called multiple times
     */
    public function gridNewBalance($data, $row)
    {
        $strikethrough = empty($data->reversed_by) ? '' : 'text-decoration: line-through;';
        $color         = $data->new_balance < 0 ? 'red;' : 'black;';
        $content       = $this->getCurrencySymbol($data->transaction_currency) . ' ' . $data->new_balance . ' ' . $data->transaction_currency;

        return Html::tag('span', ['style' => 'color:' . $color . $strikethrough], Html::encode($content));
    }

    /**
     * Render the admin (By) value for grid output.
     * This should be used in a grid using 'value'=>array($model,'gridAdmin')
     * This is so that the data requests don't get called multiple times
     */
    public function gridAdmin($data, $row)
    {
        $displayName = empty($data->admin_id) ? 'Client' : (empty($data->admin) ? 'Unknown Admin' : $data->admin->display_name);

        if ($data->transaction) {
            $tx          = $data->transaction;
            $title       = 'Transaction Details (Vertex)';
            $ccNumber    = Globals::cardAccess() ? Util::viewCreditCardNumber($tx->cc_number) : Util::viewCreditCardNumber($tx->cc_number, true, 0);
            $content     = "<strong>$tx->cc_type - $ccNumber (exp. $tx->cc_exp_date)</strong><br />
                        $tx->first_name $tx->last_name<br />
                        $tx->address, $tx->city $tx->zip $tx->state<br />
                        $tx->country<br />
                        CVV: $tx->cc_cvv<br />
                        IP: $data->ip_address<br />
                        Amount: $tx->currency $tx->amount<br />";
            $displayName = Html::a($displayName, 'javascript:void(0)', [
                'data-title'     => $title,
                'data-placement' => 'top',
                'data-trigger'   => 'hover',
                'data-content'   => $content,
                'rel'            => 'popover',
            ]);
        } elseif ($data->transactionfd) {
            $tx       = $data->transactionfd;
            $title    = 'Transaction Details (First Data)';
            $ccNumber = Globals::cardAccess() ? Util::viewCreditCardNumber($tx->cc_number) : Util::viewCreditCardNumber($tx->cc_number, true, 0);
            $content  = "<strong>$tx->cc_type - $ccNumber (exp. $tx->cc_exp_date)</strong><br />
                        $tx->cardholder_name<br />
                        $tx->address, $tx->city $tx->zip $tx->state<br />
                        $tx->country<br />
                        CVV: $tx->cc_cvv<br />
                        IP: $data->ip_address<br />
                        Amount: $tx->currency $tx->amount<br />
                        Transaction Tag: $tx->transaction_tag<br />
                        Auth Num: $tx->authorization_num<br />
                        Msg: $tx->bank_resp_code $tx->bank_message<br />";

            $displayName = Html::a($displayName, 'javascript:void(0)', [
                'data-title'     => $title,
                'data-placement' => 'top',
                'data-trigger'   => 'hover',
                'data-content'   => $content,
                'rel'            => 'popover',
            ]);
        }
        $suffix = '';
        if ($data->payment_type == self::PAYMENT_TYPE_CLIENT_CARD_PHONEPAY || $data->payment_type == self::PAYMENT_TYPE_CLIENT_CARD_PHONEQUICKPAY) {
            $link = '';
            // add play buttons to hear client transaction info for approved transactions
            if (!empty($data->transactionfd->transaction_approved)) {
                $url  = "http://pbx.codetele.com/audio.php?file=" . $data->transaction_id;
                $link .= '<br />' . Html::a('<small>Name: </small><i class="icon-play-circle"></i>', $url . '-name', ['target' => '_blank', 'class' => 'playaudio', 'title' => 'Play Name']);
                $link .= '<br />' . Html::a('<small>Address: </small><i class="icon-play-circle"></i>', $url . '-address', ['target' => '_blank', 'class' => 'playaudio', 'title' => 'Play Address']);
            }
            $suffix = ' (phonepay) ' . $link;
        } elseif ($data->quick_pay) {
            $suffix = ' (quickpay)';
        }

        return $displayName . $suffix;
    }

    /**
     * Render the actions value for grid output.
     * This should be used in a grid using 'value'=>array($model,'gridActions')
     */
    public function gridActions($data, $row)
    {
        if (!empty($data->transactionfd->authorization_num) && ($data->reason == 'Credit Purchase' || $data->reason == 'Journal Entry Payment')) {
            // show that the credit has already been reversed if it has been
            if (!empty($data->reversed_by)) {
                return "<span class='text-error'>Void/Refund (#$data->reversed_by)</span>";
            }

            $voidButton   = '';
            $refundButton = '';
            // add void button if transaction was made today
            if (Util::isToday($data->datetime)) {
                $url        = Yii::$app->controller->createUrl("/transactionFd/void", ["id" => $data->transactionfd->id, "asDialog" => 1, "gridId" => 'client-transaction-grid']);
                $voidButton = Yii::$app->controller->widget('bootstrap.widgets.TbButton', [
                    'id'          => 'Void_' . $data->transaction_id, // must specify ID or the button won't work after grid update
                    'buttonType'  => 'link',
                    'label'       => 'Void',
                    'type'        => 'danger',
                    'size'        => 'mini',
                    'icon'        => 'remove-sign white',
                    // note we are sending the transactionFd id to the url
                    'url'         => 'javascript:void(0)',
                    'htmlOptions' => [
                        'class'   => 'bulk-action',
                        'onclick' => '$("#popup-frame").attr("src","' . $url . '");$("#popup-dialog").dialog("option","title","Void Transaction #' . $data->transaction_id . '");$("#popup-dialog").dialog("open");',
                        //'confirm' => 'Are you sure you want to remove all selected items?',
                    ],
                ], true);
            }

            // refund button
            $url          = Yii::$app->controller->createUrl("/transactionFd/refund", ["id" => $data->transactionfd->id, "asDialog" => 1, "gridId" => 'client-transaction-grid']);
            $refundButton = Yii::$app->controller->widget('bootstrap.widgets.TbButton', [
                'id'          => 'Void_' . $data->transaction_id, // must specify ID or the button won't work after grid update
                'buttonType'  => 'link',
                'label'       => 'Refund',
                'type'        => 'danger',
                'size'        => 'mini',
                'icon'        => 'minus-sign white',
                // note we are sending the transactionFd id to the url
                'url'         => 'javascript:void(0)',
                'htmlOptions' => [
                    'class'   => 'bulk-action',
                    'onclick' => '$("#popup-frame").attr("src","' . $url . '");$("#popup-dialog").dialog("option","title","Refund Transaction #' . $data->transaction_id . '");$("#popup-dialog").dialog("open");',
                ],
            ], true);

            return trim($voidButton . ' ' . $refundButton);
        } elseif ($data->reason == 'Reseller Credit') {
            // show that the credit has already been reversed if it has been
            if (!empty($data->reversed_by)) {
                return "<span class='text-error'>Reversed (#$data->reversed_by)</span>";
            }

            $reverseButton = Yii::$app->controller->widget('bootstrap.widgets.TbButton', [
                'id'          => 'Void_' . $data->transaction_id, // must specify ID or the button won't work after grid update
                'buttonType'  => 'link',
                'label'       => 'Reverse',
                'type'        => 'danger',
                'size'        => 'mini',
                'icon'        => 'minus-sign white',
                // note we are sending the transactionFd id to the url
                'url'         => 'javascript:void(0)',
                'htmlOptions' => [
                    'class'                 => 'bulk-action',
                    'data-reseller-reverse' => $data->transaction_id,
                ],
            ], true);

            return $reverseButton;
        }

        return null;
    }

    /**
     * Render the actions value for grid output.
     * This should be used in a grid using 'value'=>array($model,'gridDispute')
     */
    public function gridDispute($data, $row)
    {
        if (!empty($data->transactionfd->authorization_num) && $data->reason == 'Credit Purchase') {
            $disputeButton = '';

            if (!empty($data->dispute) && $data->dispute->status > 0) {
                // dispute was closed
                $label         = $data->dispute->ref . ($data->dispute->status == Dispute::STATUS_CLOSED_WIN ? ' Won' : ' Lost');
                $type          = $data->dispute->status == Dispute::STATUS_CLOSED_WIN ? 'success' : 'important';
                $disputeButton = Yii::$app->controller->widget('bootstrap.widgets.TbLabel', [
                    'label' => $label,
                    'type'  => $type,
                ], true);
            } elseif (!empty($data->dispute) && $data->dispute->status == 0) {
                // dispute is in progress
                $url           = Yii::$app->controller->createUrl("/transactionFd/disputeClose", ["id" => $data->transactionfd->id, "asDialog" => 1, "gridId" => 'client-transaction-grid']);
                $disputeButton = Yii::$app->controller->widget('bootstrap.widgets.TbButton', [
                    'id'          => 'Dispute_' . $data->transaction_id, // must specify ID or the button won't work after grid update
                    'buttonType'  => 'link',
                    'label'       => 'Close ' . $data->dispute->ref,
                    'type'        => 'primary',
                    'size'        => 'mini',
                    'icon'        => 'warning-sign white',  // white
                    // note we are sending the transactionFd id to the url
                    'url'         => 'javascript:void(0)',
                    'htmlOptions' => [
                        'class'   => 'bulk-action',
                        'style'   => 'font-weight:bold;',
                        'onclick' => '$("#popup-frame").attr("src","' . $url . '");$("#popup-dialog").dialog("option","title","Close Dispute For Transaction #' . $data->transaction_id . '");$("#popup-dialog").dialog("open");',
                        //'confirm' => 'Are you sure you want to remove all selected items?',
                    ],
                ], true);
            } else {
                // no dispute filed (show add dispute button)
                $url           = Yii::$app->controller->createUrl("/transactionFd/disputeOpen", ["id" => $data->transactionfd->id, "asDialog" => 1, "gridId" => 'client-transaction-grid']);
                $disputeButton = Yii::$app->controller->widget('bootstrap.widgets.TbButton', [
                    'id'          => 'Dispute_' . $data->transaction_id, // must specify ID or the button won't work after grid update
                    'buttonType'  => 'link',
                    'label'       => 'Add Dispute',
                    'type'        => 'warning',
                    'size'        => 'mini',
                    'icon'        => 'warning-sign white',  // white
                    // note we are sending the transactionFd id to the url
                    'url'         => 'javascript:void(0)',
                    'htmlOptions' => [
                        'class'   => 'bulk-action',
                        'style'   => 'font-weight:bold;',
                        'onclick' => '$("#popup-frame").attr("src","' . $url . '");$("#popup-dialog").dialog("option","title","Add Dispute For Transaction #' . $data->transaction_id . '");$("#popup-dialog").dialog("open");',
                        //'confirm' => 'Are you sure you want to remove all selected items?',
                    ],
                ], true);
            }

            return $disputeButton;
        }

        if (!empty($data->dispute)) {
            $ref = $data->dispute->ref;
            if ($data->dispute->status == 0) {
                return Yii::$app->controller->widget('bootstrap.widgets.TbLabel', [
                    'label' => $ref . ' In Dispute',
                ], true);
            } else {
                $label = $ref . ($data->dispute->status == Dispute::STATUS_CLOSED_WIN ? ' Won' : ' Lost');
                $type  = $data->dispute->status == Dispute::STATUS_CLOSED_WIN ? 'success' : 'important';

                return Yii::$app->controller->widget('bootstrap.widgets.TbLabel', [
                    'label' => $label,
                    'type'  => $type,
                ], true);
            }
        }

        return null;
    }

    /**
     * @return mixed
     */
    public function getDisputes()
    {
        $sql = "SELECT DISTINCT(d.id) AS val,CONCAT(d.ref,(CASE WHEN d.status = 0 THEN ' (active)' ELSE ' (closed)' END)) AS text
                FROM user_transactions ut
                INNER JOIN dispute d ON ut.dispute_id = d.id
                WHERE ut.user_id = :userId
                ORDER BY d.id DESC";

        return ArrayHelper::map(Yii::$app->db->createCommand($sql)->queryAll(true, [':userId' => $this->user_id]), 'val', 'text');
    }

}