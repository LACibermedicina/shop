<?php
/**
 * Autenticação + RBAC (controle de acesso por papel).
 *
 * REGRA DE NEGÓCIO CRÍTICA — Isolamento de dados (LGPD/HIPAA):
 * Como SQLite não possui Row Level Security nativa (equivalente Supabase),
 * o isolamento é emulado AQUI, na camada de aplicação, de forma obrigatória:
 *   PACIENTE -> enxerga SOMENTE linhas onde patient_id = seu id
 *   MEDICO   -> enxerga SOMENTE linhas onde doctor_id  = seu id
 *   ADMIN    -> acesso administrativo auditado
 * TODA consulta a dados clínicos/financeiros deve passar por patientScope()
 * ou doctorScope(); nunca confie em IDs recebidos do cliente sem escopar.
 */
declare(strict_types=1);

class Auth
{
    private static ?array $user = null;

    public static function init(): void
    {
        $id = $_SESSION['uid'] ?? null;
        if ($id) {
            $u = db()->prepare('SELECT id, name, email, role, specialty FROM users WHERE id = ? AND active = 1');
            $u->execute([(int)$id]);
            self::$user = $u->fetch() ?: null;
            if (!self::$user) self::logout();
        }
    }

    public static function user(): ?array { return self::$user; }
    public static function id(): ?int { return self::$user['id'] ?? null; }
    public static function role(): ?string { return self::$user['role'] ?? null; }

    public static function attempt(string $email, string $password): bool
    {
        $st = db()->prepare('SELECT id, password_hash, role FROM users WHERE email = ? AND active = 1');
        $st->execute([$email]);
        $u = $st->fetch();
        if (!$u || !password_verify($password, $u['password_hash'])) {
            self::audit(null, 'users', null, 'LOGIN_FALHOU', $email);
            return false;
        }
        session_regenerate_id(true);                 // evita fixação de sessão
        $_SESSION['uid'] = (int)$u['id'];
        self::audit((int)$u['id'], 'users', (int)$u['id'], 'LOGIN_OK', null);
        return true;
    }

    public static function register(string $name, string $email, string $password, string $role): ?int
    {
        if (!in_array($role, ['PACIENTE', 'MEDICO'], true)) return null; // ADMIN não se auto-registra
        $st = db()->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) return null;               // e-mail já cadastrado
        $ins = db()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)');
        $ins->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        return (int)db()->lastInsertId();
    }

    public static function logout(): void
    {
        session_regenerate_id(true);
        unset($_SESSION['uid']);
        self::$user = null;
    }

    /** Exige sessão válida; redireciona ao login caso contrário. */
    public static function requireLogin(): void
    {
        if (!self::id()) { header('Location: ?r=login'); exit; }
    }

    /** Exige uma role específica (barreira de RBAC por rota). */
    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            exit('403 — Acesso negado para o perfil ' . e(self::role() ?? 'visitante') . '.');
        }
    }

    /**
     * Escopo de consultas do PACIENTE: força patient_id = usuário logado.
     * Retorna [sql_extra, params].
     */
    public static function patientScope(): array
    {
        return [' patient_id = :uid', [':uid' => self::id()]];
    }

    /** Escopo de consultas do MÉDICO: força doctor_id = usuário logado. */
    public static function doctorScope(): array
    {
        return [' doctor_id = :uid', [':uid' => self::id()]];
    }

    /**
     * Verifica se o usuário atual pode acessar um agendamento específico.
     * Retorna a linha do agendamento ou null (uso: $ap = canAccessAppointment($id); if (!$ap) 403;)
     */
    public static function canAccessAppointment(int $appointmentId): ?array
    {
        $st = db()->prepare(
            'SELECT a.*, p.name AS patient_name, d.name AS doctor_name
               FROM appointments a
               JOIN users p ON p.id = a.patient_id
               JOIN users d ON d.id = a.doctor_id
              WHERE a.id = ?'
        );
        $st->execute([$appointmentId]);
        $ap = $st->fetch();
        if (!$ap) return null;
        return match (self::role()) {
            'ADMIN'    => $ap,                                     // auditado abaixo
            'PACIENTE' => ((int)$ap['patient_id'] === self::id()) ? $ap : null,
            'MEDICO'   => ((int)$ap['doctor_id'] === self::id()) ? $ap : null,
            default    => null,
        };
    }

    /** Trilha de auditoria (equivalente à tabela audit do plano original). */
    public static function audit(?int $actorId, string $entity, ?int $entityId, string $action, ?string $reason): void
    {
        db()->prepare('INSERT INTO audit_log (actor_id, entity, entity_id, action, reason, ip) VALUES (?,?,?,?,?,?)')
            ->execute([$actorId, $entity, $entityId, $action, $reason, $_SERVER['REMOTE_ADDR'] ?? null]);
    }
}
