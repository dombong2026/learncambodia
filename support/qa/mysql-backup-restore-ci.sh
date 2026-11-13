#!/usr/bin/env bash
# GitHub Actions ONLY, disposable MySQL service.
set -Eeuo pipefail
set +x
umask 077
fail() { printf 'MYSQL_RESTORE_GATE=FAIL: %s\n' "$*" >&2; exit 1; }
[[ "$CI" == "true" && "$GITHUB_ACTIONS" == "true" ]] || fail "GitHub Actions CI required"
[[ "$APP_ENV" == "testing" && "$DB_CONNECTION" == "mysql" ]] || fail "testing MySQL only"
[[ "$DB_DATABASE" == "learncambodia_qa_primary" ]] || fail "incorrect disposable database"
[[ "$DB_HOST" == "127.0.0.1" && "$DB_PORT" == "3306" ]] || fail "local service only"
[[ "$DB_USERNAME" == "root" && -n "$DB_PASSWORD" ]] || fail "disposable CI credential only"
[[ "$INSTALLER_ENABLED" == "false" && "$PUBLIC_STUDENT_PROFILES_ENABLED" == "false" ]] || fail "integration gate changed"
command -v mysql >/dev/null && command -v mysqldump >/dev/null || fail "MySQL client not installed"
export MYSQL_PWD="$DB_PASSWORD"
primary=learncambodia_qa_primary
restored=learncambodia_qa_restored
scratch="$(mktemp -d)"
trap 'rm -rf -- "$scratch"' EXIT
[[ -d "$scratch" && ! -L "$scratch" ]] || fail "unsafe scratch"
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$primary" -e "CREATE TABLE qa_backup_sentinel (id INT PRIMARY KEY, note VARCHAR(120) NOT NULL); INSERT INTO qa_backup_sentinel VALUES (1,'disposable relational restore proof');"
count="$(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" --batch --skip-column-names "$primary" -e "SELECT COUNT(*) FROM categories;")"
[[ "$count" =~ ^[0-9]+$ && "$count" -ge 20 ]] || fail "missing reference seed"
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -e "CREATE DATABASE learncambodia_qa_restored CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" --single-transaction --skip-lock-tables --no-tablespaces --skip-comments --skip-dump-date "$primary" > "$scratch/original.sql"
[[ -s "$scratch/original.sql" ]] || fail "empty backup"
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$restored" < "$scratch/original.sql"
test "$(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" --batch --skip-column-names "$restored" -e "SELECT COUNT(*) FROM categories;")" = "$count" || fail "category count differs"
test "$(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" --batch --skip-column-names "$restored" -e "SELECT note FROM qa_backup_sentinel WHERE id=1;")" = "disposable relational restore proof" || fail "sentinel differs"
for db in "$primary" "$restored"; do
  mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" --no-create-info --skip-comments --skip-dump-date --skip-lock-tables --skip-extended-insert --order-by-primary --no-tablespaces "$db" > "$scratch/$db.rows"
  mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" --batch --skip-column-names -e "SELECT table_name,column_name,column_type,is_nullable,COALESCE(column_default,'<NULL>'),extra FROM information_schema.columns WHERE table_schema='$db' ORDER BY table_name,ordinal_position;" > "$scratch/$db.schema"
done
cmp -s "$scratch/$primary.rows" "$scratch/$restored.rows" || fail "row payload mismatch"
cmp -s "$scratch/$primary.schema" "$scratch/$restored.schema" || fail "columns mismatch"
DB_DATABASE="$restored" php artisan migrate:status --no-interaction >/dev/null || fail "migration history invalid"
hash="$(sha256sum "$scratch/$restored.rows" | cut -d' ' -f1)"
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -e "DROP DATABASE learncambodia_qa_restored;"
printf 'MYSQL_BACKUP=PASS\nMYSQL_RESTORE=PASS\nMYSQL_SCHEMA=PASS\nMYSQL_MIGRATIONS=PASS\n'
printf 'RESTORED_CATEGORIES=%s\nRESTORED_ROWS_SHA256=%s\n' "$count" "$hash"
printf 'MYSQL_RESTORE_GATE=PASS\n'
