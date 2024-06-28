<?php
class Controller
{
    protected $views, $model;
    public function __construct()
    {
        $this->views = new Views();
        $this->loadModel();
    }

    public function loadModel()
    {
        $model = get_class($this) . "Model";
        $rute = "Models/" . $model . ".php";
        if (file_exists($rute)) {
            require_once $rute;
            $this->model = new $model(); // Assign the new instance to the $model property
        }
    }
}
