<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db_config.php';

// --- Super Admin Authentication Check ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../index.php?error=unauthorized');
    exit;
}
$active_page = 'superadmin';

// ====================================================================
// COMBINED DATA FETCHING FOR FINAL DASHBOARD
// ====================================================================
$current_year = date('Y');
$current_month = date('m');

// --- 1. Top-Level KPIs ---
$kpi_data = $conn->query("
    SELECT
        (SELECT SUM(amount) FROM fees WHERE payment_status = 'Paid' AND YEAR(paid_date) = $current_year) as revenue_ytd,
        (SELECT SUM(amount) FROM fees WHERE payment_status != 'Paid') as outstanding_fees,
        (SELECT COUNT(id) FROM users WHERE role = 'student') as total_students,
        (SELECT COUNT(id) FROM users WHERE role = 'student' AND YEAR(created_at) = $current_year AND MONTH(created_at) = $current_month) as new_students_this_month
")->fetch_assoc();

// --- 2. Monthly Revenue Growth Calculation ---
$revenue_current_month = $conn->query("SELECT SUM(amount) as total FROM fees WHERE payment_status = 'Paid' AND YEAR(paid_date) = $current_year AND MONTH(paid_date) = $current_month")->fetch_assoc()['total'] ?? 0;
$previous_month_date = date('Y-m', strtotime('-1 month'));
$revenue_previous_month = $conn->query("SELECT SUM(amount) as total FROM fees WHERE payment_status = 'Paid' AND DATE_FORMAT(paid_date, '%Y-%m') = '$previous_month_date'")->fetch_assoc()['total'] ?? 0;

if ($revenue_previous_month > 0) {
    $revenue_growth = (($revenue_current_month - $revenue_previous_month) / $revenue_previous_month) * 100;
} else {
    $revenue_growth = $revenue_current_month > 0 ? 100 : 0;
}

// --- 3. Table Data (Revenue vs. New Students) ---
$result = $conn->query("
    SELECT
        DATE_FORMAT(d.month_date, '%Y-%m') AS month,
        (SELECT COUNT(id) FROM users WHERE role='student' AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(d.month_date, '%Y-%m')) AS new_students,
        (SELECT SUM(amount) FROM fees WHERE payment_status='Paid' AND DATE_FORMAT(paid_date, '%Y-%m') = DATE_format(d.month_date, '%Y-%m')) AS revenue
    FROM (SELECT DATE_SUB(CURDATE(), INTERVAL (a.a + (10 * b.a)) MONTH) AS month_date FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS a CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1) AS b) d
    WHERE d.month_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) AND d.month_date <= CURDATE()
    ORDER BY d.month_date ASC
");
$revenue_student_data = $result->fetch_all(MYSQLI_ASSOC);


// --- 4. User Role Distribution Data ---
$user_counts = $conn->query("SELECT role, COUNT(id) as count FROM users GROUP BY role")->fetch_all(MYSQLI_ASSOC);

