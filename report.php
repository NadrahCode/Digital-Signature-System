<?php
ob_start();
session_start();
require('inc/db_config.php');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: dashboard.php");
    exit;
}

$user_id    = $_SESSION['user_id'];
$user_name  = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_role  = $_SESSION['role'];
$profile_img = $_SESSION['profile_img'] ?? '';

// ── DATA ─────────────────────────────────────────────────────────────
$users_stats      = $connect->query("SELECT COUNT(*) as total_users, SUM(CASE WHEN role='user' THEN 1 ELSE 0 END) as total_regular_users, SUM(CASE WHEN role='admin' THEN 1 ELSE 0 END) as total_admins, SUM(CASE WHEN role='superadmin' THEN 1 ELSE 0 END) as total_superadmins, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_users, SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) as inactive_users, SUM(CASE WHEN user_class IS NOT NULL AND user_class != 'unassigned' THEN 1 ELSE 0 END) as assigned_users FROM users")->fetch_assoc();
$docs_stats       = $connect->query("SELECT COUNT(*) as total_docs, SUM(CASE WHEN source_type='upload' THEN 1 ELSE 0 END) as uploaded_docs, SUM(CASE WHEN source_type='text' THEN 1 ELSE 0 END) as text_docs FROM documents")->fetch_assoc();
$recipients_stats = $connect->query("SELECT COUNT(*) as total_recipients, COUNT(DISTINCT user_id) as unique_recipients, SUM(CASE WHEN viewed_at IS NOT NULL THEN 1 ELSE 0 END) as viewed_count, SUM(CASE WHEN downloaded_at IS NOT NULL THEN 1 ELSE 0 END) as downloaded_count FROM document_recipients")->fetch_assoc();
$classes_stats    = $connect->query("SELECT COUNT(*) as total_classes FROM classes")->fetch_assoc();
$queries_stats    = $connect->query("SELECT COUNT(*) as total_queries, SUM(CASE WHEN status='new' THEN 1 ELSE 0 END) as new_queries, SUM(CASE WHEN status='read' THEN 1 ELSE 0 END) as read_queries, SUM(CASE WHEN status='resolved' THEN 1 ELSE 0 END) as resolved_queries FROM queries")->fetch_assoc();

$all_users     = $connect->query("SELECT user_id, name, email, role, status, user_class, created_at FROM users ORDER BY created_at DESC");
$all_documents = $connect->query("SELECT d.id, d.doc_name, d.source_type, d.created_at, u.name as creator_name, (SELECT COUNT(*) FROM document_recipients WHERE document_id = d.id) as recipient_count FROM documents d LEFT JOIN users u ON d.created_by = u.user_id ORDER BY d.created_at DESC");
$all_classes   = $connect->query("SELECT c.class_id, c.class_name, c.created_at, u.name as creator_name, (SELECT COUNT(*) FROM users WHERE user_class = c.class_name) as student_count FROM classes c LEFT JOIN users u ON c.created_by = u.user_id ORDER BY c.created_at DESC");

// ── CSV DOWNLOAD ──────────────────────────────────────────────────────
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    ob_end_clean();
    $section = $_GET['section'] ?? 'users';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . $section . '_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    if ($section === 'users') {
        fputcsv($out, ['ID','Name','Email','Role','Status','Class','Registered']);
        while ($r = $all_users->fetch_assoc())
            fputcsv($out, [$r['user_id'],$r['name'],$r['email'],$r['role'],$r['status'],$r['user_class'],date('Y-m-d', strtotime($r['created_at']))]);
    } elseif ($section === 'documents') {
        fputcsv($out, ['ID','Document Name','Type','Creator','Recipients','Created']);
        while ($r = $all_documents->fetch_assoc())
            fputcsv($out, [$r['id'],$r['doc_name'],$r['source_type'],$r['creator_name'],$r['recipient_count'],date('Y-m-d', strtotime($r['created_at']))]);
    } elseif ($section === 'classes') {
        fputcsv($out, ['ID','Class Name','Students','Creator','Created']);
        while ($r = $all_classes->fetch_assoc())
            fputcsv($out, [$r['class_id'],$r['class_name'],$r['student_count'],$r['creator_name'],date('Y-m-d', strtotime($r['created_at']))]);
    }
    fclose($out);
    exit;
}

