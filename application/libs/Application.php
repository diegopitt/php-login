<?php
class Application
{
    private $url_controller;
    private $url_action;
    private $url_parameter_1;
    private $url_parameter_2;
    private $url_parameter_3;
    public function __construct()
    {
        $this->splitUrl();
        if ($this->url_controller) {
            if (file_exists(CONTROLLER_PATH . $this->url_controller . '.php')) {
                require CONTROLLER_PATH . $this->url_controller . '.php';
                $this->url_controller = new $this->url_controller();
                if ($this->url_action) {
                    if (method_exists($this->url_controller, $this->url_action)) {
                        if (isset($this->url_parameter_3)) {
                            $this->url_controller->{$this->url_action}($this->url_parameter_1, $this->url_parameter_2, $this->url_parameter_3);
                        } elseif (isset($this->url_parameter_2)) {
                            $this->url_controller->{$this->url_action}($this->url_parameter_1, $this->url_parameter_2);
                        } elseif (isset($this->url_parameter_1)) {
                            $this->url_controller->{$this->url_action}($this->url_parameter_1);
                        } else {
                            $this->url_controller->{$this->url_action}();
                        }
                    } else {
                        header('location: ' . URL . 'error/index');
                    }
                } else {
                    $this->url_controller->index();
                }
            } else {
                header('location: ' . URL . 'error/index');
            }
        } else {
            require CONTROLLER_PATH . 'index.php';
            $controller = new Index();
            $controller->index();
        }
    }
    private function splitUrl()
    {
        if (isset($_GET['url'])) {
            $url = rtrim($_GET['url'], '/');
            $url = filter_var($url, FILTER_SANITIZE_URL);
            $url = explode('/', $url);
            $this->url_controller = (isset($url[0]) ? $url[0] : null);
            $this->url_action = (isset($url[1]) ? $url[1] : null);
            $this->url_parameter_1 = (isset($url[2]) ? $url[2] : null);
            $this->url_parameter_2 = (isset($url[3]) ? $url[3] : null);
            $this->url_parameter_3 = (isset($url[4]) ? $url[4] : null);
        }
    }
}
