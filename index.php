<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => 'dashboard.designcykler.dk.linux100.curanetserver.dk',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'None'
]);
include 'cors.php';
session_start();

// 1) include DB so we can re-query
include 'db.php';

// 2) redirect if not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// 3) re-sync flags (and store_id) from DB
$stmt = $conn->prepare("
  SELECT is_admin, is_store_manager, store_id
    FROM admin_auth
   WHERE id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($dbIsAdmin, $dbIsStoreManager, $dbStoreId);
$stmt->fetch();
$stmt->close();

// 4) overwrite session flags
$_SESSION['is_admin']         = (bool)$dbIsAdmin;
$_SESSION['is_store_manager'] = (bool)$dbIsStoreManager;
$_SESSION['store_id']         = $dbStoreId;

// 5) convenience variables
$is_admin         = (bool)($_SESSION['is_admin'] ?? false);
$is_store_manager = (bool)($_SESSION['is_store_manager'] ?? false);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Customer Dashboard</title>
    <link rel="icon" href="favicon.png">
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <style>
        #catalogGrid {
            min-height: 500px;
            overflow-y: auto;
        }
    </style>
</head>

<body>
    <!-- Tabs Navigation -->
    <div id="tabs">
        <ul>
            <?php if ($is_store_manager): ?>

                <!-- Store Managers: two flat tabs -->
                <li><a href="#store-catalog">Store Catalog</a></li>
                <li><a href="#store-transfer"class="active">Transfer Stock</a></li>

            <?php else: ?>

                <!-- Everyone else (admins and staff): grouped dropdowns -->
                <li><a href="#main-page" class="active">Main Page</a></li>

                <li class="dropdown">
                    <button type="button" class="dropbtn">Products</button>
                    <ul class="dropdown-menu">
                        <li><a href="#all-products">All Products</a></li>
                        <li><a href="#product-creation">Product Creation</a></li>
                        <li><a href="#product-catalog">Product Performance</a></li>
                    </ul>
                </li>

                <li class="dropdown">
                    <button type="button" class="dropbtn">Store</button>
                    <ul class="dropdown-menu">
                        <li><a href="#store-traffic">Store Traffic</a></li>
                        <li><a href="#store-catalog">Store Catalog</a></li>
                        <?php if ($is_admin): ?>
                            <li><a href="#store-catalog-sales">Store Catalog sales</a></li>
                        <?php endif; ?>
                        <li><a href="#store-pickups">Store Pickups</a></li>
                    </ul>
                </li>

                <li class="dropdown">
                    <button type="button" class="dropbtn">Website</button>
                    <ul class="dropdown-menu">
                        <li><a href="#bikerace-cms">Bikerace CMS</a></li>
                    </ul>
                </li>

                <?php if ($is_admin): ?>
                    <li class="dropdown">
                        <button type="button" class="dropbtn">Transfers</button>
                        <ul class="dropdown-menu">
                            <li><a href="#store-transfer">Transfer Stock</a></li>
                            <li><a href="#transfer-monitor">Transfer Monitor</a></li>
                        </ul>
                    </li>
                    <li><a href="#admin-tab">Admin Panel</a></li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Tab Content -->
    <div id="tab-content">

        <?php if ($is_store_manager): ?>

            <!-- Only for Store Managers -->
            <?php include 'store_catalog.php'; ?>
            <?php include 'store_transfer.php'; ?>

        <?php else: ?>

            <!-- Main Page -->
            <div id="main-page" class="tab active">
                <h1>Metrics</h1>
                <section id="charts" class="charts-grid">
                    <div id="storeSelection" class="selection-container">
                        <div>
                            <label for="storeDropdown">Select Store:</label>
                            <select id="storeDropdown">
                                <option value="all">All Stores</option>
                                <option value="excludeOnline">All Stores except online</option>
                            </select>
                        </div>
                        <div>
                            <label for="yearDropdown">Select Year:</label>
                            <select id="yearDropdown">
                                <option value="2025">2025</option>
                                <option value="2024">2024</option>
                                <option value="2023">2023</option>
                                <option value="2022">2022</option>
                                <option value="2021">2021</option>
                                <option value="2020">2020</option>
                            </select>
                        </div>
                        <div>
                            <label for="monthDropdown">Select Month:</label>
                            <select id="monthDropdown">
                                <option value="all">All Months</option>
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>
                        <div>
                            <input type="checkbox" id="customRange">
                            <label for="customRange">Custom Range</label>
                        </div>
                        <div id="customRangeContainer" style="display:none;">
                            <div class="date-container">
                                <label for="startDate">Start Date:</label>
                                <input type="date" id="startDate">
                            </div>
                            <div class="date-container">
                                <label for="endDate">End Date:</label>
                                <input type="date" id="endDate">
                            </div>
                        </div>
                    </div>
                    <div id="customLegend"></div>
                    <div class="chart-container">
                        <h3>Monthly Comparison</h3>
                        <canvas id="monthlyChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h3>Yearly Summary</h3>
                        <canvas id="yearlyChart"></canvas>
                    </div>
                </section>
            </div>

            <!-- All Products -->
            <div id="all-products" class="tab">
                <h1>All Products</h1>
                <div id="product-table"></div>
            </div>

            <!-- Product Creation -->
            <div id="product-creation" class="tab" style="display: none;">
                <h1>Product Creation</h1>
                <form id="productCreationForm" class="creation-form">
                    <!-- Produktnavn -->
                    <div>
                        <label for="produktnavn">Produktnavn:</label>
                        <input type="text" id="produktnavn" name="produktnavn" placeholder="Angiv produktnavn" required>
                    </div>
                    <!-- Produktnummer -->
                    <div>
                        <label for="produktnummer">Produktnummer:</label>
                        <input type="text" id="produktnummer" name="produktnummer" placeholder="Angiv produktnummer" required>
                    </div>
                    <!-- EAN / Stregkode -->
                    <div>
                        <label for="ean">EAN / Stregkode:</label>
                        <input type="text" id="ean" name="ean" placeholder="Angiv EAN / Stregkode">
                    </div>
                    <!-- Pris -->
                    <div>
                        <label for="pris">Pris:</label>
                        <input type="number" id="pris" name="pris" placeholder="Angiv pris" required>
                    </div>
                    <!-- Beskrivelse -->
                    <div>
                        <label for="beskrivelse">Beskrivelse:</label>
                        <textarea id="beskrivelse" name="beskrivelse" rows="3" placeholder="Evt. manuel beskrivelse"></textarea>
                    </div>
                    <!-- Kort beskrivelse -->
                    <div>
                        <label for="kortBeskrivelse">Kort beskrivelse:</label>
                        <textarea id="kortBeskrivelse" name="kortBeskrivelse" rows="3" placeholder="AI‐genereret kort beskrivelse"></textarea>
                    </div>
                    <!-- Udvidet beskrivelse -->
                    <div>
                        <label for="udvidetBeskrivelse">Udvidet beskrivelse:</label>
                        <textarea id="udvidetBeskrivelse" name="udvidetBeskrivelse" rows="4" placeholder="AI‐genereret udvidet beskrivelse"></textarea>
                    </div>
                    <!-- Title Tag -->
                    <div>
                        <label for="titleTag">Title Tag:</label>
                        <input type="text" id="titleTag" name="titleTag" placeholder="AI‐genereret Title Tag">
                    </div>
                    <!-- Metatag beskrivelse -->
                    <div>
                        <label for="metaTagBeskrivelse">Metatag beskrivelse:</label>
                        <textarea id="metaTagBeskrivelse" name="metaTagBeskrivelse" rows="2" placeholder="AI‐genereret metatag beskrivelse"></textarea>
                    </div>
                    <!-- CSV‐upload -->
                    <div>
                        <label for="productCSV">Supplerende CSV (valgfri):</label>
                        <input type="file" id="productCSV" accept=".csv">
                        <small>CSV kan indeholde flere produkter. Vi bruger "Produktnummer" til at finde den relevante række.</small>
                    </div>
                    <!-- Generer AI -->
                    <div style="margin-top: 20px; text-align: center;">
                        <button type="button" id="generateDescriptionBtn" class="creation-btn">
                            Generer via AI
                        </button>
                    </div>
                    <!-- AI Preview -->
                    <div id="aiPreviewContainer" style="margin-top: 20px; display: none;">
                        <h2>AI‐forslag (klik og rediger efter behov):</h2>
                        <!-- AI kort -->
                        <div>
                            <label for="aiKort">Kort beskrivelse (AI):</label>
                            <textarea id="aiKort" rows="3" style="
                                width: 100%; padding: 10px; font-size: 14px;
                                border: 1px solid #ccc; border-radius: 4px;
                                background-color: #f9f9f9; color: #333;
                                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                                resize: vertical;
                            "></textarea>
                        </div>
                        <!-- AI udvidet -->
                        <div style="margin-top: 10px;">
                            <label for="aiUdvidet">Udvidet beskrivelse (AI):</label>
                            <textarea id="aiUdvidet" rows="4" style="
                                width: 100%; padding: 10px; font-size: 14px;
                                border: 1px solid #ccc; border-radius: 4px;
                                background-color: #f9f9f9; color: #333;
                                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                                resize: vertical;
                            "></textarea>
                        </div>
                        <!-- AI Title Tag -->
                        <div style="margin-top: 10px;">
                            <label for="aiTitleTag">Title Tag (AI):</label>
                            <input type="text" id="aiTitleTag" style="
                                width: 100%; padding: 10px; font-size: 14px;
                                border: 1px solid #ccc; border-radius: 4px;
                                background-color: #f9f9f9; color: #333;
                                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                            ">
                        </div>
                        <!-- AI Metatag -->
                        <div style="margin-top: 10px;">
                            <label for="aiMetaTag">Metatag beskrivelse (AI):</label>
                            <textarea id="aiMetaTag" rows="2" style="
                                width: 100%; padding: 10px; font-size: 14px;
                                border: 1px solid #ccc; border-radius: 4px;
                                background-color: #f9f9f9; color: #333;
                                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                                resize: vertical;
                            "></textarea>
                        </div>
                    </div>
                    <!-- Opret Produkt -->
                    <div style="margin-top: 30px; text-align: center;">
                        <button type="submit" class="creation-btn">Opret Produkt</button>
                    </div>
                </form>
            </div>

            <!-- Product Performance -->
            <div id="product-catalog" class="tab">
                <h1>Product Performance</h1>
                <div class="catalog-controls">
                    <div class="filter-row">
                        <div>
                            <label for="storeDropdownCatalog">Select Store:</label>
                            <select id="storeDropdownCatalog">
                                <option value="all">All Stores</option>
                                <option value="excludeOnline">All Stores except Online</option>
                            </select>
                        </div>
                        <div>
                            <label for="yearDropdownCatalog">Select Year:</label>
                            <select id="yearDropdownCatalog">
                                <option value="2025">2025</option>
                                <option value="2024">2024</option>
                                <option value="2023">2023</option>
                                <option value="2022">2022</option>
                                <option value="2021">2021</option>
                                <option value="2020">2020</option>
                            </select>
                        </div>
                        <div>
                            <label for="monthDropdownCatalog">Select Month:</label>
                            <select id="monthDropdownCatalog">
                                <option value="all">All Months</option>
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-row">
                        <div>
                            <label for="productSearch">Search Product:</label>
                            <input type="text" id="productSearch" placeholder="Enter product ID or name">
                        </div>
                        <div>
                            <label for="categoryDropdown">Category:</label>
                            <select id="categoryDropdown">
                                <option value="all">All Categories</option>
                            </select>
                        </div>
                        <div>
                            <label for="brandDropdown">Brand:</label>
                            <select id="brandDropdown">
                                <option value="all">All Brands</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="catalogGrid" class="catalog-grid">
                    <!-- Catalog items will be dynamically inserted here -->
                </div>
            </div>

            <!-- Detailed View (hidden by default) -->
            <div id="detailView" class="detail-view" style="display: none;">
                <div class="detail-header">
                    <select id="detailYearDropdown"></select>
                    <select id="detailMonthDropdown">
                        <option value="all">All Months</option>
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                    <button id="backButton">Back</button>
                </div>
                <div class="detail-content">
                    <div class="box" id="boxItemInfo"></div>
                    <div class="box" id="boxSalesSummary"></div>
                    <div class="box" id="boxItemGroup"></div>
                    <div class="box" id="boxShopPerformance">
                        <canvas id="shopPerformanceChart"></canvas>
                    </div>
                </div>
                <div class="detail-trend">
                    <canvas id="salesTrendChart"></canvas>
                </div>
                <div class="detail-comparison" style="height: 400px; margin-top: 20px;">
                    <canvas id="storeComparisonChart"></canvas>
                </div>
            </div>

            <!-- Store Traffic -->
            <div id="store-traffic" class="tab" style="display: none;">
                <h1>Store Traffic</h1>
                <div id="storeSelection" class="selection-container">
                    <div>
                        <label for="storeTrafficDropdown">Select Store:</label>
                        <select id="storeTrafficDropdown">
                            <option value="all">All Stores</option>
                        </select>
                    </div>
                    <div>
                        <label for="trafficDateInput">Select Date:</label>
                        <input type="date" id="trafficDateInput" />
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>

            <!-- Store Catalog Sales -->
            <?php if ($is_admin): ?>
                <?php include 'store_catalog_sales.php'; ?>
            <?php endif; ?>

            <!-- Store Pickups -->
            <div id="store-pickups" class="tab" style="display: none;">
                <h1>Store Pickups</h1>

                <div id="storeSelection" class="selection-container">
                    <div>
                        <label for="storeDropdownPickups">Select Store:</label>
                        <select id="storeDropdownPickups"></select>
                    </div>
                    <div>
                        <label for="yearDropdownPickups">Select Year:</label>
                        <select id="yearDropdownPickups"></select>
                    </div>
                    <div>
                        <label for="monthDropdownPickups">Select Month:</label>
                        <select id="monthDropdownPickups"></select>
                    </div>
                </div>

                <div class="chart-container">
                    <canvas id="pickupsChart"></canvas>
                </div>
            </div>

            <!-- Store Catalog & Transfer -->
            <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                <?php include 'store_catalog.php'; ?>
                <?php include 'store_transfer.php'; ?>
                <!-- Admin Tab -->
                <div id="admin-tab" class="tab">
                    <h1>Admin Panel</h1>
                    <h3>Sales database</h3>
                    <h4 id="last-update-time">Fetching last update...</h4>
                    <div id="admin-content">
                        <form id="createUserForm" class="admin-form">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" placeholder="Enter Username" required />
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Enter Password" required />
                            <button type="submit" class="admin-btn">Create User</button>
                        </form>
                    </div>
                    <h2>Manage Users</h2>
                    <div id="user-list"></div>
                </div>
            <?php endif; ?>

            <?php if ($is_admin): ?>
                <!-- Transfer Monitor -->
                <div id="transfer-monitor" class="tab" style="display: none;">
                    <h1>Transfer Monitor</h1>
                    <div id="transfer-monitor-container">
                        <table id="adminTransferTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Product</th>
                                    <th>Source</th>
                                    <th>Destination</th>
                                    <th>Qty</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Populated by js/admin_transfers.js -->
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            <?php include __DIR__ . '/bikerace.php'; ?>
        <?php endif; ?>
    </div>

    <!-- Logout -->
    <a href="logout.php" class="logout-link">Logout</a>

    <!-- Scripts -->
    <script src="js/tabs.js"></script>
    <script src="js/admin.js"></script>
    <script src="js/admin_transfers.js"></script>
    <script src="js/products.js"></script>
    <script src="js/customers.js"></script>
    <script src="js/orders.js"></script>
    <script src="js/charts.js"></script>
    <script src="js/product_catalog.js"></script>
    <script src="js/detail_view.js"></script>
    <script src="js/product_creation.js"></script>
    <script src="js/store_traffic.js"></script>
    <script src="js/store_catalog.js"></script>
    <script src="js/store_catalog_sales.js"></script>
    <script src="js/store_transfer.js"></script>
    <script src="js/store_pickups.js"></script>
    <script src="js/bikerace.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const customRangeCheckbox = document.getElementById("customRange");
            const customRangeContainer = document.getElementById("customRangeContainer");
            const yearDropdown = document.getElementById("yearDropdown");
            const monthDropdown = document.getElementById("monthDropdown");
            const yearLabel = document.querySelector('label[for="yearDropdown"]');
            const monthLabel = document.querySelector('label[for="monthDropdown"]');

            customRangeCheckbox.addEventListener("change", function() {
                if (this.checked) {
                    yearDropdown.disabled = monthDropdown.disabled = true;
                    yearDropdown.style.display = monthDropdown.style.display = "none";
                    yearLabel.style.display = monthLabel.style.display = "none";
                    customRangeContainer.style.display = "flex";
                } else {
                    yearDropdown.disabled = monthDropdown.disabled = false;
                    yearDropdown.style.display = monthDropdown.style.display = "inline-block";
                    yearLabel.style.display = monthLabel.style.display = "inline";
                    customRangeContainer.style.display = "none";
                }
                if (typeof handleInputChange === "function") handleInputChange();
            });
        });
    </script>
</body>

</html>