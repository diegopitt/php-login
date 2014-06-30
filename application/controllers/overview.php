<?php
class Overview extends Controller
{
    function __construct()
    {
        parent::__construct();
    }
    function index()
    {
        $overview_model = $this->loadModel('Overview');
        $this->view->users = $overview_model->getAllUsersProfiles();
        $this->view->render('overview/index');
    }
    function showUserProfile($user_id)
    {
        if (isset($user_id)) {
            $overview_model = $this->loadModel('Overview');
            $this->view->user = $overview_model->getUserProfile($user_id);
            $this->view->render('overview/showuserprofile');
        } else {
            header('location: ' . URL);
        }
    }
}
