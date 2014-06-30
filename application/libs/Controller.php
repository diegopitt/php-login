<?php
class Controller
{
    function __construct()
    {
        Session::init();
        if (!isset($_SESSION['user_logged_in']) && isset($_COOKIE['rememberme'])) {
            header('location: ' . URL . 'login/loginWithCookie');
        }
        try {
            $this->db = new Database();
        } catch (PDOException $e) {
            die('Database connection could not be established.');
        }
        $this->view = new View();
    }
    public function loadModel($name)
    {
        $path = MODELS_PATH . strtolower($name) . '_model.php';

        if (file_exists($path)) {
            require MODELS_PATH . strtolower($name) . '_model.php';
            $modelName = $name . 'Model';
            return new $modelName($this->db);
        }
    }
}
