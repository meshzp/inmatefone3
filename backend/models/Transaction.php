<?php

namespace backend\models;

use backend\helpers\Globals;
use yii\base\Exception;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "transactions".
 *
 * The followings are the available columns in table 'transactions':
 * @property string $transaction_id
 * @property integer $user_id
 * @property string $amount
 * @property string $currency
 * @property string $cc_type
 * @property string $cc_number
 * @property string $cc_exp_date
 * @property string $cc_cvv
 * @property string $first_name
 * @property string $last_name
 * @property string $address
 * @property string $city
 * @property string $state
 * @property string $zip
 * @property string $country
 * @property string $response
 * @property string $datetime
 * @property string $token
 * @property integer $user_transaction_id
 * @property integer $admin_id
 * @property string $ip_address
 * @property integer $quick_pay
 */
class Transaction extends ActiveRecord
{

    /**
     * @deprecated
     * Returns the static model of the specified AR class.
     *
     * @param string $className active record class name.
     *
     * @return Transaction|ActiveRecord the static model class
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'transactions';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['cc_type, cc_number, cc_exp_date, cc_cvv', 'required'],
            ['user_id, user_transaction_id, admin_id, quick_pay', 'numerical', 'integerOnly' => true],
            ['amount', 'length', 'max' => 10],
            ['currency', 'length', 'max' => 3],
            ['cc_type, cc_number, cc_exp_date, cc_cvv, first_name, last_name, address, city, state, zip, country, response, token, ip_address', 'length', 'max' => 255],
            ['datetime, token, ip_address, response, first_name, last_name, address, city, state, zip, country', 'safe'],
            ['transaction_id, user_id, amount, currency, cc_type, cc_number, cc_exp_date, cc_cvv, first_name, last_name, address, city, state, zip, country, response, datetime, token, user_transaction_id, admin_id, ip_address, quick_pay', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'transaction_id'      => 'Transaction',
            'user_id'             => 'User',
            'amount'              => 'Amount',
            'currency'            => 'Currency',
            'cc_type'             => 'Cc Type',
            'cc_number'           => 'Cc Number',
            'cc_exp_date'         => 'Cc Exp Date',
            'cc_cvv'              => 'Cc Cvv',
            'first_name'          => 'First Name',
            'last_name'           => 'Last Name',
            'address'             => 'Address',
            'city'                => 'City',
            'state'               => 'State',
            'zip'                 => 'Zip',
            'country'             => 'Country',
            'response'            => 'Response',
            'datetime'            => 'Datetime',
            'token'               => 'Token',
            'user_transaction_id' => 'User Transaction',
            'admin_id'            => 'Admin',
            'ip_address'          => 'Ip Address',
            'quick_pay'           => 'Quick Pay',
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeSave()
    {
        // create token and datetime for new records
        if ($this->isNewRecord) {
            $this->token    = Globals::unique_md5();
            $this->datetime = date('Y-m-d H:i:s');
            $this->admin_id = \Yii::$app->getUser()->id;
        }

        return parent::beforeSave();
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        $criteria = new CDbCriteria;

        $criteria->compare('transaction_id', $this->transaction_id, true);
        $criteria->compare('user_id', $this->user_id);
        $criteria->compare('amount', $this->amount, true);
        $criteria->compare('currency', $this->currency, true);
        $criteria->compare('cc_type', $this->cc_type, true);
        $criteria->compare('cc_number', $this->cc_number, true);
        $criteria->compare('cc_exp_date', $this->cc_exp_date, true);
        $criteria->compare('cc_cvv', $this->cc_cvv, true);
        $criteria->compare('first_name', $this->first_name, true);
        $criteria->compare('last_name', $this->last_name, true);
        $criteria->compare('address', $this->address, true);
        $criteria->compare('city', $this->city, true);
        $criteria->compare('state', $this->state, true);
        $criteria->compare('zip', $this->zip, true);
        $criteria->compare('country', $this->country, true);
        $criteria->compare('response', $this->response, true);
        $criteria->compare('datetime', $this->datetime, true);
        $criteria->compare('token', $this->token, true);
        $criteria->compare('user_transaction_id', $this->user_transaction_id);
        $criteria->compare('admin_id', $this->admin_id);
        $criteria->compare('ip_address', $this->ip_address, true);
        $criteria->compare('quick_pay', $this->quick_pay);

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
        ]);
    }

    /**
     * @deprecated
     *
     * @param $clientId
     * @param $params
     *
     * @return bool
     * @throws Exception
     */
    public function chargeCC($clientId, $params)
    {
        $firstData = true;
        // temp change to First Data
        if ($firstData) {
            $response = TransactionFd::chargeCC($clientId, $params);
            if (is_array($response)) {
                return $response['approved'] ? true : false;
            } else {
                return false;
            }
        }

        /**
         * @var $clientModel Client
         */
        $clientModel = Client::findOne($clientId);
        if ($clientModel === null) {
            throw new Exception('The requested client does not exist.');
        }

        $defaultParams = [
            //'user_id' => $clientId,
            'profile_id'  => 0,  // this is the card id number from billing_details and should be provided or zero if one off payment (quickPay)
            'amount'      => 0,
            'currency'    => 'USD',
            'cc_type'     => null,
            'cc_number'   => null,
            'cc_exp_date' => null,
            'cc_cvv'      => null,
            'first_name'  => null,
            'last_name'   => null,
            'address_1'   => null,
            'city'        => null,
            'state'       => null,
            'zip'         => null,
            'country'     => null,

            'reason'  => 'Credit Purchase',
            'comment' => 'Online Credit Purchase',
        ];
        $params        = CMap::mergeArray($defaultParams, $params);

        // make sure amount is formatted to 2 dp
        $params['amount'] = number_format($params['amount'], 2, '.', '');
        // make sure credit card number, cvv, and exp date are numbers only
        $params['cc_number']   = Globals::numbersOnly($params['cc_number']);
        $params['cc_exp_date'] = Globals::numbersOnly($params['cc_exp_date']);
        $params['cc_cvv']      = Globals::numbersOnly($params['cc_cvv']);

        // add full address for the db to use (when using $transactionModel)
        if (!isset($params['address'])) {
            $params['address'] = (!empty($params['address_1']) ? $params['address_1'] : '') .
                (!empty($params['address_1']) && !empty($params['address_2']) ? ', ' : '') .
                (!empty($params['address_2']) ? $params['address_2'] : '');
        }

        if ($params['currency'] != 'USD') {
            throw new Exception('The currency is unsupported.');
        }

        $transactionModel              = new Transaction;
        $transactionModel->user_id     = $clientId;
        $transactionModel->amount      = $params['amount'];
        $transactionModel->currency    = $params['currency'];
        $transactionModel->cc_type     = $params['cc_type'];
        $transactionModel->cc_number   = $params['cc_number'];
        $transactionModel->cc_exp_date = $params['cc_exp_date'];
        $transactionModel->cc_cvv      = $params['cc_cvv'];
        $transactionModel->first_name  = $params['first_name'];
        $transactionModel->last_name   = $params['last_name'];
        $transactionModel->address     = $params['address'];
        $transactionModel->city        = $params['city'];
        $transactionModel->state       = $params['state'];
        $transactionModel->zip         = $params['zip'];
        $transactionModel->country     = $params['country'];
        $transactionModel->response    = 0;
        $transactionModel->quick_pay   = $params['profile_id'] ? 0 : 1;
        // save the model so we know a transaction was attempted - TODO: is this really necessary? We could save later on.
        if ($transactionModel->save()) {
            $billingParams = [
                'firstname' => $params['first_name'],
                'lastname'  => $params['last_name'],
                'address1'  => $params['address_1'],
                'city'      => $params['city'],
                'state'     => $params['state'],
                'zip'       => $params['zip'],
                'country'   => $params['country'],
            ];

            $response = Yii::$app->gwapi->doSale($params['amount'], $params['cc_number'], $params['cc_exp_date'], $params['cc_cvv'], $billingParams);

            //dumpd($response);
            if ($response && isset($response['response'])) {
                $transactionModel->response = $response['response'];
                $transactionModel->save(false, ['response']);
                // messages....
                // TODO: check who is receiving these messages, just admin staff or end users as well?
                switch ($response['response']) {
                    case GwApi::RESPONSE_APPROVED:
                        // don't really need a message here do we?
                        break;
                    case GwApi::RESPONSE_DECLINED:
                        Globals::setFlash('error', 'The card was declined and we are unable to process the transaction. Please contact your financial institution.');
                        Globals::setFlash('error', 'Response Text: ' . $response['responsetext']);
                        Globals::setFlash('error', 'Response Code #' . $response['response_code'] . ' - ' . Yii::app()->gwapi->getResponseCodes($response['response_code']));
                        break;
                    case GwApi::RESPONSE_ERROR:
                        Globals::setFlash('error', 'There was an error while processing your transaction. Please contact your financial institution.');
                        Globals::setFlash('error', 'Response Text: ' . $response['responsetext']);
                        Globals::setFlash('error', 'Response Code #' . $response['response_code'] . ' - ' . Yii::app()->gwapi->getResponseCodes($response['response_code']));
                        break;
                    default:
                        Globals::setFlash('error', 'There was an unknown error while processing your transaction.');
                        Globals::setFlash('error', 'Response Text: ' . $response['responsetext']);
                        Globals::setFlash('error', 'Response Code #' . $response['response_code'] . ' - ' . Yii::app()->gwapi->getResponseCodes($response['response_code']));
                        break;
                }
                $referredClientId = 0;
                if ($response['response'] == GwApi::RESPONSE_APPROVED) {
                    $clientDidInstance = ClientDid::model();
                    // approved!
                    // update client
                    // should this also check if the client has enough credit to be activated?
                    // !!!!!!!!!!! be careful as the user_balance field might have been updated by the user_transaction trigger !!!!!!!!!!
                    // we can get around this by updating only the user_status
                    //Client::model()->updateByPk($clientId, array('user_status' => 1));
                    $clientModel->user_status = 1;
                    if (!$clientModel->save(false, ['user_status'])) {
                        $msg = 'Error Saving Client Record. ' . CHtml::errorSummary($clientModel);
                        Globals::setFlash('error', $msg);
                    }

                    $clientTransactionModel                       = new ClientTransaction;
                    $clientTransactionModel->transaction_currency = $params['currency'];
                    $clientTransactionModel->user_id              = $clientId;
                    $clientTransactionModel->profile_id           = $params['profile_id'];
                    $clientTransactionModel->credit_update        = $params['amount'];
                    // note: the new_balance field will be automatically set by the update trigger
                    // in the old code, this was set to 0
                    //$clientTransactionModel->new_balance = $clientModel->user_balance;
                    $clientTransactionModel->reason  = $params['reason'];
                    $clientTransactionModel->comment = $params['comment'];
                    // if no billing id (profile id) flag as a quick pay
                    // note: not sure if this is the correct use of the quick_pay field but it doesn't seem to be used for anything else now
                    $clientTransactionModel->quick_pay = $params['profile_id'] ? 0 : 1;
                    if (!$clientTransactionModel->save()) {
                        $msg = 'Error Saving Client Transaction Record. ' . CHtml::errorSummary($clientTransactionModel);
                        Globals::setFlash('error', $msg);
                    }

                    // at this point, the update user balance trigger should run and update the client's balance and the new_balance field

                    // add new clientTransaction id to our current transaction model
                    // we can save this again now
                    $transactionModel->user_transaction_id = $clientTransactionModel->transaction_id;
                    if (!$transactionModel->save(false, ['user_transaction_id'])) {
                        $msg = 'Error Saving Transaction Record. ' . CHtml::errorSummary($transactionModel);
                        Globals::setFlash('error', $msg);
                    }
                    unset($clientTransactionModel);
                    unset($transactionModel);
                    // update client DID's - TODO: is it worth throwing exception on error? Remember this returns amount of rows.
                    Yii::$app->db->createCommand()->update('user_dids', [
                        'datetime_cancel'      => '0000-00-00 00:00:00',
                        'datetime_last_update' => date('Y-m-d H:i:s'),
                        'status'               => 1,
                        'admin_id_last_update' => @empty(user()->id) ? 1 : @user()->id,
                        'asterisk'             => 0,
                    ], 'user_id=:clientId AND status!=0', [':clientId' => $clientId]);

                    // update SIPs
                    Yii::$app->db->createCommand()->update('user_sips', [
                        'sip_datetime_cancel'      => '0000-00-00 00:00:00',
                        'sip_datetime_last_update' => date('Y-m-d H:i:s'),
                        'sip_status'               => 1,
                        'admin_id_last_update'     => @empty(user()->id) ? 1 : @user()->id,
                        'sip_update'               => 1,
                    ], 'user_id=:clientId AND sip_status!=0', [':clientId' => $clientId]);

                    // add to log - TODO: is it worth throwing exception on error?
                    $log              = new ClientLog;
                    $log->user_id     = $clientId;
                    $log->user_status = 1;
                    $log->save();
                    unset($log);

                    // referrals
                    if ($params['reason'] == 'Credit Purchase' && $params['amount'] > 0 && $clientModel->user_currency == $params['currency']) {
                        // set default referral amount
                        $referralAmount = $params['amount'] / 10;

                        // get referred user -
                        // TODO: double check that this will always just return one row - with Lionel's code you never know!
                        $referredClientId = Yii::$app->db->createCommand()
                            ->select('referred_user_id')
                            ->from(ClientReferral::tableName())
                            ->where('referrer_user_id=:clientId AND reference_active != 0', [':clientId' => $clientId])
                            ->queryScalar();
                        if ($referredClientId) {
                            if ($clientModel->user_referral_credit_percentage > 0) {
                                $referralAmount = ($params['amount'] / 100) * $clientModel->user_referral_credit_percentage;
                            } else {
                                $referralAmount = $clientModel->user_referral_credit_fixed;
                            }

                            $clientTransactionModel                       = new ClientTransaction;
                            $clientTransactionModel->transaction_currency = $params['currency'];
                            $clientTransactionModel->user_id              = $referredClientId;
                            $clientTransactionModel->credit_update        = number_format($referralAmount, 2);
                            $clientTransactionModel->reason               = 'Referral Credit';
                            $clientTransactionModel->comment              = '#' . $clientId . ' / ' . $clientModel->user_last_name . ', ' . $clientModel->user_first_name;
                            if (!$clientTransactionModel->save()) {
                                $msg = 'Error Saving Client Referral Transaction Record. ' . CHtml::errorSummary($clientTransactionModel);
                                Globals::setFlash('error', $msg);
                            }
                            unset($clientTransactionModel);
                            // at this point, the update user balance trigger should run and update the client's balance and the new_balance field
                        }
                    }

                    if ($referredClientId) {
                        $clientDidInstance->blockDid($referredClientId);
                    }
                    $clientDidInstance->blockDid($clientId);

                    return true;
                } else {
                    // the card was declined
                    $clientTransactionModel                       = new ClientTransaction;
                    $clientTransactionModel->transaction_currency = $params['currency'];
                    $clientTransactionModel->user_id              = $clientId;
                    $clientTransactionModel->profile_id           = $params['profile_id'];
                    $clientTransactionModel->credit_update        = 0;
                    // note: the new_balance field will be automatically set by the update trigger
                    $clientTransactionModel->reason    = $params['reason'];
                    $clientTransactionModel->comment   = 'DECLINED';
                    $clientTransactionModel->quick_pay = $params['profile_id'] ? 0 : 1;
                    if (!$clientTransactionModel->save()) {
                        $msg = 'Error Saving Client Transaction Record. ' . CHtml::errorSummary($clientTransactionModel);
                        Globals::setFlash('error', $msg);
                    } else {
                        $transactionModel->user_transaction_id = $clientTransactionModel->transaction_id;
                        if (!$transactionModel->save(false, ['user_transaction_id'])) {
                            $msg = 'Error Saving Transaction Record. ' . CHtml::errorSummary($transactionModel);
                            Globals::setFlash('error', $msg);
                        }
                    }
                    unset($clientTransactionModel);

                    return false;
                }
            } else {
                // error messages for either console or web app
                $message = 'Error connecting to the Merchant Gateway. Please contact support.';
                Globals::setFlash('error', $message);

                return false;
            }
        } else {
            // error messages for either console or web app
            $message = 'Error saving to transactions table: ' . CHtml::errorSummary($transactionModel);
            Globals::setFlash('error', $message);

            return false;
        }

        return true;
    }
}
