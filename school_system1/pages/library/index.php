<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Номын сан';
$user_id   = $_SESSION['user_id'];
$isManager = isManager() || isAdmin() || isDirector();

// ── POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();
    $action = $_POST['action'];

    if ($action === 'add_book' && $isManager) {
        dbExec("INSERT INTO library_books (isbn, title, author, publisher, year, category, total_copies, available, description, cover_url)
                VALUES (?,?,?,?,?,?,?,?,?,?)", [
            $_POST['isbn'] ?: null,
            trim($_POST['title']),
            $_POST['author'] ?: null,
            $_POST['publisher'] ?: null,
            $_POST['pub_year'] ?: null,
            $_POST['category'] ?: null,
            (int)$_POST['total_copies'],
            (int)$_POST['total_copies'],
            $_POST['description'] ?: null,
            $_POST['cover_url'] ?: null,
        ]);
        auditLog('book_add', null, 'title=' . $_POST['title']);
        setFlash('success', 'Ном амжилттай нэмэгдлээ.');
        header('Location: /school_system1/pages/library/index.php');
        exit;
    }

    if ($action === 'borrow' && $isManager) {
        $bookId     = (int)$_POST['book_id'];
        $borrowerId = (int)$_POST['borrower_id'];
        $dueDate    = $_POST['due_date'];
        $book       = dbOne("SELECT * FROM library_books WHERE book_id=?", [$bookId]);
        
        // Ижил номыг буцаагаагүй байхад дахин зээлэхийг хориглох
        $activeLoan = dbOne("SELECT loan_id FROM library_loans WHERE book_id=? AND borrower_id=? AND returned_date IS NULL", [$bookId, $borrowerId]);
        if ($activeLoan) {
            setFlash('error', 'Энэ хэрэглэгч уг номыг аль хэдийн зээлсэн бөгөөд буцаагаагүй байна.');
        } elseif ($book && $book['available'] > 0) {
            dbExec("INSERT INTO library_loans (book_id, borrower_id, loan_date, due_date, recorded_by) VALUES (?,?,CURDATE(),?,?)",
                   [$bookId, $borrowerId, $dueDate, $user_id]);
            dbUpdate("UPDATE library_books SET available = available - 1 WHERE book_id=?", [$bookId]);
            auditLog('book_borrow', $bookId);
            setFlash('success', 'Ном амжилттай зээлэгдлээ.');
        } else {
            setFlash('error', 'Ном боломжгүй байна.');
        }
        header('Location: /school_system1/pages/library/index.php?tab=loans');
        exit;
    }

    if ($action === 'return_book' && $isManager) {
        $loanId = (int)$_POST['loan_id'];
        $loan   = dbOne("SELECT * FROM library_loans WHERE loan_id=?", [$loanId]);
        if ($loan && $loan['status'] === 'borrowed') {
            $fine = 0;
            // Хугацаа хэтэрсэн бол торгуул
            if (strtotime($loan['due_date']) < time()) {
                $days  = ceil((time() - strtotime($loan['due_date'])) / 86400);
                $fine  = $days * 500; // Өдөрт 500₮
            }
            dbUpdate("UPDATE library_loans SET status='returned', return_date=CURDATE(), fine_amount=? WHERE loan_id=?",
                     [$fine, $loanId]);
            dbUpdate("UPDATE library_books SET available = available + 1 WHERE book_id=?", [$loan['book_id']]);
            auditLog('book_return', $loanId);
            $msg = 'Ном буцаагдлаа.';
            if ($fine) $msg .= " Торгуул: ₮" . number_format($fine);
            setFlash('success', $msg);
        }
        header('Location: /school_system1/pages/library/index.php?tab=loans');
        exit;
    }
    if ($action === 'seed_samples' && $isManager) {

        $samples = [
            ['978-0132350884', 'Clean Code', 'Robert C. Martin', 'Prentice Hall', 2008, 'Мэдээлэл зүй', 5, 'https://m.media-amazon.com/images/I/41xShlnTZTL._SX376_BO1,204,203,200_.jpg', 'A Handbook of Agile Software Craftsmanship.'],
            ['978-0596007126', 'Head First Design Patterns', 'Eric Freeman', 'O\'Reilly', 2004, 'Мэдээлэл зүй', 3, 'https://m.media-amazon.com/images/I/91S+mP4Gk7L.jpg', 'A Brain-Friendly Guide to Design Patterns.'],
            ['978-0201633610', 'Design Patterns', 'Gang of Four', 'Addison-Wesley', 1994, 'Мэдээлэл зүй', 2, 'https://m.media-amazon.com/images/I/81gtKoapHFL.jpg', 'Elements of Reusable Object-Oriented Software.']
        ];
        foreach($samples as $s) {
            dbExec("INSERT INTO library_books (isbn, title, author, publisher, year, category, total_copies, available, cover_url, description) 
                    VALUES (?,?,?,?,?,?,?,?,?,?)", [$s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[6], $s[7], $s[8]]);
        }
        setFlash('success', 'Жишээ номнууд амжилттай нэмэгдлээ.');
        header('Location: /school_system1/pages/library/index.php');
        exit;
    }
}