// ── PDF DOWNLOAD ──────────────────────────────────────────────────────
if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    ob_end_clean();
    require_once(__DIR__ . '/lib/tcpdf/tcpdf.php');
    $pdf = new TCPDF('P','mm','A4',true,'UTF-8',false);
    $pdf->SetCreator('Digital Signature System');
    $pdf->SetTitle('System Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15,15,15);
    $pdf->SetAutoPageBreak(true,15);

    // Cover
    $pdf->AddPage();
    $pdf->SetFont('helvetica','B',22);
    $pdf->SetTextColor(2,128,144);
    $pdf->Cell(0,12,'SYSTEM REPORT',0,1,'C');
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('helvetica','',11);
    $pdf->Cell(0,7,'Digital Signature System — UPTM',0,1,'C');
    $pdf->Cell(0,7,'Generated: '.date('F j, Y g:i A').' by '.$user_name,0,1,'C');
    $pdf->Ln(10);

    // Summary table
    $pdf->SetFont('helvetica','B',13);
    $pdf->SetFillColor(2,128,144);
    $pdf->SetTextColor(255,255,255);
    $pdf->Cell(0,9,'SUMMARY',0,1,'L',true);
    $pdf->SetTextColor(0,0,0);
    $pdf->Ln(3);
    $rows = [
        ['Total Users', $users_stats['total_users']],
        ['Total Documents', $docs_stats['total_docs']],
        ['Total Classes', $classes_stats['total_classes']],
        ['Documents Viewed', $recipients_stats['viewed_count']],
        ['Documents Downloaded', $recipients_stats['downloaded_count']],
        ['New Feedback/Queries', $queries_stats['new_queries']],
    ];
    $pdf->SetFont('helvetica','',11);
    foreach ($rows as $i => [$label,$val]) {
        if ($i % 2 === 0) $pdf->SetFillColor(240,248,250); else $pdf->SetFillColor(255,255,255);
        $pdf->Cell(130,8,$label,0,0,'L',true);
        $pdf->SetFont('helvetica','B',11);
        $pdf->Cell(0,8,$val,0,1,'L',true);
        $pdf->SetFont('helvetica','',11);
    }

    // Helper to draw a simple table
    $drawTable = function($pdf, $title, $headers, $widths, $rows_data) {
        $pdf->AddPage();
        $pdf->SetFont('helvetica','B',13);
        $pdf->SetFillColor(2,128,144);
        $pdf->SetTextColor(255,255,255);
        $pdf->Cell(0,9,$title,0,1,'L',true);
        $pdf->SetTextColor(0,0,0);
        $pdf->Ln(3);
        $pdf->SetFont('helvetica','B',9);
        $pdf->SetFillColor(230,230,230);
        foreach ($headers as $i => $h) $pdf->Cell($widths[$i],8,$h,1,0,'C',true);
        $pdf->Ln();
        $pdf->SetFont('helvetica','',8);
        foreach ($rows_data as $j => $row) {
            $pdf->SetFillColor($j%2===0 ? 250 : 255, $j%2===0 ? 250 : 255, $j%2===0 ? 250 : 255);
            foreach ($row as $i => $cell) $pdf->Cell($widths[$i],7,substr($cell,0,40),1,0,'L',true);
            $pdf->Ln();
        }
    };

    // Users table
    $urows = [];
    while ($r = $all_users->fetch_assoc())
        $urows[] = [$r['user_id'],$r['name'],$r['email'],strtoupper($r['role']),ucfirst($r['status']),$r['user_class'] ?? '-'];
    $drawTable($pdf,'ALL USERS',['ID','Name','Email','Role','Status','Class'],[12,45,55,25,22,21],$urows);

    // Documents table
    $drows = [];
    while ($r = $all_documents->fetch_assoc())
        $drows[] = [$r['id'],$r['doc_name'],ucfirst($r['source_type']),$r['creator_name'],$r['recipient_count'],date('M j Y',strtotime($r['created_at']))];
    $drawTable($pdf,'ALL DOCUMENTS',['ID','Document Name','Type','Creator','Recip.','Created'],[12,65,25,40,20,18],$drows);

    // Classes table
    $crows = [];
    while ($r = $all_classes->fetch_assoc())
        $crows[] = [$r['class_id'],$r['class_name'],$r['student_count'],$r['creator_name'],date('M j Y',strtotime($r['created_at']))];
    $drawTable($pdf,'ALL CLASSES',['ID','Class Name','Students','Creator','Created'],[12,60,30,58,20],$crows);

    $pdf->Output('system_report_'.date('Y-m-d').'.pdf','D');
    exit;
}

