<?php
use Gregwar\Captcha\CaptchaBuilder;

class LoginModel
{
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    public function login()
    {

        if (!isset($_POST['user_name']) OR empty($_POST['user_name'])) {
            $_SESSION["feedback_negative"][] = FEEDBACK_USERNAME_FIELD_EMPTY;
            return false;
        }
        if (!isset($_POST['user_password']) OR empty($_POST['user_password'])) {
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_FIELD_EMPTY;
            return false;
        }
        $sth = $this->db->prepare("SELECT user_id,
                                          user_name,
                                          user_email,
                                          user_password_hash,
                                          user_active,
                                          user_account_type,
                                          user_failed_logins,
                                          user_last_failed_login
                                   FROM   users
                                   WHERE  (user_name = :user_name OR user_email = :user_name)
                                          AND user_provider_type = :provider_type");
        $sth->execute(array(':user_name' => $_POST['user_name'], ':provider_type' => 'DEFAULT'));
        $count =  $sth->rowCount();
        if ($count != 1) {
            $_SESSION["feedback_negative"][] = FEEDBACK_LOGIN_FAILED;
            return false;
        }
        $result = $sth->fetch();
        if (($result->user_failed_logins >= 3) AND ($result->user_last_failed_login > (time()-30))) {
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_WRONG_3_TIMES;
            return false;
        }
        if (password_verify($_POST['user_password'], $result->user_password_hash)) {

            if ($result->user_active != 1) {
                $_SESSION["feedback_negative"][] = FEEDBACK_ACCOUNT_NOT_ACTIVATED_YET;
                return false;
            }
            Session::init();
            Session::set('user_logged_in', true);
            Session::set('user_id', $result->user_id);
            Session::set('user_name', $result->user_name);
            Session::set('user_email', $result->user_email);
            Session::set('user_account_type', $result->user_account_type);
            Session::set('user_provider_type', 'DEFAULT');
            Session::set('user_avatar_file', $this->getUserAvatarFilePath());
            $this->setGravatarImageUrl($result->user_email, AVATAR_SIZE);
            if ($result->user_last_failed_login > 0) {
                $sql = "UPDATE users SET user_failed_logins = 0, user_last_failed_login = NULL
                        WHERE user_id = :user_id AND user_failed_logins != 0";
                $sth = $this->db->prepare($sql);
                $sth->execute(array(':user_id' => $result->user_id));
            }
            $user_last_login_timestamp = time();
            $sql = "UPDATE users SET user_last_login_timestamp = :user_last_login_timestamp WHERE user_id = :user_id";
            $sth = $this->db->prepare($sql);
            $sth->execute(array(':user_id' => $result->user_id, ':user_last_login_timestamp' => $user_last_login_timestamp));
            if (isset($_POST['user_rememberme'])) {
                $random_token_string = hash('sha256', mt_rand());
                $sql = "UPDATE users SET user_rememberme_token = :user_rememberme_token WHERE user_id = :user_id";
                $sth = $this->db->prepare($sql);
                $sth->execute(array(':user_rememberme_token' => $random_token_string, ':user_id' => $result->user_id));
                $cookie_string_first_part = $result->user_id . ':' . $random_token_string;
                $cookie_string_hash = hash('sha256', $cookie_string_first_part);
                $cookie_string = $cookie_string_first_part . ':' . $cookie_string_hash;
                setcookie('rememberme', $cookie_string, time() + COOKIE_RUNTIME, "/", COOKIE_DOMAIN);
            }
            return true;

        } else {
            $sql = "UPDATE users
                    SET user_failed_logins = user_failed_logins+1, user_last_failed_login = :user_last_failed_login
                    WHERE user_name = :user_name OR user_email = :user_name";
            $sth = $this->db->prepare($sql);
            $sth->execute(array(':user_name' => $_POST['user_name'], ':user_last_failed_login' => time() ));
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_WRONG;
            return false;
        }
        return false;
    }
    public function loginWithCookie()
    {
        $cookie = isset($_COOKIE['rememberme']) ? $_COOKIE['rememberme'] : '';
        if (!$cookie) {
            $_SESSION["feedback_negative"][] = FEEDBACK_COOKIE_INVALID;
            return false;
        }
        list ($user_id, $token, $hash) = explode(':', $cookie);
        if ($hash !== hash('sha256', $user_id . ':' . $token)) {
            $_SESSION["feedback_negative"][] = FEEDBACK_COOKIE_INVALID;
            return false;
        }
        if (empty($token)) {
            $_SESSION["feedback_negative"][] = FEEDBACK_COOKIE_INVALID;
            return false;
        }
        $query = $this->db->prepare("SELECT user_id, user_name, user_email, user_password_hash, user_active,
                                          user_account_type,  user_has_avatar, user_failed_logins, user_last_failed_login
                                     FROM users
                                     WHERE user_id = :user_id
                                       AND user_rememberme_token = :user_rememberme_token
                                       AND user_rememberme_token IS NOT NULL
                                       AND user_provider_type = :provider_type");
        $query->execute(array(':user_id' => $user_id, ':user_rememberme_token' => $token, ':provider_type' => 'DEFAULT'));
        $count =  $query->rowCount();
        if ($count == 1) {
            $result = $query->fetch();
            Session::init();
            Session::set('user_logged_in', true);
            Session::set('user_id', $result->user_id);
            Session::set('user_name', $result->user_name);
            Session::set('user_email', $result->user_email);
            Session::set('user_account_type', $result->user_account_type);
            Session::set('user_provider_type', 'DEFAULT');
            Session::set('user_avatar_file', $this->getUserAvatarFilePath());
            $this->setGravatarImageUrl($result->user_email, AVATAR_SIZE);
            $user_last_login_timestamp = time();
            $sql = "UPDATE users SET user_last_login_timestamp = :user_last_login_timestamp WHERE user_id = :user_id";
            $sth = $this->db->prepare($sql);
            $sth->execute(array(':user_id' => $user_id, ':user_last_login_timestamp' => $user_last_login_timestamp));
            $_SESSION["feedback_positive"][] = FEEDBACK_COOKIE_LOGIN_SUCCESSFUL;
            return true;
        } else {
            $_SESSION["feedback_negative"][] = FEEDBACK_COOKIE_INVALID;
            return false;
        }
    }
    public function loginWithFacebook()
    {
        $facebook = new Facebook(array('appId' => FACEBOOK_LOGIN_APP_ID, 'secret' => FACEBOOK_LOGIN_APP_SECRET));
        $user = $facebook->getUser();
        if ($user) {
            try {
                $facebook_user_data = $facebook->api('/me');
                $query = $this->db->prepare("SELECT user_id,
                                              user_name,
                                              user_email,
                                              user_account_type,
                                              user_provider_type
                                           FROM users
                                           WHERE user_facebook_uid = :user_facebook_uid
                                             AND user_provider_type = :provider_type");
                $query->execute(array(':user_facebook_uid' => $facebook_user_data["id"], ':provider_type' => 'FACEBOOK'));
                $count =  $query->rowCount();
                if ($count != 1) {
                    $_SESSION["feedback_negative"][] = FEEDBACK_FACEBOOK_LOGIN_NOT_REGISTERED;
                    return false;
                }

                $result = $query->fetch();
                Session::init();
                Session::set('user_logged_in', true);
                Session::set('user_id', $result->user_id);
                Session::set('user_name', $result->user_name);
                Session::set('user_email', $result->user_email);
                Session::set('user_account_type', $result->user_account_type);
                Session::set('user_provider_type', 'FACEBOOK');
                Session::set('user_avatar_file', $this->getUserAvatarFilePath());
                $user_last_login_timestamp = time();
                $sql = "UPDATE users SET user_last_login_timestamp = :user_last_login_timestamp WHERE user_id = :user_id";
                $sth = $this->db->prepare($sql);
                $sth->execute(array(':user_id' => $result->user_id, ':user_last_login_timestamp' => $user_last_login_timestamp));

                return true;

            } catch (FacebookApiException $e) {
                error_log($e);
                $user = null;
            }
        }
        return false;
    }
    public function logout()
    {
        setcookie('rememberme', false, time() - (3600 * 3650), '/', COOKIE_DOMAIN);
        Session::destroy();
    }
    public function deleteCookie()
    {
        setcookie('rememberme', false, time() - (3600 * 3650), '/', COOKIE_DOMAIN);
    }
    public function isUserLoggedIn()
    {
        return Session::get('user_logged_in');
    }
    public function editUserName()
    {
        if (!isset($_POST['user_name']) OR empty($_POST['user_name'])) {
            $_SESSION["feedback_negative"][] = FEEDBACK_USERNAME_FIELD_EMPTY;
            return false;
        }
        if ($_POST['user_name'] == $_SESSION["user_name"]) {
            $_SESSION["feedback_negative"][] = FEEDBACK_USERNAME_SAME_AS_OLD_ONE;
            return false;
        }
        if (!preg_match("/^(?=.{2,64}$)[a-zA-Z][a-zA-Z0-9]*(?: [a-zA-Z0-9]+)*$/", $_POST['user_name'])) {
            $_SESSION["feedback_negative"][] = FEEDBACK_USERNAME_DOES_NOT_FIT_PATTERN;
            return false;
        }
        $user_name = substr(strip_tags($_POST['user_name']), 0, 64);
        $query = $this->db->prepare("SELECT user_id FROM users WHERE user_name = :user_name");
        $query->execute(array(':user_name' => $user_name));
        $count =  $query->rowCount();
        if ($count == 1) {
            $_SESSION["feedback_negative"][] = FEEDBACK_USERNAME_ALREADY_TAKEN;
            return false;
        }

        $query = $this->db->prepare("UPDATE users SET user_name = :user_name WHERE user_id = :user_id");
        $query->execute(array(':user_name' => $user_name, ':user_id' => $_SESSION['user_id']));
        $count =  $query->rowCount();
        if ($count == 1) {
            Session::set('user_name', $user_name);
            $_SESSION["feedback_positive"][] = FEEDBACK_USERNAME_CHANGE_SUCCESSFUL;
            return true;
        } else {
            $_SESSION["feedback_negative"][] = FEEDBACK_UNKNOWN_ERROR;
            return false;
        }
    }
    public function editUserEmail()
    {
        if (!isset($_POST['user_email']) OR empty($_POST['user_email'])) {
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_FIELD_EMPTY;
            return false;
        }
        if ($_POST['user_email'] == $_SESSION["user_email"]) {
            $_SESSION["feedback_negative"][] = FEEDBACK_EMAIL_SAME_AS_OLD_ONE;
            return false;
        }
        if (!filter_var($_POST['user_email'], FILTER_VALIDATE_EMAIL)) {
            $_SESSION["feedback_negative"][] = FEEDBACK_EMAIL_DOES_NOT_FIT_PATTERN;
            return false;
        }
        $query = $this->db->prepare("SELECT * FROM users WHERE user_email = :user_email");
        $query->execute(array(':user_email' => $_POST['user_email']));
        $count =  $query->rowCount();
        if ($count == 1) {
            $_SESSION["feedback_negative"][] = FEEDBACK_USER_EMAIL_ALREADY_TAKEN;
            return false;
        }
        $user_email = substr(strip_tags($_POST['user_email']), 0, 64);
        $query = $this->db->prepare("UPDATE users SET user_email = :user_email WHERE user_id = :user_id");
        $query->execute(array(':user_email' => $user_email, ':user_id' => $_SESSION['user_id']));
        $count =  $query->rowCount();
        if ($count != 1) {
            $_SESSION["feedback_negative"][] = FEEDBACK_UNKNOWN_ERROR;
            return false;
        }

        Session::set('user_email', $user_email);
        $this->setGravatarImageUrl($user_email, AVATAR_SIZE);
        $_SESSION["feedback_positive"][] = FEEDBACK_EMAIL_CHANGE_SUCCESSFUL;
        return false;
    }
    public function registerNewUser()
    {
        if (!$this->checkCaptcha()) {
            $_SESSION["feedback_negative"][] = FEEDBACK_CAPTCHA_WRONG;
        } elseif (empty($_POST['user_name'])) {
            $_SESSION["feedback_negative"][] = FEEDBACK_USERNAME_FIELD_EMPTY;
        } elseif (empty($_POST['user_password_new']) OR empty($_POST['user_password_repeat'])) {
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_FIELD_EMPTY;
        } elseif ($_POST['user_password_new'] !== $_POST['user_password_repeat']) {
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_REPEAT_WRONG;
        } elseif (strlen($_POST['user_password_new']) < 6) {
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_TOO_SHORT;
        } elseif (strlen($_POST['user_name']) > 64 OR strlen($_POST['user_name']) < 2) {
            $_SESSION["feedback_negative"][] = FEEDBACK_USERNAME_TOO_SHORT_OR_TOO_LONG;
        } elseif (!preg_match('/^[a-z\d]{2,64}$/i', $_POST['user_name'])) {
            $_SESSION["feedback_negative"][] = FEEDBACK_USERNAME_DOES_NOT_FIT_PATTERN;
        } elseif (empty($_POST['user_email'])) {
            $_SESSION["feedback_negative"][] = FEEDBACK_EMAIL_FIELD_EMPTY;
        } elseif (strlen($_POST['user_email']) > 64) {
            $_SESSION["feedback_negative"][] = FEEDBACK_EMAIL_TOO_LONG;
        } elseif (!filter_var($_POST['user_email'], FILTER_VALIDATE_EMAIL)) {
            $_SESSION["feedback_negative"][] = FEEDBACK_EMAIL_DOES_NOT_FIT_PATTERN;
        } elseif (!empty($_POST['user_name'])
            AND strlen($_POST['user_name']) <= 64
            AND strlen($_POST['user_name']) >= 2
            AND preg_match('/^[a-z\d]{2,64}$/i', $_POST['user_name'])
            AND !empty($_POST['user_email'])
            AND strlen($_POST['user_email']) <= 64
            AND filter_var($_POST['user_email'], FILTER_VALIDATE_EMAIL)
            AND !empty($_POST['user_password_new'])
            AND !empty($_POST['user_password_repeat'])
            AND ($_POST['user_password_new'] === $_POST['user_password_repeat'])) {
            $user_name = strip_tags($_POST['user_name']);
            $user_email = strip_tags($_POST['user_email']);
            $hash_cost_factor = (defined('HASH_COST_FACTOR') ? HASH_COST_FACTOR : null);
            $user_password_hash = password_hash($_POST['user_password_new'], PASSWORD_DEFAULT, array('cost' => $hash_cost_factor));
            $query = $this->db->prepare("SELECT * FROM users WHERE user_name = :user_name");
            $query->execute(array(':user_name' => $user_name));
            $count =  $query->rowCount();
            if ($count == 1) {
                $_SESSION["feedback_negative"][] = FEEDBACK_USERNAME_ALREADY_TAKEN;
                return false;
            }
            $query = $this->db->prepare("SELECT user_id FROM users WHERE user_email = :user_email");
            $query->execute(array(':user_email' => $user_email));
            $count =  $query->rowCount();
            if ($count == 1) {
                $_SESSION["feedback_negative"][] = FEEDBACK_USER_EMAIL_ALREADY_TAKEN;
                return false;
            }
            $user_activation_hash = sha1(uniqid(mt_rand(), true));
            $user_creation_timestamp = time();
            $sql = "INSERT INTO users (user_name, user_password_hash, user_email, user_creation_timestamp, user_activation_hash, user_provider_type)
                    VALUES (:user_name, :user_password_hash, :user_email, :user_creation_timestamp, :user_activation_hash, :user_provider_type)";
            $query = $this->db->prepare($sql);
            $query->execute(array(':user_name' => $user_name,
                                  ':user_password_hash' => $user_password_hash,
                                  ':user_email' => $user_email,
                                  ':user_creation_timestamp' => $user_creation_timestamp,
                                  ':user_activation_hash' => $user_activation_hash,
                                  ':user_provider_type' => 'DEFAULT'));
            $count =  $query->rowCount();
            if ($count != 1) {
                $_SESSION["feedback_negative"][] = FEEDBACK_ACCOUNT_CREATION_FAILED;
                return false;
            }
            $query = $this->db->prepare("SELECT user_id FROM users WHERE user_name = :user_name");
            $query->execute(array(':user_name' => $user_name));
            if ($query->rowCount() != 1) {
                $_SESSION["feedback_negative"][] = FEEDBACK_UNKNOWN_ERROR;
                return false;
            }
            $result_user_row = $query->fetch();
            $user_id = $result_user_row->user_id;
            if ($this->sendVerificationEmail($user_id, $user_email, $user_activation_hash)) {
                $_SESSION["feedback_positive"][] = FEEDBACK_ACCOUNT_SUCCESSFULLY_CREATED;
                return true;
            } else {
                $query = $this->db->prepare("DELETE FROM users WHERE user_id = :last_inserted_id");
                $query->execute(array(':last_inserted_id' => $user_id));
                $_SESSION["feedback_negative"][] = FEEDBACK_VERIFICATION_MAIL_SENDING_FAILED;
                return false;
            }
        } else {
            $_SESSION["feedback_negative"][] = FEEDBACK_UNKNOWN_ERROR;
        }
        return false;
    }
    private function sendVerificationEmail($user_id, $user_email, $user_activation_hash)
    {
        $mail = new PHPMailer;
        if (EMAIL_USE_SMTP) {
            $mail->IsSMTP();
            $mail->SMTPDebug = PHPMAILER_DEBUG_MODE;
            $mail->SMTPAuth = EMAIL_SMTP_AUTH;
            if (defined('EMAIL_SMTP_ENCRYPTION')) {
                $mail->SMTPSecure = EMAIL_SMTP_ENCRYPTION;
            }
            $mail->Host = EMAIL_SMTP_HOST;
            $mail->Username = EMAIL_SMTP_USERNAME;
            $mail->Password = EMAIL_SMTP_PASSWORD;
            $mail->Port = EMAIL_SMTP_PORT;
        } else {
            $mail->IsMail();
        }

        $mail->From = EMAIL_VERIFICATION_FROM_EMAIL;
        $mail->FromName = EMAIL_VERIFICATION_FROM_NAME;
        $mail->AddAddress($user_email);
        $mail->Subject = EMAIL_VERIFICATION_SUBJECT;
        $mail->Body = EMAIL_VERIFICATION_CONTENT . EMAIL_VERIFICATION_URL . '/' . urlencode($user_id) . '/' . urlencode($user_activation_hash);
        if($mail->Send()) {
            $_SESSION["feedback_positive"][] = FEEDBACK_VERIFICATION_MAIL_SENDING_SUCCESSFUL;
            return true;
        } else {
            $_SESSION["feedback_negative"][] = FEEDBACK_VERIFICATION_MAIL_SENDING_ERROR . $mail->ErrorInfo;
            return false;
        }
    }
    public function verifyNewUser($user_id, $user_activation_verification_code)
    {
        $sth = $this->db->prepare("UPDATE users
                                   SET user_active = 1, user_activation_hash = NULL
                                   WHERE user_id = :user_id AND user_activation_hash = :user_activation_hash");
        $sth->execute(array(':user_id' => $user_id, ':user_activation_hash' => $user_activation_verification_code));

        if ($sth->rowCount() == 1) {
            $_SESSION["feedback_positive"][] = FEEDBACK_ACCOUNT_ACTIVATION_SUCCESSFUL;
            return true;
        } else {
            $_SESSION["feedback_negative"][] = FEEDBACK_ACCOUNT_ACTIVATION_FAILED;
            return false;
        }
    }

    public function setGravatarImageUrl($email, $s = 44, $d = 'mm', $r = 'pg', $attributes = array())
    {
        $image_url = 'http://www.gravatar.com/avatar/' . md5(strtolower(trim($email))) .  "?s=$s&d=$d&r=$r";
        Session::set('user_gravatar_image_url', $image_url);
        $image_url_with_tag = '<img src="' . $image_url . '"';
        foreach ($attributes as $key => $val) {
            $image_url_with_tag .= ' ' . $key . '="' . $val . '"';
        }
        $image_url_with_tag .= ' />';
        Session::set('user_gravatar_image_tag', $image_url_with_tag);
    }
    public function getUserAvatarFilePath()
    {
        $query = $this->db->prepare("SELECT user_has_avatar FROM users WHERE user_id = :user_id");
        $query->execute(array(':user_id' => $_SESSION['user_id']));

        if ($query->fetch()->user_has_avatar) {
            return URL . AVATAR_PATH . $_SESSION['user_id'] . '.jpg';
        } else {
            return URL . AVATAR_PATH . AVATAR_DEFAULT_IMAGE;
        }
    }
    public function createAvatar()
    {
        if (!is_dir(AVATAR_PATH) OR !is_writable(AVATAR_PATH)) {
            $_SESSION["feedback_negative"][] = FEEDBACK_AVATAR_FOLDER_DOES_NOT_EXIST_OR_NOT_WRITABLE;
            return false;
        }

        if (!isset($_FILES['avatar_file']) OR empty ($_FILES['avatar_file']['tmp_name'])) {
            $_SESSION["feedback_negative"][] = FEEDBACK_AVATAR_IMAGE_UPLOAD_FAILED;
            return false;
        }
        $image_proportions = getimagesize($_FILES['avatar_file']['tmp_name']);
        if ($_FILES['avatar_file']['size'] > 5000000 ) {
            $_SESSION["feedback_negative"][] = FEEDBACK_AVATAR_UPLOAD_TOO_BIG;
            return false;
        }
        if ($image_proportions[0] < AVATAR_SIZE OR $image_proportions[1] < AVATAR_SIZE) {
            $_SESSION["feedback_negative"][] = FEEDBACK_AVATAR_UPLOAD_TOO_SMALL;
            return false;
        }

        if ($image_proportions['mime'] == 'image/jpeg' || $image_proportions['mime'] == 'image/png') {
            $target_file_path = AVATAR_PATH . $_SESSION['user_id'] . ".jpg";
            $this->resizeAvatarImage($_FILES['avatar_file']['tmp_name'], $target_file_path, AVATAR_SIZE, AVATAR_SIZE, AVATAR_JPEG_QUALITY, true);
            $query = $this->db->prepare("UPDATE users SET user_has_avatar = TRUE WHERE user_id = :user_id");
            $query->execute(array(':user_id' => $_SESSION['user_id']));
            Session::set('user_avatar_file', $this->getUserAvatarFilePath());
            $_SESSION["feedback_positive"][] = FEEDBACK_AVATAR_UPLOAD_SUCCESSFUL;
            return true;
        } else {
            $_SESSION["feedback_negative"][] = FEEDBACK_AVATAR_UPLOAD_WRONG_TYPE;
            return false;
        }
    }

    public function resizeAvatarImage(
        $source_image, $destination_filename, $width = 44, $height = 44, $quality = 85, $crop = true)
    {
        $image_data = getimagesize($source_image);
        if (!$image_data) {
            return false;
        }
        switch ($image_data['mime']) {
            case 'image/gif':
                $get_func = 'imagecreatefromgif';
                $suffix = ".gif";
            break;
            case 'image/jpeg';
                $get_func = 'imagecreatefromjpeg';
                $suffix = ".jpg";
            break;
            case 'image/png':
                $get_func = 'imagecreatefrompng';
                $suffix = ".png";
            break;
        }

        $img_original = call_user_func($get_func, $source_image );
        $old_width = $image_data[0];
        $old_height = $image_data[1];
        $new_width = $width;
        $new_height = $height;
        $src_x = 0;
        $src_y = 0;
        $current_ratio = round($old_width / $old_height, 2);
        $desired_ratio_after = round($width / $height, 2);
        $desired_ratio_before = round($height / $width, 2);

        if ($old_width < $width OR $old_height < $height) {
            return false;
        }
        if ($crop) {
            $new_image = imagecreatetruecolor($width, $height);
            if ($current_ratio > $desired_ratio_after) {
                $new_width = $old_width * $height / $old_height;
            }
            if ($current_ratio > $desired_ratio_before AND $current_ratio < $desired_ratio_after) {

                if ($old_width > $old_height) {
                    $new_height = max($width, $height);
                    $new_width = $old_width * $new_height / $old_height;
                } else {
                    $new_height = $old_height * $width / $old_width;
                }
            }
            if ($current_ratio < $desired_ratio_before) {
                $new_height = $old_height * $width / $old_width;
            }
            $width_ratio = $old_width / $new_width;
            $height_ratio = $old_height / $new_height;
            $src_x = floor((($new_width - $width) / 2) * $width_ratio);
            $src_y = round((($new_height - $height) / 2) * $height_ratio);
        }
        else {
            if ($old_width > $old_height) {
                $ratio = max($old_width, $old_height) / max($width, $height);
            } else {
                $ratio = max($old_width, $old_height) / min($width, $height);
            }

            $new_width = $old_width / $ratio;
            $new_height = $old_height / $ratio;
            $new_image = imagecreatetruecolor($new_width, $new_height);
        }
        imagecopyresampled($new_image, $img_original, 0, 0, $src_x, $src_y, $new_width, $new_height, $old_width, $old_height);
        imagejpeg($new_image, $destination_filename, $quality);
        imagedestroy($new_image);
        imagedestroy($img_original);

        if (file_exists($destination_filename)) {
            return true;
        }
        return false;
    }
    public function requestPasswordReset()
    {
        if (!isset($_POST['user_name']) OR empty($_POST['user_name'])) {
            $_SESSION["feedback_negative"][] = FEEDBACK_USERNAME_FIELD_EMPTY;
            return false;
        }

        $temporary_timestamp = time();
        $user_password_reset_hash = sha1(uniqid(mt_rand(), true));
        $user_name = strip_tags($_POST['user_name']);
        $query = $this->db->prepare("SELECT user_id, user_email FROM users
                                     WHERE user_name = :user_name AND user_provider_type = :provider_type");
        $query->execute(array(':user_name' => $user_name, ':provider_type' => 'DEFAULT'));
        $count = $query->rowCount();
        if ($count != 1) {
            $_SESSION["feedback_negative"][] = FEEDBACK_USER_DOES_NOT_EXIST;
            return false;
        }
        $result_user_row = $result = $query->fetch();
        $user_email = $result_user_row->user_email;
        if ($this->setPasswordResetDatabaseToken($user_name, $user_password_reset_hash, $temporary_timestamp) == true) {
            if ($this->sendPasswordResetMail($user_name, $user_password_reset_hash, $user_email)) {
                return true;
            }
        }
        return false;
    }

    public function setPasswordResetDatabaseToken($user_name, $user_password_reset_hash, $temporary_timestamp)
    {
        $query_two = $this->db->prepare("UPDATE users
                                            SET user_password_reset_hash = :user_password_reset_hash,
                                                user_password_reset_timestamp = :user_password_reset_timestamp
                                          WHERE user_name = :user_name AND user_provider_type = :provider_type");
        $query_two->execute(array(':user_password_reset_hash' => $user_password_reset_hash,
                                  ':user_password_reset_timestamp' => $temporary_timestamp,
                                  ':user_name' => $user_name,
                                  ':provider_type' => 'DEFAULT'));

        $count =  $query_two->rowCount();
        if ($count == 1) {
            return true;
        } else {
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_RESET_TOKEN_FAIL;
            return false;
        }
    }

    public function sendPasswordResetMail($user_name, $user_password_reset_hash, $user_email)
    {
        $mail = new PHPMailer;
        if (EMAIL_USE_SMTP) {
            $mail->IsSMTP();
            $mail->SMTPDebug = PHPMAILER_DEBUG_MODE;
            $mail->SMTPAuth = EMAIL_SMTP_AUTH;
            if (defined('EMAIL_SMTP_ENCRYPTION')) {
                $mail->SMTPSecure = EMAIL_SMTP_ENCRYPTION;
            }
            $mail->Host = EMAIL_SMTP_HOST;
            $mail->Username = EMAIL_SMTP_USERNAME;
            $mail->Password = EMAIL_SMTP_PASSWORD;
            $mail->Port = EMAIL_SMTP_PORT;
        } else {
            $mail->IsMail();
        }
        $mail->From = EMAIL_PASSWORD_RESET_FROM_EMAIL;
        $mail->FromName = EMAIL_PASSWORD_RESET_FROM_NAME;
        $mail->AddAddress($user_email);
        $mail->Subject = EMAIL_PASSWORD_RESET_SUBJECT;
        $link = EMAIL_PASSWORD_RESET_URL . '/' . urlencode($user_name) . '/' . urlencode($user_password_reset_hash);
        $mail->Body = EMAIL_PASSWORD_RESET_CONTENT . ' ' . $link;
        if($mail->Send()) {
            $_SESSION["feedback_positive"][] = FEEDBACK_PASSWORD_RESET_MAIL_SENDING_SUCCESSFUL;
            return true;
        } else {
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_RESET_MAIL_SENDING_ERROR . $mail->ErrorInfo;
            return false;
        }
    }

    public function verifyPasswordReset($user_name, $verification_code)
    {
        $query = $this->db->prepare("SELECT user_id, user_password_reset_timestamp
                                       FROM users
                                      WHERE user_name = :user_name
                                        AND user_password_reset_hash = :user_password_reset_hash
                                        AND user_provider_type = :user_provider_type");
        $query->execute(array(':user_password_reset_hash' => $verification_code,
                              ':user_name' => $user_name,
                              ':user_provider_type' => 'DEFAULT'));

        if ($query->rowCount() != 1) {
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_RESET_COMBINATION_DOES_NOT_EXIST;
            return false;
        }
        $result_user_row = $query->fetch();
        $timestamp_one_hour_ago = time() - 3600;
        if ($result_user_row->user_password_reset_timestamp > $timestamp_one_hour_ago) {
            $_SESSION["feedback_positive"][] = FEEDBACK_PASSWORD_RESET_LINK_VALID;
            return true;
        } else {
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_RESET_LINK_EXPIRED;
            return false;
        }
    }

    public function setNewPassword()
    {
        if (!isset($_POST['user_name']) OR empty($_POST['user_name'])) {
            $_SESSION["feedback_negative"][] = FEEDBACK_USERNAME_FIELD_EMPTY;
            return false;
        }
        if (!isset($_POST['user_password_reset_hash']) OR empty($_POST['user_password_reset_hash'])) {
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_RESET_TOKEN_MISSING;
            return false;
        }
        if (!isset($_POST['user_password_new']) OR empty($_POST['user_password_new'])) {
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_FIELD_EMPTY;
            return false;
        }
        if (!isset($_POST['user_password_repeat']) OR empty($_POST['user_password_repeat'])) {
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_FIELD_EMPTY;
            return false;
        }
        if ($_POST['user_password_new'] !== $_POST['user_password_repeat']) {
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_REPEAT_WRONG;
            return false;
        }
        if (strlen($_POST['user_password_new']) < 6) {
            $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_TOO_SHORT;
            return false;
        }
        $hash_cost_factor = (defined('HASH_COST_FACTOR') ? HASH_COST_FACTOR : null);
        $user_password_hash = password_hash($_POST['user_password_new'], PASSWORD_DEFAULT, array('cost' => $hash_cost_factor));
        $query = $this->db->prepare("UPDATE users
                                        SET user_password_hash = :user_password_hash,
                                            user_password_reset_hash = NULL,
                                            user_password_reset_timestamp = NULL
                                      WHERE user_name = :user_name
                                        AND user_password_reset_hash = :user_password_reset_hash
                                        AND user_provider_type = :user_provider_type");

        $query->execute(array(':user_password_hash' => $user_password_hash,
                              ':user_name' => $_POST['user_name'],
                              ':user_password_reset_hash' => $_POST['user_password_reset_hash'],
                              ':user_provider_type' => 'DEFAULT'));

        if ($query->rowCount() == 1) {
            $_SESSION["feedback_positive"][] = FEEDBACK_PASSWORD_CHANGE_SUCCESSFUL;
            return true;
        }

        $_SESSION["feedback_negative"][] = FEEDBACK_PASSWORD_CHANGE_FAILED;
        return false;
    }

    public function changeAccountType()
    {
        if (isset($_POST["user_account_upgrade"]) AND !empty($_POST["user_account_upgrade"])) {
            $query = $this->db->prepare("UPDATE users SET user_account_type = 2 WHERE user_id = :user_id");
            $query->execute(array(':user_id' => $_SESSION["user_id"]));

            if ($query->rowCount() == 1) {
                Session::set('user_account_type', 2);
                $_SESSION["feedback_positive"][] = FEEDBACK_ACCOUNT_UPGRADE_SUCCESSFUL;
            } else {
                $_SESSION["feedback_negative"][] = FEEDBACK_ACCOUNT_UPGRADE_FAILED;
            }
        } elseif (isset($_POST["user_account_downgrade"]) AND !empty($_POST["user_account_downgrade"])) {

            $query = $this->db->prepare("UPDATE users SET user_account_type = 1 WHERE user_id = :user_id");
            $query->execute(array(':user_id' => $_SESSION["user_id"]));

            if ($query->rowCount() == 1) {
                Session::set('user_account_type', 1);
                $_SESSION["feedback_positive"][] = FEEDBACK_ACCOUNT_DOWNGRADE_SUCCESSFUL;
            } else {
                $_SESSION["feedback_negative"][] = FEEDBACK_ACCOUNT_DOWNGRADE_FAILED;
            }
        }
    }

    public function generateCaptcha()
    {
        $builder = new CaptchaBuilder;
        $builder->build();

        $_SESSION['captcha'] = $builder->getPhrase();

        header('Content-type: image/jpeg');
        $builder->output();
    }

    private function checkCaptcha()
    {
        if (isset($_POST["captcha"]) AND ($_POST["captcha"] == $_SESSION['captcha'])) {
            return true;
        }
        return false;
    }
    public function getFacebookLoginUrl()
    {
        $facebook = new Facebook(array('appId'  => FACEBOOK_LOGIN_APP_ID, 'secret' => FACEBOOK_LOGIN_APP_SECRET));
        $facebook_login_url = $facebook->getLoginUrl(array('redirect_uri' => URL . FACEBOOK_LOGIN_PATH));

        return $facebook_login_url;
    }

    public function getFacebookRegisterUrl()
    {
        $facebook = new Facebook(array('appId'  => FACEBOOK_LOGIN_APP_ID, 'secret' => FACEBOOK_LOGIN_APP_SECRET));
        $redirect_url_after_facebook_auth = URL . FACEBOOK_REGISTER_PATH;
        $facebook_register_url = $facebook->getLoginUrl(array(
            'scope' => 'email',
            'redirect_uri' => $redirect_url_after_facebook_auth
        ));

        return $facebook_register_url;
    }

    public function registerWithFacebook()
    {
        $facebook = new Facebook(array('appId'  => FACEBOOK_LOGIN_APP_ID, 'secret' => FACEBOOK_LOGIN_APP_SECRET));
        $user = $facebook->getUser();
        if ($user) {
            try {
                $facebook_user_data = $facebook->api('/me');
            } catch (FacebookApiException $e) {
                error_log($e);
                $user = null;
                $_SESSION["feedback_negative"][] = FEEDBACK_FACEBOOK_OFFLINE;
                return false;
            }
        }
        if (!$facebook_user_data) {
            $_SESSION["feedback_negative"][] = FEEDBACK_FACEBOOK_UID_ALREADY_EXISTS;
            return false;
        }

        if (!$this->facebookUserHasEmail($facebook_user_data)) {
            $_SESSION["feedback_negative"][] = FEEDBACK_FACEBOOK_EMAIL_NEEDED;
            return false;
        }
        if ($this->facebookUserIdExistsAlreadyInDatabase($facebook_user_data)) {
            $_SESSION["feedback_negative"][] = FEEDBACK_FACEBOOK_UID_ALREADY_EXISTS;
            return false;
        }

        if ($this->facebookUserNameExistsAlreadyInDatabase($facebook_user_data)) {
        	$facebook_user_data["username"] = $this->generateUniqueUserNameFromExistingUserName($facebook_user_data["username"]);
         if ($this->facebookUserNameExistsAlreadyInDatabase($facebook_user_data)) {
        	$_SESSION["feedback_negative"][] = FEEDBACK_FACEBOOK_USERNAME_ALREADY_EXISTS;
          return false;
         }
        }
        if ($this->facebookUserEmailExistsAlreadyInDatabase($facebook_user_data)) {
            $_SESSION["feedback_negative"][] = FEEDBACK_FACEBOOK_EMAIL_ALREADY_EXISTS;
            return false;
        }
        if ($this->registerNewUserWithFacebook($facebook_user_data)) {
            $_SESSION["feedback_positive"][] = FEEDBACK_FACEBOOK_REGISTER_SUCCESSFUL;
            return true;
        } else {
            $_SESSION["feedback_negative"][] = FEEDBACK_UNKNOWN_ERROR;
            return false;
        }
        return false;
    }
    public function registerNewUserWithFacebook($facebook_user_data)
    {
        $clean_user_name_from_facebook = str_replace(".", "", $facebook_user_data["username"]);
        $user_creation_timestamp = time();

        $sql = "INSERT INTO users (user_name, user_email, user_creation_timestamp, user_active, user_provider_type, user_facebook_uid)
                VALUES (:user_name, :user_email, :user_creation_timestamp, :user_active, :user_provider_type, :user_facebook_uid)";
        $query = $this->db->prepare($sql);
        $query->execute(array(':user_name' => $clean_user_name_from_facebook,
                              ':user_email' => $facebook_user_data["email"],
                              ':user_creation_timestamp' => $user_creation_timestamp,
                              ':user_active' => 1,
                              ':user_provider_type' => 'FACEBOOK',
                              ':user_facebook_uid' => $facebook_user_data["id"]));

        $count = $query->rowCount();
        if ($count == 1) {
            $query = $this->db->prepare("SELECT user_id, user_name, user_email, user_account_type, user_provider_type
                                         FROM   users
                                         WHERE  user_name = :user_name AND user_provider_type = :provider_type");
            $query->execute(array(':user_name' => $clean_user_name_from_facebook, ':provider_type' => 'FACEBOOK'));
            $count_from_select_statement = $query->rowCount();
            if ($count_from_select_statement == 1) {
                return true;
            }
        }
        return false;
    }
    public function facebookUserHasEmail($facebook_user_data)
    {
        if (isset($facebook_user_data["email"]) && !empty($facebook_user_data["email"])) {
            return true;
        }
        return false;
    }
    public function facebookUserIdExistsAlreadyInDatabase($facebook_user_data)
    {
        $query = $this->db->prepare("SELECT user_id FROM users WHERE user_facebook_uid = :user_facebook_uid");
        $query->execute(array(':user_facebook_uid' => $facebook_user_data["id"]));

        if ($query->rowCount() == 1) {
            return true;
        }
        return false;
    }

    public function facebookUserNameExistsAlreadyInDatabase($facebook_user_data)
    {
        $clean_user_name_from_facebook = str_replace(".", "", $facebook_user_data["username"]);

        $query = $this->db->prepare("SELECT user_id FROM users WHERE user_name = :clean_user_name_from_facebook");
        $query->execute(array(':clean_user_name_from_facebook' => $clean_user_name_from_facebook));

        if ($query->rowCount() == 1) {
            return true;
        }
        return false;
    }

    public function facebookUserEmailExistsAlreadyInDatabase($facebook_user_data)
    {
        $query = $this->db->prepare("SELECT user_id FROM users WHERE user_email = :facebook_email");
        $query->execute(array(':facebook_email' => $facebook_user_data["email"]));

        if ($query->rowCount() == 1) {
            return true;
        }
        return false;
    }
    public function generateUniqueUserNameFromExistingUserName($existing_name)
    {
        $existing_name = str_replace(".", "", $existing_name);
        $existing_name = preg_replace('/\s*\d+$/', '', $existing_name);

    	$n = 0;
    	do {
            $n = $n+1;
            $new_username = $existing_name . $n;
            $query = $this->db->prepare("SELECT user_id FROM users WHERE user_name = :name_with_number");
            $query->execute(array(':name_with_number' => $new_username));
    	 	 
    	 } while ($query->rowCount() == 1);

    	return $new_username;
    }

}
