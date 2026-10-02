<?php
/**
 * Front controller (padrão App Router): todas as rotas passam por aqui.
 * Mapa de rotas:
 *   ?r=login|register|logout        -> Módulo 1: Autenticação
 *   ?r=dashboard                    -> Dashboards por role (RBAC)
 *   ?r=schedule  (POST agendar)     -> Agendamento + entrada da triagem IA
 *   ?r=room      (GET  sala)        -> Módulo 3: Teleconsulta (vídeo + PEP)
 *   ?r=shop / ?r=checkout           -> Módulo 4: Vitrine e checkout
 *   ?r=admin                        -> Visão administrativa
 */
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
Auth::init();

$route  = $_GET['r'] ?? 'home';
$method = $_SERVER['REQUEST_METHOD'];

switch ($route) {

    // ================= MÓDULO 1: AUTENTICAÇÃO =================
    case 'login':
        if ($method === 'POST') {
            csrf_check();
            if (Auth::attempt(trim($_POST['email'] ?? ''), $_POST['password'] ?? '')) {
                header('Location: ?r=dashboard'); exit;
            }
            flash('E-mail ou senha inválidos.', 'err');
        }
        require __DIR__ . '/../public/views/login.php';
        break;

    case 'register':
        if ($method === 'POST') {
            csrf_check();
            $role = ($_POST['role'] ?? '') === 'MEDICO' ? 'MEDICO' : 'PACIENTE';
            $id = Auth::register(trim($_POST['name'] ?? ''), trim($_POST['email'] ?? ''), $_POST['password'] ?? '', $role);
            if ($id) {
                if ($role === 'MEDICO') {
                    db()->prepare('UPDATE users SET specialty = ? WHERE id = ?')
                        ->execute([trim($_POST['specialty'] ?? 'Clínica Geral'), $id]);
                }
                Auth::attempt(trim($_POST['email'] ?? ''), $_POST['password'] ?? '');
                header('Location: ?r=dashboard'); exit;
            }
            flash('Não foi possível cadastrar (e-mail já existe ou dados inválidos).', 'err');
        }
        require __DIR__ . '/../public/views/register.php';
        break;

    case 'logout':
        Auth::logout();
        header('Location: ?r=login'); exit;

    // ================= DASHBOARD POR ROLE =================
    case 'dashboard':
        Auth::requireLogin();
        $u = Auth::user();

        if (Auth::role() === 'PACIENTE') {
            // App-Level RLS: apenas consultas do próprio paciente
            [$w, $p] = Auth::patientScope();
            $apts = db()->prepare("SELECT a.*, d.name AS doctor_name FROM appointments a JOIN users d ON d.id=a.doctor_id WHERE $w ORDER BY a.scheduled_at DESC");
            $apts->execute($p);
            $appointments = $apts->fetchAll();
        } elseif (Auth::role() === 'MEDICO') {
            [$w, $p] = Auth::doctorScope();
            $apts = db()->prepare("SELECT a.*, p.name AS patient_name FROM appointments a JOIN users p ON p.id=a.patient_id WHERE $w ORDER BY a.scheduled_at ASC");
            $apts->execute($p);
            $appointments = $apts->fetchAll();
        } else {
            $appointments = db()->query('SELECT a.*, p.name AS patient_name, d.name AS doctor_name FROM appointments a JOIN users p ON p.id=a.patient_id JOIN users d ON d.id=a.doctor_id ORDER BY a.scheduled_at DESC LIMIT 100')->fetchAll();
        }

        if (Auth::role() === 'MEDICO') {
            $docs = db()->prepare('SELECT m.*, a.scheduled_at, p.name AS patient_name FROM medical_records m JOIN appointments a ON a.id=m.appointment_id JOIN users p ON p.id=m.patient_id WHERE m.doctor_id = ? ORDER BY m.created_at DESC LIMIT 50');
            $docs->execute([Auth::id()]);
            $records = $docs->fetchAll();
        } else {
            $docs = db()->prepare('SELECT m.*, d.name AS doctor_name FROM medical_records m JOIN users d ON d.id=m.doctor_id WHERE m.patient_id = ? ORDER BY m.created_at DESC LIMIT 50');
            $docs->execute([Auth::id()]);
            $records = $docs->fetchAll();
        }
        require __DIR__ . '/../public/views/dashboard.php';
        break;

    // ================= AGENDAMENTO + TRIAGEM IA =================
    case 'schedule':
        Auth::requireRole('PACIENTE');
        $doctors = db()->query("SELECT id, name, specialty FROM users WHERE role='MEDICO' AND active=1")->fetchAll();

        if ($method === 'POST') {
            csrf_check();
            $doctorId = (int)($_POST['doctor_id'] ?? 0);
            $when     = $_POST['scheduled_at'] ?? '';
            $prod     = db()->prepare("SELECT price_cents FROM shop_products WHERE code='CONSULTA_AVULSA'");
            $prod->execute();
            $price = (int)($prod->fetch()['price_cents'] ?? 15000);
            $room  = 'm3d-' . bin2hex(random_bytes(6));      // código único da sala LiveKit

            $st = db()->prepare("INSERT INTO appointments (patient_id, doctor_id, specialty, scheduled_at, price_cents, room_code) VALUES (?,?,?,?,?,?)");
            $st->execute([Auth::id(), $doctorId, $_POST['specialty'] ?? null, $when, $price, $room]);
            $apptId = (int)db()->lastInsertId();

            header('Location: ?r=triage&appt=' . $apptId);   // fluxo: agendar -> triagem IA -> checkout
            exit;
        }
        require __DIR__ . '/../public/views/schedule.php';
        break;

    // Chat de triagem (frontend consome a API Python via fetch)
    case 'triage':
        Auth::requireRole('PACIENTE');
        $apptId = (int)($_GET['appt'] ?? 0);
        // RLS: só o dono do agendamento entra na triagem
        $st = db()->prepare('SELECT * FROM appointments WHERE id = ? AND patient_id = ?');
        $st->execute([$apptId, Auth::id()]);
        $appt = $st->fetch() ?: exit('Agendamento não encontrado.');
        require __DIR__ . '/../public/views/triage.php';
        break;

    // ================= MÓDULO 3: SALA DE TELECONSULTA =================
    case 'room':
        Auth::requireLogin();
        $appt = Auth::canAccessAppointment((int)($_GET['appt'] ?? 0))
            ?: (http_response_code(403) && exit('403 — Você não tem acesso a esta sala.'));

        $rec = db()->prepare('SELECT * FROM medical_records WHERE appointment_id = ?');
        $rec->execute([$appt['id']]);
        $record = $rec->fetch() ?: null;

        if (Auth::role() === 'MEDICO' && $method === 'POST') {
            // Registro da evolução clínica, CID-10, receita e atestado — somente o médico responsável
            csrf_check();
            $ins = db()->prepare("INSERT INTO medical_records (appointment_id, patient_id, doctor_id, triage_summary, evolution, cid10, prescription, certificate)
                                  VALUES (?,?,?,?,?,?,?,?)
                                  ON CONFLICT(appointment_id) DO UPDATE SET evolution=excluded.evolution, cid10=excluded.cid10,
                                  prescription=excluded.prescription, certificate=excluded.certificate");
            $ins->execute([
                $appt['id'], $appt['patient_id'], Auth::id(),
                $_POST['triage_summary'] ?? $record['triage_summary'] ?? null,
                trim($_POST['evolution'] ?? '') ?: null,
                strtoupper(trim($_POST['cid10'] ?? '')) ?: null,
                trim($_POST['prescription'] ?? '') ?: null,
                trim($_POST['certificate'] ?? '') ?: null,
            ]);
            db()->prepare("UPDATE appointments SET status='REALIZADO' WHERE id=?")->execute([$appt['id']]);
            Auth::audit(Auth::id(), 'medical_records', $appt['id'], 'EVOLUCAO_REGISTRADA', null);
            header('Location: ?r=room&appt=' . $appt['id'] . '&saved=1'); exit;
        }
        require __DIR__ . '/../public/views/room.php';
        break;

    // Geração de PDF (prontuário/receita) via microsserviço Python
    case 'pdf':
        Auth::requireLogin();
        $appt = Auth::canAccessAppointment((int)($_GET['appt'] ?? 0))
            ?: (http_response_code(403) && exit('403'));
        $out = IntegrationClient::postOrError('/pdf/medical-record', ['appointment_id' => $appt['id']], 'PDF');
        if (!empty($out['error'])) { flash('Falha ao gerar PDF: ' . ($out['detail'] ?? ''), 'err'); header('Location: ?r=room&appt=' . $appt['id']); exit; }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $out['filename'] . '"');
        readfile(private_storage_path($out['path']));
        exit;

    // ================= MÓDULO 4: SHOP & CHECKOUT =================
    case 'shop':
        Auth::requireLogin();
        $products = db()->query('SELECT * FROM shop_products WHERE active=1 ORDER BY price_cents')->fetchAll();
        require __DIR__ . '/../public/views/shop.php';
        break;

    case 'checkout':
        Auth::requireLogin();
        $code = $_GET['p'] ?? '';
        $apptId = isset($_GET['appt']) ? (int)$_GET['appt'] : null;
        $prod = db()->prepare('SELECT * FROM shop_products WHERE code = ? AND active = 1');
        $prod->execute([$code]);
        $product = $prod->fetch() ?: exit('Produto inexistente.');

        if ($method === 'POST') {
            csrf_check();
            $gateway = ($_POST['gateway'] ?? '') === 'MERCADO_PAGO' ? 'MERCADO_PAGO' : 'STRIPE';
            // Cria cobrança no gateway escolhido (ou MOCK sem chaves) e registra pagamento PENDENTE
            $pay = IntegrationClient::postOrError('/payments/create', [
                'gateway'   => $gateway,
                'amount'    => (int)$product['price_cents'],
                'product'   => $product['code'],
                'user_id'   => Auth::id(),
                'appt_id'   => $apptId,
                'base_url'  => (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'],
            ], 'CHECKOUT');
            if (!empty($pay['error'])) { flash('Gateway indisponível: ' . ($pay['detail'] ?? ''), 'err'); header('Location: ?r=shop'); exit; }

            db()->prepare("INSERT INTO payments (appointment_id, user_id, product_code, gateway, amount_cents, status, external_id)
                           VALUES (?,?,?,?,?,'PENDENTE',?)")->execute([
                $apptId, Auth::id(), $product['code'], $gateway, (int)$product['price_cents'], $pay['external_id'] ?? null,
            ]);
            if (!empty($pay['checkout_url'])) { header('Location: ' . $pay['checkout_url']); exit; }
            // Mock: confirma direto simulando o webhook
            header('Location: ?r=mock_pay&ref=' . urlencode($pay['external_id'] ?? '')); exit;
        }
        require __DIR__ . '/../public/views/checkout.php';
        break;

    case 'mock_pay':
        // Simulação do retorno do gateway em ambiente de desenvolvimento (sem chaves reais)
        Auth::requireLogin();
        $ref = $_GET['ref'] ?? '';
        IntegrationClient::post('/webhooks/mock-confirm', ['external_id' => $ref]);
        flash('Pagamento aprovado (modo demonstração).');
        header('Location: ?r=dashboard'); exit;

    // ================= ADMIN =================
    case 'admin':
        Auth::requireRole('ADMIN');
        $stats = [
            'users'  => (int)db()->query('SELECT COUNT(*) c FROM users')->fetch()['c'],
            'appts'  => (int)db()->query('SELECT COUNT(*) c FROM appointments')->fetch()['c'],
            'paid'   => (int)db()->query("SELECT COUNT(*) c FROM payments WHERE status='PAGO'")->fetch()['c'],
            'revenue'=> (int)db()->query("SELECT COALESCE(SUM(amount_cents),0) c FROM payments WHERE status='PAGO'")->fetch()['c'],
        ];
        $logs = db()->query('SELECT l.*, u.name AS actor FROM audit_log l LEFT JOIN users u ON u.id=l.actor_id ORDER BY l.id DESC LIMIT 50')->fetchAll();
        require __DIR__ . '/../public/views/admin.php';
        break;

    case 'home':
    default:
        require __DIR__ . '/../public/views/home.php';
}
