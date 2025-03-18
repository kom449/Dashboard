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

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
} else {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard</title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <!-- Tabs Navigation -->
    <div id="tabs">
        <ul>
            <li><a href="#main-page" class="active">Main Page</a></li>
            <li><a href="#all-products">All Products</a></li>
            <li><a href="#product-catalog">Product Performance</a></li>
            <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                <li><a href="#admin-tab">Admin</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Tab Content -->
    <div id="tab-content">
        <!-- Main Page -->
        <div id="main-page" class="tab active">
            <h1>Metrics</h1>
            <!-- Charts -->
            <section id="charts" class="charts-grid">
                <!-- Store, Date, and Interval Selection -->
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
                <!-- Custom Legend Container -->
                <div id="customLegend"></div>
                <!-- Monthly Comparison Chart Container -->
                <div class="chart-container">
                    <h3>Monthly Comparison</h3>
                    <canvas id="monthlyChart"></canvas>
                </div>
                <!-- Yearly Summary Chart Container -->
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

        <!-- Product Catalog -->
        <div id="product-catalog" class="tab">
            <h1>Product Performance</h1>
            <div class="catalog-controls">
                <!-- First Row: Store, Year, Month Dropdowns -->
                <div class="filter-row">
                    <div>
                        <label for="storeDropdownCatalog">Select Store:</label>
                        <select id="storeDropdownCatalog">
                            <option value="all">All Stores</option>
                            <option value="excludeOnline">All Stores except Online</option>
                            <!-- Additional store options can be loaded dynamically -->
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
                <!-- Second Row: Search Bar, Category, and Brand Dropdowns -->
                <div class="filter-row">
                    <div>
                        <label for="productSearch">Search Product:</label>
                        <input type="text" id="productSearch" placeholder="Enter product ID or name">
                    </div>
                    <div>
                        <label for="categoryDropdown">Category:</label>
                        <select id="categoryDropdown">
                            <option value="all">All Categories</option>
                            <!-- Options can be loaded from the product_categories table -->
                        </select>
                    </div>
                    <div>
                        <label for="brandDropdown">Brand:</label>
                        <select id="brandDropdown">
                            <option value="all">All Brands</option>
                            <!-- Options can be loaded dynamically (e.g., from the items table) -->
                        </select>
                    </div>
                </div>
            </div>
            <!-- Catalog Grid -->
            <div id="catalogGrid" class="catalog-grid">
                <!-- The product catalog items will be inserted here via JavaScript -->
            </div>
        </div>

        <!-- Admin Tab -->
        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
            <div id="admin-tab" class="tab">
                <h1>Admin Panel</h1>
                <!-- Last Update Timestamp -->
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
    </div>

    <!-- Logout -->
    <a href="logout.php" class="logout-link">Logout</a>

    <!-- Scripts -->
    <script src="js/tabs.js"></script>
    <script src="js/admin.js"></script>
    <script src="js/products.js"></script>
    <script src="js/customers.js"></script>
    <script src="js/orders.js"></script>
    <script src="js/charts.js"></script>
    <script src="js/product_catalog.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var customRangeCheckbox = document.getElementById("customRange");
            var customRangeContainer = document.getElementById("customRangeContainer");
            var yearDropdown = document.getElementById("yearDropdown");
            var monthDropdown = document.getElementById("monthDropdown");
            var yearDropdownLabel = document.querySelector('label[for="yearDropdown"]');
            var monthDropdownLabel = document.querySelector('label[for="monthDropdown"]');

            customRangeCheckbox.addEventListener("change", function() {
                if (this.checked) {
                    yearDropdown.disabled = true;
                    monthDropdown.disabled = true;
                    monthDropdown.style.display = "none";
                    yearDropdown.style.display = "none";
                    if (yearDropdownLabel) yearDropdownLabel.style.display = "none";
                    if (monthDropdownLabel) monthDropdownLabel.style.display = "none";

                    customRangeContainer.style.display = "flex";
                } else {
                    yearDropdown.disabled = false;
                    monthDropdown.disabled = false;
                    monthDropdown.style.display = "inline-block";
                    yearDropdown.style.display = "inline-block";
                    if (yearDropdownLabel) yearDropdownLabel.style.display = "inline";
                    if (monthDropdownLabel) monthDropdownLabel.style.display = "inline";

                    customRangeContainer.style.display = "none";
                }

                if (typeof handleInputChange === "function") {
                    handleInputChange();
                }
            });
        });
    </script>
</body>

</html>