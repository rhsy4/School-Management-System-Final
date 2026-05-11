<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Алдрын Танхим';
include __DIR__ . '/../../includes/header.php';

// Хамгийн өндөр урамшууллын оноотой шилдэг 10 сурагч (Merit Board)
$topStudents = dbQuery("SELECT s.student_id, CONCAT(s.last_name, ' ', s.first_name) AS full_name, c.class_name, s.merit_points
                        FROM students s
                        JOIN classes c ON s.class_id = c.class_id
                        WHERE s.is_active = 1
                        ORDER BY s.merit_points DESC LIMIT 10");
?>

<div class="card" style="background: linear-gradient(135deg, #4f46e5, #9333ea); color:#fff; border:none; margin-bottom: 20px; overflow:hidden; position:relative;">
    <div style="position:absolute; top:-20px; right:-20px; font-size:150px; opacity:0.1; color:#fff;"><i class="fas fa-trophy"></i></div>
    <div class="card-body" style="text-align: center; padding: 40px 20px; position:relative; z-index:1;">
        <h1 style="font-size: 32px; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.3); margin-bottom:10px;">Алдрын Танхим</h1>
        <p style="opacity: 0.9; font-size: 16px; margin-bottom:30px;">Сургуулийн хамгийн өндөр урамшууллын оноотой шилдэг сурагчид</p>
        
        <!-- TOP 3 PODIUM -->
        <div style="display:flex; justify-content:center; align-items:flex-end; gap:20px; margin-top:20px;">
            <?php 
            // Podium order: 2, 1, 3
            $podiumIndices = [1, 0, 2];
            foreach($podiumIndices as $rankIdx):
                if(isset($topStudents[$rankIdx])):
                    $stu = $topStudents[$rankIdx];
                    $isFirst = ($rankIdx == 0);
                    $h = $isFirst ? '140px' : '100px';
                    $color = $rankIdx == 0 ? '#fbbf24' : ($rankIdx == 1 ? '#e5e7eb' : '#d97706');
            ?>
            <div style="display:flex; flex-direction:column; align-items:center;">
                <div style="width:60px; height:60px; border-radius:50%; background:rgba(255,255,255,0.2); border:3px solid <?= $color ?>; display:flex; align-items:center; justify-content:center; margin-bottom:10px; font-weight:bold; font-size:24px;">
                    <?= mb_substr($stu['full_name'],0,1) ?>
                </div>
                <div style="width:80px; height:<?= $h ?>; background:linear-gradient(to top, rgba(255,255,255,0.2), rgba(255,255,255,0.05)); border:1px solid rgba(255,255,255,0.1); border-radius:10px 10px 0 0; display:flex; flex-direction:column; justify-content:center; padding:10px;">
                    <span style="font-size:20px; font-weight:bold; color:<?= $color ?>;">#<?= $rankIdx+1 ?></span>
                    <div style="font-size:10px; margin-top:5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= h($stu['full_name']) ?></div>
                    <div style="font-size:12px; font-weight:bold;"><?= $stu['merit_points'] ?></div>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <?php if(empty($topStudents)): ?>
            <p style="padding:20px; text-align:center;">Мэдээлэл олдсонгүй.</p>
        <?php else: ?>
            <table style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="background:var(--bg); border-bottom:1px solid var(--border);">
                        <th style="padding:15px; text-align:center; width:60px;">Байр</th>
                        <th style="padding:15px; text-align:left;">Сурагч</th>
                        <th style="padding:15px; text-align:left;">Анги</th>
                        <th style="padding:15px; text-align:center;">Оноо</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($topStudents as $index => $stu): 
                        $rank = $index + 1;
                        $medalConf = '';
                        if($rank == 1) $medalConf = '<i class="fas fa-medal" style="color:#fbbf24; font-size:24px; text-shadow:0 2px 4px rgba(0,0,0,0.2);"></i>';      // Alt
                        else if($rank == 2) $medalConf = '<i class="fas fa-medal" style="color:#9ca3af; font-size:24px; text-shadow:0 2px 4px rgba(0,0,0,0.2);"></i>'; // Mungu
                        else if($rank == 3) $medalConf = '<i class="fas fa-medal" style="color:#b45309; font-size:24px; text-shadow:0 2px 4px rgba(0,0,0,0.2);"></i>'; // Khurel
                        else $medalConf = '<span style="font-weight:bold; color:var(--muted); font-size:18px;">'.$rank.'</span>';
                    ?>
                    <tr style="border-bottom:1px solid var(--border); <?= $rank<=3 ? 'background:rgba(251,191,36,0.05);' : '' ?>">
                        <td style="padding:15px; text-align:center;"><?= $medalConf ?></td>
                        <td style="padding:15px;">
                            <div style="font-weight:600; font-size: 16px; <?= $rank==1 ? 'color:var(--primary);' : '' ?>"><?= h($stu['full_name']) ?></div>
                        </td>
                        <td style="padding:15px; color:var(--muted);"><?= h($stu['class_name']) ?></td>
                        <td style="padding:15px; text-align:center;">
                            <span class="badge" style="background:var(--primary); color:#fff; font-size:14px; padding:6px 12px; border-radius:20px;">
                                <i class="fas fa-star" style="color:#fbbf24; margin-right:5px;"></i><?= h($stu['merit_points']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<style>
    tbody tr:hover { background: var(--bg) !important; transition: background 0.3s ease; }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
