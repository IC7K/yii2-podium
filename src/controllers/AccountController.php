<?php

namespace bizley\podium\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\helpers\Html;
use yii\helpers\Url;
use bizley\podium\models\User;
use bizley\podium\models\LoginForm;
use bizley\podium\models\ReForm;
use bizley\podium\behaviors\FlashBehavior;

class AccountController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow'         => false,
                        'matchCallback' => function ($rule, $action) {
                            return !$this->module->getInstalled();
                        },
                        'denyCallback' => function ($rule, $action) {
                            return $this->redirect(['install/run']);
                        }
                    ],
                    [
                        'allow' => true,
                        'roles' => ['?']
                    ],
                ],
            ],
            'flash' => FlashBehavior::className(),
        ];
    }

    public function actionLogin()
    {
        $model = new LoginForm();

        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }
        else {
            return $this->render('login', [
                        'model' => $model,
            ]);
        }
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    public function actionRegister()
    {
        $model = new User();

        if ($model->load(Yii::$app->request->post()) && $model->register()) {
            try {
                $mailer = Yii::$app->mailer->compose('/mail/register', [
                            'forum' => $this->module->getParam('name', 'Podium Forum'),
                            'link'  => Url::to(['account/activate', 'token' => $model->activation_token], true)
                        ])->setFrom($this->module->getParam('email', 'no-reply@podium-default.net'))
                        ->setTo($model->email)
                        ->setSubject(Yii::t('podium/mail', 'Welcome to {NAME}! This is your activation link', ['NAME' => $this->module->getParam('name', 'Podium Forum')]));
                if ($mailer->send()) {
                    $this->success('Your account has been created but it is not active yet. Click the activation link that has been sent to your e-mail address.');
                }
                else {
                    $this->warning('Your account has been created but it is not active yet. '
                            . 'Unfortunately there was some error while sending you the activation link. '
                            . 'Contact administrator about this or try to {LINK}resend the link{CLOSELINK}.', [
                        'LINK'      => Html::beginTag('a', ['href' => Url::to('account/reactivate')]),
                        'CLOSELINK' => Html::endTag('a')
                    ]);
                }

                return $this->goBack();
            }
            catch (\Exception $e) {
                $this->warning('Your account has been created but it is not active yet. '
                        . 'Unfortunately there was some error while sending you the activation link. '
                        . 'Contact administrator about this or try to {LINK}resend the link{CLOSELINK}.', [
                    'LINK'      => Html::beginTag('a', ['href' => Url::to('account/reactivate')]),
                    'CLOSELINK' => Html::endTag('a')
                ]);
            }
        }
        else {
            return $this->render('register', [
                        'model' => $model,
            ]);
        }
    }

    public function actionReset()
    {
        $model = new ReForm();

        if ($model->load(Yii::$app->request->post())) {

            if ($model->reset()) {
                try {
                    $mailer = Yii::$app->mailer->compose('/mail/reset', [
                                'forum' => $this->module->getParam('name', 'Podium Forum'),
                                'link'  => Url::to(['account/password', 'token' => $model->getUser()->password_reset_token], true)
                            ])->setFrom($this->module->getParam('email', 'no-reply@podium-default.net'))
                            ->setTo($model->getUser()->email)
                            ->setSubject(Yii::t('podium/mail', '{NAME} password reset link', ['NAME' => $this->module->getParam('name', 'Podium Forum')]));
                    if ($mailer->send()) {
                        $this->success('The password reset link has been sent to your e-mail address.');
                    }
                    else {
                        $this->error('Sorry! There was some error while sending you the password reset link. Contact administrator about this problem.');
                    }

                    return $this->goBack();
                }
                catch (\Exception $e) {
                    $this->error('Sorry! There was some error while sending you the password reset link. Contact administrator about this problem.');
                }
            }
            else {
                $this->error('Sorry! We can not find the account with that user name or e-mail address.');
            }
        }

        return $this->render('reset', [
                    'model' => $model,
        ]);
    }

    public function actionReactivate()
    {
        $model = new ReForm();

        if ($model->load(Yii::$app->request->post())) {

            if ($model->reactivate()) {
                try {
                    $mailer = Yii::$app->mailer->compose('/mail/reactivate', [
                                'forum' => $this->module->getParam('name', 'Podium Forum'),
                                'link'  => Url::to(['account/activate', 'token' => $model->getUser()->activation_token], true)
                            ])->setFrom($this->module->getParam('email', 'no-reply@podium-default.net'))
                            ->setTo($model->getUser()->email)
                            ->setSubject(Yii::t('podium/mail', '{NAME} password reset link', ['NAME' => $this->module->getParam('name', 'Podium Forum')]));
                    if ($mailer->send()) {
                        $this->success('The account activation link has been sent to your e-mail address.');
                    }
                    else {
                        $this->error('Sorry! There was some error while sending you the account activation link. Contact administrator about this problem.');
                    }

                    return $this->goBack();
                }
                catch (\Exception $e) {
                    $this->error('Sorry! There was some error while sending you the account activation link. Contact administrator about this problem.');
                }
            }
            else {
                $this->error('Sorry! We can not find the account with that user name or e-mail address.');
            }
        }

        return $this->render('reactivate', [
                    'model' => $model,
        ]);
    }

    public function actionPassword($token)
    {
        $model = User::findByPasswordResetToken($token);

        if ($model) {
            $model->setScenario('passwordChange');
            if ($model->load(Yii::$app->request->post()) && $model->changePassword()) {
                $this->success('Your account password has been changed.');
                return $this->goHome();
            }
            else {
                return $this->render('password', [
                        'model' => $model
                ]);
            }
        }
        else {
            $this->error('The provided password reset token is invalid or expired.');
            return $this->goHome();
        }
    }

    public function actionActivate($token)
    {
        $model = User::findByActivationTokenToken($token);

        if ($model) {
            $model->setScenario('token');
            if ($model->activate()) {
                $this->success('Your account has been activated.');
            }
            else {
                $this->error('Sorry! There was some error while activating your account. Contact administrator about this problem.');
            }
            return $this->goHome();
        }
        else {
            $this->error('The provided activation token is invalid or expired.');
            return $this->goHome();
        }
    }
}