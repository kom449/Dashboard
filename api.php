<?php
session_start();

if (!isset($_SESSION['user_id']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(["error" => "Access denied. Please log in."]);
    exit();
}

require_once 'db.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

$interval = $_GET['interval'] ?? '';
$storeId = $_GET['store_id'] ?? 'all';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? null;
$excludedShopId = 4582108;

if (isset($_GET['fetch_stores']) && $_GET['fetch_stores'] == 1) {
    $sql = "SELECT shop_id, shop_name FROM shops ORDER BY shop_name ASC";
    $result = $conn->query($sql);

    if (!$result) {
        echo json_encode(["error" => "Database query failed"]);
        exit();
    }

    $stores = [];
    while ($row = $result->fetch_assoc()) {
        $stores[] = $row;
    }

    array_unshift($stores, [
        "shop_id" => "excludeOnline",
        "shop_name" => "All Stores except Online"
    ]);

    echo json_encode($stores);
    exit();
}

try {
    if ($interval === 'monthly_comparison') {
        $selectedYear = (int)$year;
        $previousYear = $selectedYear - 1;
        
        if ($month && is_numeric($month)) {
            $selectedMonth = (int)$month;
            // Build explicit date ranges for current year
            $startDateCurrent = sprintf("%d-%02d-01", $selectedYear, $selectedMonth);
            $daysInMonthCurrent = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
            // If the selected month is the current month in the current year, restrict to the current day
            if ($selectedYear === (int)date('Y') && $selectedMonth === (int)date('n')) {
                $currentDay = (int)date('j');
                $endDateCurrent = sprintf("%d-%02d-%02d", $selectedYear, $selectedMonth, $currentDay);
            } else {
                $endDateCurrent = sprintf("%d-%02d-%02d", $selectedYear, $selectedMonth, $daysInMonthCurrent);
            }
            // Build date ranges for previous year
            $startDatePrevious = sprintf("%d-%02d-01", $previousYear, $selectedMonth);
            $daysInMonthPrevious = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $previousYear);
            $endDatePrevious = sprintf("%d-%02d-%02d", $previousYear, $selectedMonth, $daysInMonthPrevious);
            
            $sql = "SELECT 
                        DAY(sd.date) AS day,
                        MONTH(sd.date) AS month, 
                        YEAR(sd.date) AS year,
                        SUM(sd.total_this_year) AS total_sales,
                        SUM(sd.db_this_year) AS db_this_year
                    FROM sales_data sd
                    WHERE (
                        (sd.date BETWEEN '$startDateCurrent' AND '$endDateCurrent')
                        OR 
                        (sd.date BETWEEN '$startDatePrevious' AND '$endDatePrevious')
                    )";
        } else {
            if ($selectedYear === (int)date('Y')) {
                $currentMonth = (int)date('n');
                $currentDay = (int)date('j');
                $sql = "SELECT 
                            MONTH(sd.date) AS month, 
                            YEAR(sd.date) AS year,
                            SUM(sd.total_this_year) AS total_sales,
                            SUM(sd.db_this_year) AS db_this_year
                        FROM sales_data sd
                        WHERE YEAR(sd.date) IN ($selectedYear, $previousYear)
                          AND MONTH(sd.date) <= $currentMonth
                          AND (MONTH(sd.date) <> $currentMonth OR DAY(sd.date) <= $currentDay)";
            } else {
                $sql = "SELECT 
                            MONTH(sd.date) AS month, 
                            YEAR(sd.date) AS year,
                            SUM(sd.total_this_year) AS total_sales,
                            SUM(sd.db_this_year) AS db_this_year
                        FROM sales_data sd
                        WHERE YEAR(sd.date) IN ($selectedYear, $previousYear)";
            }
        }
        
        if ($storeId === 'excludeOnline') {
            $sql .= " AND sd.shop_id != '$excludedShopId'";
        } elseif ($storeId !== 'all') {
            $sql .= " AND sd.shop_id = '$storeId'";
        }
        
        if ($month && is_numeric($month)) {
            $sql .= " GROUP BY YEAR(sd.date), MONTH(sd.date), DAY(sd.date)
                      ORDER BY YEAR(sd.date) ASC, DAY(sd.date) ASC";
        } else {
            $sql .= " GROUP BY YEAR(sd.date), MONTH(sd.date)
                      ORDER BY YEAR(sd.date) ASC, MONTH(sd.date) ASC";
        }
        
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Database query failed: " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    } elseif ($interval === 'yearly_summary') {
        $sql = "SELECT 
                    YEAR(sd.date) AS year,
                    SUM(sd.total_this_year) AS total_sales,
                    SUM(sd.db_this_year) AS db_this_year
                FROM sales_data sd";
        if ($storeId === 'excludeOnline') {
            $sql .= " WHERE sd.shop_id != '$excludedShopId'";
        } elseif ($storeId !== 'all') {
            $sql .= " WHERE sd.shop_id = '$storeId'";
        }
        $sql .= " GROUP BY YEAR(sd.date)
                  ORDER BY YEAR(sd.date) ASC";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Database query failed: " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    } elseif ($interval === 'custom_range') {
        if (!isset($_GET['start']) || !isset($_GET['end'])) {
            throw new Exception("Custom range requires start and end dates.");
        }
        $start = $_GET['start'];
        $end = $_GET['end'];
        
        $sql = "SELECT 
                    DATE(sd.date) AS date,
                    SUM(sd.total_this_year) AS total_sales,
                    SUM(sd.db_this_year) AS db_this_year
                FROM sales_data sd
                WHERE ((sd.date BETWEEN '$start' AND '$end')
                   OR (sd.date BETWEEN DATE_SUB('$start', INTERVAL 1 YEAR) AND DATE_SUB('$end', INTERVAL 1 YEAR)))";
        if ($storeId === 'excludeOnline') {
            $sql .= " AND sd.shop_id != '$excludedShopId'";
        } elseif ($storeId !== 'all') {
            $sql .= " AND sd.shop_id = '$storeId'";
        }
        $sql .= " GROUP BY DATE(sd.date)
                  ORDER BY DATE(sd.date) ASC";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Database query failed: " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    } elseif ($interval === 'store_performance') {
        $sql = "SELECT s.shop_id COLLATE utf8mb4_general_ci AS shop_id, 
                       COALESCE(
                         (SELECT shop_name FROM shops 
                          WHERE shop_id COLLATE utf8mb4_general_ci = s.shop_id COLLATE utf8mb4_general_ci LIMIT 1),
                         s.shop_id
                       ) AS shop_name,
                       SUM(s.quantity) AS total_quantity
                FROM item_sales s
                JOIN items i ON s.product_id COLLATE utf8mb4_general_ci = i.id COLLATE utf8mb4_general_ci
                GROUP BY s.shop_id COLLATE utf8mb4_general_ci
                ORDER BY total_quantity DESC";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Database query failed: " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
             $data[] = $row;
        }
        echo json_encode($data);
        exit();
    } elseif ($interval === 'top_items') {
        $storeCondition = "";
        if (isset($_GET['store_id']) && $_GET['store_id'] !== 'all') {
             $store_id = $_GET['store_id'];
             $storeCondition = "WHERE s.shop_id = '$store_id' ";
        }
        $sql = "SELECT 
                  COALESCE(i.group_id COLLATE utf8mb4_general_ci, i.id COLLATE utf8mb4_general_ci) AS product_group,
                  i.title,
                  SUM(s.quantity) AS total_quantity
                FROM item_sales s
                JOIN items i ON s.product_id COLLATE utf8mb4_general_ci = i.id COLLATE utf8mb4_general_ci
                $storeCondition
                GROUP BY product_group, i.title
                ORDER BY total_quantity DESC
                LIMIT 10";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Database query failed: " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
             $data[] = $row;
        }
        echo json_encode($data);
        exit();
    } elseif ($interval === 'product_performance') {
        $sql = "SELECT 
                    COALESCE(i.group_id COLLATE utf8mb4_general_ci, i.id COLLATE utf8mb4_general_ci) AS product_group,
                    i.title,
                    SUM(s.quantity) AS total_quantity,
                    AVG(i.sale_price) AS average_price
                FROM item_sales s
                JOIN items i ON s.product_id COLLATE utf8mb4_general_ci = i.id COLLATE utf8mb4_general_ci ";
        
        if ($storeId === 'excludeOnline') {
            $sql .= "WHERE s.shop_id != $excludedShopId ";
        } elseif ($storeId !== 'all') {
            $storeId = intval($storeId);
            $sql .= "WHERE s.shop_id = $storeId ";
        }
        
        if (isset($_GET['product_id']) && !empty($_GET['product_id'])) {
            $product_id = $_GET['product_id'];
            if (strpos($sql, 'WHERE') !== false) {
                $sql .= " AND (i.id COLLATE utf8mb4_general_ci = '$product_id' OR i.group_id COLLATE utf8mb4_general_ci = '$product_id') ";
            } else {
                $sql .= " WHERE (i.id COLLATE utf8mb4_general_ci = '$product_id' OR i.group_id COLLATE utf8mb4_general_ci = '$product_id') ";
            }
        }
        
        $sql .= "GROUP BY product_group, i.title
                  ORDER BY total_quantity DESC";
        
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Database query failed: " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    } else {
        throw new Exception("Invalid interval specified.");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
    error_log("API Error: " . $e->getMessage(), 0);
    exit();
}
?>
