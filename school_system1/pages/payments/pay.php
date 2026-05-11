<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

// Төлбөр төлөх / Банк харах
if (!isStudent() && $_SESSION['role'] !== 'parent') {
    header('Location: /school_system1/pages/payments/index.php'); exit;
}

$tuitionId = (int)($_GET['id'] ?? 0);
if (!$tuitionId) { header('Location: /school_system1/pages/payments/index.php'); exit; }

// Гүйлгээний өгөгдөл + Оюутны мэдээлэл
$tuition = dbOne("SELECT t.*, CONCAT(s.last_name,' ',s.first_name) AS student_name, c.class_name
    FROM tuition t
    JOIN students s ON t.student_id=s.student_id
    JOIN classes c ON s.class_id=c.class_id
    WHERE t.tuition_id=?", [$tuitionId]);

if (!$tuition) { header('Location: /school_system1/pages/payments/index.php'); exit; }

// ?????????: ?????? ??????????, ???? ?? ???????????? ? ?????
if (isStudent()) {
    $me = dbOne("SELECT student_id FROM students WHERE user_id=?", [$_SESSION['user_id']]);
    if (!$me || $me['student_id'] != $tuition['student_id']) {
        header('Location: /school_system1/pages/payments/index.php'); exit;
    }
}
if ($_SESSION['role'] === 'parent') {
    $parent = dbOne("SELECT p.parent_id FROM parents p WHERE p.user_id=?", [$_SESSION['user_id']]);
    $child  = dbOne("SELECT student_id FROM students WHERE student_id=? AND parent_id=?",
        [$tuition['student_id'], $parent['parent_id'] ?? 0]);
    if (!$child) { header('Location: /school_system1/pages/payments/index.php'); exit; }
}

// Өмнө нь төлөгдсөн бол буцаах
if ($tuition['status'] === 'paid') {
    setFlash('success', 'Энэ төлбөр өмнө нь төлөгдөж дууссан байна!');
    header('Location: /school_system1/pages/payments/index.php'); exit;
}

// -- POST: Төлбөр баталгаажуулах ------------------------------
$success = false;
$receiptNo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $method = $_POST['method'] ?? '';
    $validMethods = ['qpay','card','bank'];
    if (in_array($method, $validMethods)) {
        $receiptNo = 'RCP-' . date('Y') . '-' . strtoupper(substr(uniqid(), 5, 6));
        $methodLabel = ['qpay'=>'QPay','card'=>'Карт','bank'=>'Дансаар шилжүүлэх'][$method];
        dbUpdate("UPDATE tuition SET status='paid', paid_date=?, payment_method=?, receipt_no=? WHERE tuition_id=?",
            [date('Y-m-d'), $method, $receiptNo, $tuitionId]);
        auditLog('payment_online', $tuitionId, "$methodLabel-өөр {$tuition['amount']} төлөгдлөө");
        $success = true;
        // ?????????? ???????? ????
        $tuition = dbOne("SELECT t.*, CONCAT(s.last_name,' ',s.first_name) AS student_name, c.class_name
            FROM tuition t JOIN students s ON t.student_id=s.student_id JOIN classes c ON s.class_id=c.class_id
            WHERE t.tuition_id=?", [$tuitionId]);
    }
}

$pageTitle = 'Төлбөр төлөх';
include __DIR__ . '/../../includes/header.php';
?>

<style>
.pay-container { max-width: 600px; margin: 0 auto; }
.pay-info-card {
    background: linear-gradient(135deg, #1e3a5f, #2563eb);
    color: #fff;
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 24px;
    box-shadow: 0 8px 32px rgba(37,99,235,.3);
}
.pay-info-card .amount { font-size: 2.4rem; font-weight: 800; margin: 8px 0; }
.pay-info-card .meta { opacity: .8; font-size: 13px; }
.method-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin: 20px 0; }
.method-btn {
    border: 2px solid #e5e7eb;
    border-radius: 14px;
    padding: 20px 12px;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    background: #fff;
    position: relative;
}
.method-btn:hover { border-color: #2563eb; background: #eff6ff; transform: translateY(-2px); box-shadow: 0 4px 16px rgba(37,99,235,.15); }
.method-btn.selected { border-color: #2563eb; background: #eff6ff; }
.method-btn.selected::after {
    content: '?';
    position: absolute;
    top: 8px; right: 10px;
    background: #2563eb;
    color: #fff;
    width: 20px; height: 20px;
    border-radius: 50%;
    font-size: 12px;
    display: flex; align-items: center; justify-content: center;
}
.method-btn input[type=radio] { display: none; }
.method-btn .icon { font-size: 2rem; margin-bottom: 8px; display: block; }
.method-btn .label { font-weight: 600; font-size: 14px; color: #1e293b; }
.method-btn .desc { font-size: 11px; color: #64748b; margin-top: 3px; }
/* QPay QR ??????? */
.qpay-box { background: #f0fdf4; border: 2px dashed #16a34a; border-radius: 14px; padding: 24px; text-align: center; display: none; }
.qpay-box.active { display: block; }
.qpay-qr { width: 180px; height: 180px; margin: 0 auto 12px; border-radius: 12px; overflow: hidden; }
.bank-box { background: #eff6ff; border: 2px dashed #2563eb; border-radius: 14px; padding: 20px; display: none; }
.bank-box.active { display: block; }
.bank-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #dbeafe; font-size: 14px; }
.bank-row:last-child { border: none; }
/* Receipt */
.receipt { background: #f8fafc; border-radius: 14px; padding: 24px; text-align: center; }
.receipt .check { font-size: 3rem; color: #16a34a; margin-bottom: 12px; }
.receipt-no { background: #dcfce7; color: #15803d; border-radius: 8px; padding: 10px; font-family: monospace; font-size: 18px; font-weight: 700; display: inline-block; margin: 12px 0; letter-spacing: 2px; }
.receipt-detail { font-size: 13px; color: #64748b; margin-top: 8px; }
.btn-pay { width: 100%; padding: 16px; font-size: 16px; font-weight: 700; border-radius: 12px; display: none; }
.btn-pay.show { display: block; }
</style>

<div class="pay-container">

<?php if ($success): ?>
<!-- ? ????????? -->
<div class="card">
  <div class="card-body">
    <div class="receipt">
      <div class="check"><i class="fas fa-check-circle"></i></div>
      <h2 style="color:#15803d">Төлбөр төлөгдлөө!</h2>
      <p style="color:#64748b">Таны төлбөр амжилттай баталгаажлаа.</p>
      <div class="receipt-no"><?= h($tuition['receipt_no']) ?></div>
      <div class="receipt-detail">
        <strong><?= h($tuition['student_name']) ?></strong> · <?= h($tuition['class_name']) ?><br>
        Дүн: <strong><?= mnMoney($tuition['amount']) ?></strong> · Огноо: <?= date('Y/m/d') ?><br>
        Суваг: <strong><?= ['qpay'=>'QPay','card'=>'Карт','bank'=>'Дансаар шилжүүлэх'][$tuition['payment_method']] ?? '-' ?></strong>
      </div>
      <div style="margin-top:24px;display:flex;gap:12px;justify-content:center">
        <a href="/school_system1/pages/payments/index.php" class="btn btn-secondary">
          <i class="fas fa-list"></i> Гүйлгээний түүх
        </a>
        <a href="/school_system1/dashboard.php" class="btn btn-primary">
          <i class="fas fa-home"></i> Нүүр хуудас
        </a>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- Төлбөрийн мэдээлэл -->
<div class="pay-info-card">
  <div class="meta"><i class="fas fa-file-invoice-dollar"></i> Нэхэмжлэх мэдээлэл</div>
  <div class="amount"><?= mnMoney($tuition['amount']) ?></div>
  <div style="display:flex;gap:20px;margin-top:8px">
    <span><i class="fas fa-user"></i> <?= h($tuition['student_name']) ?></span>
    <span><i class="fas fa-chalkboard"></i> <?= h($tuition['class_name']) ?></span>
    <span><i class="fas fa-calendar"></i> <?= h($tuition['due_date']) ?></span>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-credit-card"></i> Төлбөрийн хэлбэр сонгох</h2>
  </div>
  <div class="card-body">
    <form method="POST" id="payForm">
      <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
      <input type="hidden" name="method" id="methodInput" value="">

      <div class="method-grid">
        <!-- QPay -->
        <label class="method-btn" id="btn-qpay" onclick="selectMethod('qpay')">
          <input type="radio" name="m" value="qpay">
          <span class="icon">??</span>
          <div class="label">QPay</div>
          <div class="desc">QR уншуулах</div>
        </label>
        <!-- Карт -->
        <label class="method-btn" id="btn-card" onclick="selectMethod('card')">
          <input type="radio" name="m" value="card">
          <span class="icon"><i class="fas fa-credit-card"></i></span>
          <div class="label">Карт</div>
          <div class="desc">Дебит / Кредит</div>
        </label>
        <!-- Данс -->
        <label class="method-btn" id="btn-bank" onclick="selectMethod('bank')">
          <input type="radio" name="m" value="bank">
          <span class="icon"><i class="fas fa-university"></i></span>
          <div class="label">Шилжүүлэг</div>
          <div class="desc">Дансаар төлөх</div>
        </label>
      </div>

      <!-- QPay QR -->
      <div class="qpay-box" id="box-qpay">
        <div style="font-weight:700;color:#15803d;margin-bottom:12px"><i class="fas fa-qrcode"></i> QPay QR-аар төлбөр хийх</div>
        <div class="qpay-qr">
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=QPay|SchoolDB|<?= $tuition['tuition_id'] ?>|<?= $tuition['amount'] ?>" width="180" height="180" alt="QPay QR">
        </div>
        <div style="font-size:13px;color:#166534">Гүйлгээний утга: <strong>SCHOOL-<?= str_pad($tuitionId, 6, '0', STR_PAD_LEFT) ?></strong></div>
        <div style="font-size:12px;color:#64748b;margin-top:6px">QPay апп ашиглан QR уншуулна уу</div>
      </div>

      <!-- Картаар төлөх -->
      <div class="bank-box" id="box-card" style="background:#faf5ff;border-color:#7c3aed">
        <div style="font-weight:700;color:#5b21b6;margin-bottom:14px"><i class="fas fa-credit-card"></i> Картаар төлөх</div>
        <div class="form-group">
          <label>Картны дугаар</label>
          <input type="text" class="form-control" placeholder="0000 0000 0000 0000"
            maxlength="19" oninput="this.value=this.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim()">
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label>Дуусах хугацаа</label>
            <input type="text" class="form-control" placeholder="MM/YY" maxlength="5"
              oninput="this.value=this.value.replace(/\D/g,'').replace(/(\d{2})(\d)/,'$1/$2')">
          </div>
          <div class="form-group">
            <label>CVV</label>
            <input type="password" class="form-control" placeholder="•••" maxlength="3">
          </div>
        </div>
        <div class="form-group">
          <label>Карт эзэмшигчийн нэр</label>
          <input type="text" class="form-control" placeholder="BOLD ENKHBAYAR">
        </div>
      </div>

      <!-- Дансаар төлөх -->
      <div class="bank-box" id="box-bank">
        <div style="font-weight:700;color:#1e40af;margin-bottom:12px"><i class="fas fa-university"></i> Дансаар шилжүүлэх</div>
        <div class="bank-row"><span>Банк:</span> <strong>ХААН БАНК</strong></div>
        <div class="bank-row"><span>Дансны дугаар:</span> <strong>5000123456</strong></div>
        <div class="bank-row"><span>Дансны нэр:</span> <strong>Сургуулийн төлбөрийн данс</strong></div>
        <div class="bank-row"><span>Дүн:</span> <strong><?= mnMoney($tuition['amount']) ?></strong></div>
        <div class="bank-row"><span>Гүйлгээний утга:</span>
          <strong style="color:#1d4ed8">SCHOOL-<?= str_pad($tuitionId, 6, '0', STR_PAD_LEFT) ?></strong>
        </div>
        <div style="background:#dbeafe;border-radius:8px;padding:10px;margin-top:12px;font-size:12px;color:#1e40af">
          <i class="fas fa-info-circle"></i> Гүйлгээний утга дээр <strong>SCHOOL-<?= str_pad($tuitionId, 6, '0', STR_PAD_LEFT) ?></strong> заавал бичиж өгнө үү.
          Шилжүүлэг хийсний дараа "Төлбөр баталгаажуулах" товчийг дарна уу.
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-pay" id="submitBtn">
        <i class="fas fa-lock"></i> Төлбөрийг баталгаажуулах — <?= mnMoney($tuition['amount']) ?>
      </button>
    </form>

    <div style="margin-top:16px;text-align:center">
      <a href="/school_system1/pages/payments/index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Болих
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

</div>

<script>
function selectMethod(m) {
    // ??? ??????? ????????
    ['qpay','card','bank'].forEach(function(k) {
        document.getElementById('btn-'+k).classList.remove('selected');
        document.getElementById('box-'+k).classList.remove('active');
    });
    // ???????? ???????????
    document.getElementById('btn-'+m).classList.add('selected');
    document.getElementById('box-'+m).classList.add('active');
    document.getElementById('methodInput').value = m;
    document.getElementById('submitBtn').classList.add('show');
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

