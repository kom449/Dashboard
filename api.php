<?php
session_start();

if (!isset($_SESSION['user_id']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(["error" => "Access denied. Please log in."]);
    exit();
}

require_once 'db.php';
$conn->set_charset("utf8mb4");
$conn->query("SET collation_connection = 'utf8mb4_0900_ai_ci'");

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

$interval = $_GET['interval'] ?? '';
$storeId = $_GET['store_id'] ?? 'all';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? 'all';
$excludedShopId = 4582108;

// FETCH STORES
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
    $filteredStores = array_filter($stores, function ($store) {
        return $store['shop_id'] !== 'all';
    });
    $defaultStores = [
        ["shop_id" => "all", "shop_name" => "All Stores"],
        ["shop_id" => "excludeOnline", "shop_name" => "All Stores except Online"]
    ];
    $stores = array_merge($defaultStores, array_values($filteredStores));
    echo json_encode($stores);
    exit();
}

try {
    // ----- Detailed View Branch: item_detail -----
    if ($interval === 'item_detail') {
        $productId = $_GET['product_id'] ?? '';
        if (!$productId) {
            throw new Exception("Product ID not provided.");
        }
        $productIdEscaped = $conn->real_escape_string($productId);
        $selectedYear = $conn->real_escape_string((string)$year);
        $selectedMonth = ($month !== 'all') ? $conn->real_escape_string((string)$month) : null;
        
        // 1. Retrieve basic item info
        $sqlItem = "SELECT id, title, image_link, brand, group_id 
                    FROM items 
                    WHERE (id COLLATE utf8mb4_0900_ai_ci = CONVERT('$productIdEscaped' USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
                           OR group_id COLLATE utf8mb4_0900_ai_ci = CONVERT('$productIdEscaped' USING utf8mb4) COLLATE utf8mb4_0900_ai_ci)
                    LIMIT 1";
        $resultItem = $conn->query($sqlItem);
        if (!$resultItem || $resultItem->num_rows === 0) {
            throw new Exception("Item not found.");
        }
        $item = $resultItem->fetch_assoc();
        
        // 2. Sales summary: count, total sales, and total cost. Also get unit price.
        $identifier = !empty($item['group_id']) ? $conn->real_escape_string($item['group_id']) : $productIdEscaped;
        $sqlSales = "SELECT 
                        COUNT(psi.id) AS count_sales,
                        SUM(psi.amount) AS total_sales,
                        MAX(psi.amount) AS unit_price,
                        SUM(psi.cost_price) AS total_cost
                     FROM product_sales_items psi
                     LEFT JOIN product_sales ps ON psi.sale_id = ps.sale_id
                     WHERE (
                        psi.product_identifier COLLATE utf8mb4_0900_ai_ci = CONVERT('$identifier' USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
                        OR psi.product_identifier COLLATE utf8mb4_0900_ai_ci = CONVERT('$productIdEscaped' USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
                     )
                     AND YEAR(ps.completed_at) = '$selectedYear'";
        if ($selectedMonth) {
            $sqlSales .= " AND MONTH(ps.completed_at) = '$selectedMonth'";
        }
        $resultSales = $conn->query($sqlSales);
        if (!$resultSales) {
            throw new Exception("Sales query failed: " . $conn->error);
        }
        $sales = $resultSales->fetch_assoc();
        $unitPrice = isset($sales['unit_price']) ? floatval($sales['unit_price']) : 0;
        $countSales = isset($sales['count_sales']) ? intval($sales['count_sales']) : 0;
        $sales['expected_total'] = $countSales * $unitPrice;
        
        // 3. Group Variations: count per variation as group_count
        $groupItems = [];
        if (!empty($item['group_id'])) {
            $groupId = $conn->real_escape_string($item['group_id']);
            $sqlGroup = "SELECT 
                            i.id,
                            i.title,
                            i.image_link,
                            COUNT(DISTINCT psi.id) AS group_count
                         FROM items i
                         LEFT JOIN product_sales_items psi 
                           ON psi.product_identifier COLLATE utf8mb4_0900_ai_ci IN (
                                CONVERT(i.id USING utf8mb4) COLLATE utf8mb4_0900_ai_ci,
                                CONVERT(i.group_id USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
                           )
                         LEFT JOIN product_sales ps
                           ON psi.sale_id = ps.sale_id
                         WHERE i.group_id COLLATE utf8mb4_0900_ai_ci = CONVERT('$groupId' USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
                           AND YEAR(ps.completed_at) = '$selectedYear'";
            if ($selectedMonth) {
                $sqlGroup .= " AND MONTH(ps.completed_at) = '$selectedMonth'";
            }
            $sqlGroup .= " GROUP BY i.id, i.title, i.image_link";
            $resultGroup = $conn->query($sqlGroup);
            if ($resultGroup) {
                while ($row = $resultGroup->fetch_assoc()) {
                    $groupItems[] = $row;
                }
            }
        }
        
        // 4. Shop Performance: aggregate counts per store.
        $sqlShops = "SELECT 
                        ps.shop_id,
                        (SELECT shop_name FROM shops 
                         WHERE shop_id COLLATE utf8mb4_0900_ai_ci = CONVERT(ps.shop_id USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
                         LIMIT 1) AS shop_name,
                        COUNT(DISTINCT psi.id) AS shop_sales
                     FROM product_sales_items psi
                     LEFT JOIN product_sales ps ON psi.sale_id = ps.sale_id
                     WHERE psi.product_identifier COLLATE utf8mb4_0900_ai_ci IN (
                           CONVERT('$identifier' USING utf8mb4) COLLATE utf8mb4_0900_ai_ci,
                           CONVERT('$productIdEscaped' USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
                     )
                     AND YEAR(ps.completed_at) = '$selectedYear'";
        if ($selectedMonth) {
            $sqlShops .= " AND MONTH(ps.completed_at) = '$selectedMonth'";
        }
        $sqlShops .= " GROUP BY ps.shop_id ORDER BY shop_sales DESC";
        $resultShops = $conn->query($sqlShops);
        $shopPerformance = [];
        if ($resultShops) {
            while ($row = $resultShops->fetch_assoc()) {
                $shopId = $row['shop_id'];
                if (isset($shopPerformance[$shopId])) {
                    $shopPerformance[$shopId]['shop_sales'] += $row['shop_sales'];
                } else {
                    $shopPerformance[$shopId] = $row;
                }
            }
            $shopPerformance = array_values($shopPerformance);
        }
        
        // 5. Sales Trend data
        if ($selectedMonth) {
            $sqlTrend = "SELECT DAY(ps.completed_at) AS period, COUNT(*) AS count
                         FROM product_sales ps
                         LEFT JOIN product_sales_items psi ON psi.sale_id = ps.sale_id
                         WHERE psi.product_identifier COLLATE utf8mb4_0900_ai_ci IN (
                                 CONVERT('$identifier' USING utf8mb4) COLLATE utf8mb4_0900_ai_ci,
                                 CONVERT('$productIdEscaped' USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
                         )
                         AND YEAR(ps.completed_at) = '$selectedYear'
                         AND MONTH(ps.completed_at) = '$selectedMonth'
                         GROUP BY DAY(ps.completed_at)
                         ORDER BY period ASC";
        } else {
            $sqlTrend = "SELECT MONTH(ps.completed_at) AS period, COUNT(*) AS count
                         FROM product_sales ps
                         LEFT JOIN product_sales_items psi ON psi.sale_id = ps.sale_id
                         WHERE psi.product_identifier COLLATE utf8mb4_0900_ai_ci IN (
                                 CONVERT('$identifier' USING utf8mb4) COLLATE utf8mb4_0900_ai_ci,
                                 CONVERT('$productIdEscaped' USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
                         )
                         AND YEAR(ps.completed_at) = '$selectedYear'
                         GROUP BY MONTH(ps.completed_at)
                         ORDER BY period ASC";
        }
        $resultTrend = $conn->query($sqlTrend);
        $salesTrend = [];
        if ($resultTrend) {
            while ($row = $resultTrend->fetch_assoc()) {
                $salesTrend[] = $row;
            }
        }
        
        $response = [
            "item" => $item,
            "sales" => $sales,
            "group" => $groupItems,
            "shopPerformance" => $shopPerformance,
            "salesTrend" => $salesTrend
        ];
        echo json_encode($response);
        exit();
    }
    // ==================== FETCH YEARS ====================
    elseif ($interval === 'fetch_years') {
        $sql = "SELECT DISTINCT YEAR(completed_at) AS year FROM product_sales ORDER BY year DESC";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Database query failed (fetch_years): " . $conn->error);
        }
        $years = [];
        while ($row = $result->fetch_assoc()) {
            $years[] = $row['year'];
        }
        echo json_encode($years);
        exit();
    }
    // ==================== FETCH CATEGORIES ====================
    elseif ($interval === 'fetch_categories') {
        $sql = "SELECT id, identifier, display_name FROM product_categories ORDER BY display_name ASC";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Database query failed (fetch_categories): " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    }
    // ==================== FETCH BRANDS ====================
    elseif ($interval === 'fetch_brands') {
        $sql = "SELECT DISTINCT brand FROM items WHERE brand IS NOT NULL ORDER BY brand ASC";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Database query failed (fetch_brands): " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    }
    // ==================== MONTHLY COMPARISON ====================
    elseif ($interval === 'monthly_comparison') {
        $selectedYear = (int)$year;
        $previousYear = $selectedYear - 1;
        if ($month && is_numeric($month)) {
            $selectedMonth = (int)$month;
            $startDateCurrent = sprintf("%d-%02d-01", $selectedYear, $selectedMonth);
            $daysInMonthCurrent = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
            if ($selectedYear === (int)date('Y') && $selectedMonth === (int)date('n')) {
                $currentDay = (int)date('j');
                $endDateCurrent = sprintf("%d-%02d-%02d", $selectedYear, $selectedMonth, $currentDay);
            } else {
                $endDateCurrent = sprintf("%d-%02d-%02d", $selectedYear, $selectedMonth, $daysInMonthCurrent);
            }
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
            $sql .= " AND sd.shop_id != '" . $conn->real_escape_string((string)$excludedShopId) . "'";
        } elseif ($storeId !== 'all') {
            $sql .= " AND sd.shop_id = '" . $conn->real_escape_string((string)$storeId) . "'";
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
            throw new Exception("Database query failed (monthly_comparison): " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    }
    // ==================== YEARLY SUMMARY ====================
    elseif ($interval === 'yearly_summary') {
        $sql = "SELECT 
                    YEAR(sd.date) AS year,
                    SUM(sd.total_this_year) AS total_sales,
                    SUM(sd.db_this_year) AS db_this_year
                FROM sales_data sd";
        if ($storeId === 'excludeOnline') {
            $sql .= " WHERE sd.shop_id != '" . $conn->real_escape_string((string)$excludedShopId) . "'";
        } elseif ($storeId !== 'all') {
            $sql .= " WHERE sd.shop_id = '" . $conn->real_escape_string((string)$storeId) . "'";
        }
        $sql .= " GROUP BY YEAR(sd.date)
                  ORDER BY YEAR(sd.date) ASC";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Database query failed (yearly_summary): " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    }
    // ==================== CUSTOM RANGE ====================
    elseif ($interval === 'custom_range') {
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
            $sql .= " AND sd.shop_id != '" . $conn->real_escape_string((string)$excludedShopId) . "'";
        } elseif ($storeId !== 'all') {
            $sql .= " AND sd.shop_id = '" . $conn->real_escape_string((string)$storeId) . "'";
        }
        $sql .= " GROUP BY DATE(sd.date)
                  ORDER BY DATE(sd.date) ASC";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Database query failed (custom_range): " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    }
    // ==================== STORE PERFORMANCE ====================
    elseif ($interval === 'store_performance') {
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
            throw new Exception("Database query failed (store_performance): " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    }
    // ==================== TOP ITEMS ====================
    elseif ($interval === 'top_items') {
        $storeCondition = "";
        if (isset($_GET['store_id']) && $_GET['store_id'] !== 'all') {
            $store_id = $_GET['store_id'];
            $storeCondition = "WHERE s.shop_id = '" . $conn->real_escape_string((string)$store_id) . "' ";
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
            throw new Exception("Database query failed (top_items): " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    }
    // ==================== PRODUCT PERFORMANCE ====================
    elseif ($interval === 'product_performance') {
        $sql = "SELECT 
                    COALESCE(i.group_id COLLATE utf8mb4_general_ci, i.id COLLATE utf8mb4_general_ci) AS product_group,
                    i.title,
                    SUM(s.quantity) AS total_quantity,
                    AVG(i.sale_price) AS average_price
                FROM item_sales s
                JOIN items i ON s.product_id COLLATE utf8mb4_general_ci = i.id COLLATE utf8mb4_general_ci ";
        if ($storeId === 'excludeOnline') {
            $sql .= "WHERE s.shop_id != " . $conn->real_escape_string((string)$excludedShopId) . " ";
        } elseif ($storeId !== 'all') {
            $storeId = intval($storeId);
            $sql .= "WHERE s.shop_id = $storeId ";
        }
        if (isset($_GET['product_id']) && !empty($_GET['product_id'])) {
            $product_id = $conn->real_escape_string((string)$_GET['product_id']);
            if (strpos($sql, 'WHERE') !== false) {
                $sql .= " AND (i.id COLLATE utf8mb4_general_ci = CONVERT('$product_id' USING utf8mb4) COLLATE utf8mb4_general_ci 
                          OR i.group_id COLLATE utf8mb4_general_ci = CONVERT('$product_id' USING utf8mb4) COLLATE utf8mb4_general_ci) ";
            } else {
                $sql .= " WHERE (i.id COLLATE utf8mb4_general_ci = CONVERT('$product_id' USING utf8mb4) COLLATE utf8mb4_general_ci 
                          OR i.group_id COLLATE utf8mb4_general_ci = CONVERT('$product_id' USING utf8mb4) COLLATE utf8mb4_general_ci) ";
            }
        }
        $sql .= "GROUP BY product_group, i.title
                  ORDER BY total_quantity DESC";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Database query failed (product_performance): " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    }
    // ==================== PRODUCT CATALOG (with pagination) ====================
    elseif ($interval === 'product_catalog') {
        $search   = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? 'all';
        $brand    = $_GET['brand'] ?? 'all';

        // Pagination parameters: default to page 1 and pageSize of 20 if not provided
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $pageSize = isset($_GET['page_size']) ? (int)$_GET['page_size'] : 20;
        if ($page < 1) { $page = 1; }
        if ($pageSize < 1) { $pageSize = 20; }
        $offset = ($page - 1) * $pageSize;

        $sql = "SELECT 
                    i.id,
                    i.title,
                    i.image_link,
                    i.brand,
                    SUM(psi.amount) AS total_sales,
                    COUNT(psi.id) AS count_sales,
                    SUM(psi.cost_price) AS total_cost
                FROM items i
                LEFT JOIN product_sales_items psi 
                    ON (psi.product_identifier = i.id OR psi.product_identifier = i.group_id)
                LEFT JOIN product_sales ps 
                    ON psi.sale_id = ps.sale_id
                WHERE 1=1";
        if ($storeId === 'excludeOnline') {
            $sql .= " AND ps.shop_id != '" . $conn->real_escape_string((string)$excludedShopId) . "'";
        } elseif ($storeId !== 'all') {
            $sql .= " AND ps.shop_id = '" . $conn->real_escape_string((string)$storeId) . "'";
        }
        if ($year) {
            $sql .= " AND YEAR(ps.completed_at) = '" . $conn->real_escape_string((string)$year) . "'";
        }
        if ($month !== 'all') {
            $sql .= " AND MONTH(ps.completed_at) = '" . $conn->real_escape_string((string)$month) . "'";
        }
        if (!empty($search)) {
            $searchEscaped = $conn->real_escape_string((string)$search);
            $sql .= " AND (i.id LIKE '%$searchEscaped%' OR i.title LIKE '%$searchEscaped%')";
        }
        if ($category !== 'all') {
            $categoryEscaped = $conn->real_escape_string((string)$category);
            $sql .= " AND psi.product_category_identifier = '$categoryEscaped'";
        }
        if ($brand !== 'all') {
            $brandEscaped = $conn->real_escape_string((string)$brand);
            $sql .= " AND i.brand = '$brandEscaped'";
        }
        $sql .= " GROUP BY i.id, i.title, i.image_link, i.brand";
        // UPDATED: Order by count_sales (i.e. product count) descending.
        $sql .= " ORDER BY count_sales DESC";
        // Apply pagination with LIMIT and OFFSET
        $sql .= " LIMIT " . $pageSize . " OFFSET " . $offset;

        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Database query failed (product_catalog): " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    }
    // ==================== PRODUCT PERFORMANCE ====================
    elseif ($interval === 'product_performance') {
        $sql = "SELECT 
                    COALESCE(i.group_id COLLATE utf8mb4_general_ci, i.id COLLATE utf8mb4_general_ci) AS product_group,
                    i.title,
                    SUM(s.quantity) AS total_quantity,
                    AVG(i.sale_price) AS average_price
                FROM item_sales s
                JOIN items i ON s.product_id COLLATE utf8mb4_general_ci = i.id COLLATE utf8mb4_general_ci ";
        if ($storeId === 'excludeOnline') {
            $sql .= "WHERE s.shop_id != " . $conn->real_escape_string((string)$excludedShopId) . " ";
        } elseif ($storeId !== 'all') {
            $storeId = intval($storeId);
            $sql .= "WHERE s.shop_id = $storeId ";
        }
        if (isset($_GET['product_id']) && !empty($_GET['product_id'])) {
            $product_id = $conn->real_escape_string((string)$_GET['product_id']);
            if (strpos($sql, 'WHERE') !== false) {
                $sql .= " AND (i.id COLLATE utf8mb4_general_ci = CONVERT('$product_id' USING utf8mb4) COLLATE utf8mb4_general_ci 
                          OR i.group_id COLLATE utf8mb4_general_ci = CONVERT('$product_id' USING utf8mb4) COLLATE utf8mb4_general_ci) ";
            } else {
                $sql .= " WHERE (i.id COLLATE utf8mb4_general_ci = CONVERT('$product_id' USING utf8mb4) COLLATE utf8mb4_general_ci 
                          OR i.group_id COLLATE utf8mb4_general_ci = CONVERT('$product_id' USING utf8mb4) COLLATE utf8mb4_general_ci) ";
            }
        }
        $sql .= "GROUP BY product_group, i.title
                  ORDER BY total_quantity DESC";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Database query failed (product_performance): " . $conn->error);
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    }
    else {
        throw new Exception("Invalid interval specified.");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
    error_log("API Error: " . $e->getMessage(), 0);
    exit();
}
?>
