<?php
function autoload($class) {
    if (file_exists(LIBS_PATH . $class . ".php")) {
        require LIBS_PATH . $class . ".php";
    } else {
        exit ('The file ' . $class . '.php is missing in the libs folder.');
    }
}

spl_autoload_register("autoload");