ob_end_flush();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>System Report | Digital Signature System</title>
<?php require('inc/links.php'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="css/design.css">
<link rel="stylesheet" href="css/dashboard.css">
<style>
.report-stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:14px; margin-bottom:28px; }
.report-stat { background:var(--card-bg); border:1px solid var(--sidebar-border); border-radius:12px; padding:18px 14px; text-align:center; }
.report-stat .num { font-size:28px; font-weight:700; color:#028090; }
.report-stat .lbl { font-size:12px; color:var(--text-secondary); margin-top:4px; }
.chart-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:28px; }
.chart-card { background:var(--card-bg); border:1px solid var(--sidebar-border); border-radius:12px; padding:20px; }
.chart-card h4 { margin:0 0 14px; font-size:14px; color:var(--text-primary); }
.section-tabs { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
.sec-tab { padding:8px 18px; border:2px solid var(--sidebar-border); border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; color:var(--text-secondary); background:var(--card-bg); transition:all 0.2s; }
.sec-tab.active { border-color:#028090; color:#028090; background:rgba(2,128,144,0.07); }
.data-section { display:none; }
.data-section.active { display:block; }
.report-table { width:100%; border-collapse:collapse; font-size:13px; }
.report-table thead { background:linear-gradient(135deg,#028090,#114B2F); color:white; }
.report-table th { padding:11px 14px; text-align:left; font-weight:600; }
.report-table td { padding:10px 14px; border-bottom:1px solid var(--sidebar-border); color:var(--text-primary); }
.report-table tr:last-child td { border-bottom:none; }
.report-table tr:hover { background:var(--table-bg); }
.badge { display:inline-block; padding:3px 9px; border-radius:10px; font-size:11px; font-weight:600; }
.badge-user { background:#e7f3ff; color:#0066cc; }
.badge-admin { background:#fff3cd; color:#856404; }
.badge-superadmin { background:#f8d7da; color:#721c24; }
.badge-active { background:#d4edda; color:#155724; }
.badge-inactive { background:#f8d7da; color:#721c24; }
.toolbar { display:flex; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
.toolbar input { padding:8px 12px; border:1px solid var(--sidebar-border); border-radius:8px; background:var(--card-bg); color:var(--text-primary); font-size:13px; flex:1; min-width:180px; }
.dl-btn { padding:8px 16px; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
.dl-csv { background:#28a745; color:white; }
.dl-pdf { background:linear-gradient(135deg,#028090,#114B2F); color:white; }
.color-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px; }
.color-row label { font-size:12px; color:var(--text-secondary); display:flex; align-items:center; gap:5px; }
.color-row input[type=color] { width:32px; height:28px; border:none; border-radius:4px; cursor:pointer; padding:0; }
@media(max-width:700px){ .chart-grid { grid-template-columns:1fr; } }
</style>
</head>
<body data-theme="light">
<div class="dashboard-container">
    <?php require('inc/sidebar.php'); ?>
    <main class="main-content">
        <?php require('inc/topheader.php'); ?>

        <section class="welcome-section">
            <h1 class="welcome-title">System Report 📊</h1>
            <h2 class="system-title">DIGITAL SIGNATURE SYSTEM</h2>
            <div class="logo-container">
                <a href="dashboard.php"><img src="images/logo-main.png" alt="Logo" class="system-logo"></a>
            </div>
        </section>

        <div style="max-width:1300px; margin:0 auto; padding:24px 30px;">

            <!-- Download row -->
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-bottom:22px; flex-wrap:wrap;">
                <a href="report.php?download=csv&section=users"   class="dl-btn dl-csv"><i class="bi bi-filetype-csv"></i> CSV Users</a>
                <a href="report.php?download=csv&section=documents" class="dl-btn dl-csv"><i class="bi bi-filetype-csv"></i> CSV Docs</a>
                <a href="report.php?download=csv&section=classes" class="dl-btn dl-csv"><i class="bi bi-filetype-csv"></i> CSV Classes</a>
                <a href="report.php?download=pdf"                 class="dl-btn dl-pdf"><i class="bi bi-file-pdf"></i> Download PDF</a>
            </div>

            <!-- Summary stats -->
            <div class="report-stat-grid">
                <div class="report-stat"><div class="num"><?php echo $users_stats['total_users']; ?></div><div class="lbl">Total Users</div></div>
                <div class="report-stat"><div class="num"><?php echo $docs_stats['total_docs']; ?></div><div class="lbl">Documents</div></div>
                <div class="report-stat"><div class="num"><?php echo $classes_stats['total_classes']; ?></div><div class="lbl">Classes</div></div>
                <div class="report-stat"><div class="num"><?php echo $recipients_stats['viewed_count']; ?></div><div class="lbl">Viewed</div></div>
                <div class="report-stat"><div class="num"><?php echo $recipients_stats['downloaded_count']; ?></div><div class="lbl">Downloaded</div></div>
                <div class="report-stat"><div class="num"><?php echo $queries_stats['new_queries']; ?></div><div class="lbl">New Queries</div></div>
            </div>

            <!-- Chart colour picker -->
            <div class="color-row">
                <span style="font-size:13px; font-weight:600; color:var(--text-primary);">Chart colours:</span>
                <label>A <input type="color" id="c1" value="#028090" oninput="updateChartColors()"></label>
                <label>B <input type="color" id="c2" value="#114B2F" oninput="updateChartColors()"></label>
                <label>C <input type="color" id="c3" value="#38ef7d" oninput="updateChartColors()"></label>
                <label>D <input type="color" id="c4" value="#f39c12" oninput="updateChartColors()"></label>
                <label>E <input type="color" id="c5" value="#e74c3c" oninput="updateChartColors()"></label>
            </div>

            <!-- Charts -->
            <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
            <div class="chart-grid">
                <div class="chart-card">
                    <h4>👥 User roles</h4>
                    <div style="position:relative; height:180px;"><canvas id="rolesChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <h4>📄 Document activity</h4>
                    <div style="position:relative; height:180px;"><canvas id="docsChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <h4>💬 Query status</h4>
                    <div style="position:relative; height:180px;"><canvas id="queriesChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <h4>✅ User status</h4>
                    <div style="position:relative; height:180px;"><canvas id="statusChart"></canvas></div>
                </div>
            </div>

            <!-- Section tabs -->
            <div class="section-tabs">
                <div class="sec-tab active" onclick="showSection('users',this)">👥 Users</div>
                <div class="sec-tab" onclick="showSection('documents',this)">📄 Documents</div>
                <div class="sec-tab" onclick="showSection('classes',this)">📚 Classes</div>
            </div>

            <!-- Users table -->
            <div id="sec-users" class="data-section active">
                <div class="toolbar">
                    <input type="text" placeholder="🔍 Search users..." oninput="filterTable('usersTable',this.value)">
                    <select onchange="filterTableCol('usersTable',3,this.value)" style="padding:8px 12px; border:1px solid var(--sidebar-border); border-radius:8px; background:var(--card-bg); color:var(--text-primary); font-size:13px;">
                        <option value="">All roles</option>
                        <option>user</option><option>admin</option><option>superadmin</option>
                    </select>
                </div>
                <div style="overflow-x:auto; border-radius:10px; border:1px solid var(--sidebar-border);">
                <table class="report-table" id="usersTable">
                    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Class</th><th>Registered</th></tr></thead>
                    <tbody>
                    <?php while ($u = $all_users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $u['user_id']; ?></td>
                            <td style="font-weight:600"><?php echo htmlspecialchars($u['name']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><span class="badge badge-<?php echo $u['role']; ?>"><?php echo strtoupper($u['role']); ?></span></td>
                            <td><span class="badge badge-<?php echo $u['status']; ?>"><?php echo ucfirst($u['status']); ?></span></td>
                            <td><?php echo htmlspecialchars($u['user_class'] ?? '—'); ?></td>
                            <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Documents table -->
            <div id="sec-documents" class="data-section">
                <div class="toolbar">
                    <input type="text" placeholder="🔍 Search documents..." oninput="filterTable('docsTable',this.value)">
                </div>
                <div style="overflow-x:auto; border-radius:10px; border:1px solid var(--sidebar-border);">
                <table class="report-table" id="docsTable">
                    <thead><tr><th>ID</th><th>Document Name</th><th>Type</th><th>Creator</th><th>Recipients</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php $all_documents->data_seek(0); while ($d = $all_documents->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $d['id']; ?></td>
                            <td style="font-weight:600">📄 <?php echo htmlspecialchars($d['doc_name']); ?></td>
                            <td><?php echo ucfirst($d['source_type']); ?></td>
                            <td><?php echo htmlspecialchars($d['creator_name'] ?? '—'); ?></td>
                            <td><?php echo $d['recipient_count']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($d['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Classes table -->
            <div id="sec-classes" class="data-section">
                <div class="toolbar">
                    <input type="text" placeholder="🔍 Search classes..." oninput="filterTable('classesTable',this.value)">
                </div>
                <div style="overflow-x:auto; border-radius:10px; border:1px solid var(--sidebar-border);">
                <table class="report-table" id="classesTable">
                    <thead><tr><th>ID</th><th>Class Name</th><th>Students</th><th>Creator</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php $all_classes->data_seek(0); while ($c = $all_classes->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $c['class_id']; ?></td>
                            <td style="font-weight:600">📖 <?php echo htmlspecialchars($c['class_name']); ?></td>
                            <td><?php echo $c['student_count']; ?></td>
                            <td><?php echo htmlspecialchars($c['creator_name'] ?? '—'); ?></td>
                            <td><?php echo date('M j, Y', strtotime($c['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <div style="text-align:center; margin-top:30px; padding-top:20px; border-top:1px solid var(--sidebar-border); font-size:12px; color:var(--text-secondary);">
                Report generated <?php echo date('F j, Y \a\t g:i A'); ?> by <?php echo htmlspecialchars($user_name); ?>
            </div>
        </div>
    </main>
</div>

<script src="js/dashboard.js"></script>
<script>
// ── CHARTS ────────────────────────────────────────────────────────────
const phpData = {
    roles:   [<?php echo (int)$users_stats['total_regular_users'].','.( int)$users_stats['total_admins'].','.( int)$users_stats['total_superadmins']; ?>],
    docs:    [<?php echo (int)$docs_stats['total_docs'].','.( int)$docs_stats['uploaded_docs'].','.( int)$docs_stats['text_docs'].','.( int)$recipients_stats['viewed_count'].','.( int)$recipients_stats['downloaded_count']; ?>],
    queries: [<?php echo (int)$queries_stats['new_queries'].','.( int)$queries_stats['read_queries'].','.( int)$queries_stats['resolved_queries']; ?>],
    status:  [<?php echo (int)$users_stats['active_users'].','.( int)$users_stats['inactive_users']; ?>],
};

let charts = {};
function getColors() {
    return ['c1','c2','c3','c4','c5'].map(id => document.getElementById(id).value);
}

function buildCharts() {
    const c = getColors();
    if (charts.roles) { Object.values(charts).forEach(ch => ch.destroy()); charts = {}; }

    charts.roles = new Chart(document.getElementById('rolesChart'), {
        type: 'doughnut',
        data: { labels:['Users','Admins','Superadmins'], datasets:[{ data: phpData.roles, backgroundColor:[c[0],c[1],c[2]], borderWidth:2, borderColor:'transparent' }] },
        options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom', labels:{ font:{size:11}, padding:10 } } } }
    });

    charts.docs = new Chart(document.getElementById('docsChart'), {
        type: 'bar',
        data: { labels:['Total','Uploaded','Text','Viewed','Downloaded'], datasets:[{ data: phpData.docs, backgroundColor:[c[0],c[1],c[2],c[3],c[4]], borderRadius:6, borderSkipped:false }] },
        options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false} }, scales:{ y:{ beginAtZero:true, ticks:{stepSize:1}, grid:{ color:'rgba(0,0,0,0.05)' } }, x:{ grid:{display:false} } } }
    });

    charts.queries = new Chart(document.getElementById('queriesChart'), {
        type: 'bar',
        data: { labels:['New','Read','Resolved'], datasets:[{ data: phpData.queries, backgroundColor:[c[4],c[3],c[0]], borderRadius:6, borderSkipped:false }] },
        options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false} }, scales:{ y:{ beginAtZero:true, ticks:{stepSize:1}, grid:{ color:'rgba(0,0,0,0.05)' } }, x:{ grid:{display:false} } } }
    });

    charts.status = new Chart(document.getElementById('statusChart'), {
        type: 'pie',
        data: { labels:['Active','Inactive'], datasets:[{ data: phpData.status, backgroundColor:[c[0],c[4]], borderWidth:2, borderColor:'transparent' }] },
        options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom', labels:{ font:{size:11}, padding:10 } } } }
    });
}

function updateChartColors() { buildCharts(); }
buildCharts();

// ── SECTION TABS ──────────────────────────────────────────────────────
function showSection(name, tab) {
    document.querySelectorAll('.data-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.sec-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('sec-' + name).classList.add('active');
    tab.classList.add('active');
}

// ── TABLE SEARCH ──────────────────────────────────────────────────────
function filterTable(tableId, query) {
    const q = query.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

function filterTableCol(tableId, colIdx, value) {
    const q = value.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(row => {
        const cell = row.cells[colIdx];
        row.style.display = (!q || (cell && cell.textContent.toLowerCase().includes(q))) ? '' : 'none';
    });
}
</script>
</body>
</html>