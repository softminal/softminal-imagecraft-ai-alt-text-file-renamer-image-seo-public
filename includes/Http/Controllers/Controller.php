<?php

namespace Softminal\ImageCraft\Http\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

abstract class Controller
{
    protected function response($data, $status = 200)
    {
        return new \WP_REST_Response($data, $status);
    }
}