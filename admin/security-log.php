<?php
require_once __DIR__ . '/includes/session_admin.php';
requireAdminLogin();

require_once __DIR__ . '/includes/identity.php';
if (!isAdmin()) {
    header("Location: dashboard.php");
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#3e454c">
  <title>Security Audit Logs</title>

  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .filter-row { display:flex; gap:10px; flex-wrap:wrap; align-items:end; margin: 10px 0 15px; }
    .filter-row .form-group { margin-bottom:0; }
    .tag-ok { background:#d1e7dd; color:#0f5132; padding:3px 8px; border-radius:10px; font-weight:700; font-size:12px; }
    .tag-bad { background:#f8d7da; color:#842029; padding:3px 8px; border-radius:10px; font-weight:700; font-size:12px; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    .small { font-size:12px; color:#666; }

    /* ✅ DataTables scroll header/body alignment helpers */
    div.dataTables_wrapper div.dataTables_scrollBody {
      border-bottom: 1px solid #ddd;
    }
    table.dataTable {
      margin-top: 0 !important;
      margin-bottom: 0 !important;
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<div class="ts-main-content">
<?php include __DIR__ . '/includes/leftbar.php'; ?>

<div class="content-wrapper">
  <div class="container-fluid">

    <!-- <h2 class="page-title">Security Audit Logs</h2> -->

    <div class="panel panel-default">
      <div class="panel-heading">Security Audit Logs - Logs</div>
      <div class="panel-body">

        <!-- Filters -->
        <div class="filter-row">
          <div class="form-group">
            <label>Email</label>
            <input type="text" id="fEmail" class="form-control" placeholder="user@example.com">
          </div>

          <div class="form-group">
            <label>Action</label>
            <select id="fAction" class="form-control">
              <option value="">All</option>
              <option value="forgot_username">forgot_username</option>
              <option value="forgot_password">forgot_password</option>
              <option value="reset_password">reset_password</option>
              <option value="admin_login">admin_login</option>
              <option value="admin_login_failed">admin_login_failed</option>
            </select>
            <div class="small">Some actions appear only if you log them.</div>
          </div>

          <div class="form-group">
            <label>Result</label>
            <select id="fSuccess" class="form-control">
              <option value="">All</option>
              <option value="1">Success</option>
              <option value="0">Failed</option>
            </select>
          </div>

          <div class="form-group">
            <label>Date From</label>
            <input type="date" id="fFrom" class="form-control">
          </div>

          <div class="form-group">
            <label>Date To</label>
            <input type="date" id="fTo" class="form-control">
          </div>

          <div class="form-group">
            <button class="btn btn-primary" id="btnApply">
              <i class="fa fa-filter"></i> Apply
            </button>
            <button class="btn btn-default" id="btnReset" style="margin-left:6px;">
              Reset
            </button>
          </div>
        </div>

        <table id="logTable" class="table table-striped table-bordered" style="width:100%;">
          <thead>
            <tr>
              <th style="width:60px;">ID</th>
              <th style="width:160px;">Time</th>
              <th>Email</th>
              <th style="width:80px;">Admin ID</th>
              <th>Action</th>
              <th style="width:90px;">Result</th>
              <th style="width:140px;">IP</th>
              <th style="width:220px;">User Agent</th>
              <th>Meta</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>

        <div class="small" style="margin-top:10px;">
          Tip: Use the search box (top-right) to search across all columns.
        </div>

      </div>
    </div>

  </div>
</div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap.min.js"></script>

<script>
$(function(){

  const table = $('#logTable').DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    order: [[1, 'desc']], // Time desc

    /* ✅ THIS makes only tbody scroll and thead fixed */
    scrollY: '350px',
    scrollCollapse: true,
    scrollX: true,
    autoWidth: false,

    ajax: {
      url: 'ajax/security_log_data.php',
      type: 'GET',
      data: function(d){
        d.email      = $('#fEmail').val();
        d.action     = $('#fAction').val();
        d.success    = $('#fSuccess').val();
        d.date_from  = $('#fFrom').val();
        d.date_to    = $('#fTo').val();
      }
    },
    columns: [
      { data: 'id', width: '60px' },
      { data: 'created_at', width: '160px' },
      { data: 'email' },
      { data: 'admin_id', width: '80px' },
      { data: 'action', className: 'mono' },
      { data: 'success', width: '90px', orderable: true, searchable: false },
      { data: 'ip', className: 'mono', width: '140px' },
      { data: 'user_agent', width: '220px' },
      { data: 'meta' }
    ],
    columnDefs: [
      { targets: 5, render: function(data){ return data; } }
    ],

    /* ✅ Fix header/column alignment after render */
    initComplete: function () {
      setTimeout(function(){ table.columns.adjust().draw(false); }, 50);
    }
  });

  $('#btnApply').on('click', function(e){
    e.preventDefault();
    table.ajax.reload();
  });

  $('#btnReset').on('click', function(e){
    e.preventDefault();
    $('#fEmail').val('');
    $('#fAction').val('');
    $('#fSuccess').val('');
    $('#fFrom').val('');
    $('#fTo').val('');
    table.ajax.reload();
  });

  // ✅ If page is resized, keep header aligned
  $(window).on('resize', function(){
    table.columns.adjust();
  });

});
</script>
</body>
</html>
