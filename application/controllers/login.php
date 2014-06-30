<?php
class Login extends Controller
{
    function __construct()
    {
        parent::__construct();
    }
    function index()
    {
        $login_model = $this->loadModel('Login');
        if (FACEBOOK_LOGIN == true) {
            $this->view->facebook_login_url = $login_model->getFacebookLoginUrl();
        }
        $this->view->render('login/index');
    }
    function login()
    {
        $login_model = $this->loadModel('Login');
        $login_successful = $login_model->login();

        if ($login_successful) {
            header('location: ' . URL . 'dashboard/index');
        } else {
            header('location: ' . URL . 'login/index');
        }
    }
    function loginWithFacebook()
    {
        $login_model = $this->loadModel('Login');
        $login_successful = $login_model->loginWithFacebook();
        if ($login_successful) {
            header('location: ' . URL . 'dashboard/index');
        } else {
            header('location: ' . URL . 'login/index');
        }
    }
    function logout()
    {
        $login_model = $this->loadModel('Login');
        $login_model->logout();
        header('location: ' . URL);
    }
    function loginWithCookie()
    {
        $login_model = $this->loadModel('Login');
        $login_successful = $login_model->loginWithCookie();

        if ($login_successful) {
            header('location: ' . URL . 'dashboard/index');
        } else {
            $login_model->deleteCookie();
            header('location: ' . URL . 'login/index');
        }
    }
    function showProfile()
    {
        Auth::handleLogin();
        $this->view->render('login/showprofile');
    }
    function editUsername()
    {
        Auth::handleLogin();
        $this->view->render('login/editusername');
    }
    function editUsername_action()
    {
        Auth::handleLogin();
        $login_model = $this->loadModel('Login');
        $login_model->editUserName();
        $this->view->render('login/editusername');
    }
    function editUserEmail()
    {
        Auth::handleLogin();
        $this->view->render('login/edituseremail');
    }
    function editUserEmail_action()
    {
        Auth::handleLogin();
        $login_model = $this->loadModel('Login');
        $login_model->editUserEmail();
        $this->view->render('login/edituseremail');
    }
    function uploadAvatar()
    {
        Auth::handleLogin();
        $login_model = $this->loadModel('Login');
        $this->view->avatar_file_path = $login_model->getUserAvatarFilePath();
        $this->view->render('login/uploadavatar');
    }

    function uploadAvatar_action()
    {
        Auth::handleLogin();
        $login_model = $this->loadModel('Login');
        $login_model->createAvatar();
        $this->view->render('login/uploadavatar');
    }

    function changeAccountType()
    {
        Auth::handleLogin();
        $this->view->render('login/changeaccounttype');
    }
    function changeAccountType_action()
    {
        Auth::handleLogin();
        $login_model = $this->loadModel('Login');
        $login_model->changeAccountType();
        $this->view->render('login/changeaccounttype');
    }
    function register()
    {
        $login_model = $this->loadModel('Login');
        if (FACEBOOK_LOGIN == true) {
            $this->view->facebook_register_url = $login_model->getFacebookRegisterUrl();
        }

        $this->view->render('login/register');
    }
    function register_action()
    {
        $login_model = $this->loadModel('Login');
        $registration_successful = $login_model->registerNewUser();

        if ($registration_successful == true) {
            header('location: ' . URL . 'login/index');
        } else {
            header('location: ' . URL . 'login/register');
        }
    }

    function registerWithFacebook()
    {
        $login_model = $this->loadModel('Login');
        $registration_successful = $login_model->registerWithFacebook();
        if ($registration_successful) {
            header('location: ' . URL . 'login/index');
        } else {
            header('location: ' . URL . 'login/register');
        }
    }
    function verify($user_id, $user_activation_verification_code)
    {
        if (isset($user_id) && isset($user_activation_verification_code)) {
            $login_model = $this->loadModel('Login');
            $login_model->verifyNewUser($user_id, $user_activation_verification_code);
            $this->view->render('login/verify');
        } else {
            header('location: ' . URL . 'login/index');
        }
    }
    function requestPasswordReset()
    {
        $this->view->render('login/requestpasswordreset');
    }
    function requestPasswordReset_action()
    {
        $login_model = $this->loadModel('Login');
        $login_model->requestPasswordReset();
        $this->view->render('login/requestpasswordreset');
    }
    function verifyPasswordReset($user_name, $verification_code)
    {
        $login_model = $this->loadModel('Login');
        if ($login_model->verifyPasswordReset($user_name, $verification_code)) {
            $this->view->user_name = $user_name;
            $this->view->user_password_reset_hash = $verification_code;
            $this->view->render('login/changepassword');
        } else {
            header('location: ' . URL . 'login/index');
        }
    }
    function setNewPassword()
    {
        $login_model = $this->loadModel('Login');
        $login_model->setNewPassword();
        header('location: ' . URL . 'login/index');
    }

    function showCaptcha()
    {
        $login_model = $this->loadModel('Login');
        $login_model->generateCaptcha();
    }
}
