<?php

    use Core\DB;

    // Connect to SQL Server
    $sqlsrv = sqlsrv_connect("your_sql_server_host", [
        "Database" => "YourSQLServerDatabase",
        "UID" => "your_sqlsrv_user",
        "PWD" => "your_sqlsrv_password"
    ]);

    if (!$sqlsrv) {
        die("SQL Server connection failed: " . print_r(sqlsrv_errors(), true));
    }

    // Step 1: Get latest `created_at` from MySQL cron_jobs table
    $cronJob = DB::raw("SELECT created_at FROM cron_jobs WHERE `type` = 'stock' ORDER BY created_at DESC LIMIT 1");
    $lastCronTime = $cronJob[0]['created_at'] ?? null;

    if (!$lastCronTime) {
        die("No previous stock cron job found.");
    }

    // Format timestamp for SQL Server
    $lastCronTimeFormatted = date('Y-m-d H:i:s', strtotime($lastCronTime));

    // Step 2: Fetch data from SQL Server newer than last cron job
    $sql = "SELECT order_id, product_code_id, quantity FROM source_stock_data WHERE created_at > ?";
    $params = [$lastCronTimeFormatted];
    $stmt = sqlsrv_query($sqlsrv, $sql, $params);

    if (!$stmt) {
        die("SQL Server query error: " . print_r(sqlsrv_errors(), true));
    }

    // Step 3: Process and sync data to MySQL
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $orderId = $row['order_id'];
        $productCodeId = $row['product_code_id'];
        $quantity = $row['quantity'];

        // Insert into in_stocks table (MySQL)
        DB::raw("INSERT INTO in_stocks (`order_id`, `product_code_id`, `quantity`) VALUES (?, ?, ?)", [
            $orderId, $productCodeId, $quantity
        ]);

        // Update stocks table by ADDING quantity (MySQL)
        DB::raw("UPDATE `stocks` SET quantity = quantity + ? WHERE product_code_id = ?", [
            $quantity, $productCodeId
        ]);
    }

    // Step 4: Insert new cron_jobs record for tracking
    DB::raw("INSERT INTO cron_jobs (`type`, `created_at`) VALUES (?, ?)", [
        'stock', date('Y-m-d H:i:s')
    ]);

    // Close SQL Server connection
    sqlsrv_close($sqlsrv);

    echo "Stock sync completed successfully.";
