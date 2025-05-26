<?php

    function request()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        // For GET requests, return $_GET
        if ($method === 'GET') {
            return $_GET;
        }

        // For POST, PUT, PATCH, DELETE: check content type and parse accordingly
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            // Get raw input and decode JSON
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            return is_array($data) ? $data : [];
        }

        // Otherwise fallback to $_POST for form-encoded data
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $_POST;
        }

        // Default empty array if nothing matches
        return [];
    }

    function response($data, $status = 200)
    {
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    function debug($data){
        echo '<pre style="background:#111; color:#212529; padding:10px; border:1px solid #ddd; border-radius:5px;">';
        // var_export returns a parsable string representation
        $exported = var_export($data, true);
        // highlight_string adds syntax coloring to PHP code
        echo highlight_string("<?php\n\$data = " . $exported . ";", true);
        echo '</pre>';
        exit;
    }