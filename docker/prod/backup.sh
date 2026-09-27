#!/bin/bash
# Sao lưu database (mysqldump) + ảnh upload (storage/app/public).
#   backup.sh loop            chạy nền, sao lưu mỗi ngày lúc BACKUP_TIME, xóa bản cũ hơn BACKUP_KEEP_DAYS ngày
#   backup.sh once            sao lưu ngay
#   backup.sh restore <file>  khôi phục database từ file .sql.gz trong /backups/db
set -euo pipefail

DB_DIR=/backups/db
FILES_DIR=/backups/uploads
mkdir -p "$DB_DIR" "$FILES_DIR"

backup_once() {
    local stamp db_file
    stamp=$(date +%Y%m%d-%H%M%S)
    db_file="$DB_DIR/${DB_DATABASE}-${stamp}.sql.gz"

    mysqldump -h "$MYSQL_HOST" -uroot --single-transaction --quick --routines --triggers --no-tablespaces \
        --set-gtid-purged=OFF "$DB_DATABASE" | gzip -9 > "$db_file.tmp"
    mv "$db_file.tmp" "$db_file"

    if [ -d /storage/app/public ]; then
        tar -czf "$FILES_DIR/uploads-${stamp}.tar.gz" -C /storage/app public
    fi

    find "$DB_DIR" "$FILES_DIR" -type f -mtime +"${BACKUP_KEEP_DAYS:-14}" -delete

    echo "[$(date '+%F %T')] Đã sao lưu: $(basename "$db_file") ($(du -h "$db_file" | cut -f1))"
}

case "${1:-loop}" in
    once)
        backup_once
        ;;
    restore)
        file="${2:?Cần tên file, vd: backup.sh restore nha_hang-20260928-030000.sql.gz}"
        [ -f "$file" ] || file="$DB_DIR/$file"
        echo "Khôi phục $file vào database $DB_DATABASE..."
        gunzip -c "$file" | mysql -h "$MYSQL_HOST" -uroot "$DB_DATABASE"
        echo "Xong."
        ;;
    loop)
        echo "Sao lưu mỗi ngày lúc ${BACKUP_TIME:-03:00}, giữ ${BACKUP_KEEP_DAYS:-14} ngày."
        while :; do
            now=$(date +%s)
            next=$(date -d "today ${BACKUP_TIME:-03:00}" +%s)
            [ "$next" -le "$now" ] && next=$(date -d "tomorrow ${BACKUP_TIME:-03:00}" +%s)
            sleep $((next - now))
            backup_once || echo "[$(date '+%F %T')] Sao lưu lỗi" >&2
        done
        ;;
    *)
        echo "Dùng: backup.sh loop|once|restore <file>" >&2
        exit 1
        ;;
esac
