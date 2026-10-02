<?php
/**
 * Seed de demonstração — usa o MESMO algoritmo de hash do login (bcrypt/PHP).
 * Cria: 1 admin, 2 médicos, 2 pacientes e o catálogo do Shop.
 */
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$pdo = db();

$seedUsers = [
    ['Administrador M3D', 'admin@m3d.pro',    'ADMIN',    null],
    ['Dra. Helena Costa', 'medico@m3d.pro',   'MEDICO',   'Cardiologia'],
    ['Dr. Rafael Nunes',  'medico2@m3d.pro',  'MEDICO',   'Clínica Geral'],
    ['Ana Paula Souza',   'paciente@m3d.pro', 'PACIENTE', null],
    ['Carlos Lima',       'paciente2@m3d.pro','PACIENTE', null],
];
foreach ($seedUsers as [$name, $email, $role, $spec]) {
    $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute([$email]);
    if (!$st->fetch()) {
        $pdo->prepare('INSERT INTO users (name, email, password_hash, role, specialty) VALUES (?,?,?,?,?)')
            ->execute([$name, $email, password_hash('m3d12345', PASSWORD_DEFAULT), $role, $spec]);
        echo "[seed] usuário $email ($role)\n";
    }
}

$products = [
    ['CONSULTA_AVULSA',   'Consulta de telemedicina avulsa', 'Atendimento por vídeo com médico credenciado, incluindo triagem por IA, receita e atestado em PDF.', 'PACIENTE', 15000],
    ['MODULO_IA_CLINICA', 'Assinatura — Módulo de IA para clínicas', 'Triagem automatizada de pacientes e resumo de anamnese para a equipe médica da sua clínica (mensal).', 'CLINICA', 29900],
    ['MODULO_PEP_PLUS',   'Assinatura — PEP Plus', 'Prontuário eletrônico avançado com documentos assináveis (ICP-Brasil) e relatórios gerenciais (mensal).', 'CLINICA', 19900],
];
foreach ($products as [$code, $name, $desc, $aud, $price]) {
    $pdo->prepare('INSERT OR IGNORE INTO shop_products (code, name, description, audience, price_cents) VALUES (?,?,?,?,?)')
        ->execute([$code, $name, $desc, $aud, $price]);
}
echo "[seed] catálogo do shop ok\n";
