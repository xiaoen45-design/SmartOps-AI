from __future__ import annotations

import hashlib
import os
import re
import shutil
import subprocess
import tempfile
import threading
from datetime import date, datetime, time
from decimal import Decimal
from pathlib import Path
from typing import Any

import pymysql


PROJECT_ROOT = Path(__file__).resolve().parents[1]
DEFAULT_SQL_PATH = PROJECT_ROOT / "phpmyadmin_database" / "smartops_unified_database.sql"
DEFAULT_VERSION_PATH = PROJECT_ROOT / "config" / "snapshot_version.txt"
_SNAPSHOT_LOCK = threading.Lock()


def snapshot_enabled() -> bool:
    value = os.getenv("AUTO_UPDATE_SQL_SNAPSHOT", "true").strip().lower()
    return value not in {"0", "false", "off", "no"}


def _safe_identifier(value: str) -> str:
    return "`" + value.replace("`", "``") + "`"


def _snapshot_version(case_id: str | None = None) -> str:
    timestamp = datetime.now().strftime("%Y%m%d-%H%M%S-%f")
    safe_case = re.sub(r"[^A-Za-z0-9_-]+", "-", str(case_id or "manual")).strip("-")
    safe_case = safe_case[:60] or "manual"
    return f"snapshot-{timestamp}-{safe_case}"


def _locate_mysqldump() -> Path | None:
    configured = os.getenv("MYSQLDUMP_PATH", "").strip()
    candidates = [
        configured,
        shutil.which("mysqldump") or "",
        "/Applications/XAMPP/xamppfiles/bin/mysqldump",
        "/opt/lampp/bin/mysqldump",
        r"C:\xampp\mysql\bin\mysqldump.exe",
        r"C:\xampp\mariadb\bin\mysqldump.exe",
    ]
    for candidate in candidates:
        if not candidate:
            continue
        path = Path(candidate).expanduser()
        if path.is_file():
            return path
    return None


def _dump_with_mysqldump(options: dict[str, Any], output_path: Path) -> None:
    executable = _locate_mysqldump()
    if executable is None:
        raise FileNotFoundError("mysqldump was not found")

    database = str(options["database"])
    command = [
        str(executable),
        "--single-transaction",
        "--quick",
        "--routines",
        "--triggers",
        "--events",
        "--hex-blob",
        "--skip-comments",
        "--add-drop-database",
        "--default-character-set=utf8mb4",
        f"--user={options.get('user', 'root')}",
    ]

    if options.get("unix_socket"):
        command.append(f"--socket={options['unix_socket']}")
    else:
        command.extend(
            [
                f"--host={options.get('host', '127.0.0.1')}",
                f"--port={int(options.get('port', 3306))}",
                "--protocol=TCP",
            ]
        )

    command.extend(["--databases", database])
    environment = os.environ.copy()
    environment["MYSQL_PWD"] = str(options.get("password", ""))

    with output_path.open("wb") as output_file:
        completed = subprocess.run(
            command,
            stdout=output_file,
            stderr=subprocess.PIPE,
            env=environment,
            check=False,
            timeout=120,
        )

    if completed.returncode != 0:
        message = completed.stderr.decode("utf-8", errors="replace").strip()
        raise RuntimeError(message or f"mysqldump exited with code {completed.returncode}")


def _sql_literal(connection: pymysql.connections.Connection, value: Any) -> str:
    if value is None:
        return "NULL"
    if isinstance(value, bool):
        return "1" if value else "0"
    if isinstance(value, (int, float, Decimal)):
        return str(value)
    if isinstance(value, (datetime, date, time)):
        return connection.escape(value)
    return connection.escape(value)


def _dump_with_pymysql(options: dict[str, Any], output_path: Path) -> None:
    """Portable fallback used when XAMPP's mysqldump executable cannot be found."""
    connection = pymysql.connect(**options)
    database = str(options["database"])
    try:
        with connection.cursor() as cursor, output_path.open("w", encoding="utf-8", newline="\n") as output:
            output.write("-- SmartOps automatically updated SQL snapshot\n")
            output.write("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n")
            output.write("SET time_zone = '+08:00';\n")
            output.write("SET NAMES utf8mb4;\n")
            output.write("SET FOREIGN_KEY_CHECKS = 0;\n\n")
            output.write(f"CREATE DATABASE IF NOT EXISTS {_safe_identifier(database)} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n")
            output.write(f"USE {_safe_identifier(database)};\n\n")

            cursor.execute("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")
            rows = cursor.fetchall()
            table_names: list[str] = []
            for row in rows:
                if isinstance(row, dict):
                    table_names.append(str(next(iter(row.values()))))
                else:
                    table_names.append(str(row[0]))

            for table in table_names:
                quoted_table = _safe_identifier(table)
                cursor.execute(f"SHOW CREATE TABLE {quoted_table}")
                create_row = cursor.fetchone()
                if isinstance(create_row, dict):
                    create_sql = str(create_row.get("Create Table") or list(create_row.values())[-1])
                else:
                    create_sql = str(create_row[1])

                output.write(f"DROP TABLE IF EXISTS {quoted_table};\n")
                output.write(create_sql + ";\n\n")

            for table in table_names:
                quoted_table = _safe_identifier(table)
                cursor.execute(f"SELECT * FROM {quoted_table}")
                data_rows = cursor.fetchall()
                if not data_rows:
                    continue

                if isinstance(data_rows[0], dict):
                    columns = list(data_rows[0].keys())
                    row_values = [[row[column] for column in columns] for row in data_rows]
                else:
                    columns = [str(description[0]) for description in cursor.description]
                    row_values = [list(row) for row in data_rows]

                column_sql = ",".join(_safe_identifier(column) for column in columns)
                for start in range(0, len(row_values), 100):
                    batch = row_values[start : start + 100]
                    value_sql = []
                    for values in batch:
                        value_sql.append("(" + ",".join(_sql_literal(connection, value) for value in values) + ")")
                    output.write(f"INSERT INTO {quoted_table} ({column_sql}) VALUES\n")
                    output.write(",\n".join(value_sql) + ";\n")
                output.write("\n")

            output.write("SET FOREIGN_KEY_CHECKS = 1;\n")
    finally:
        connection.close()


