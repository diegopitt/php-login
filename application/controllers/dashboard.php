<?php

class Dashboard extends Controller
{
    function __construct()
    {
        parent::__construct();
        Auth::handleLogin();
    }
    function index()
    {
        $this->view->render('dashboard/index');
    }
}