// --- 5. Overdue Fees List ---
$overdue_fees_result = $conn->query("
    SELECT u.username, f.amount, f.due_date, DATEDIFF(CURDATE(), f.due_date) as days_overdue
    FROM fees f JOIN users u ON f.student_id = u.id
    WHERE f.payment_status != 'Paid' AND f.due_date < CURDATE()
    ORDER BY f.due_date ASC LIMIT 5
");

// --- 6. Revenue by Fee Type (YTD) ---
$revenue_by_type_result = $conn->query("
    SELECT fee_type, SUM(amount) as total_revenue
    FROM fees
    WHERE payment_status = 'Paid' AND YEAR(paid_date) = $current_year
    GROUP BY fee_type
    ORDER BY total_revenue DESC
");

// --- 7. Most Active Students (Exams Taken) ---
$most_active_students_result = $conn->query("
    SELECT u.username, COUNT(es.id) as session_count
    FROM exam_sessions es JOIN users u ON es.user_id = u.id
    WHERE es.completed = 1 GROUP BY u.id ORDER BY session_count DESC LIMIT 5
");


$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="image.png" type="image/png">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5', secondary: '#0EA5E9', dark: '#1E293B', light: '#F8FAFC',
                        success: '#10B981', danger: '#EF4444', warning: '#F59E0B'
                    }
                }
            }
        }
    </script>
    <style>
        html, body { height: 100%; }
        body { background-color: #F8FAFC; display: flex; flex-direction: column; }
        .shadow-custom { box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .fade-in { animation: fadeIn 0.5s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 767px) {
            main { padding-top: 80px; padding-bottom: 100px; }
            .grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4.gap-6 { grid-template-columns: 1fr; }
            .lg\:col-span-3, .lg\:col-span-2 { grid-column: span 1 / span 1; }
            .grid.grid-cols-1.lg\:grid-cols-3.gap-8 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="text-gray-800 flex flex-col min-h-screen">

    <?php require_once 'header.php'; ?>

    <div class="flex flex-1">
        <?php require_once 'sidebar.php'; ?>

        <main class="flex-1 p-6">
            <h2 class="text-3xl font-bold text-dark mb-2">Super Admin Dashboard</h2>
            <p class="text-gray-500 mb-8">A complete overview of the entire system.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-custom p-6 fade-in">
                    <p class="text-gray-500 text-sm">Revenue (YTD)</p>
                    <h3 class="text-3xl font-bold text-dark mt-2">BDT <?= number_format($kpi_data['revenue_ytd'] ?? 0, 0); ?></h3>
                </div>
                <div class="bg-white rounded-xl shadow-custom p-6 fade-in" style="animation-delay: 0.1s;">
                    <p class="text-gray-500 text-sm">Monthly Growth</p>
                     <div class="flex items-baseline mt-2">
                        <h3 class="text-3xl font-bold <?= $revenue_growth >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($revenue_growth, 1); ?>%</h3>
                        <i class="fas <?= $revenue_growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?> ml-2 <?= $revenue_growth >= 0 ? 'text-success' : 'text-danger' ?>"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-custom p-6 fade-in" style="animation-delay: 0.2s;">
                    <p class="text-gray-500 text-sm">Active Students</p>
                    <h3 class="text-3xl font-bold text-dark mt-2"><?= $kpi_data['total_students'] ?? 0; ?></h3>
                </div>
                <div class="bg-white rounded-xl shadow-custom p-6 fade-in" style="animation-delay: 0.3s;">
                    <p class="text-gray-500 text-sm">Outstanding Fees</p>
                    <h3 class="text-3xl font-bold text-warning mt-2">BDT <?= number_format($kpi_data['outstanding_fees'] ?? 0, 0); ?></h3>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 mb-8">
                <div class="lg:col-span-3 bg-white rounded-xl shadow-custom p-6 fade-in" style="animation-delay: 0.4s;">
                    <h4 class="text-lg font-bold text-dark mb-4">Revenue vs. New Students (Last 12 Months)</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="p-3 text-left font-medium text-gray-600">Month</th>
                                    <th class="p-3 text-right font-medium text-gray-600">Revenue (BDT)</th>
                                    <th class="p-3 text-right font-medium text-gray-600">New Students</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach($revenue_student_data as $data): ?>
                                <tr>
                                    <td class="p-3 font-medium"><?= date("F Y", strtotime($data['month'])); ?></td>
                                    <td class="p-3 text-right text-green-600 font-semibold"><?= number_format($data['revenue'] ?? 0, 2); ?></td>
                                    <td class="p-3 text-right font-semibold"><?= $data['new_students'] ?? 0; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="lg:col-span-2 bg-white rounded-xl shadow-custom p-6 fade-in" style="animation-delay: 0.5s;">
                    <h4 class="text-lg font-bold text-dark mb-4">User Distribution</h4>
                     <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="p-3 text-left font-medium text-gray-600">Role</th>
                                    <th class="p-3 text-right font-medium text-gray-600">Count</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach($user_counts as $role_data): ?>
                                <tr>
                                    <td class="p-3 font-medium"><?= ucfirst(str_replace('_', ' ', $role_data['role'])); ?></td>
                                    <td class="p-3 text-right font-semibold"><?= $role_data['count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="bg-white rounded-xl shadow-custom fade-in" style="animation-delay: 0.6s;">
                    <h4 class="text-lg font-bold text-dark p-6 border-b">Revenue by Fee Type (YTD)</h4>
                    <div class="divide-y">
                        <?php if ($revenue_by_type_result && $revenue_by_type_result->num_rows > 0): ?>
                            <?php while($type = $revenue_by_type_result->fetch_assoc()): ?>
                            <div class="p-4 flex justify-between items-center">
                                <p class="font-semibold text-dark"><?= ucwords(str_replace('_', ' ', htmlspecialchars($type['fee_type']))); ?></p>
                                <p class="text-sm font-bold text-success">BDT <?= number_format($type['total_revenue']); ?></p>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="p-4 text-center text-gray-500">No revenue data for fee types yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-custom fade-in" style="animation-delay: 0.7s;">
                    <h4 class="text-lg font-bold text-dark p-6 border-b">Most Active Students</h4>
                    <div class="divide-y">
                         <?php while($student = $most_active_students_result->fetch_assoc()): ?>
                        <div class="p-4 flex justify-between items-center">
                            <p class="font-semibold text-dark"><?= htmlspecialchars($student['username']); ?></p>
                            <p class="text-sm text-gray-500"><?= $student['session_count'] ?> exams taken</p>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-custom fade-in" style="animation-delay: 0.8s;">
                    <h4 class="text-lg font-bold text-dark p-6 border-b">Overdue Fee Payments</h4>
                    <div class="divide-y">
                         <?php if ($overdue_fees_result->num_rows > 0): ?>
                             <?php while($fee = $overdue_fees_result->fetch_assoc()): ?>
                            <div class="p-4 flex justify-between items-center">
                                <div>
                                    <p class="font-semibold text-dark"><?= htmlspecialchars($fee['username']); ?></p>
                                    <p class="text-sm text-gray-500">Due: <?= date('M d, Y', strtotime($fee['due_date'])); ?></p>
                                </div>
                                <p class="font-bold text-danger"><?= htmlspecialchars($fee['days_overdue']); ?> days</p>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="p-4 text-center text-gray-500">No overdue fees.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const userMenuButton = document.getElementById('user-menu-button');
    const userMenu = document.getElementById('user-menu');
    if(userMenuButton) {
        userMenuButton.addEventListener('click', (e) => { e.stopPropagation(); userMenu.classList.toggle('hidden'); });
    }
    document.addEventListener('click', (e) => {
        if (userMenu && !userMenu.classList.contains('hidden') && !userMenuButton.contains(e.target)) {
            userMenu.classList.add('hidden');
        }
    });

    const mobileMoreBtn = document.getElementById('mobile-more-btn');
    const mobileMoreMenu = document.getElementById('mobile-more-menu');
    if(mobileMoreBtn){
        mobileMoreBtn.addEventListener('click', (e) => {
            e.preventDefault();
            mobileMoreMenu.classList.toggle('hidden');
        });
    }
    document.addEventListener('click', (e) => {
        if (mobileMoreMenu && !mobileMoreMenu.classList.contains('hidden') && !mobileMoreBtn.contains(e.target) && !mobileMoreMenu.contains(e.target)) {
            mobileMoreMenu.classList.add('hidden');
        }
    });
});
</script>
</body>
</html>