def _append_snapshot_version(sql_path: Path, database: str, version: str) -> None:
    escaped_version = version.replace("'", "''")
    updated_at = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    with sql_path.open("a", encoding="utf-8", newline="\n") as output:
        output.write("\n-- SmartOps snapshot version marker\n")
        output.write(f"USE {_safe_identifier(database)};\n")
        output.write(
            "INSERT INTO `demo_meta` (`meta_key`,`meta_value`,`updated_at`) VALUES "
            f"('dataset_version','{escaped_version}','{updated_at}') "
            "ON DUPLICATE KEY UPDATE `meta_value`=VALUES(`meta_value`),`updated_at`=VALUES(`updated_at`);\n"
        )
        output.write(
            "INSERT INTO `demo_meta` (`meta_key`,`meta_value`,`updated_at`) VALUES "
            f"('dataset_mode','Auto-updated SQL snapshot','{updated_at}') "
            "ON DUPLICATE KEY UPDATE `meta_value`=VALUES(`meta_value`),`updated_at`=VALUES(`updated_at`);\n"
        )


def _atomic_write_text(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temp_path = path.with_name(path.name + ".tmp")
    temp_path.write_text(content, encoding="utf-8")
    os.replace(temp_path, path)


def _update_live_version(options: dict[str, Any], version: str) -> None:
    connection = pymysql.connect(**options)
    try:
        with connection.cursor() as cursor:
            cursor.execute(
                """INSERT INTO demo_meta (meta_key,meta_value,updated_at)
                   VALUES('dataset_version',%s,NOW())
                   ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value),updated_at=VALUES(updated_at)""",
                (version,),
            )
            cursor.execute(
                """INSERT INTO demo_meta (meta_key,meta_value,updated_at)
                   VALUES('dataset_mode','Auto-updated SQL snapshot',NOW())
                   ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value),updated_at=VALUES(updated_at)"""
            )
        connection.commit()
    finally:
        connection.close()


def export_database_snapshot(
    connection_options: dict[str, Any],
    *,
    case_id: str | None = None,
    output_path: Path | None = None,
    version_path: Path | None = None,
) -> dict[str, Any]:
    """Export the live SmartOps database into the bundled SQL file.

    The operation is synchronous and atomic at file level. A complaint remains
    saved even if snapshot generation fails; callers receive the error details.
    """
    if not snapshot_enabled():
        return {"success": False, "skipped": True, "reason": "disabled"}

    sql_path = (output_path or DEFAULT_SQL_PATH).resolve()
    marker_path = (version_path or DEFAULT_VERSION_PATH).resolve()
    database = str(connection_options["database"])
    version = _snapshot_version(case_id)

    with _SNAPSHOT_LOCK:
        sql_path.parent.mkdir(parents=True, exist_ok=True)
        temporary_file = tempfile.NamedTemporaryFile(
            prefix=sql_path.stem + "-",
            suffix=".sql.tmp",
            dir=sql_path.parent,
            delete=False,
        )
        temporary_path = Path(temporary_file.name)
        temporary_file.close()

        export_method = "mysqldump"
        try:
            try:
                _dump_with_mysqldump(connection_options, temporary_path)
            except (FileNotFoundError, RuntimeError) as mysqldump_error:
                export_method = "pymysql-fallback"
                try:
                    _dump_with_pymysql(connection_options, temporary_path)
                except Exception as fallback_error:
                    raise RuntimeError(
                        f"mysqldump failed: {mysqldump_error}; fallback failed: {fallback_error}"
                    ) from fallback_error

            if not temporary_path.exists() or temporary_path.stat().st_size < 500:
                raise RuntimeError("Generated SQL snapshot is unexpectedly empty")

            _append_snapshot_version(temporary_path, database, version)
            digest = hashlib.sha256(temporary_path.read_bytes()).hexdigest()

            os.replace(temporary_path, sql_path)
            _atomic_write_text(marker_path, version + "\n")
            _update_live_version(connection_options, version)

            try:
                display_path = str(sql_path.relative_to(PROJECT_ROOT))
            except ValueError:
                display_path = str(sql_path)

            return {
                "success": True,
                "version": version,
                "method": export_method,
                "file": display_path,
                "size_bytes": sql_path.stat().st_size,
                "sha256": digest,
            }
        finally:
            if temporary_path.exists():
                temporary_path.unlink(missing_ok=True)