// ── Өгөгдөл татах ──────────────────────────────────────────────
$activeTab  = $_GET['tab'] ?? 'books';
$search     = trim($_GET['q'] ?? '');
$catFilter  = $_GET['cat'] ?? '';

// Номууд
$booksWhere = "1=1";
$booksParams = [];
if ($search) {
    $booksWhere .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
    $booksParams = array_merge($booksParams, ["%$search%", "%$search%", "%$search%"]);
}
if ($catFilter) {
    $booksWhere .= " AND b.category = ?";
    $booksParams[] = $catFilter;
}

// Books pagination
$booksCountResult = dbOne("SELECT COUNT(*) AS cnt FROM library_books b WHERE $booksWhere", $booksParams);
$booksTotalCount = (int)($booksCountResult['cnt'] ?? 0);
$pag = paginate($booksTotalCount, (int)($_GET['page'] ?? 1), 12);
$books    = dbQuery("SELECT * FROM library_books b WHERE $booksWhere ORDER BY b.title LIMIT {$pag['offset']}, {$pag['perPage']}", $booksParams);
$cats     = dbQuery("SELECT DISTINCT category FROM library_books WHERE category IS NOT NULL ORDER BY category");

// Зээлийн бүртгэл
$loans = dbQuery("SELECT l.*, b.title, b.category,
                         u.full_name AS borrower_name,
                         DATEDIFF(l.due_date, CURDATE()) AS days_left
                  FROM library_loans l
                  JOIN library_books b ON l.book_id = b.book_id
                  JOIN users u ON l.borrower_id = u.user_id
                  ORDER BY CASE WHEN l.status='borrowed' THEN 0 ELSE 1 END, l.due_date ASC");

// Сурагчид зээлд өгөх
$allStudents = dbQuery("SELECT u.user_id, u.full_name, c.class_name, r.role_name
                        FROM users u
                        LEFT JOIN students s ON u.user_id = s.user_id
                        LEFT JOIN classes c ON s.class_id = c.class_id
                        JOIN user_roles r ON u.role_id = r.role_id
                        WHERE u.is_active = 1 ORDER BY r.role_name, c.class_name, u.full_name");

$totalBooks    = array_sum(array_column($books, 'total_copies'));
$availBooks    = array_sum(array_column($books, 'available'));
$activeLoans   = count(array_filter($loans, fn($l) => $l['status'] === 'borrowed'));
$overdueLoans  = count(array_filter($loans, fn($l) => $l['status'] === 'borrowed' && $l['days_left'] < 0));

include __DIR__ . '/../../includes/header.php';
?>

<style>
.library-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 24px;
}
.book-poster {
    position: relative;
    width: 100%;
    aspect-ratio: 2/3;
    background: #1e293b;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
}
.book-poster:hover {
    transform: scale(1.05);
    z-index: 10;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);
}
.book-poster img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: filter 0.3s;
}
.book-poster:hover img {
    filter: brightness(0.4);
}
.book-info-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20px;
    background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
    color: #fff;
    opacity: 0;
    transition: opacity 0.3s;
}
.book-poster:hover .book-info-overlay {
    opacity: 1;
}
.book-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    z-index: 2;
}
</style>

