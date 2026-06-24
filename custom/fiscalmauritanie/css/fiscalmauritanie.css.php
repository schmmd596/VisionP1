<?php
if (!defined('NOREQUIREUSER')) define('NOREQUIREUSER', '1');
if (!defined('NOREQUIREDB')) define('NOREQUIREDB', '1');
if (!defined('NOREQUIRESOC')) define('NOREQUIRESOC', '1');
if (!defined('NOREQUIRETRAN')) define('NOREQUIRETRAN', '1');
require_once '../../main.inc.php';
header('Content-type: text/css');
?>
.fiscalmauritanie-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.fiscalmauritanie-card {
    background: #fff;
    border: 1px solid #d7d7d7;
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 1px 2px rgba(0,0,0,.06);
}
.fiscalmauritanie-card-title {
    font-size: 13px;
    color: #666;
    text-transform: uppercase;
    margin-bottom: 8px;
}
.fiscalmauritanie-card-value {
    font-size: 24px;
    font-weight: 700;
    color: #1f4e79;
}
.fiscalmauritanie-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
}
.fiscalmauritanie-badge-draft { background: #f3f4f6; color: #374151; }
.fiscalmauritanie-badge-validated { background: #dbeafe; color: #1d4ed8; }
.fiscalmauritanie-badge-paid { background: #dcfce7; color: #166534; }
.fiscalmauritanie-badge-late { background: #fee2e2; color: #991b1b; }
.fiscalmauritanie-chart-wrap {
    min-height: 260px;
}
