<?php
/**
 * Notification System - Email & SMS
 * Сургуулийн системд мэдэгдэл илгээх
 */

require_once __DIR__ . '/config.php';

class NotificationService {
    private $db;
    private $emailFrom = 'school@localhost';
    private $smtpConfig = [];
    
    public function __construct() {
        $this->db = getDB();
        $this->smtpConfig = [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => getenv('SMTP_USER'),
            'password' => getenv('SMTP_PASS'),
        ];
    }
    
    /**
     * Email илгээх
     */
    public function sendEmail($to, $subject, $body, $isHtml = true) {
        // Sanitize to prevent header injection
        $to = filter_var($to, FILTER_VALIDATE_EMAIL);
        if (!$to) return ['success' => false, 'error' => 'Имэйл хаяг буруу байна'];
        $subject = str_replace(["\r", "\n"], '', $subject);
        
        try {
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: " . ($isHtml ? "text/html" : "text/plain") . "; charset=UTF-8\r\n";
            $headers .= "From: " . $this->emailFrom . "\r\n";
            $headers .= "X-Priority: 1\r\n";
            
            // mail() функцийг ашиглах (SMTP configure-д хэрэгтэй)
            $result = mail($to, $subject, $body, $headers);
            
            if ($result) {
                $this->logNotification('email', $to, $subject, 'sent');
                return ['success' => true];
            } else {
                $this->logNotification('email', $to, $subject, 'failed');
                return ['success' => false, 'error' => 'Email илгээлт алдаа'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * SMS илгээх (Twilio API ашиглах)
     */
    public function sendSMS($phone, $message) {
        try {
            $twilioSid = getenv('TWILIO_SID');
            $twilioAuth = getenv('TWILIO_AUTH_TOKEN');
            $twilioFrom = getenv('TWILIO_FROM');
            
            if (!$twilioSid || !$twilioAuth) {
                return ['success' => false, 'error' => 'SMS config байхгүй'];
            }
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.twilio.com/2010-04-01/Accounts/$twilioSid/Messages.json",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => http_build_query([
                    'From' => $twilioFrom,
                    'To' => $phone,
                    'Body' => $message,
                ]),
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => "$twilioSid:$twilioAuth",
            ]);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            $result = json_decode($response, true);
            
            if (isset($result['sid'])) {
                $this->logNotification('sms', $phone, $message, 'sent');
                return ['success' => true, 'sid' => $result['sid']];
            } else {
                $this->logNotification('sms', $phone, $message, 'failed');
                return ['success' => false, 'error' => 'SMS илгээлт алдаа'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Массын Email илгээх (Эцэг эхүүдэд)
     */
    public function sendBulkEmail($recipientType, $subject, $body, $filters = []) {
        try {
            $recipients = [];
            
            if ($recipientType === 'parents') {
                // Бүх эцэг эхүүдэд
                $sql = "SELECT DISTINCT u.email FROM users u 
                        JOIN parents p ON u.user_id=p.user_id 
                        WHERE u.email IS NOT NULL";
                $recipients = $this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
            } else if ($recipientType === 'teachers') {
                // Бүх багшид
                $sql = "SELECT DISTINCT u.email FROM users u 
                        JOIN teachers t ON u.user_id=t.user_id 
                        WHERE u.email IS NOT NULL";
                $recipients = $this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
            } else if ($recipientType === 'class') {
                // Тодорхой ангийн эцэг эхүүдэд
                $classId = $filters['class_id'] ?? null;
                if ($classId) {
                    $sql = "SELECT DISTINCT u.email FROM users u 
                            JOIN parents p ON u.user_id=p.user_id 
                            JOIN students s ON p.parent_id=s.parent_id 
                            WHERE s.class_id=? AND u.email IS NOT NULL";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$classId]);
                    $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                }
            }
            
            $sent = 0;
            $failed = 0;
            
            foreach ($recipients as $email) {
                $result = $this->sendEmail($email, $subject, $body);
                if ($result['success']) {
                    $sent++;
                } else {
                    $failed++;
                }
            }
            
            return ['success' => true, 'sent' => $sent, 'failed' => $failed];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Автомат сурлага тааруу сурагчдад сэрэмжлүүлэг илгээх
     */
    public function notifyLowGradeStudents($gradeThreshold = 60) {
        try {
            // Дутуу оноотой сурагчдыг олох
            $sql = "SELECT DISTINCT s.student_id, s.user_id, CONCAT(s.last_name, ' ', s.first_name) as name, 
                        u.email, p.user_id as parent_user_id
                 FROM students s
                 JOIN users u ON s.user_id=u.user_id
                 LEFT JOIN parents p ON s.parent_id=p.parent_id
                 WHERE s.student_id IN (
                    SELECT student_id FROM grades 
                    WHERE grade_value < ? 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY student_id
                 )";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$gradeThreshold]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $notified = 0;
            
            foreach ($students as $student) {
                // Сурагчид имэйл
                $studentBody = "Таны сүүлийн дүн {$gradeThreshold}-аас доор байна. Багштайгаа уулзахыг хүсэж байна.";
                $this->sendEmail($student['email'], "Сурлагын анхааруулга", $studentBody);
                
                // Эцэг эхүүдэд
                if ($student['parent_user_id']) {
                    $stmt = $this->db->prepare("SELECT email FROM users WHERE user_id=?");
                    $stmt->execute([$student['parent_user_id']]);
                    $parentEmail = $stmt->fetchColumn();
                    
                    if ($parentEmail) {
                        $parentBody = "{$student['name']}-ын сүүлийн оноо {$gradeThreshold}-аас доор байна.";
                        $this->sendEmail($parentEmail, "Сурагчийн дүнгийн сэрэмжлүүлэг", $parentBody);
                    }
                }
                
                $notified++;
            }
            
            return ['success' => true, 'notified' => $notified];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Мэдэгдэнэ лог хадгалах
     */
    private function logNotification($type, $recipient, $content, $status) {
        try {
            $this->db->prepare(
                "INSERT INTO notification_logs (type, recipient, content, status, created_at) 
                 VALUES (?, ?, ?, ?, NOW())"
            )->execute([$type, $recipient, $content, $status]);
        } catch (Exception $e) {
            // Логирование алдаа (чимээгүй)
        }
    }
    
    /**
     * Төлбөрийн мэдэгдлийг сарын 1-нд илгээх
     */
    public function sendMonthlyPaymentReminder() {
        $unpaid = $this->db->query(
            "SELECT DISTINCT p.user_id, u.email, CONCAT(s.last_name, ' ', s.first_name) as student_name
             FROM tuition t
             JOIN students s ON t.student_id=s.student_id
             JOIN parents p ON s.parent_id=p.parent_id
             JOIN users u ON p.user_id=u.user_id
             WHERE t.status='unpaid' AND u.email IS NOT NULL"
        )->fetchAll(PDO::FETCH_ASSOC);
        
        $sent = 0;
        
        foreach ($unpaid as $parent) {
            $subject = "Төлбөрийн сануулга";
            $body = "<h2>Төлбөрийн сануулга</h2>
                    <p>{$parent['student_name']}-үүдийн төлөгдөөгүй төлбөр байна.</p>
                    <p><a href='http://localhost/school_system1/pages/payments/'>Төлбөр төлөх</a></p>";
            
            if ($this->sendEmail($parent['email'], $subject, $body)['success']) {
                $sent++;
            }
        }
        
        return ['success' => true, 'sent' => $sent];
    }

    // ═══════════════════════════════════════════════════════════════
    // 1. TUITION OVERDUE — due_date өнгөрсөн, unpaid сурагчдын
    //    эцэг эхэд email сануулга илгээх
    // ═══════════════════════════════════════════════════════════════
    /**
     * Төлбөрийн хугацаа хэтэрсэн (due_date < TODAY, status='unpaid')
     * сурагчдын эцэг эхийн email-рүү сануулга илгээх.
     *
     * @return array ['success'=>bool, 'sent'=>int, 'details'=>array]
     */
    public function sendOverdueTuitionReminders(): array {
        try {
            $sql = "SELECT t.tuition_id, t.student_id, t.amount, t.due_date,
                           DATEDIFF(CURDATE(), t.due_date) AS overdue_days,
                           CONCAT(s.last_name, ' ', s.first_name) AS student_name,
                           s.parent_id,
                           pu.email AS parent_email,
                           pu.full_name AS parent_name
                    FROM tuition t
                    JOIN students s ON t.student_id = s.student_id
                    LEFT JOIN users pu ON s.parent_id = pu.user_id
                    WHERE t.status = 'unpaid'
                      AND t.due_date < CURDATE()
                      AND s.parent_id IS NOT NULL
                      AND pu.email IS NOT NULL
                      AND pu.email != ''
                    ORDER BY t.due_date ASC";

            $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            $sent = 0;
            $details = [];

            foreach ($rows as $row) {
                $amount = number_format((float)$row['amount'], 0, '.', ',');
                $dueDate = date('Y оны m сарын d', strtotime($row['due_date']));
                $overdueDays = (int)$row['overdue_days'];

                $subject = "⚠️ Төлбөрийн сануулга — {$row['student_name']}";
                $body = "
                    <div style='font-family:Inter,Arial,sans-serif; max-width:600px; margin:0 auto;'>
                        <div style='background:linear-gradient(135deg,#6366f1,#4f46e5); color:#fff; padding:30px; border-radius:16px 16px 0 0;'>
                            <h2 style='margin:0;'>⚠️ Төлбөрийн сануулга</h2>
                            <p style='margin:8px 0 0; opacity:0.9;'>Цахим Сургуулийн Систем</p>
                        </div>
                        <div style='background:#fff; padding:30px; border:1px solid #e2e8f0; border-top:none; border-radius:0 0 16px 16px;'>
                            <p>Эрхэм <strong>{$row['parent_name']}</strong>,</p>
                            <p>Таны хүүхэд <strong>{$row['student_name']}</strong>-ын дараах төлбөр хугацаа хэтэрсэн байна:</p>
                            <table style='width:100%; border-collapse:collapse; margin:20px 0;'>
                                <tr style='background:#f8fafc;'>
                                    <td style='padding:12px; border:1px solid #e2e8f0; font-weight:600;'>Төлбөрийн дүн</td>
                                    <td style='padding:12px; border:1px solid #e2e8f0; color:#ef4444; font-weight:700;'>₮{$amount}</td>
                                </tr>
                                <tr>
                                    <td style='padding:12px; border:1px solid #e2e8f0; font-weight:600;'>Төлөх хугацаа</td>
                                    <td style='padding:12px; border:1px solid #e2e8f0;'>{$dueDate}</td>
                                </tr>
                                <tr style='background:#fef2f2;'>
                                    <td style='padding:12px; border:1px solid #e2e8f0; font-weight:600;'>Хоцорсон хоног</td>
                                    <td style='padding:12px; border:1px solid #e2e8f0; color:#ef4444; font-weight:700;'>{$overdueDays} хоног</td>
                                </tr>
                            </table>
                            <p>Төлбөрөө аль болох хурдан төлнө үү.</p>
                            <a href='" . SITE_URL . "/pages/payments/' style='display:inline-block; background:#6366f1; color:#fff; padding:12px 24px; border-radius:8px; text-decoration:none; font-weight:600;'>💳 Төлбөр төлөх</a>
                        </div>
                    </div>";

                $result = $this->sendEmail($row['parent_email'], $subject, $body);

                // Системийн дотоод мессеж мөн илгээх
                if (function_exists('sendSmartAlert') && $row['parent_id']) {
                    sendSmartAlert(
                        (int)$row['parent_id'],
                        "⚠️ {$row['student_name']}-ын ₮{$amount} төлбөр {$overdueDays} хоногийн хугацаа хэтэрсэн байна. Төлбөрөө төлнө үү."
                    );
                }

                if ($result['success']) {
                    $sent++;
                }

                $details[] = [
                    'student'  => $row['student_name'],
                    'email'    => $row['parent_email'],
                    'amount'   => $row['amount'],
                    'overdue'  => $overdueDays,
                    'sent'     => $result['success'],
                ];
            }

            return ['success' => true, 'sent' => $sent, 'total' => count($rows), 'details' => $details];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // 2. ATTENDANCE ABSENT — Тасалсан сурагчийн эцэг эхэд email
    // ═══════════════════════════════════════════════════════════════
    /**
     * Тухайн сурагч тасалсан бол (status_id=2) эцэг эхийн email рүү
     * мэдэгдэл илгээх.
     *
     * @param int    $studentId  Сурагчийн ID
     * @param int    $subjectId  Хичээлийн ID
     * @param string $date       Огноо (Y-m-d)
     * @return array ['success'=>bool, 'email_sent'=>bool]
     */
    public function notifyAbsentStudentParents(int $studentId, int $subjectId, string $date): array {
        try {
            // Сурагчийн мэдээлэл + эцэг эхийн email авах
            $sql = "SELECT s.student_id, s.parent_id,
                           CONCAT(s.last_name, ' ', s.first_name) AS student_name,
                           sub.subject_name,
                           pu.email AS parent_email,
                           pu.full_name AS parent_name
                    FROM students s
                    JOIN subjects sub ON sub.subject_id = ?
                    LEFT JOIN users pu ON s.parent_id = pu.user_id
                    WHERE s.student_id = ?";

            $info = $this->db->prepare($sql);
            $info->execute([$subjectId, $studentId]);
            $row = $info->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return ['success' => false, 'error' => 'Сурагч олдсонгүй'];
            }

            $emailSent = false;
            $formattedDate = date('Y оны m сарын d', strtotime($date));

            if ($row['parent_email']) {
                $subject = "🔴 Таслалтын мэдэгдэл — {$row['student_name']}";
                $body = "
                    <div style='font-family:Inter,Arial,sans-serif; max-width:600px; margin:0 auto;'>
                        <div style='background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; padding:30px; border-radius:16px 16px 0 0;'>
                            <h2 style='margin:0;'>🔴 Таслалтын мэдэгдэл</h2>
                            <p style='margin:8px 0 0; opacity:0.9;'>Цахим Сургуулийн Систем</p>
                        </div>
                        <div style='background:#fff; padding:30px; border:1px solid #e2e8f0; border-top:none; border-radius:0 0 16px 16px;'>
                            <p>Эрхэм <strong>{$row['parent_name']}</strong>,</p>
                            <p>Таны хүүхэд <strong>{$row['student_name']}</strong> дараах хичээлийг тасалсан байна:</p>
                            <div style='background:#fef2f2; border:1px solid #fecaca; border-radius:12px; padding:20px; margin:20px 0;'>
                                <div style='display:flex; gap:15px; align-items:center;'>
                                    <div style='font-size:36px;'>📚</div>
                                    <div>
                                        <div style='font-weight:700; font-size:16px; color:#1e293b;'>{$row['subject_name']}</div>
                                        <div style='color:#64748b; font-size:14px;'>📅 {$formattedDate}</div>
                                    </div>
                                </div>
                            </div>
                            <p style='color:#64748b;'>Хэрэв хүүхэд тань хүндэтгэх шалтгаантай байсан бол ангийн багштай холбогдоно уу.</p>
                            <a href='" . SITE_URL . "/pages/attendance/' style='display:inline-block; background:#6366f1; color:#fff; padding:12px 24px; border-radius:8px; text-decoration:none; font-weight:600;'>📋 Ирцийн бүртгэл харах</a>
                        </div>
                    </div>";

                $result = $this->sendEmail($row['parent_email'], $subject, $body);
                $emailSent = $result['success'];
            }

            // Системийн мессеж
            if (function_exists('sendSmartAlert') && $row['parent_id']) {
                sendSmartAlert(
                    (int)$row['parent_id'],
                    "🔴 Таны хүүхэд {$row['student_name']} {$formattedDate}-ны {$row['subject_name']} хичээлийг тасалсан байна."
                );
            }

            return ['success' => true, 'email_sent' => $emailSent];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // 4. WELCOME EMAIL — Шинэ хэрэглэгчид нэвтрэх мэдээлэл
    // ═══════════════════════════════════════════════════════════════
    /**
     * Шинэ хэрэглэгч үүссэн үед нэвтрэх мэдээллийг email-ээр илгээх.
     *
     * @param int    $userId        Шинэ хэрэглэгчийн user_id
     * @param string $username      Нэвтрэх нэр
     * @param string $plainPassword Нууц үг (plain text — бүртгэлийн үед л мэдэгдэнэ)
     * @return array ['success'=>bool]
     */
    public function sendWelcomeEmail(int $userId, string $username, string $plainPassword): array {
        try {
            $user = $this->db->prepare("SELECT email, full_name, role_id FROM users WHERE user_id = ?");
            $user->execute([$userId]);
            $userData = $user->fetch(PDO::FETCH_ASSOC);

            if (!$userData || !$userData['email']) {
                return ['success' => false, 'error' => 'Email хаяг олдсонгүй'];
            }

            $roleName = match((int)$userData['role_id']) {
                1 => 'Админ',
                2 => 'Менежер',
                3 => 'Багш',
                4 => 'Сурагч',
                5 => 'Эцэг эх',
                6 => 'Захирал',
                default => 'Хэрэглэгч',
            };

            $loginUrl = SITE_URL . '/index.php';

            $subject = "🎉 Тавтай морил — Нэвтрэх мэдээлэл";
            $body = "
                <div style='font-family:Inter,Arial,sans-serif; max-width:600px; margin:0 auto;'>
                    <div style='background:linear-gradient(135deg,#10b981,#059669); color:#fff; padding:30px; border-radius:16px 16px 0 0;'>
                        <h2 style='margin:0;'>🎉 Тавтай морил!</h2>
                        <p style='margin:8px 0 0; opacity:0.9;'>Цахим Сургуулийн Систем</p>
                    </div>
                    <div style='background:#fff; padding:30px; border:1px solid #e2e8f0; border-top:none; border-radius:0 0 16px 16px;'>
                        <p>Эрхэм <strong>{$userData['full_name']}</strong>,</p>
                        <p>Таны бүртгэл амжилттай баталгаажлаа! Доорх мэдээллийг ашиглан системд нэвтэрнэ үү:</p>
                        <div style='background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:24px; margin:20px 0;'>
                            <table style='width:100%; border-collapse:collapse;'>
                                <tr>
                                    <td style='padding:8px 0; font-weight:600; color:#64748b; width:140px;'>👤 Эрх:</td>
                                    <td style='padding:8px 0; font-weight:700; color:#059669;'>{$roleName}</td>
                                </tr>
                                <tr>
                                    <td style='padding:8px 0; font-weight:600; color:#64748b;'>🔑 Нэвтрэх нэр:</td>
                                    <td style='padding:8px 0; font-weight:700; color:#1e293b;'>{$username}</td>
                                </tr>
                                <tr>
                                    <td style='padding:8px 0; font-weight:600; color:#64748b;'>🔒 Нууц үг:</td>
                                    <td style='padding:8px 0; font-weight:700; color:#1e293b;'>{$plainPassword}</td>
                                </tr>
                            </table>
                        </div>
                        <div style='background:#fef3c7; border:1px solid #fcd34d; border-radius:8px; padding:12px; margin:15px 0; font-size:13px; color:#92400e;'>
                            ⚠️ Анхааруулга: Нэвтэрсний дараа нууц үгээ заавал солино уу!
                        </div>
                        <a href='{$loginUrl}' style='display:inline-block; background:#6366f1; color:#fff; padding:14px 28px; border-radius:8px; text-decoration:none; font-weight:600; margin-top:10px;'>🚀 Системд нэвтрэх</a>
                    </div>
                </div>";

            $result = $this->sendEmail($userData['email'], $subject, $body);
            return $result;
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

// ═══════════════════════════════════════════════════════════════════
// 5. SCHEDULE CONFLICT CHECK — Хуваарийн мөргөлдөөн шалгах функц
// ═══════════════════════════════════════════════════════════════════
/**
 * Хуваарь хадгалахаас өмнө багш, анги, өрөөний давхцал шалгах.
 *
 * @param int    $teacherId   Багшийн user_id
 * @param int    $classId     Ангийн ID
 * @param string $room        Өрөөний нэр
 * @param int    $dayOfWeek   Гарагийн дугаар (1=Даваа ... 5=Баасан)
 * @param string $startTime   Эхлэх цаг (HH:MM эсвэл HH:MM:SS)
 * @param string $endTime     Дуусах цаг (HH:MM эсвэл HH:MM:SS)
 * @param int|null $excludeId Засварлах үед өөрийгөө хасах schedule_id
 * @return array|null null=давхцалгүй, эсвэл ['type'=>string, 'message'=>string, 'conflict'=>array]
 */
function checkScheduleConflict(
    int $teacherId,
    int $classId,
    string $room,
    int $dayOfWeek,
    string $startTime,
    string $endTime,
    ?int $excludeId = null
): ?array {
    // MySQL цагийн формат нэгтгэх (HH:MM → HH:MM:SS)
    $startDb = (strlen($startTime) === 5) ? $startTime . ':00' : $startTime;
    $endDb   = (strlen($endTime) === 5)   ? $endTime . ':00'   : $endTime;

    $excludeClause = $excludeId ? " AND schedule_id != ?" : "";
    $baseParams = function(array $mainParams) use ($excludeId) {
        return $excludeId ? array_merge($mainParams, [$excludeId]) : $mainParams;
    };

    // ── 1. Багш давхцал ──────────────────────────────────────────
    $sql = "SELECT sc.*, sub.subject_name, c.class_name
            FROM schedule sc
            JOIN subjects sub ON sc.subject_id = sub.subject_id
            JOIN classes c ON sc.class_id = c.class_id
            WHERE sc.teacher_id = ?
              AND sc.day_of_week = ?
              AND NOT (sc.end_time <= ? OR sc.start_time >= ?)
              {$excludeClause}
            LIMIT 1";
    $conflict = dbOne($sql, $baseParams([$teacherId, $dayOfWeek, $startDb, $endDb]));
    if ($conflict) {
        return [
            'type'     => 'teacher',
            'message'  => "⚠️ Багшийн давхцал! Сонгосон багш тухайн цагт \"{$conflict['class_name']}\" ангид \"{$conflict['subject_name']}\" хичээл заадаг.",
            'conflict' => $conflict,
        ];
    }

    // ── 2. Анги давхцал ──────────────────────────────────────────
    $sql = "SELECT sc.*, sub.subject_name,
                   CONCAT(t.last_name, ' ', t.first_name) AS teacher_name
            FROM schedule sc
            JOIN subjects sub ON sc.subject_id = sub.subject_id
            JOIN teachers t ON sc.teacher_id = t.user_id
            WHERE sc.class_id = ?
              AND sc.day_of_week = ?
              AND NOT (sc.end_time <= ? OR sc.start_time >= ?)
              {$excludeClause}
            LIMIT 1";
    $conflict = dbOne($sql, $baseParams([$classId, $dayOfWeek, $startDb, $endDb]));
    if ($conflict) {
        return [
            'type'     => 'class',
            'message'  => "⚠️ Ангийн давхцал! Энэ анги тухайн цагт \"{$conflict['subject_name']}\" хичээлтэй (Багш: {$conflict['teacher_name']}).",
            'conflict' => $conflict,
        ];
    }

    // ── 3. Өрөө давхцал ─────────────────────────────────────────
    if ($room && trim($room) !== '') {
        $sql = "SELECT sc.*, sub.subject_name, c.class_name,
                       CONCAT(t.last_name, ' ', t.first_name) AS teacher_name
                FROM schedule sc
                JOIN subjects sub ON sc.subject_id = sub.subject_id
                JOIN classes c ON sc.class_id = c.class_id
                JOIN teachers t ON sc.teacher_id = t.user_id
                WHERE sc.room = ?
                  AND sc.day_of_week = ?
                  AND NOT (sc.end_time <= ? OR sc.start_time >= ?)
                  {$excludeClause}
                LIMIT 1";
        $conflict = dbOne($sql, $baseParams([$room, $dayOfWeek, $startDb, $endDb]));
        if ($conflict) {
            return [
                'type'     => 'room',
                'message'  => "⚠️ Өрөөний давхцал! \"{$room}\" өрөөнд тухайн цагт \"{$conflict['class_name']}\" ангийн \"{$conflict['subject_name']}\" хичээл орсон байна.",
                'conflict' => $conflict,
            ];
        }
    }

    return null; // Давхцал байхгүй ✅
}

// ═══════════════════════════════════════════════════════════════
// 📚 Номын зээлийн хугацаа хэтэрсэн сануулга
// ═══════════════════════════════════════════════════════════════

/**
 * library_loans хүснэгтээс due_date өнгөрсөн, status='borrowed'
 * номуудыг олж borrower_id-аар хэрэглэгчийн email рүү сануулга илгээх.
 */
function sendOverdueLibraryReminders(): array {
    $overdue = dbQuery("
        SELECT l.loan_id, l.due_date, l.borrower_id,
               b.title AS book_title, b.author,
               u.email, u.full_name,
               DATEDIFF(CURDATE(), l.due_date) AS overdue_days
        FROM library_loans l
        JOIN library_books b ON l.book_id = b.book_id
        JOIN users u ON l.borrower_id = u.user_id
        WHERE l.status = 'borrowed'
          AND l.due_date < CURDATE()
        ORDER BY l.due_date ASC
    ");

    if (empty($overdue)) {
        return ['success' => true, 'sent' => 0, 'total' => 0, 'details' => []];
    }

    $sent = 0;
    $details = [];

    foreach ($overdue as $row) {
        $emailSent = false;
        $overdueDays = (int)$row['overdue_days'];
        $fine = $overdueDays * 500; // Өдөрт 500₮ торгуул
        $formattedDue = date('Y оны m сарын d', strtotime($row['due_date']));

        if ($row['email']) {
            $subject = "📚 Номын буцаалтын сануулга — {$row['book_title']}";
            $body = "
                <div style='font-family:Inter,Arial,sans-serif; max-width:600px; margin:0 auto;'>
                    <div style='background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; padding:30px; border-radius:16px 16px 0 0;'>
                        <h2 style='margin:0;'>📚 Номын буцаалтын сануулга</h2>
                        <p style='margin:8px 0 0; opacity:0.9;'>Цахим Сургуулийн Систем — Номын сан</p>
                    </div>
                    <div style='background:#fff; padding:30px; border:1px solid #e2e8f0; border-top:none; border-radius:0 0 16px 16px;'>
                        <p>Эрхэм <strong>{$row['full_name']}</strong>,</p>
                        <p>Таны зээлсэн дараах номын буцаах хугацаа хэтэрсэн байна:</p>
                        <div style='background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:20px; margin:20px 0;'>
                            <div style='font-weight:700; font-size:16px; color:#1e293b;'>📖 {$row['book_title']}</div>
                            <div style='color:#64748b; font-size:14px; margin-top:6px;'>✍️ {$row['author']}</div>
                            <div style='margin-top:12px; display:flex; gap:20px;'>
                                <div><span style='color:#64748b;'>Буцаах огноо:</span> <strong style='color:#dc2626;'>{$formattedDue}</strong></div>
                                <div><span style='color:#64748b;'>Хоцорсон:</span> <strong style='color:#dc2626;'>{$overdueDays} хоног</strong></div>
                            </div>
                            <div style='margin-top:10px; background:#fef2f2; padding:10px; border-radius:8px;'>
                                <span style='color:#ef4444; font-weight:700;'>⚠️ Торгуул: ₮" . number_format($fine) . "</span>
                                <span style='color:#64748b; font-size:12px;'> (өдөрт ₮500)</span>
                            </div>
                        </div>
                        <p style='color:#64748b;'>Номыг аль болох хурдан буцааж өгнө үү.</p>
                        <a href='" . SITE_URL . "/pages/library/index.php?tab=loans' style='display:inline-block; background:#f59e0b; color:#fff; padding:12px 24px; border-radius:8px; text-decoration:none; font-weight:600;'>📚 Номын сан руу очих</a>
                    </div>
                </div>
            ";
            $emailSent = $this->sendEmail($row['email'], $subject, $body);
        }

        // Системийн дотоод мессеж мөн илгээх
        $msg = "📚 Таны зээлсэн \"{$row['book_title']}\" номын буцаах хугацаа {$overdueDays} хоногоор хэтэрсэн байна. Торгуул: ₮" . number_format($fine);
        sendSmartAlert($row['borrower_id'], $msg);

        if ($emailSent) $sent++;
        $details[] = [
            'book'    => $row['book_title'],
            'name'    => $row['full_name'],
            'email'   => $row['email'] ?? 'N/A',
            'overdue' => $overdueDays,
            'fine'    => $fine,
            'sent'    => $emailSent,
        ];
    }

    return ['success' => true, 'sent' => $sent, 'total' => count($overdue), 'details' => $details];
}

// Cron job ашигла зэрэгцүүлэх
// Өдөр бүр 08:00-д overdue tuition сануулга илгээх:
// 0 8 * * * /usr/bin/php /path/to/school_system1/tools/cron_tuition_reminder.php
//
// Номын сангийн сануулга:
// 0 9 * * * /usr/bin/php /path/to/school_system1/tools/cron_library_reminder.php
//
// Windows Task Scheduler:
// powershell -Command "& {php C:\xampp\htdocs\school_system1\tools\cron_tuition_reminder.php}"
?>