<div style="display:flex; gap:10px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
    <a href="?tab=books" class="btn <?= $activeTab === 'books' ? 'btn-primary' : 'btn-secondary' ?>">
        <i class="fas fa-book"></i> Номнууд
    </a>
    <?php if ($isManager): ?>
    <a href="?tab=loans" class="btn <?= $activeTab === 'loans' ? 'btn-primary' : 'btn-secondary' ?>">
        <i class="fas fa-handshake"></i> Зээлийн бүртгэл
    </a>
    <?php endif; ?>
</div>

<?php if ($activeTab === 'books'): ?>

<?php if ($isManager): ?>
<div style="display:flex; gap:10px; margin-bottom:20px;">
    <a href="?tab=add" class="btn btn-primary"><i class="fas fa-plus"></i> Шинэ ном нэмэх</a>
    <a href="/school_system1/pages/library/import.php" class="btn btn-info" style="background:#0ea5e9; color:#fff; border-color:#0ea5e9;"><i class="fas fa-file-import"></i> Excel-ээс оруулах</a>
</div>
<?php endif; ?>

<div class="library-grid">

    <?php if (empty($books)): ?>
    <div style="grid-column:1/-1; text-align:center; padding:80px 20px; background:var(--card-bg); border:2px dashed var(--border); border-radius:16px;">
        <i class="fas fa-book-open" style="font-size:60px; color:var(--primary); opacity:.2; margin-bottom:20px; display:block;"></i>
        <h3 style="color:var(--muted); margin-bottom:10px;">Номын сан хоосон байна</h3>
        <p style="color:var(--muted); font-size:14px; margin-bottom:30px;">Одоогоор бүртгэлтэй ном байхгүй байна.</p>
        <?php if ($isManager): ?>
        <div style="display:flex; gap:10px; justify-content:center;">
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="seed_samples">
                <button type="submit" class="btn btn-secondary"><i class="fas fa-magic"></i> Жишээ номнууд оруулах</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php foreach ($books as $book): 
        $defaultCover = 'https://images.unsplash.com/photo-1543005187-a20af70a1f4e?q=80&w=300&auto=format&fit=crop';
        $cover = $book['cover_url'] ?: $defaultCover;
    ?>
    <div class="book-poster" onclick="viewBookDetails(<?= h(json_encode($book)) ?>)">
        <span class="book-badge <?= $book['available'] > 0 ? 'badge-success' : 'badge-danger' ?>" style="background:<?= $book['available'] > 0 ? '#10b981':'#ef4444' ?>; color:#fff;">
            <?= $book['available'] > 0 ? $book['available'] . ' ш' : 'Дууссан' ?>
        </span>
        <img src="<?= $cover ?>" alt="<?= h($book['title']) ?>">
        <div class="book-info-overlay">
            <div style="font-weight:700; font-size:14px; margin-bottom:4px;"><?= h($book['title']) ?></div>
            <div style="font-size:11px; opacity:0.8; margin-bottom:8px;">✍️ <?= h($book['author'] ?: 'Тодорхойгүй') ?></div>
            <div style="display:flex; flex-wrap:wrap; gap:5px; margin-bottom:10px;">
                <span style="font-size:9px; background:rgba(255,255,255,0.2); padding:2px 6px; border-radius:4px;"><?= h($book['category']) ?></span>
                <span style="font-size:9px; background:rgba(255,255,255,0.2); padding:2px 6px; border-radius:4px;"><?= $book['year'] ?></span>
            </div>
            <?php if ($isManager && $book['available'] > 0): ?>
            <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); openBorrow(<?= $book['book_id'] ?>, '<?= h(addslashes($book['title'])) ?>')" style="width:100%; border-radius:4px; padding:6px; font-size:11px;">
                <i class="fas fa-hand-holding"></i> Зээлэх
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../../includes/pagination.php'; ?>


