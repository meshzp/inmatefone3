<?php

namespace backend\models;

use backend\helpers\Globals;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\base\Exception;
use yii\db\Expression;

/**
 * This is the model class for table "transaction_fd".
 *
 * The followings are the available columns in table 'transaction_fd':
 * @property string $id
 * @property string $user_id
 * @property string $transaction_type
 * @property string $amount
 * @property string $currency
 * @property string $cc_type
 * @property string $cc_number
 * @property string $cc_number_plain
 * @property string $cc_exp_date
 * @property string $cc_cvv
 * @property string $cc_cvv_plain
 * @property string $first_name
 * @property string $last_name
 * @property string $cardholder_name
 * @property string $address
 * @property string $city
 * @property string $state
 * @property string $zip
 * @property string $country
 * @property string $ip_address
 * @property integer $quick_pay
 * @property integer $admin_id
 * @property string $requested
 * @property integer $transaction_error
 * @property integer $transaction_approved
 * @property string $exact_resp_code
 * @property string $exact_message
 * @property string $bank_resp_code
 * @property string $bank_message
 * @property string $bank_resp_code_2
 * @property string $transaction_tag
 * @property string $authorization_num
 * @property string $sequence_no
 * @property string $avs
 * @property string $cvv2
 * @property string $retrieval_ref_no
 * @property string $ctr
 * @property integer $user_transaction_id
 *
 * @property Client $client
 * @property ClientTransaction $clientTransaction
 */
