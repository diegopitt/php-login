<?php
class OverviewModel
{
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    public function getAllUsersProfiles()
    {
        $sth = $this->db->prepare("SELECT user_id, user_name, user_email, user_active, user_has_avatar FROM users");
        $sth->execute();

        $all_users_profiles = array();

        foreach ($sth->fetchAll() as $user) {
            $all_users_profiles[$user->user_id] = new stdClass();
            $all_users_profiles[$user->user_id]->user_id = $user->user_id;
            $all_users_profiles[$user->user_id]->user_name = $user->user_name;
            $all_users_profiles[$user->user_id]->user_email = $user->user_email;

            if (USE_GRAVATAR) {
                $all_users_profiles[$user->user_id]->user_avatar_link =
                    $this->getGravatarLinkFromEmail($user->user_email);
            } else {
                $all_users_profiles[$user->user_id]->user_avatar_link =
                    $this->getUserAvatarFilePath($user->user_has_avatar, $user->user_id);
            }

            $all_users_profiles[$user->user_id]->user_active = $user->user_active;
        }
        return $all_users_profiles;
    }
    public function getUserProfile($user_id)
    {
        $sql = "SELECT user_id, user_name, user_email, user_active, user_has_avatar
                FROM users WHERE user_id = :user_id";
        $sth = $this->db->prepare($sql);
        $sth->execute(array(':user_id' => $user_id));

        $user = $sth->fetch();
        $count =  $sth->rowCount();

        if ($count == 1) {
            if (USE_GRAVATAR) {
                $user->user_avatar_link = $this->getGravatarLinkFromEmail($user->user_email);
            } else {
                $user->user_avatar_link = $this->getUserAvatarFilePath($user->user_has_avatar, $user->user_id);
            }
        } else {
            $_SESSION["feedback_negative"][] = FEEDBACK_USER_DOES_NOT_EXIST;
        }

        return $user;
    }
    public function getGravatarLinkFromEmail($email, $s = AVATAR_SIZE, $d = 'mm', $r = 'pg', $options = array())
    {
        $gravatar_image_link = 'http://www.gravatar.com/avatar/';
        $gravatar_image_link .= md5( strtolower( trim( $email ) ) );
        $gravatar_image_link .= "?s=$s&d=$d&r=$r";

        return $gravatar_image_link;
    }
    public function getUserAvatarFilePath($user_has_avatar, $user_id)
    {
        if ($user_has_avatar) {
            return URL . AVATAR_PATH . $user_id . '.jpg';
        } else {
            return URL . AVATAR_PATH . AVATAR_DEFAULT_IMAGE;
        }
        return null;
    }
}