<!-- НОМЫН ДЭЛГЭРЭНГҮЙ MODAL -->
<div id="bookDetailModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.8); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(5px);">
    <div style="background:var(--card-bg); border-radius:20px; padding:0; max-width:800px; width:95%; display:flex; overflow:hidden;">
        <div id="modalPoster" style="width:300px; background:#1e293b; display:flex; align-items:center; justify-content:center;">
            <img src="" style="width:100%; height:100%; object-fit:cover;" id="detailCover">
        </div>
        <div style="flex:1; padding:40px; position:relative;">
            <button onclick="closeBookDetails()" style="position:absolute; top:20px; right:20px; background:none; border:none; color:var(--muted); font-size:24px; cursor:pointer;">×</button>
            <h2 id="detailTitle" style="font-size:28px; margin-bottom:10px;"></h2>
            <div id="detailAuthor" style="color:var(--primary); font-size:18px; margin-bottom:20px;"></div>
            <div style="display:flex; gap:20px; margin-bottom:20px; border-top:1px solid var(--border); border-bottom:1px solid var(--border); padding:15px 0;">
                <div><label style="font-size:11px; color:var(--muted); display:block;">Ангилал</label><span id="detailCat"></span></div>
                <div><label style="font-size:11px; color:var(--muted); display:block;">Он</label><span id="detailYear"></span></div>
                <div><label style="font-size:11px; color:var(--muted); display:block;">Боломжтой</label><span id="detailAvail"></span></div>
            </div>
            <div id="detailDesc" style="line-height:1.6; color:var(--text); margin-bottom:30px;"></div>
            <div style="display:flex; gap:10px;">
                <button id="modalBorrowBtn" class="btn btn-primary btn-lg" style="display:none;"><i class="fas fa-hand-holding"></i> Ном зээлэх</button>
                <button onclick="closeBookDetails()" class="btn btn-secondary btn-lg">Хаах</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewBookDetails(book) {
    document.getElementById('detailTitle').textContent = book.title;
    document.getElementById('detailAuthor').textContent = book.author || 'Зохиолч тодорхойгүй';
    document.getElementById('detailCover').src = book.cover_url || 'https://images.unsplash.com/photo-1543005187-a20af70a1f4e?q=80&w=300&auto=format&fit=crop';
    document.getElementById('detailCat').textContent = book.category || 'Бусад';
    document.getElementById('detailYear').textContent = book.year || '—';
    document.getElementById('detailAvail').textContent = `${book.available} / ${book.total_copies}`;
    document.getElementById('detailDesc').textContent = book.description || 'Тайлбар оруулаагүй байна.';
    
    if (<?= $isManager ? 'true':'false' ?> && book.available > 0) {
        document.getElementById('modalBorrowBtn').style.display = 'inline-block';
        document.getElementById('modalBorrowBtn').onclick = () => { closeBookDetails(); openBorrow(book.book_id, book.title); };
    } else {
        document.getElementById('modalBorrowBtn').style.display = 'none';
    }
    document.getElementById('bookDetailModal').style.display = 'flex';
}
function closeBookDetails() {
    document.getElementById('bookDetailModal').style.display = 'none';
}
</script>

<?php elseif ($activeTab === 'loans'): ?>