class TransactionFd extends ActiveRecord
{
    public $static_key; // the static key used for encryption
    public $refundComment; // amount and comment used in void and refund forms
    public $refundAmount; // amount and comment used in dispute form
    public $disputeComment;
    public $disputeAmount;
    public $disputeRef;
    public $disputeStatus;
    public $unusableCodes = [501, 502];

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'transaction_fd';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['refundAmount', 'required', 'on' => 'refund'],
            ['disputeRef', 'required', 'on' => 'disputeOpen'],
            ['disputeStatus', 'required', 'on' => 'disputeClose'],
            [['quick_pay', 'admin_id', 'transaction_error', 'transaction_approved', 'user_transaction_id'], 'numerical', 'integerOnly' => true],
            ['user_id', 'length', 'max' => 11],
            ['amount, cc_type, refundAmount, disputeAmount', 'length', 'max' => 10],
            ['currency, bank_resp_code', 'length', 'max' => 3],
            ['cc_number, cc_number_plain, cc_exp_date, cc_cvv, cc_cvv_plain, first_name, last_name, cardholder_name, address, city, state, zip, country, ip_address, exact_message, bank_message, refundComment, disputeComment, disputeRef', 'length', 'max' => 255],
            ['exact_resp_code, bank_resp_code_2', 'length', 'max' => 2],
            ['transaction_tag, authorization_num, retrieval_ref_no', 'length', 'max' => 20],
            ['sequence_no', 'length', 'max' => 50],
            ['avs, cvv2', 'length', 'max' => 1],
            ['ctr, requested', 'safe'],
            [['id', 'user_id', 'amount', 'currency', 'cc_type', 'cc_number', 'cc_number_plain', 'cc_exp_date', 'cc_cvv', 'cc_cvv_plain', 'first_name', 'last_name', 'cardholder_name', 'address', 'city', 'state', 'zip', 'country', 'ip_address', 'quick_pay', 'admin_id', 'requested', 'transaction_error', 'transaction_approved', 'exact_resp_code', 'exact_message', 'bank_resp_code', 'bank_message', 'bank_resp_code_2', 'transaction_tag', 'authorization_num', 'sequence_no', 'avs', 'cvv2', 'retrieval_ref_no', 'ctr', 'user_transaction_id'], 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate()
    {
        if ($this->scenario == 'refund') {
            $this->refundAmount = Globals::numbersOnly($this->refundAmount, '.');
            if ($this->refundAmount > $this->amount) {
                $this->addError('refundAmount', 'Amount must be below or equal to original transaction');
            }
        }

        return parent::beforeValidate();
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id'                   => 'ID',
            'user_id'              => 'User',
            'amount'               => 'Amount',
            'currency'             => 'Currency',
            'cc_type'              => 'Cc Type',
            'cc_number'            => 'Cc Number',
            'cc_number_plain'      => 'Cc Number Plain',
            'cc_exp_date'          => 'Cc Expiry',
            'cc_cvv'               => 'Cc Cvv',
            'cc_cvv_plain'         => 'Cc Cvv Plain',
            'first_name'           => 'First Name',
            'last_name'            => 'Last Name',
            'address'              => 'Address',
            'city'                 => 'City',
            'state'                => 'State',
            'zip'                  => 'Zip',
            'country'              => 'Country',
            'ip_address'           => 'Ip Address',
            'quick_pay'            => 'Quick Pay',
            'admin_id'             => 'Admin',
            'requested'            => 'Requested',
            'transaction_error'    => 'Transaction Error',
            'transaction_approved' => 'Transaction Approved',
            'exact_resp_code'      => 'Exact Resp Code',
            'exact_message'        => 'Exact Message',
            'bank_resp_code'       => 'Bank Resp Code',
            'bank_message'         => 'Bank Message',
            'bank_resp_code_2'     => 'Bank Resp Code 2',
            'transaction_tag'      => 'Transaction Tag',
            'authorization_num'    => 'Authorization Num',
            'sequence_no'          => 'Sequence No',
            'avs'                  => 'Avs',
            'cvv2'                 => 'Cvv2',
            'retrieval_ref_no'     => 'Retrieval Ref No',
            'ctr'                  => 'Ctr',
            'user_transaction_id'  => 'User Transaction',
            'refundComment'        => 'comment',
            'refundAmount'         => 'Amount',
            'disputeComment'       => 'comment',
            'disputeAmount'        => 'Amount',
            'disputeRef'           => 'Ref',
            'disputeStatus'        => 'Status',
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeSave()
    {
        if ($this->isNewRecord) {
            $this->ip_address = Yii::$app->request->getUserIP();
            $this->requested  = date('Y-m-d H:i:s');
            $this->admin_id   = Yii::$app->getUser()->getId();
            // encrypt cc number and cvv
            $this->cc_number_plain = $this->cc_number;
            $this->cc_number       = Globals::encrypt($this->cc_number, $this->user_id);
            $this->cc_cvv_plain    = $this->cc_cvv;
            $this->cc_cvv          = Globals::encrypt($this->cc_cvv, $this->user_id);
        }

        return parent::beforeSave();
    }

    public function afterFind()
    {
        // decrypt encrypted values
        $this->cc_number = Globals::decrypt($this->cc_number, $this->user_id);
        $this->cc_cvv    = Globals::decrypt($this->cc_cvv, $this->user_id);

        parent::afterFind();
    }

    /**
     * @deprecated
     * Charge a card via First Data payment gateway
     *
     * @param $clientId int
     * @param $params array
     * @throws Exception
     * @return bool|array False if there was an error before reaching the gateway. An array containg 'approved' and 'ctr' indexes.
     */
    public static function chargeCC($clientId, $params)
    {
        $clientModel = Client::findOne($clientId);
        if ($clientModel === null) {
            throw new Exception('The requested client does not exist.');
        }

        // check whether public site or not
        $public = empty(Yii::$app->params['serviceId']) ? false : true;

        $defaultParams = [
            'profile_id'      => 0, // this is the card id number from billing_details and should be provided or zero if one off payment (quickPay)
            'amount'          => 0,
            'currency'        => 'USD',
            'cc_type'         => null,
            'cc_number'       => null,
            'cc_exp_date'     => null,
            'cc_cvv'          => null,
            'first_name'      => null,
            'last_name'       => null,
            'cardholder_name' => null,
            'address_1'       => null,
            'city'            => null,
            'state'           => null,
            'zip'             => null,
            'country'         => null,
            'reason'          => 'Credit Purchase',
            'comment'         => 'Online Credit Purchase',
            'payment_type'    => null,
        ];
        $params        = CMap::mergeArray($defaultParams, $params);

        // make sure amount is formatted to 2 dp
        $params['amount'] = number_format($params['amount'], 2, '.', '');
        // make sure credit card number, cvv, and exp date are numbers only
        $params['cc_number']   = Globals::numbersOnly($params['cc_number']);
        $params['cc_exp_date'] = Globals::numbersOnly($params['cc_exp_date']);
        $params['cc_cvv']      = Globals::numbersOnly($params['cc_cvv']);

        // make sure cardholder_name is not empty
        if (empty($params['cardholder_name'])) {
            $params['cardholder_name'] = trim($params['first_name'] . ' ' . $params['last_name']);
        }
        if (empty($params['cardholder_name'])) {
            throw new Exception('The carholder name must be present.');
        }

        // add full address for the db to use (when using $transactionModel)
        if (!isset($params['address'])) {
            $params['address'] = (!empty($params['address_1']) ? $params['address_1'] : '') .
                (!empty($params['address_1']) && !empty($params['address_2']) ? ', ' : '') .
                (!empty($params['address_2']) ? $params['address_2'] : '');
        }

        // check currency is USD - why are we doing this?
        if ($params['currency'] != 'USD') {
            throw new Exception('The currency is unsupported.');
        }

        // first save a new client transaction in preparation - make sure process_balance is set to zero otherwise the balance will be processed immediately
        $clientTransactionModel                       = new ClientTransaction;
        $clientTransactionModel->transaction_currency = $params['currency'];
        $clientTransactionModel->user_id              = $clientId;
        $clientTransactionModel->profile_id           = $params['profile_id'];
        // note: the new_balance field will no longer be automatically set by the update trigger due to process_balance = 0 being set here
        // so, for now, let's set credit_update = 0 and put in the current user balance
        $clientTransactionModel->credit_update = 0;
        $clientTransactionModel->new_balance   = $clientModel->user_balance;
        $clientTransactionModel->reason        = $params['reason'];
        // the comment will be replaced after the transaction is made, for now put 'FAILED' in case something goes wrong
        $clientTransactionModel->comment = 'FAILED - CHECK IF PROCESSED AT FIRST DATA'; //$params['comment'];
        // if no billing id (profile id) flag as a quick pay
        // note: not sure if this is the correct use of the quick_pay field but it doesn't seem to be used for anything else now
        $clientTransactionModel->quick_pay = $params['profile_id'] ? 0 : 1;
        // process balnce = 0 is very important otherwise the user's balance will get updated twice - see the update_user_balance trigger
        $clientTransactionModel->process_balance = 0;
        $clientTransactionModel->payment_type    = $params['payment_type'];
        if ($clientTransactionModel->save()) {
            // save initial transaction model
            $transactionModel                      = new TransactionFd;
            $transactionModel->user_id             = $clientId;
            $transactionModel->amount              = $params['amount'];
            $transactionModel->currency            = $params['currency'];
            $transactionModel->cc_type             = $params['cc_type'];
            $transactionModel->cc_number           = $params['cc_number'];
            $transactionModel->cc_exp_date         = $params['cc_exp_date'];
            $transactionModel->cc_cvv              = $params['cc_cvv'];
            $transactionModel->first_name          = $params['first_name'];
            $transactionModel->last_name           = $params['last_name'];
            $transactionModel->cardholder_name     = $params['cardholder_name'];
            $transactionModel->address             = $params['address'];
            $transactionModel->city                = $params['city'];
            $transactionModel->state               = $params['state'];
            $transactionModel->zip                 = $params['zip'];
            $transactionModel->country             = $params['country'];
            $transactionModel->quick_pay           = $params['profile_id'] ? 0 : 1; // TODO: remove quick pay from here (more likely) or from user_transactions - no need for both!
            $transactionModel->user_transaction_id = $clientTransactionModel->transaction_id;
            if ($transactionModel->save()) {
                // now we have id's for the transaction and client transaction models, we can process the payment
                /*
                 * Common optional params:
                 * cc_verification_str1     Street Address|Zip/Postal|City|State/Prov|Country
                 * cc_verification_str2     CVV2
                 * cvd_presence_ind         How should CVV2 be handled? Default 0/null = Not Supported, 1 = Value provided by Cardholder, 2 = Value provided on card is Illegible, 9 = Cardholder states data is not available
                 * reference_no             Our Client Transaction ID
                 * customer_ref             Our Client ID
                 * reference_3              Our Transaction ID
                 * client_ip                IP Address. Use for client processing of cards.
                 * client_email
                 */
                // address shouldn't be more than 28 characters
                $address = trim($params['address']);
                if (strlen($address) > 28) {
                    $address = substr($address, 0, 28);
                }

                $billingParams = [
                    //'cc_verification_str1' => $params['address'] . '|' . $params['zip'] . '|' . $params['city'] . '|' . $params['state'] . '|' . $params['country'],
                    'cc_verification_str1' => $address . '|' . trim($params['zip']),
                    'cc_verification_str2' => $params['cc_cvv'],
                    'cvd_presence_ind'     => empty($params['cc_cvv']) ? 0 : 1,
                    'reference_no'         => $clientTransactionModel->transaction_id,
                    'reference_3'          => $transactionModel->id,
                    'customer_ref'         => $clientId,
                    'client_ip'            => $transactionModel->ip_address,
                ];
                //$amount,$cardHolderName,$cardNumber,$cardExpiry,$optionalParams = array()
                $response = Yii::$app->fdapi->doPurchase($params['amount'], $params['cardholder_name'], $params['cc_number'], $params['cc_exp_date'], $billingParams);
                if (!empty($response) && is_object($response)) {
                    // successful response (not necessarily successful transaction at this point though)
                    // save the response fields to the transaction model
                    $responseFields = ['transaction_error', 'transaction_approved', 'exact_resp_code', 'exact_message', 'bank_resp_code', 'bank_message', 'bank_resp_code_2',
                        'transaction_tag', 'authorization_num', 'sequence_no', 'avs', 'cvv2', 'retrieval_ref_no', 'ctr'];
                    foreach ($responseFields as $field) {
                        $transactionModel->$field = @$response->$field;
                    }
                    $transactionModel->save(false, $responseFields);

                    // check for errors
                    if ($response->transaction_approved) {
                        // transaction was successful
                        // Note: In the old code, the user status and all user dids would be set to 1 here and saved, but prob not a good idea.
                        // The new user_status trigger should take care of it all now.

                        // Because we didn't allow the user_balance trigger to run when inserting the user_transaction record, we need to do it here
                        // to be safe, we should use the database to do the sum in case things have changed since the client model was loaded
                        // TODO: think about whether it's safe enough to allow an update trigger on the user_transactions table to allow processing after initial insert
                        // TODO: what do we do if any of the following db updates fail?
                        if ($clientTransactionModel->reason != 'Journal Entry Payment') {
                            $clientModel->user_balance = new Expression('user_balance + :creditUpdate', [':creditUpdate' => $params['amount']]);
                            $clientModel->save(false, ['user_balance']);
                            $clientModel->refresh();
                        }

                        // save the correct comment and amounts in the client transaction model
                        $clientTransactionModel->credit_update = $params['amount'];
                        $clientTransactionModel->new_balance   = new \yii\db\Exception('(SELECT user_balance FROM user_datas WHERE user_id = :clientId)', [':clientId' => $clientId]);
                        $clientTransactionModel->comment       = $params['comment'];
                        $clientTransactionModel->save(false, ['credit_update', 'new_balance', 'comment']);
                        // NOTE: if we use new_balance from the clientTransactionModel again after this point, it will be necessary to refresh the model

                        // process referrals
                        //                        if ($params['reason'] == 'Credit Purchase' && $params['amount'] > 0 && $clientModel->user_currency == $params['currency'])
                        //                            self::processReferrals($clientModel, $params['amount'], $clientTransactionModel->transaction_id);

                        // update user status
                        ClientStatus::process($clientId, 'ChargeCC');

                        // return flagged status to normal if relevant and all is now ok with card details
                        if ($params['profile_id'] > 0) {
                            $clientBillingModel = ClientBilling::model()->findByPk($params['profile_id'], 'flagged > 0');
                            if ($clientBillingModel) {
                                // for the time being we might as well leave the other flagged details in there in case needed
                                $clientBillingModel->flagged       = 0;
                                $clientBillingModel->flagged_count = 0;
                                $clientBillingModel->save(false, ['flagged', 'flagged_count']);
                            }
                            unset($clientBillingModel);
                        }
                    } elseif ($response->transaction_error) {
                        // there was a processing error
                        Globals::setFlash('error', 'There was an error while processing your transaction.');
                        Globals::setFlash('error', $response->exact_resp_code . ' ' . $response->exact_message);
                        // no need to save anything in the client transaction model as it will show a failed transaction from the initial save
                    } else {
                        // card was declined
                        $declinedMsg1 = $public ? 'The card was declined and we are unable to process the transaction. Please contact your financial institution.' : 'The card was declined.';
                        $declinedMsg2 = $public ? 'Transaction Not Approved ' . $response->bank_resp_code : $response->bank_resp_code . ' ' . $response->bank_message;
                        Globals::setFlash('error', $declinedMsg1);
                        Globals::setFlash('error', $declinedMsg2);

                        // update the Client Transaction model to show it was a declined transaction
                        $clientTransactionModel->comment = 'DECLINED';
                        $clientTransactionModel->save(false, ['comment']);

                        // flag the card as being declined
                        if ($params['profile_id'] > 0) {
                            // add a flag to the clientBilling model to show this card was declined ...
                            // note there are two flag levels ...
                            // level 1 is a general decline and the card is probably still useable. Automatic charges to the card should be used with caution.
                            // level 2 means the card is completely unusable e.g. code 502 Lost/Stolen
                            $clientBillingModel = ClientBilling::findOne($params['profile_id']);
                            if ($clientBillingModel) {
                                $clientBillingModel->flagged          = in_array((int)$response->bank_resp_code, $transactionModel->unusableCodes) ? 2 : 1;
                                $clientBillingModel->flagged_by       = (int)@user()->id;
                                $clientBillingModel->flagged_reason   = $response->bank_resp_code . ' ' . $response->bank_message;
                                $clientBillingModel->flagged_datetime = $clientTransactionModel->datetime;  // use the same datetime as the client transaction
                                $clientBillingModel->flagged_count    = $clientBillingModel->flagged_count + 1;
                                $clientBillingModel->save(false, ['flagged', 'flagged_by', 'flagged_reason', 'flagged_datetime', 'flagged_count']);
                                if (!$public && $clientBillingModel->flagged == 2) {
                                    Globals::setFlash('error', 'The card has been flagged as unusable to stop future use.');
                                }
                            }
                            unset($clientBillingModel);
                        }
                    }

                    // return the approved value and ctr (which should be displayed to the customer if appropriate)
                    return [
                        'approved' => $response->transaction_approved,
                        'ctr'      => @$response->ctr,
                        'ref'      => @$clientTransactionModel->transaction_id,
                    ];
                } else {
                    // error with merchant gateway response
                    $clientTransactionModel->comment = 'FAILED - TRY AGAIN';
                    $clientTransactionModel->save(false, ['comment']);
                    $message = 'Error connecting to the payment processor. Please try again in a few minutes. If you repeatedly see this message, please contact support.';
                    Globals::setFlash('error', $message);
                }
            } else {
                // error saving initial transaction model
                $clientTransactionModel->comment = 'FAILED - TRY AGAIN';
                $clientTransactionModel->save(false, ['comment']);
                $message = 'Error saving to transactions table: ' . CHtml::errorSummary($transactionModel);
                Globals::setFlash('error', $message);
            }
        } else {
            // error saving initial client transaction model
            $msg = 'Error Saving Client Transaction Record. ' . CHtml::errorSummary($clientTransactionModel);
            Globals::setFlash('error', $msg);
        }

        // something went wrong
        return false;
    }

    /**
     * @deprecated
     * Retrieves a list of models based on the current search/filter conditions.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria = new CDbCriteria;

        $criteria->compare('id', $this->id, true);
        $criteria->compare('user_id', $this->user_id, true);
        $criteria->compare('amount', $this->amount, true);
        $criteria->compare('currency', $this->currency, true);
        $criteria->compare('cc_type', $this->cc_type, true);
        $criteria->compare('cc_number', $this->cc_number, true);
        $criteria->compare('cc_number_plain', $this->cc_number_plain, true);
        $criteria->compare('cc_exp_date', $this->cc_exp_date, true);
        $criteria->compare('cc_cvv', $this->cc_cvv, true);
        $criteria->compare('cc_cvv_plain', $this->cc_cvv_plain, true);
        $criteria->compare('first_name', $this->first_name, true);
        $criteria->compare('last_name', $this->last_name, true);
        $criteria->compare('cardholder_name', $this->last_name, true);
        $criteria->compare('address', $this->address, true);
        $criteria->compare('city', $this->city, true);
        $criteria->compare('state', $this->state, true);
        $criteria->compare('zip', $this->zip, true);
        $criteria->compare('country', $this->country, true);
        $criteria->compare('ip_address', $this->ip_address, true);
        $criteria->compare('quick_pay', $this->quick_pay);
        $criteria->compare('admin_id', $this->admin_id);
        $criteria->compare('requested', $this->requested, true);
        $criteria->compare('transaction_error', $this->transaction_error);
        $criteria->compare('transaction_approved', $this->transaction_approved);
        $criteria->compare('exact_resp_code', $this->exact_resp_code, true);
        $criteria->compare('exact_message', $this->exact_message, true);
        $criteria->compare('bank_resp_code', $this->bank_resp_code, true);
        $criteria->compare('bank_message', $this->bank_message, true);
        $criteria->compare('bank_resp_code_2', $this->bank_resp_code_2, true);
        $criteria->compare('transaction_tag', $this->transaction_tag, true);
        $criteria->compare('authorization_num', $this->authorization_num, true);
        $criteria->compare('sequence_no', $this->sequence_no, true);
        $criteria->compare('avs', $this->avs, true);
        $criteria->compare('cvv2', $this->cvv2, true);
        $criteria->compare('retrieval_ref_no', $this->retrieval_ref_no, true);
        $criteria->compare('ctr', $this->ctr, true);
        $criteria->compare('user_transaction_id', $this->user_transaction_id);

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
        ]);
    }

    /**
     * @deprecated
     * The following function is for voids and refunds on the currently loaded transaction model
     *
     * @param bool $doVoid Use void rather than refund?
     *
     * @return bool
     */
    public function refund($doVoid = false)
    {
        if ($this->isNewRecord) {
            throw new Exception('A transaction must be loaded before attempting a void/refund');
        }
        // get the client model
        if ($this->client === null) {
            throw new Exception('The requested client does not exist.');
        }
        // get the original client transaction
        if ($this->clientTransaction === null) {
            throw new Exception('The requested client transaction does not exist.');
        }

        $clientId = $this->client->user_id;

        // make sure we can do the void if requested - do refund if not
        if ($doVoid && !Util::isToday($this->requested)) {
            $doVoid = false;
        }

        // first save a new client transaction in preparation - make sure process_balance is set to zero otherwise the balance will be processed immediately
        $clientTransactionModel                       = new ClientTransaction;
        $clientTransactionModel->transaction_currency = $this->clientTransaction->transaction_currency;
        $clientTransactionModel->user_id              = $clientId;
        $clientTransactionModel->profile_id           = $this->clientTransaction->profile_id;
        // note: the new_balance field will no longer be automatically set by the update trigger due to process_balance = 0 being set here
        // so, for now, let's set credit_update = 0 and put in the current user balance
        $clientTransactionModel->credit_update = 0;
        $clientTransactionModel->new_balance   = $this->client->user_balance;
        $clientTransactionModel->reason        = $this->clientTransaction->reason == 'Journal Entry Payment' ? 'Journal Entry Refund' : 'Refund';
        // the comment will be replaced after the transaction is made, for now put 'FAILED' in case something goes wrong
        $clientTransactionModel->comment = 'FAILED - CHECK IF PROCESSED AT FIRST DATA'; //$params['comment'];
        // if no billing id (profile id) flag as a quick pay
        // note: not sure if this is the correct use of the quick_pay field but it doesn't seem to be used for anything else now
        $clientTransactionModel->quick_pay = $this->clientTransaction->quick_pay;
        // process balnce = 0 is very important otherwise the user's balance will get updated twice - see the update_user_balance trigger
        $clientTransactionModel->process_balance = 0;
        $clientTransactionModel->linked_to       = empty($this->clientTransaction->linked_to) ? $this->clientTransaction->transaction_id : $this->clientTransaction->linked_to;
        if ($clientTransactionModel->save()) {
            // save initial transaction model
            $transactionModel          = new TransactionFd;
            $transactionModel->user_id = $clientId;
            // set transaction type 34 = tagged refund, 33 = tagged void
            $transactionModel->transaction_type    = $doVoid ? '33' : '34';
            $transactionModel->amount              = $this->refundAmount;
            $transactionModel->currency            = $this->currency;
            $transactionModel->cc_type             = $this->cc_type;
            $transactionModel->cc_number           = $this->cc_number;
            $transactionModel->cc_exp_date         = $this->cc_exp_date;
            $transactionModel->cc_cvv              = $this->cc_cvv;
            $transactionModel->first_name          = $this->first_name;
            $transactionModel->last_name           = $this->last_name;
            $transactionModel->cardholder_name     = $this->cardholder_name;
            $transactionModel->address             = $this->address;
            $transactionModel->city                = $this->city;
            $transactionModel->state               = $this->state;
            $transactionModel->zip                 = $this->zip;
            $transactionModel->country             = $this->country;
            $transactionModel->quick_pay           = $this->quick_pay; // TODO: remove quick pay from here (more likely) or from user_transactions - no need for both!
            $transactionModel->user_transaction_id = $clientTransactionModel->transaction_id;
            if ($transactionModel->save()) {
                // Not sure if we need to re-send verification details but let's send anyway
                // address shouldn't be more than 28 characters
                $address = trim($this->address);
                if (strlen($address) > 28) {
                    $address = substr($address, 0, 28);
                }

                $billingParams = [
                    //'cc_verification_str1' => $params['address'] . '|' . $params['zip'] . '|' . $params['city'] . '|' . $params['state'] . '|' . $params['country'],
                    'cc_verification_str1' => $address . '|' . trim($this->zip),
                    'cc_verification_str2' => $this->cc_cvv,
                    'cvd_presence_ind'     => empty($this->cc_cvv) ? 0 : 1,
                    'reference_no'         => $clientTransactionModel->transaction_id,
                    'reference_3'          => $transactionModel->id,
                    'customer_ref'         => $clientId,
                    //'client_ip' => $transactionModel->ip_address,
                ];
                //$amount,$cardHolderName,$cardNumber,$cardExpiry,$optionalParams = array()
                $response = $doVoid ? Yii::app()->fdapi->doTaggedVoid($this->refundAmount, $this->transaction_tag, $this->authorization_num, $billingParams) :
                    Yii::app()->fdapi->doTaggedRefund($this->refundAmount, $this->transaction_tag, $this->authorization_num, $billingParams);
                if (!empty($response) && is_object($response)) {
                    // successful response (not necessarily successful transaction at this point though)
                    // save the response fields to the transaction model
                    $responseFields = ['transaction_error', 'transaction_approved', 'exact_resp_code', 'exact_message', 'bank_resp_code', 'bank_message', 'bank_resp_code_2',
                        'transaction_tag', 'authorization_num', 'sequence_no', 'avs', 'cvv2', 'retrieval_ref_no', 'ctr'];
                    foreach ($responseFields as $field) {
                        $transactionModel->$field = @$response->$field;
                    }
                    $transactionModel->save(false, $responseFields);

                    // check for errors
                    if ($response->transaction_approved) {
                        // transaction was successful
                        // Note: In the old code, the user status and all user dids would be set to 1 here and saved, but prob not a good idea.
                        // The new user_status trigger should take care of it all now.

                        // Because we didn't allow the user_balance trigger to run when inserting the user_transaction record, we need to do it here
                        // to be safe, we should use the database to do the sum in case things have changed since the client model was loaded
                        // TODO: think about whether it's safe enough to allow an update trigger on the user_transactions table to allow processing after initial insert
                        // TODO: what do we do if any of the following db updates fail?
                        //$query = $credit ? 'user_balance + :creditUpdate' : 'user_balance - :creditUpdate';
                        if ($clientTransactionModel->reason != 'Journal Entry Refund') {
                            $this->client->user_balance = new CDbExpression('user_balance - :creditUpdate', [':creditUpdate' => $this->refundAmount]);
                            $this->client->save(false, ['user_balance']);
                            //$this->client->refresh();
                        }

                        // save the correct comment and amounts in the client transaction model
                        $txcomment                             = ($doVoid ? 'VOID #' : 'REFUND #') . $clientTransactionModel->linked_to . ' ' . $this->refundComment;
                        $clientTransactionModel->credit_update = (0 - $this->refundAmount);
                        $clientTransactionModel->new_balance   = new CDbExpression('(SELECT user_balance FROM user_datas WHERE user_id = :clientId)', [':clientId' => $clientId]);
                        $clientTransactionModel->comment       = trim($txcomment);
                        $clientTransactionModel->save(false, ['credit_update', 'new_balance', 'comment']);

                        // NOTE: if we use new_balance from the clientTransactionModel again after this point, it will be necessary to refresh the model

                        // update the reversed_by field for the original client transaction model
                        $this->clientTransaction->reversed_by = $clientTransactionModel->transaction_id;
                        $this->clientTransaction->save(false, ['reversed_by']);

                        // reverse any referrals
                        if ($this->refundAmount > 0 && $this->client->user_currency == $clientTransactionModel->transaction_currency) {
                            self::reverseReferral($this->clientTransaction->transaction_id, 'Refund For Client #' . $clientId);
                        }

                        // update user status
                        ClientStatus::process($clientId, 'Refund');
                    } elseif ($response->transaction_error) {
                        // there was a processing error
                        Globals::setFlash('error', 'There was an error while processing your transaction.');
                        Globals::setFlash('error', $response->exact_resp_code . ' ' . $response->exact_message);
                        // no need to save anything in the client transaction model as it will show a failed transaction from the initial save
                    } else {
                        // transaction not approved
                        Globals::setFlash('error', 'The transaction was not approved.');
                        Globals::setFlash('error', $response->bank_resp_code . ' ' . $response->bank_message);
                        // no need to save anything in the client transaction model as it will show a failed transaction from the initial save
                    }

                    // return the approved value and ctr (which should be displayed to the customer if appropriate)
                    return [
                        'approved' => $response->transaction_approved,
                        'ctr'      => @$response->ctr,
                    ];
                } else {
                    // error with merchant gateway response
                    // save message to try again
                    $clientTransactionModel->comment = 'FAILED - TRY AGAIN';
                    $clientTransactionModel->save(false, ['comment']);
                    $message = 'Error connecting to the payment processor. Please try again in a few minutes. If you repeatedly see this message, please contact support.';
                    if (Yii::app() instanceof CConsoleApplication) {
                        throw new Exception($message);
                    } else {
                        Globals::setFlash('error', $message);
                    }
                    //return false;
                }
            } else {
                // error saving initial transaction model
                $clientTransactionModel->comment = 'FAILED - TRY AGAIN';
                $clientTransactionModel->save(false, ['comment']);
                $message = 'Error saving to transactions table: ' . CHtml::errorSummary($transactionModel);
                Globals::setFlash('error', $message);
            }
        } else {
            // error saving initial client transaction model
            $msg = 'Error Saving Client Transaction Record. ' . CHtml::errorSummary($clientTransactionModel);
            Globals::setFlash('error', $msg);
        }

        // something went wrong
        return false;
    }

    /**
     * @deprecated
     * Reverses the referral after a refund/dispute
     *
     * @param int $transactionId The client transaction id of the original credit purchase that created the referral
     * @param string $comment
     *
     * @return void
     */
    public function reverseReferral($transactionId, $comment = '')
    {
        $transactions = ClientTransaction::model()->findAllByAttributes([
            'reason'    => 'Referral Credit',
            'linked_to' => $transactionId,
        ], 'reversed_by IS NULL');

        foreach ($transactions as $tx) {
            // create a new client transaction for the reversal
            $clientTransactionModel                       = new ClientTransaction;
            $clientTransactionModel->transaction_currency = $tx->transaction_currency;
            $clientTransactionModel->user_id              = $tx->user_id;
            $clientTransactionModel->credit_update        = 0 - $tx->credit_update;
            $clientTransactionModel->reason               = 'Referral Credit Reversal';
            $clientTransactionModel->comment              = trim('TID #' . $tx->transaction_id . ' ' . $comment);
            $clientTransactionModel->linked_to            = $tx->transaction_id;

            if ($clientTransactionModel->save()) {
                // add the reversed by id
                $tx->reversed_by = $clientTransactionModel->transaction_id;
                $tx->save(false, ['reversed_by']);
            } else {
                $msg = 'Error Saving Client Referral Reversal Transaction Record. ' . CHtml::errorSummary($clientTransactionModel);
                Globals::setFlash('error', $msg);
            }
            unset($clientTransactionModel);
            // at this point, the update user balance trigger should run and update the client's balance and the new_balance field

            // check referred user status
            ClientStatus::process($tx->user_id, 'Referral Credit Reversal For TID #' . $tx->transaction_id);
        }
    }

    /**
     * @deprecated
     * @param bool $close
     *
     * @throws Exception
     */
    public function dispute($close = false)
    {
        if ($this->isNewRecord) {
            throw new Exception('A transaction must be loaded before adding/closing a dispute');
        }
        // get the client model
        if ($this->client === null) {
            throw new Exception('The requested client does not exist.');
        }
        // get the original client transaction
        if ($this->clientTransaction === null) {
            throw new Exception('The requested client transaction does not exist.');
        }

        $clientId = $this->client->user_id;

        if ($close) {
            // make sure this transaction has a dispute associated with it.
            if (empty($this->clientTransaction->dispute)) {
                throw new Exception('The requested client transaction doesn\'t have a dispute associated with it.');
            }

            if (empty($this->disputeStatus)) {
                throw new Exception('Dispute status not found');
            }

            // save dispute status
            $this->clientTransaction->dispute->status = $this->disputeStatus;
            if (!$this->clientTransaction->dispute->save()) {
                throw new Exception('Error saving dispute status');
            }

            // if dispute in our favor - reverse the reversed entry
            if ($this->disputeStatus == Dispute::STATUS_CLOSED_WIN) {
                // save a new client transaction which adds the credit back
                $clientTransactionModel                       = new ClientTransaction;
                $clientTransactionModel->dispute_id           = $this->clientTransaction->dispute_id;
                $clientTransactionModel->transaction_currency = $this->clientTransaction->transaction_currency;
                $clientTransactionModel->user_id              = $clientId;
                $clientTransactionModel->profile_id           = $this->clientTransaction->profile_id;
                $clientTransactionModel->credit_update        = $this->amount;
                $clientTransactionModel->reason               = strstr($this->clientTransaction->reason, 'Journal Entry') ? 'Journal Entry Dispute' : 'Dispute';
                $clientTransactionModel->comment              = 'TX#' . $this->clientTransaction->transaction_id . ' Ref:' . $this->clientTransaction->dispute->ref . ' (Dispute Won)'; //$params['comment'];
                // if no billing id (profile id) flag as a quick pay
                // note: not sure if this is the correct use of the quick_pay field but it doesn't seem to be used for anything else now
                $clientTransactionModel->quick_pay = $this->clientTransaction->quick_pay;
                //$clientTransactionModel->linked_to = empty($this->clientTransaction->linked_to) ? $this->clientTransaction->transaction_id : $this->clientTransaction->linked_to;
                if (!$clientTransactionModel->save()) {
                    throw new Exception('Error saving dispute win transaction.');
                }
            }
        } else {
            // make sure this transaction doesn't already have a dispute associated with it.
            if (!empty($this->clientTransaction->dispute_id)) {
                throw new Exception('The requested client transaction already has a dispute associated with it.');
            }

            // open new dispute
            $dispute      = new Dispute();
            $dispute->ref = $this->disputeRef;
            if (!$dispute->save()) {
                throw new Exception('Error saving dispute.');
            }

            // save the dispute ref in the current client transaction
            $this->clientTransaction->dispute_id = $dispute->id;
            $this->clientTransaction->save(false, ['dispute_id']);

            // save a new client transaction which reverses the payment
            $clientTransactionModel                       = new ClientTransaction;
            $clientTransactionModel->dispute_id           = $dispute->id;
            $clientTransactionModel->transaction_currency = $this->clientTransaction->transaction_currency;
            $clientTransactionModel->user_id              = $clientId;
            $clientTransactionModel->profile_id           = $this->clientTransaction->profile_id;
            $clientTransactionModel->credit_update        = 0 - $this->amount;
            $clientTransactionModel->reason               = strstr($this->clientTransaction->reason, 'Journal Entry') ? 'Journal Entry Dispute' : 'Dispute';
            $clientTransactionModel->comment              = 'TX#' . $this->clientTransaction->transaction_id . ' Ref:' . $this->disputeRef; //$params['comment'];
            if (!empty($this->disputeComment)) {
                $clientTransactionModel->comment .= ' ' . $this->disputeComment;
            }
            // if no billing id (profile id) flag as a quick pay
            // note: not sure if this is the correct use of the quick_pay field but it doesn't seem to be used for anything else now
            $clientTransactionModel->quick_pay = $this->clientTransaction->quick_pay;
            //$clientTransactionModel->linked_to = empty($this->clientTransaction->linked_to) ? $this->clientTransaction->transaction_id : $this->clientTransaction->linked_to;
            if (!$clientTransactionModel->save()) {
                throw new Exception('Error saving dispute transaction.');
            }

            // save a new client transaction which adds on $35 dispute fee
            $clientTransactionModel                       = new ClientTransaction;
            $clientTransactionModel->dispute_id           = $dispute->id;
            $clientTransactionModel->transaction_currency = $this->clientTransaction->transaction_currency;
            $clientTransactionModel->user_id              = $clientId;
            $clientTransactionModel->profile_id           = $this->clientTransaction->profile_id;
            $clientTransactionModel->credit_update        = -35;
            $clientTransactionModel->reason               = 'Fees and Charges';
            $clientTransactionModel->comment              = 'Dispute TX#' . $this->clientTransaction->transaction_id . ' Ref:' . $this->disputeRef; //$params['comment'];
            // if no billing id (profile id) flag as a quick pay
            // note: not sure if this is the correct use of the quick_pay field but it doesn't seem to be used for anything else now
            $clientTransactionModel->quick_pay = $this->clientTransaction->quick_pay;
            //$clientTransactionModel->linked_to = empty($this->clientTransaction->linked_to) ? $this->clientTransaction->transaction_id : $this->clientTransaction->linked_to;
            if (!$clientTransactionModel->save()) {
                throw new Exception('Error saving dispute fee.');
            }
        }
    }

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
    public function getClientTransaction()
    {
        return $this->hasOne(Client::className(), ['id' => 'user_transaction_id']);
    }

}