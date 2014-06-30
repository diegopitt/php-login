<!DOCTYPE html>
<html ng-app lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Version 2</title>
    <meta name="description" content="">
    <link rel="stylesheet" href="<?php echo URL; ?>public/css/reset.css" />
    <link rel="stylesheet" href="<?php echo URL; ?>public/css/style.css" />
    <link rel="stylesheet" href="<?php echo URL; ?>public/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo URL; ?>public/css/bootstrap-theme.css" />
    <script type="text/javascript" src="<?php echo URL; ?>public/js/jquery-2.1.1.min.js"></script>
    <script type="text/javascript" src="<?php echo URL; ?>public/js/application.js"></script>
    <script type="text/javascript" src='<?php echo URL; ?>public/js/angular.min.js'></script>
</head>
<body>
    <div id="wrap">
		<div class="navbar navbar-inverse navbar-fixed-top" role="navigation" style='padding:8px'>
            <div class="container">
                <div class="navbar-header">
                  <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                  </button>
                  <a class="navbar-brand" href="#">Version 2</a>
                </div>
                <div class="navbar-collapse collapse">
                    <ul class="nav navbar-nav">
                        <li <?php if ($this->checkForActiveController($filename, "index")) { echo ' class="active" '; } ?> >
                            <a href="<?php echo URL; ?>index/index">Index</a>
                        </li>
                        <li <?php if ($this->checkForActiveController($filename, "help")) { echo ' class="active" '; } ?> >
                            <a href="<?php echo URL; ?>help/index">Help</a>
                        </li>
                        <li <?php if ($this->checkForActiveController($filename, "overview")) { echo ' class="active" '; } ?> >
                            <a href="<?php echo URL; ?>overview/index">Overview</a>
                        </li>
                        <?php if (Session::get('user_logged_in') == true):?>
                            <li <?php if ($this->checkForActiveController($filename, "dashboard")) { echo ' class="active" '; } ?> >
                                <a href="<?php echo URL; ?>dashboard/index">Dashboard</a>
                            </li>
                        <?php endif; ?>
                        <?php if (Session::get('user_logged_in') == true):?>
                            <li <?php if ($this->checkForActiveController($filename, "note")) { echo ' class="active" '; } ?> >
                                <a href="<?php echo URL; ?>note/index">My Notes</a>
                            </li>
                        <?php endif; ?>
                        <?php if (Session::get('user_logged_in') == false):?>
                            <li <?php if ($this->checkForActiveControllerAndAction($filename, "login/index")) { echo ' class="active" '; } ?> >
                                <a href="<?php echo URL; ?>login/index">Login</a>
                            </li>
                            <li <?php if ($this->checkForActiveControllerAndAction($filename, "login/register")) { echo ' class="active" '; } ?> >
                                <a href="<?php echo URL; ?>login/register">Register</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <ul class="nav navbar-nav navbar-right">
                        <li class="dropdown">
                            <?php if (Session::get('user_logged_in') == true): ?>
                                <div class="header_right_box">
                                     <div class="avatar">
                                        <?php if (USE_GRAVATAR) { ?>
                                            <span class="badge badge-warning">4</span><img src='<?php echo Session::get('user_gravatar_image_url'); ?>' style='display:block;position:absolute;top:0;left:-28px;-webkit-border-radius:200px;-moz-border-radius:200px;border-radius:200px;width:<?php echo AVATAR_SIZE; ?>px; height:<?php echo AVATAR_SIZE; ?>px;' />
                                        <?php } else { ?>
                                            <span class="badge badge-warning">8</span><img src='<?php echo Session::get('user_avatar_file'); ?>' style='display:block;position:absolute;top:0;left:-28px;-webkit-border-radius:200px;-moz-border-radius:200px;border-radius:200px;width:<?php echo AVATAR_SIZE; ?>px; height:<?php echo AVATAR_SIZE; ?>px;' />
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </li>
                        <?php if (Session::get('user_logged_in') == true):?>
                            <li class="dropdown" <?php if ($this->checkForActiveController($filename, "login")) { echo ' class="active" '; } ?> >
                                <a href="#" class="dropdown-toggle" data-toggle="dropdown">DiegoP<b class="caret"></b></a>
                                <ul class="dropdown-menu">
                                    <li <?php if ($this->checkForActiveController($filename, "login")) { echo ' class="active" '; } ?> >
                                        <a href="<?php echo URL; ?>login/changeaccounttype">Change account type</a>
                                    </li>
                                    <li <?php if ($this->checkForActiveController($filename, "login")) { echo ' class="active" '; } ?> >
                                        <a href="<?php echo URL; ?>login/uploadavatar">Upload an avatar</a>
                                    </li>
                                    <li <?php if ($this->checkForActiveController($filename, "login")) { echo ' class="active" '; } ?> >
                                        <a href="<?php echo URL; ?>login/editusername">Edit my username</a>
                                    </li>
                                    <li <?php if ($this->checkForActiveController($filename, "login")) { echo ' class="active" '; } ?> >
                                        <a href="<?php echo URL; ?>login/edituseremail">Edit my email</a>
                                    </li>
                                    <li <?php if ($this->checkForActiveController($filename, "login")) { echo ' class="active" '; } ?> >
                                        <a href="<?php echo URL; ?>login/logout">Logout</a>
                                    </li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div style="height:32px;"></div>