<!-- ═══ ЗЭЭЛИЙН БҮРТГЭЛ ═══ -->
<div style="overflow-x:auto;">
<table style="width:100%; border-collapse:collapse; font-size:13px;">
    <thead>
        <tr style="border-bottom:2px solid var(--border);">
            <th style="padding:10px 12px; text-align:left;">Ном</th>
            <th style="padding:10px 12px;">Зээлсэн</th>
            <th style="padding:10px 12px;">Огноо</th>
            <th style="padding:10px 12px;">Буцаах огноо</th>
            <th style="padding:10px 12px;">Буцаасан</th>
            <th style="padding:10px 12px;">Статус</th>
            <?php if ($isManager): ?><th style="padding:10px 12px;">Үйлдэл</th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($loans)): ?>
    <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);">Зээлийн бүртгэл байхгүй.</td></tr>
    <?php endif; ?>
    <?php foreach ($loans as $loan):
        $overdue = $loan['status'] === 'borrowed' && $loan['days_left'] < 0;
    ?>
    <tr style="border-bottom:1px solid var(--border); <?= $overdue ? 'background:#fef2f2;' : '' ?>">
        <td style="padding:10px 12px;">
            <strong><?= h($loan['title']) ?></strong>
            <div style="font-size:11px; color:var(--muted);"><?= h($loan['category'] ?? '') ?></div>
        </td>
        <td style="padding:10px 12px;"><?= h($loan['borrower_name']) ?></td>
        <td style="padding:10px 12px;"><?= mnDate($loan['loan_date']) ?></td>
        <td style="padding:10px 12px; <?= $overdue ? 'color:#dc2626; font-weight:700;' : '' ?>">
            <?= mnDate($loan['due_date']) ?>
            <?php if ($overdue): ?>
            <div style="font-size:11px; color:#dc2626;">⚠️ <?= abs($loan['days_left']) ?> хоног хэтэрсэн</div>
            <?php elseif ($loan['status'] === 'borrowed'): ?>
            <div style="font-size:11px; color:var(--muted);"><?= $loan['days_left'] ?> хоног үлдсэн</div>
            <?php endif; ?>
        </td>
        <td style="padding:10px 12px; color:var(--muted);"><?= $loan['return_date'] ? mnDate($loan['return_date']) : '-' ?></td>
        <td style="padding:10px 12px;">
            <?php
            $statusColor = ['borrowed'=>'#f59e0b','returned'=>'#10b981','overdue'=>'#ef4444'];
            $statusLabel = ['borrowed'=>'📤 Зээлд','returned'=>'✅ Буцаасан','overdue'=>'⚠️ Хэтэрсэн'];
            $st = $overdue ? 'overdue' : $loan['status'];
            ?>
            <span style="background:<?= $statusColor[$st] ?? '#9ca3af' ?>22; color:<?= $statusColor[$st] ?? '#9ca3af' ?>; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:700;">
                <?= $statusLabel[$st] ?? $loan['status'] ?>
            </span>
            <?php if ($loan['fine_amount'] > 0): ?>
            <div style="font-size:11px; color:#ef4444; margin-top:3px;">Торгуул: <?= mnMoney($loan['fine_amount']) ?></div>
            <?php endif; ?>
        </td>
        <?php if ($isManager): ?>
        <td style="padding:10px 12px;">
            <?php if ($loan['status'] === 'borrowed'): ?>
            <form method="POST" onsubmit="return confirm('Буцаах уу?')">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="return_book">
                <input type="hidden" name="loan_id" value="<?= $loan['loan_id'] ?>">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="fas fa-undo"></i> Буцаах</button>
            </form>
            <?php endif; ?>
        </td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php elseif ($activeTab === 'add' && $isManager): ?>
