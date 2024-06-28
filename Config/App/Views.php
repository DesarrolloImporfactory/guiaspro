<?php
class Views
{

    public function render($controller, $view, $data = [])
    {
        $controller = get_class($controller);
        if ($controller == "Home") {
            $view = "Views/" . $view . ".php";
        } else {
            $view = "Views/" . $controller . "/" . $view . ".php";
        }
        require $view;
    }
}
