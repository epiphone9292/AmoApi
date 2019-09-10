<?php

spl_autoload_register(function ($className){
    include __DIR__ . '/classes/' . $className . '.php';
});