<!-- ═══ НОМ НЭМЭХ ═══ -->
<div style="background:var(--card-bg); border:1px solid var(--border); border-radius:14px; padding:24px; max-width:700px;">
    <h3 style="font-size:16px; font-weight:700; margin-bottom:20px;"><i class="fas fa-plus" style="color:var(--primary)"></i> Шинэ ном нэмэх</h3>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="add_book">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
            <div class="form-group" style="margin:0; grid-column:1/-1;"><label>Номын нэр <span style="color:#e53e3e">*</span></label><input type="text" name="title" class="form-control" required></div>
            <div class="form-group" style="margin:0;"><label>Зохиолч</label><input type="text" name="author" class="form-control"></div>
            <div class="form-group" style="margin:0;"><label>ISBN</label><input type="text" name="isbn" class="form-control"></div>
            <div class="form-group" style="margin:0;"><label>Хэвлэлийн газар</label><input type="text" name="publisher" class="form-control"></div>
            <div class="form-group" style="margin:0;"><label>Хэвлэгдсэн он</label><input type="number" name="pub_year" class="form-control" min="1900" max="<?= date('Y') ?>" placeholder="<?= date('Y') ?>"></div>
            <div class="form-group" style="margin:0;"><label>Ангилал</label>
                <select name="category" class="form-control">
                    <option value="">-- Сонгох --</option>
                    <?php foreach(['Монгол хэл','Математик','Байгалийн ухаан','Физик','Хими','Биологи','Түүх','Газар зүй','Нийгэм','Бусад'] as $c): ?>
                    <option value="<?= $c ?>"><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;"><label>Хувийн тоо <span style="color:#e53e3e">*</span></label><input type="number" name="total_copies" class="form-control" min="1" value="1" required></div>
            <div class="form-group" style="margin:0; grid-column:1/-1;"><label>Хавтас зураг (URL)</label><input type="url" name="cover_url" class="form-control" placeholder="https://example.com/book-cover.jpg"></div>
        </div>
        <div class="form-group"><label>Тайлбар</label><textarea name="description" class="form-control" rows="3"></textarea></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Ном нэмэх</button>
    </form>
</div>
<?php endif; ?>

<!-- Зээлэх Modal -->
<?php if ($isManager): ?>
<div id="borrowModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--card-bg); border-radius:16px; padding:28px; max-width:480px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="margin-bottom:20px; font-size:16px; font-weight:700;"><i class="fas fa-hand-holding-heart" style="color:var(--primary)"></i> Ном зээлэх</h3>
        <div id="borrowBookTitle" style="font-weight:600; color:var(--primary); margin-bottom:16px; font-size:14px;"></div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="borrow">
            <input type="hidden" name="book_id" id="borrowBookId">
            <div class="form-group">
                <label>Зээлэх хэрэглэгч <span style="color:#e53e3e">*</span></label>
                <select name="borrower_id" class="form-control" required>
                    <option value="">-- Сонгох --</option>
                    <?php
                    $lastRole = '';
                    foreach ($allStudents as $u):
                        if ($u['role_name'] !== $lastRole) {
                            if ($lastRole) echo '</optgroup>';
                            $roleN = ['student'=>'Сурагч','teacher'=>'Багш','parent'=>'Эцэг эх','manager'=>'Менежер','admin'=>'Админ'];
                            echo '<optgroup label="' . ($roleN[$u['role_name']] ?? $u['role_name']) . '">';
                            $lastRole = $u['role_name'];
                        }
                    ?>
                    <option value="<?= $u['user_id'] ?>"><?= h($u['full_name']) ?><?= $u['class_name'] ? ' (' . h($u['class_name']) . ')' : '' ?></option>
                    <?php endforeach; if ($lastRole) echo '</optgroup>'; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Буцаах огноо <span style="color:#e53e3e">*</span></label>
                <input type="date" name="due_date" class="form-control" required
                       value="<?= date('Y-m-d', strtotime('+14 days')) ?>"
                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
            </div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Зээлэх</button>
                <button type="button" class="btn btn-secondary" onclick="closeBorrow()">Болих</button>
            </div>
        </form>
    </div>
</div>
<script>
function openBorrow(id, title) {
    document.getElementById('borrowBookId').value = id;
    document.getElementById('borrowBookTitle').textContent = '📚 ' + title;
    document.getElementById('borrowModal').style.display = 'flex';
}
function closeBorrow() {
    document.getElementById('borrowModal').style.display = 'none';
}
document.getElementById('borrowModal').addEventListener('click', function(e) {
    if (e.target === this) closeBorrow();
});
</script>
<?php endif; ?>

<style>
.count-badge {
    display:inline-flex; align-items:center; justify-content:center;
    width:18px; height:18px; background:#e53e3e; color:#fff;
    border-radius:50%; font-size:10px; font-weight:700; margin-left:4px;
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
