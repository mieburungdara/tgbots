<?php

namespace TGBot\Controllers\Admin;

use TGBot\Controllers\BaseController;

abstract class BaseApiController extends BaseController
{
    /**
     * Sends a JSON response.
     *
     * @param mixed $data The data to encode as JSON.
     * @param int $status_code The HTTP status code.
     */
    protected function jsonResponse($data, $status_code = 200)
    {
        http_response_code($status_code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}
