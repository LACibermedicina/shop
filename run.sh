#!/usr/bin/env bash
# ============================================================
# shop.m3d.pro — Inicialização do MVP (desenvolvimento)
# 1. Cria a pasta de dados e aplica o schema SQLite
# 2. Popula usuários de demonstração (PACIENTE / MEDICO / ADMIN) e o catálogo
# 3. Sobe o microsserviço Python (IA/PDF/webhooks) na porta 8001
# 4. Sobe o servidor PHP (frontend) na porta 8000
# Em produção: TLS 1.3 obrigatório no nginx (ver deploy/nginx-m3d.conf)
# ============================================================
set -euo pipefail
cd "$(dirname "$0")"

mkdir -p data/private
if [ ! -f data/m3d.sqlite ]; then
  sqlite3 data/m3d.sqlite < database/schema.sql
  echo "[OK] Banco criado: data/m3d.sqlite"
else
  echo "[OK] Banco já existente."
fi

# Seed em PHP (reaproveita o hash bcrypt do PHP — mesma origem do login)
php database/seed.php

echo "[..] Subindo microsserviço Python em :8001"
nohup python3 service/app.py > logs/python.log 2>&1 &

echo "[..] Subindo frontend PHP em :8000"
nohup php -S 127.0.0.1:8000 -t public > logs/php.log 2>&1 &

sleep 2
echo "[OK] Aplicação disponível em http://localhost:8000"
echo "     Contas demo: paciente@m3d.pro / medico@m3d.pro / admin@m3d.pro — senha m3d12345"
