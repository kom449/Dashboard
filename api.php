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

    // If the product is grouped, override details with the master record (where id = group_id)
    $isGrouped = !empty($item['group_id']);
    if ($isGrouped) {
        $groupId = $conn->real_escape_string($item['group_id']);
        $masterSql = "SELECT id, title, image_link, brand 
                      FROM items 
                      WHERE id = '$groupId'
                      LIMIT 1";
        $masterResult = $conn->query($masterSql);
        if ($masterResult && $masterResult->num_rows > 0) {
            $master = $masterResult->fetch_assoc();
            if (!empty($master['title'])) {
                $item['title'] = $master['title'];
            }
            if (!empty($master['image_link'])) {
                $item['image_link'] = $master['image_link'];
            }
            if (!empty($master['brand'])) {
                $item['brand'] = $master['brand'];
            }
        }
    }
    
    // Build an identifier subquery for grouped products
    $identifierSubquery = "(
        SELECT id FROM items WHERE group_id = '" . ($isGrouped ? $groupId : '') . "'
        UNION
        SELECT '" . ($isGrouped ? $groupId : $productIdEscaped) . "'
    )";
    
    // 2. Sales summary: count, total sales, unit price, and total cost.
    if ($isGrouped) {
        $sqlSales = "SELECT 
                        COUNT(psi.id) AS count_sales,
                        SUM(psi.amount) AS total_sales,
                        MAX(psi.amount) AS unit_price,
                        SUM(psi.cost_price) AS total_cost
                     FROM product_sales_items psi
                     LEFT JOIN product_sales ps ON psi.sale_id = ps.sale_id
                     WHERE psi.product_identifier COLLATE utf8mb4_0900_ai_ci IN $identifierSubquery
                     AND YEAR(ps.completed_at) = '$selectedYear'";
    } else {
        $sqlSales = "SELECT 
                        COUNT(psi.id) AS count_sales,
                        SUM(psi.amount) AS total_sales,
                        MAX(psi.amount) AS unit_price,
                        SUM(psi.cost_price) AS total_cost
                     FROM product_sales_items psi
                     LEFT JOIN product_sales ps ON psi.sale_id = ps.sale_id
                     WHERE psi.product_identifier COLLATE utf8mb4_0900_ai_ci = CONVERT('$productIdEscaped' USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
                     AND YEAR(ps.completed_at) = '$selectedYear'";
    }
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
    
    // 3. Group Variations: get counts per variation.
    $groupItems = [];
    if ($isGrouped) {
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
    if ($isGrouped) {
        $sqlShops = "SELECT 
                        ps.shop_id,
                        (SELECT shop_name FROM shops 
                         WHERE shop_id COLLATE utf8mb4_0900_ai_ci = CONVERT(ps.shop_id USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
                         LIMIT 1) AS shop_name,
                        COUNT(DISTINCT psi.id) AS shop_sales
                     FROM product_sales_items psi
                     LEFT JOIN product_sales ps ON psi.sale_id = ps.sale_id
                     WHERE psi.product_identifier COLLATE utf8mb4_0900_ai_ci IN $identifierSubquery
                     AND YEAR(ps.completed_at) = '$selectedYear'";
    } else {
        $sqlShops = "SELECT 
                        ps.shop_id,
                        (SELECT shop_name FROM shops 
                         WHERE shop_id COLLATE utf8mb4_0900_ai_ci = CONVERT(ps.shop_id USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
                         LIMIT 1) AS shop_name,
                        COUNT(DISTINCT psi.id) AS shop_sales
                     FROM product_sales_items psi
                     LEFT JOIN product_sales ps ON psi.sale_id = ps.sale_id
                     WHERE psi.product_identifier COLLATE utf8mb4_0900_ai_ci = CONVERT('$productIdEscaped' USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
                     AND YEAR(ps.completed_at) = '$selectedYear'";
    }
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
    
    // 5. Sales Trend data.
    if ($selectedMonth) {
        if ($isGrouped) {
            $sqlTrend = "SELECT DAY(ps.completed_at) AS period, COUNT(*) AS count
                         FROM product_sales ps
                         LEFT JOIN product_sales_items psi ON psi.sale_id = ps.sale_id
                         WHERE psi.product_identifier COLLATE utf8mb4_0900_ai_ci IN $identifierSubquery
                         AND YEAR(ps.completed_at) = '$selectedYear'
                         AND MONTH(ps.completed_at) = '$selectedMonth'
                         GROUP BY DAY(ps.completed_at)
                         ORDER BY period ASC";
        } else {
            $sqlTrend = "SELECT DAY(ps.completed_at) AS period, COUNT(*) AS count
                         FROM product_sales ps
                         LEFT JOIN product_sales_items psi ON psi.sale_id = ps.sale_id
                         WHERE psi.product_identifier COLLATE utf8mb4_0900_ai_ci = CONVERT('$productIdEscaped' USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
                         AND YEAR(ps.completed_at) = '$selectedYear'
                         AND MONTH(ps.completed_at) = '$selectedMonth'
                         GROUP BY DAY(ps.completed_at)
                         ORDER BY period ASC";
        }
    } else {
        if ($isGrouped) {
            $sqlTrend = "SELECT MONTH(ps.completed_at) AS period, COUNT(*) AS count
                         FROM product_sales ps
                         LEFT JOIN product_sales_items psi ON psi.sale_id = ps.sale_id
                         WHERE psi.product_identifier COLLATE utf8mb4_0900_ai_ci IN $identifierSubquery
                         AND YEAR(ps.completed_at) = '$selectedYear'
                         GROUP BY MONTH(ps.completed_at)
                         ORDER BY period ASC";
        } else {
            $sqlTrend = "SELECT MONTH(ps.completed_at) AS period, COUNT(*) AS count
                         FROM product_sales ps
                         LEFT JOIN product_sales_items psi ON psi.sale_id = ps.sale_id
                         WHERE psi.product_identifier COLLATE utf8mb4_0900_ai_ci = CONVERT('$productIdEscaped' USING utf8mb4) COLLATE utf8mb4_0900_ai_ci
                         AND YEAR(ps.completed_at) = '$selectedYear'
                         GROUP BY MONTH(ps.completed_at)
                         ORDER BY period ASC";
        }
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
    // Enable debugging if debug=1 is passed as a parameter.
    $debug = isset($_GET['debug']) && $_GET['debug'] == 1;

    $search   = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? 'all';
    $brand    = $_GET['brand'] ?? 'all';
    $storeId  = $_GET['store_id'] ?? 'all';
    $year     = $_GET['year'] ?? '';
    $month    = $_GET['month'] ?? 'all';

    $page     = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $pageSize = isset($_GET['page_size']) ? (int)$_GET['page_size'] : 20;
    if ($page < 1) {
        $page = 1;
    }
    if ($pageSize < 1) {
        $pageSize = 20;
    }
    $offset   = ($page - 1) * $pageSize;

    $searchEscaped   = $conn->real_escape_string($search);
    $categoryEscaped = $conn->real_escape_string($category);
    $brandEscaped    = $conn->real_escape_string($brand);
    $yearEscaped     = $conn->real_escape_string($year);
    $monthEscaped    = $conn->real_escape_string($month);

    $storeCondition = "";
    if ($storeId === 'excludeOnline') {
        $storeCondition = " AND (ps.shop_id != '" . $conn->real_escape_string($excludedShopId) . "' OR ps.shop_id IS NULL) ";
    } elseif ($storeId !== 'all') {
        $storeCondition = " AND (ps.shop_id = '" . $conn->real_escape_string($storeId) . "' OR ps.shop_id IS NULL) ";
    }

    $yearCondition = "";
    if (!empty($year)) {
        $yearCondition = " AND (YEAR(ps.completed_at) = '$yearEscaped' OR ps.completed_at IS NULL) ";
    }

    $monthCondition = "";
    if ($month !== 'all') {
        $monthCondition = " AND (MONTH(ps.completed_at) = '$monthEscaped' OR ps.completed_at IS NULL) ";
    }

    $searchCondition = "";
    if (!empty($search)) {
        $searchCondition = " AND (i.id LIKE '%$searchEscaped%' OR i.title LIKE '%$searchEscaped%') ";
    }

    $categoryCondition = "";
    if ($category !== 'all') {
        $categoryCondition = " AND (psi.product_category_identifier = '$categoryEscaped' OR psi.product_category_identifier IS NULL) ";
    }

    $brandCondition = "";
    if ($brand !== 'all') {
        $brandCondition = " AND i.brand = '$brandEscaped' ";
    }

    /*
      Branch 1: Standalone Items (items without a group_id)
    */
    $standaloneSQL = "
      SELECT 
          i.id AS id,
          i.image_link AS image_link,
          i.title AS title,
          i.brand AS brand,
          COALESCE(SUM(psi.amount), 0) AS total_sales,
          COALESCE(COUNT(psi.id), 0) AS count_sales,
          COALESCE(SUM(psi.cost_price), 0) AS total_cost
      FROM items i
      LEFT JOIN product_sales_items psi 
          ON psi.product_identifier = i.id
      LEFT JOIN product_sales ps 
          ON psi.sale_id = ps.sale_id
      WHERE i.group_id IS NULL
          $storeCondition
          $yearCondition
          $monthCondition
          $searchCondition
          $categoryCondition
          $brandCondition
      GROUP BY i.id
    ";

    /*
      Branch 2: Grouped Items (items with a group_id)
      We aggregate sales from two joins and then join with a representative query.
    */
    $groupedDerived = "
      SELECT product_group, SUM(total_sales) AS total_sales, SUM(count_sales) AS count_sales, SUM(total_cost) AS total_cost
      FROM (
          SELECT 
              i.group_id AS product_group,
              COALESCE(SUM(psi.amount), 0) AS total_sales,
              COUNT(psi.id) AS count_sales,
              COALESCE(SUM(psi.cost_price), 0) AS total_cost
          FROM items i
          LEFT JOIN product_sales_items psi 
              ON psi.product_identifier = i.group_id
          LEFT JOIN product_sales ps 
              ON psi.sale_id = ps.sale_id
          WHERE i.group_id IS NOT NULL
              $storeCondition
              $yearCondition
              $monthCondition
              $searchCondition
              $categoryCondition
              $brandCondition
          GROUP BY i.group_id
          UNION ALL
          SELECT 
              i.group_id AS product_group,
              COALESCE(SUM(psi.amount), 0) AS total_sales,
              COUNT(psi.id) AS count_sales,
              COALESCE(SUM(psi.cost_price), 0) AS total_cost
          FROM items i
          LEFT JOIN product_sales_items psi 
              ON psi.product_identifier = i.id
          LEFT JOIN product_sales ps 
              ON psi.sale_id = ps.sale_id
          WHERE i.group_id IS NOT NULL
              $storeCondition
              $yearCondition
              $monthCondition
              $searchCondition
              $categoryCondition
              $brandCondition
          GROUP BY i.group_id
      ) AS grp
      GROUP BY product_group
    ";

    // Updated representative query:
    // This query joins a distinct set of group_ids to a master record (if one exists, where id = group_id)
    // and a fallback record from variations.
    $repSQL = "
      SELECT 
          g.group_id,
          COALESCE(m.image_link, v.image_link, 'img/placeholder.jpg') AS image_link,
          COALESCE(m.title, v.title, 'No Title') AS title,
          COALESCE(m.brand, v.brand, 'No Brand') AS brand
      FROM (
          SELECT DISTINCT group_id 
          FROM items 
          WHERE group_id IS NOT NULL
      ) g
      LEFT JOIN items m ON m.id = g.group_id
      LEFT JOIN (
          SELECT 
              group_id, 
              MAX(image_link) AS image_link,
              MAX(title) AS title,
              MAX(brand) AS brand
          FROM items
          GROUP BY group_id
      ) v ON v.group_id = g.group_id
    ";

    $groupedSQL = "
      SELECT 
          grp.product_group AS id,
          rep.image_link AS image_link,
          rep.title AS title,
          rep.brand AS brand,
          grp.total_sales,
          grp.count_sales,
          grp.total_cost
      FROM (
          $groupedDerived
      ) grp
      LEFT JOIN (
          $repSQL
      ) rep ON rep.group_id = grp.product_group
    ";

    $sql = "
      ($standaloneSQL)
      UNION ALL
      ($groupedSQL)
      ORDER BY count_sales DESC
      LIMIT $pageSize OFFSET $offset
    ";

    if ($debug) {
        error_log("DEBUG - Product Catalog UNION SQL Query: " . $sql);
    }

    $result = $conn->query($sql);
    if (!$result) {
        $errorMsg = "Database query failed (product_catalog): " . $conn->error;
        if ($debug) {
            error_log("DEBUG - SQL Error: " . $conn->error);
            header('Content-Type: application/json');
            echo json_encode([
                "error"     => "SQL Error",
                "sql_error" => $conn->error,
                "sql_query" => $sql
            ]);
            exit();
        }
        throw new Exception($errorMsg);
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    header('Content-Type: application/json');
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
    } else {
        throw new Exception("Invalid interval specified.");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
    error_log("API Error: " . $e->getMessage(), 0);
    exit();
}
