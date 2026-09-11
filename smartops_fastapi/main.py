from __future__ import annotations

import json
import os
import re
from datetime import datetime
from pathlib import Path
from typing import Any

import httpx
import pymysql
from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

from database_snapshot import export_database_snapshot

load_dotenv()

app = FastAPI(title="SmartOps FastAPI", version="1.6.0")
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)


class PipelineEnvelope(BaseModel):
    case_id: str | None = None
    room_id: str | None = None
    complaint_text: str | None = None
    guest_phone: str | None = None
    guest_chat_id: str | None = None
    guest_message_id: str | None = None
    metadata: dict[str, Any] | None = None
    execution_status: str | None = None
    pipeline_result: dict[str, Any] | None = None


class MLComplaintRequest(BaseModel):
    case_id: str = Field(min_length=1)
    complaint_text: str = Field(min_length=1)
    room_id: str | None = None


def ml_api_url() -> str:
    return os.getenv(
        "ML_API_URL",
        "http://127.0.0.1:8000/api/v1/recommendation",
    ).strip()


def build_ml_request(payload: MLComplaintRequest) -> dict[str, Any]:
    """Build the ML request using field names configurable in .env."""
    case_field = os.getenv("ML_REQUEST_CASE_FIELD", "case_id").strip() or "case_id"
    room_field = os.getenv("ML_REQUEST_ROOM_FIELD", "room_id").strip() or "room_id"
    text_field = os.getenv("ML_REQUEST_TEXT_FIELD", "complaint_text").strip() or "complaint_text"

    request_body: dict[str, Any] = {
        case_field: payload.case_id,
        text_field: payload.complaint_text,
    }
    if payload.room_id is not None:
        request_body[room_field] = payload.room_id
    return request_body


def mysql_connection_options() -> dict[str, Any]:
    options: dict[str, Any] = {
        "user": os.getenv("DB_USER", "root"),
        "password": os.getenv("DB_PASSWORD", ""),
        "database": os.getenv("DB_NAME", "smartops"),
        "charset": "utf8mb4",
        "cursorclass": pymysql.cursors.DictCursor,
        "autocommit": False,
        "connect_timeout": 4,
    }
    configured_socket = os.getenv("DB_UNIX_SOCKET", "").strip()
    common_xampp_socket = "/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock"
    socket_path = configured_socket or (common_xampp_socket if Path(common_xampp_socket).exists() else "")
    if socket_path and Path(socket_path).exists():
        options["unix_socket"] = socket_path
    else:
        options["host"] = os.getenv("DB_HOST", "127.0.0.1")
        options["port"] = int(os.getenv("DB_PORT", "3306"))
    return options


def db():
    return pymysql.connect(**mysql_connection_options())


def low(value: Any) -> str:
    return str(value or "").strip().lower()


def clean_asset(value: str | None) -> str:
    """Keep the JSON asset wording while removing its leading D-category code."""
    text = str(value or "").strip()
    if low(text) in {"", "-", "unknown", "none", "null", "n/a", "na", "out_of_kb", "out of kb", "out of knowledge base"}:
        return "Other"

    text = re.sub(r"^D\d+(?:\.\d+)?\s*", "", text, flags=re.IGNORECASE).strip()
    aliases = {
        "hvac": "HVAC",
        "plumbing": "Plumbing",
        "electrical": "Electrical",
        "fire": "Fire Protection",
        "fire protection": "Fire Protection",
        "fire system": "Fire Protection",
        "elevator": "Conveying",
        "conveying": "Conveying",
        "elevator & lifts": "Conveying",
        "lifts": "Conveying",
        "other": "Other",
    }
    return aliases.get(low(text), text or "Other")


def asset_with_code(value: str | None) -> str:
    """Return the canonical hotel asset including its D-category code for database/display."""
    clean = clean_asset(value)
    return {
        "Conveying": "D10 Conveying",
        "Plumbing": "D20 Plumbing",
        "HVAC": "D30 HVAC",
        "Fire Protection": "D40 Fire Protection",
        "Electrical": "D50 Electrical",
        "Other": "Other",
    }.get(clean, clean)


def component_with_code(value: str | None) -> str | None:
    text = str(value or "").strip()
    if not text:
        return None
    if re.match(r"^D\d{4}\s+", text, flags=re.IGNORECASE):
        return text
    codes = {
        "energy supply": "D3010", "heat generating systems": "D3020", "cooling generating systems": "D3030",
        "distribution systems": "D3040", "terminal & package units": "D3050", "controls & instrumentation": "D3060",
        "systems testing & balancing": "D3070", "plumbing fixtures": "D2010", "domestic water distribution": "D2020",
        "sanitary waste": "D2030", "electrical service & distribution": "D5010", "lighting & branch wiring": "D5020",
        "sprinklers": "D4010", "other fire protection system": "D4090", "elevator & lifts": "D1010",
    }
    code = codes.get(text.lower(), "")
    return f"{code} {text}" if code else text


def failure_mode_with_code(value: str | None, component: str | None = None) -> str | None:
    text = str(value or "").strip()
    if not text:
        return None
    if re.match(r"^D\d{4}\s+", text, flags=re.IGNORECASE):
        return text
    codes = {
        "cold water service": "D2021", "hot water service": "D2022", "water supply system": "D2023",
        "main electrical supply": "D5010", "elevators related": "D1010", "exhaust ventilation systems": "D3040",
        "hot water distribution": "D3040", "air distribution systems": "D3040", "fire extinguishers": "D4030",
        "exit light": "D4090", "water leakage related": "D4010",
        "fire alarm system & smoke detector related": "D4090", "faulty faucets": "D2010",
        "lighting not working related": "D5020", "clogging and blockage": "D4010", "sensitivity failure": "D4010",
        "disposal": "D2030", "drains": "D2010", "floor drain": "D2030", "toilet": "D2010",
        "terminal self-contained units / fcu": "D3050",
    }
    code = codes.get(text.lower(), "")
    if not code:
        comp = component_with_code(component)
        m = re.match(r"^(D\d{4})\s+", str(comp or ""), flags=re.IGNORECASE)
        if m:
            code = m.group(1).upper()
    return f"{code} {text}" if code else text


def normalize_severity(value: Any) -> str | None:
    text = str(value or "").strip().replace("_", " ")
    if not text:
        return None
    return text.title()


def normalize_priority(value: Any) -> str | None:
    text = str(value or "").strip().replace("_", " ").replace("-", " ")
    if not text:
        return None
    normalized = " ".join(text.lower().split())
    if normalized.startswith("p1 ") or "immediate" in normalized or normalized == "critical":
        return "Immediate"
    if normalized.startswith("p2 ") or "urgent" in normalized or normalized == "high":
        return "Urgent"
    if normalized.startswith("p3 ") or "standard" in normalized or normalized == "medium":
        return "Standard"
    if normalized.startswith("p4 ") or normalized == "low":
        return "Low"
    return normalized.title()


def to_db_text(value: Any) -> str | None:
    if value is None:
        return None
    if isinstance(value, (list, tuple, set)):
        parts = [str(item).strip() for item in value if str(item).strip()]
        return "; ".join(parts) or None
    if isinstance(value, dict):
        return json.dumps(value, ensure_ascii=False)
    text = str(value).strip()
    return text or None


def extract(payload: dict[str, Any]) -> dict[str, Any]:
    pr = payload.get("pipeline_result") or payload
    classification = pr.get("classification") or {}
    candidates = pr.get("candidates") or []
    selected = pr.get("selected_candidate") or (candidates[0] if candidates else {})
    metadata = selected.get("metadata") or {}
    recommendation = pr.get("recommendation") or {}
    governance = pr.get("governance_decision") or {}
    retrieval = pr.get("retrieval_decision") or {}
    request_metadata = payload.get("metadata") or {}

    guest_phone = payload.get("guest_phone") or request_metadata.get("guest_phone")
    guest_chat_id = payload.get("guest_chat_id") or request_metadata.get("guest_chat_id")
    guest_message_id = payload.get("guest_message_id") or request_metadata.get("guest_message_id")

    status = str(pr.get("status") or payload.get("execution_status") or "")
    status_upper = status.upper()
    approval_status = str(
        governance.get("approval_status")
        or pr.get("approval_status")
        or ""
    ).upper()

    human = bool(
        governance.get("requires_human_review")
        or pr.get("requires_human_review")
        or pr.get("human_approval_required")
        or approval_status == "HUMAN_APPROVAL_REQUIRED"
        or "HITL" in status_upper
        or "OUT_OF_KB" in status_upper
    )

    out_of_kb = bool(classification.get("is_out_of_kb")) or any(
        token in status_upper for token in ("OUT_OF_KB", "OUT_OF_KNOWLEDGE", "NO_MATCH")
    )

    raw_severity = (
        governance.get("severity")
        or recommendation.get("severity_level")
        or recommendation.get("severity")
        or metadata.get("severity")
    )
    raw_priority = (
        governance.get("priority")
        or recommendation.get("priority_level")
        or recommendation.get("priority")
    )
    severity = normalize_severity(raw_severity)
    priority = normalize_priority(raw_priority) or ({'Critical': 'Immediate', 'High': 'Urgent', 'Medium': 'Standard', 'Low': 'Low'}.get(severity))

    retrieval_reasons = [str(item).upper() for item in (retrieval.get("reasons") or [])]
    governance_reasons = [str(item).upper() for item in (governance.get("decision_reasons") or [])]
    all_reasons = retrieval_reasons + governance_reasons
    low_confidence = (
        any("LOW_" in item and ("CONFIDENCE" in item or "MARGIN" in item) for item in all_reasons)
        or any("LOW CONFIDENCE" in item for item in all_reasons)
        or ("RETRIEVAL_HITL" in status_upper and not out_of_kb)
    )

    is_critical = low(severity) == "critical" or priority == "Immediate" or str(raw_priority or "").upper().startswith("P1")
    is_high = low(severity) == "high" or priority == "Urgent" or str(raw_priority or "").upper().startswith("P2")

    # Keep the model classification separate from the manager-facing review reason.
    # A High/Urgent or Critical/Immediate case can still have Low Confidence as
    # its review reason; the dashboard derives its primary queue from
    # Severity/Priority, so classification values must never be deleted.
    if out_of_kb:
        reason = "Out of Knowledge Base"
    elif low_confidence:
        reason = "Low Confidence"
    elif is_critical:
        reason = "Critical"
    elif is_high:
        reason = "High"
    elif human:
        reason = "Low Confidence"
    else:
        reason = "AI Automation"

    # Low-confidence and OOKB recommendations remain restricted until human
    # review, but Severity and Priority are retained for routing and display.
    restricted_guidance = reason in {"Low Confidence", "Out of Knowledge Base"}

    auto = (
        approval_status == "AUTO_APPROVED"
        or ("AUTO" in status_upper and not human and not out_of_kb)
    ) and not human and not out_of_kb

    corrective = to_db_text(
        recommendation.get("corrective_actions")
        or recommendation.get("corrective_action")
        or recommendation.get("recommended_actions")
        or metadata.get("corrective_action")
    )
    preventive = to_db_text(
        recommendation.get("preventive_maintenance")
        or recommendation.get("preventive_actions")
        or metadata.get("preventive_maintenance")
    )
    safety_precautions = to_db_text(
        recommendation.get("safety_precautions")
        or metadata.get("safety_precautions")
    )
    verification = to_db_text(
        recommendation.get("verification")
        or recommendation.get("verification_steps")
        or metadata.get("verification")
        or metadata.get("verification_steps")
    )

    # The current ML JSON does not always contain a dedicated verification field.
    # For unrestricted mock cases, create one case-specific check from the retrieved symptom.
    if not restricted_guidance and not verification:
        verification_target = to_db_text(metadata.get("observed_symptoms") or metadata.get("failure_mode"))
        if verification_target:
            verification = f"Verify that the reported condition is resolved: {verification_target}."

    if restricted_guidance:
        corrective = None
        preventive = None
        verification = None

    asset_source = (
    metadata.get("hotel_asset")
    or selected.get("hotel_asset")
    or pr.get("hotel_asset")
    or classification.get("category")
    or selected.get("category")
)

    return {
        "case_id": payload.get("case_id") or pr.get("complaint_id"),
        "guest_phone": to_db_text(guest_phone),
        "guest_chat_id": to_db_text(guest_chat_id),
        "guest_message_id": to_db_text(guest_message_id),
        "room": payload.get("room_id") or pr.get("room_id") or "Not provided",
        "issue": payload.get("complaint_text") or pr.get("complaint_text") or "",
        "hotel_asset": asset_with_code(
        metadata.get("hotel_asset")
        or selected.get("hotel_asset")
        or pr.get("hotel_asset")
        or classification.get("category")
        or selected.get("category")
        or payload.get("hotel_asset")
        ),
        "component": component_with_code(
        metadata.get("component")
        or selected.get("component")
        or pr.get("component")
        or classification.get("component")
        or payload.get("component")
        ),
        "failure_mode": failure_mode_with_code(
            metadata.get("failure_mode"),
            metadata.get("component") or selected.get("component") or pr.get("component") or classification.get("component") or payload.get("component")
        ),
        "symptoms": to_db_text(metadata.get("observed_symptoms")),
        "root": to_db_text(metadata.get("possible_root_cause")),
        "corrective": corrective,
        "preventive": preventive,
        "safety_precautions": safety_precautions,
        "verification": verification,
        "severity": severity,
        "priority": priority,
        "safety": metadata.get("safety_flag", governance.get("kb_safety_flag", False)),
        "confidence": classification.get("confidence"),
        "human": human or out_of_kb,
        "auto": auto,
        "reason": reason,
        "status": status,
    }


def choose_technician(cur, asset: str):
    """Choose one normal technician with zero active tasks from the canonical department."""
    department = clean_asset(asset)
    cur.execute(
        """SELECT t.technician_id
           FROM technicians t
           LEFT JOIN (
             SELECT technician_id, COUNT(*) active_tasks
             FROM technician_tasks
             WHERE task_status IN ('Assigned','Waiting Technician Acceptance','Accepted','In Progress','Repair In Progress','Started')
               AND technician_id IS NOT NULL AND technician_id<>''
             GROUP BY technician_id
           ) a ON a.technician_id=t.technician_id
           WHERE t.is_active=1 AND t.role='Technician'
             AND LOWER(COALESCE(t.status,'')) NOT LIKE '%%leave%%'
             AND LOWER(COALESCE(t.status,'')) NOT IN ('inactive','disabled')
             AND t.department=%s
             AND COALESCE(t.active_tasks,0)=0
             AND COALESCE(a.active_tasks,0)=0
           ORDER BY t.technician_id LIMIT 1 FOR UPDATE""",
        (department,),
    )
    return cur.fetchone()


def upsert_case(cur, data: dict[str, Any]):
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    cur.execute(
        """INSERT INTO cases
           (case_id,room_id,issue_summary,issue,hotel_asset,component,severity,priority,source_module)
           VALUES(%s,%s,%s,%s,%s,%s,%s,%s,'SmartOps FastAPI')
           ON DUPLICATE KEY UPDATE
             room_id=VALUES(room_id), issue_summary=VALUES(issue_summary), issue=VALUES(issue),
             hotel_asset=VALUES(hotel_asset), component=VALUES(component), severity=VALUES(severity),
             priority=VALUES(priority), source_module=VALUES(source_module)""",
        (data["case_id"], data["room"], data["issue"], data["issue"], data["hotel_asset"], data["component"], data["severity"], data["priority"]),
    )

    cur.execute(
        """INSERT INTO smart_maintenance_tickets
           (case_id,room_id,guest_phone,guest_chat_id,guest_message_id,created_at,cleaned_comment,hotel_asset,failure_mode,component,
            observed_symptoms,possible_root_cause,safety_precautions,verification,corrective_action,preventive_maintenance,
            severity_level,priority_level,safety,escalation_required,human_approval_required,
            ticket_status,ai_confidence)
           VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
           ON DUPLICATE KEY UPDATE
             room_id=VALUES(room_id), guest_phone=VALUES(guest_phone), guest_chat_id=VALUES(guest_chat_id), guest_message_id=VALUES(guest_message_id),
             cleaned_comment=VALUES(cleaned_comment), hotel_asset=VALUES(hotel_asset),
             failure_mode=VALUES(failure_mode), component=VALUES(component), observed_symptoms=VALUES(observed_symptoms),
             possible_root_cause=VALUES(possible_root_cause), safety_precautions=VALUES(safety_precautions),
             verification=VALUES(verification), corrective_action=VALUES(corrective_action),
             preventive_maintenance=VALUES(preventive_maintenance), severity_level=VALUES(severity_level),
             priority_level=VALUES(priority_level), safety=VALUES(safety), escalation_required=VALUES(escalation_required),
             human_approval_required=VALUES(human_approval_required), ticket_status=VALUES(ticket_status), ai_confidence=VALUES(ai_confidence)""",
        (
            data["case_id"], data["room"], data.get("guest_phone"), data.get("guest_chat_id"), data.get("guest_message_id"), now,
            data["issue"], data["hotel_asset"], data["failure_mode"],
            data["component"], data["symptoms"], data["root"], data["safety_precautions"], data["verification"],
            data["corrective"], data["preventive"], data["severity"], data["priority"],
            str(bool(data["safety"])).lower(), str(data["human"]).lower(),
            str(data["human"]).lower(), "HITL Required" if data["human"] else "AI Auto Approved", data["confidence"],
        ),
    )

    if data["human"]:
        cur.execute(
            """INSERT INTO hitl_cases
               (case_id,room,issue_summary,issue,hotel_asset,component,severity,priority,
                hitl_reason,safety_flag,review_status,created_at,failure_mode,observed_symptoms,possible_root_cause)
               VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,'Pending Review',%s,%s,%s,%s)
               ON DUPLICATE KEY UPDATE
                 room=VALUES(room), issue_summary=VALUES(issue_summary), issue=VALUES(issue), hotel_asset=VALUES(hotel_asset),
                 component=VALUES(component), severity=VALUES(severity), priority=VALUES(priority), hitl_reason=VALUES(hitl_reason),
                 safety_flag=VALUES(safety_flag), review_status=COALESCE(NULLIF(review_status,''),'Pending Review'), failure_mode=VALUES(failure_mode),
                 observed_symptoms=VALUES(observed_symptoms), possible_root_cause=VALUES(possible_root_cause)""",
            (
                data["case_id"], data["room"], data["issue"], data["issue"], data["hotel_asset"], data["component"],
                data["severity"], data["priority"], data["reason"], str(bool(data["safety"])).lower(), now,
                data["failure_mode"], data["symptoms"], data["root"],
            ),
        )
        return None

    cur.execute(
        "SELECT assigned_technician_id,assigned_at,work_stage,assignment_round FROM workforce_cases WHERE case_id=%s FOR UPDATE",
        (data["case_id"],),
    )
    existing = cur.fetchone() or {}
    existing_stage = str(existing.get("work_stage") or "")
    existing_tech = existing.get("assigned_technician_id")

    # Do not reset an already assigned, supported, outsourced, or completed workflow.
    if existing and existing_stage not in {"", "Pending Assignment"}:
        return existing_tech

    tech = choose_technician(cur, data["hotel_asset"]) if data["auto"] and not existing_tech else None
    technician_id = existing_tech or (tech["technician_id"] if tech else None)
    assigned_at = existing.get("assigned_at") or (now if technician_id else None)
    stage = "Assigned - Not Started" if technician_id else "Pending Assignment"
    assignment_round = int(existing.get("assignment_round") or 0)
    if technician_id and not existing_tech:
        assignment_round += 1

    cur.execute(
        """INSERT INTO workforce_cases
           (case_id,room,issue_summary,issue,hotel_asset,component,severity,priority,work_stage,task_status,
            assigned_technician_id,assigned_at,technician_id,safety_flag,hitl_status,case_created_at,assignment_round,
            response_target_minutes,repair_target_minutes,overall_sla_status,sla_status)
           VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,'AI Automation',%s,%s,'30','90','Pending','Pending')
           ON DUPLICATE KEY UPDATE
             room=VALUES(room),issue_summary=VALUES(issue_summary),issue=VALUES(issue),hotel_asset=VALUES(hotel_asset),
             component=VALUES(component),severity=VALUES(severity),priority=VALUES(priority),work_stage=VALUES(work_stage),
             task_status=VALUES(task_status),assigned_technician_id=VALUES(assigned_technician_id),assigned_at=VALUES(assigned_at),
             technician_id=VALUES(technician_id),safety_flag=VALUES(safety_flag),hitl_status=VALUES(hitl_status),
             case_created_at=COALESCE(case_created_at,VALUES(case_created_at)),assignment_round=VALUES(assignment_round)""",
        (
            data["case_id"], data["room"], data["issue"], data["issue"], data["hotel_asset"], data["component"],
            data["severity"], data["priority"], stage, "Assigned" if technician_id else "Pending Assignment",
            technician_id, assigned_at, technician_id, str(bool(data["safety"])).lower(), now, assignment_round,
        ),
    )

    cur.execute(
        """INSERT INTO technician_tasks
           (case_id,technician_id,room,issue_summary,issue,priority,severity,escalation_trigger,task_status,assigned_time,
            response_target_minutes,repair_target_minutes,response_sla_status,repair_sla_status,overall_sla_status,sla_status,
            safety_flag,component,failure_mode,observed_symptoms,possible_root_cause,corrective_action,preventive_maintenance,
            verification,hotel_asset,assigned_technician_id,case_created_at,assignment_round)
           VALUES(%s,%s,%s,%s,%s,%s,%s,'AI Automation',%s,%s,'30','90','Pending','Pending','Pending','Pending',%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
           ON DUPLICATE KEY UPDATE
             technician_id=VALUES(technician_id),room=VALUES(room),issue_summary=VALUES(issue_summary),issue=VALUES(issue),
             priority=VALUES(priority),severity=VALUES(severity),task_status=VALUES(task_status),assigned_time=VALUES(assigned_time),
             response_target_minutes='30',repair_target_minutes='90',response_sla_status='Pending',repair_sla_status='Pending',
             overall_sla_status='Pending',sla_status='Pending',safety_flag=VALUES(safety_flag),component=VALUES(component),
             failure_mode=VALUES(failure_mode),observed_symptoms=VALUES(observed_symptoms),possible_root_cause=VALUES(possible_root_cause),
             corrective_action=VALUES(corrective_action),preventive_maintenance=VALUES(preventive_maintenance),verification=VALUES(verification),
             hotel_asset=VALUES(hotel_asset),assigned_technician_id=VALUES(assigned_technician_id),
             case_created_at=COALESCE(case_created_at,VALUES(case_created_at)),assignment_round=VALUES(assignment_round)""",
        (
            data["case_id"], technician_id, data["room"], data["issue"], data["issue"], data["priority"], data["severity"],
            "Assigned" if technician_id else "Pending Assignment", assigned_at, str(bool(data["safety"])).lower(), data["component"],
            data["failure_mode"], data["symptoms"], data["root"], data["corrective"], data["preventive"], data["verification"],
            data["hotel_asset"], technician_id, now, assignment_round,
        ),
    )

    if technician_id and not existing_tech:
        cur.execute(
            "INSERT INTO task_assignment_history (case_id,technician_id,assignment_round,assigned_at,response_sla_status) VALUES(%s,%s,%s,%s,'Pending') ON DUPLICATE KEY UPDATE technician_id=VALUES(technician_id),assigned_at=VALUES(assigned_at),ended_at=NULL,end_status=NULL",
            (data["case_id"], technician_id, assignment_round, assigned_at),
        )
        cur.execute(
            "UPDATE technicians SET status='Busy',active_tasks=1,total_tasks=COALESCE(total_tasks,0)+1,workload_percent=100,warning_reason='Currently handling one active task' WHERE technician_id=%s",
            (technician_id,),
        )

    return technician_id


@app.get("/health")
def health():
    try:
        connection = db()
        cursor = connection.cursor()
        cursor.execute("SELECT DATABASE() db, COUNT(*) technicians FROM technicians")
        row = cursor.fetchone()
        connection.close()
        options = mysql_connection_options()
        return {
            "status": "ok",
            "mode": "live",
            "database": row["db"],
            "technicians": row["technicians"],
            "connection": "unix_socket" if "unix_socket" in options else "tcp",
        }
    except Exception as error:
        raise HTTPException(500, str(error)) from error



def assign_pending_workforce_case(cur, case: dict[str, Any]) -> str | None:
    tech = choose_technician(cur, str(case.get("hotel_asset") or ""))
    if not tech:
        return None

    technician_id = str(tech["technician_id"])
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    assignment_round = int(case.get("assignment_round") or 0) + 1
    case_id = str(case["case_id"])

    cur.execute(
        """UPDATE workforce_cases
           SET assigned_technician_id=%s, technician_id=%s, assigned_at=%s,
               work_stage='Assigned - Not Started', task_status='Assigned',
               assignment_round=%s, response_sla_status='Pending', repair_sla_status='Pending',
               overall_sla_status='Pending', sla_status='Pending'
           WHERE case_id=%s AND COALESCE(assigned_technician_id,'')=''
             AND COALESCE(work_stage,'')='Pending Assignment'""",
        (technician_id, technician_id, now, assignment_round, case_id),
    )
    if cur.rowcount != 1:
        return None

    cur.execute(
        """UPDATE technician_tasks
           SET technician_id=%s, assigned_technician_id=%s, assigned_time=%s,
               task_status='Assigned', escalation_trigger='AI Automation', assignment_round=%s,
               response_sla_status='Pending', repair_sla_status='Pending',
               overall_sla_status='Pending', sla_status='Pending'
           WHERE case_id=%s""",
        (technician_id, technician_id, now, assignment_round, case_id),
    )
    cur.execute(
        """INSERT INTO task_assignment_history
           (case_id,technician_id,assignment_round,assigned_at,response_sla_status,event_note)
           VALUES(%s,%s,%s,%s,'Pending','FastAPI automatic assignment')
           ON DUPLICATE KEY UPDATE technician_id=VALUES(technician_id), assigned_at=VALUES(assigned_at),
             accepted_at=NULL, started_at=NULL, ended_at=NULL, end_status=NULL,
             response_sla_status='Pending', event_note=VALUES(event_note)""",
        (case_id, technician_id, assignment_round, now),
    )
    cur.execute(
        """UPDATE technicians
           SET status='Busy', active_tasks=1, total_tasks=COALESCE(total_tasks,0)+1,
               workload_percent=100, warning_reason='Currently handling one active task'
           WHERE technician_id=%s""",
        (technician_id,),
    )
    cur.execute(
        """UPDATE smart_maintenance_tickets
           SET technician_assigned_timestamp=%s, technician_assigned=%s, assigned_to=%s,
               ticket_status='Assigned - Not Started'
           WHERE case_id=%s""",
        (now, technician_id, technician_id, case_id),
    )
    return technician_id


def run_auto_assignment() -> dict[str, Any]:
    """Assign every pending AI Automation case and clean stale HITL rows first."""
    connection = db()
    assigned: list[dict[str, str]] = []
    unavailable: list[str] = []
    try:
        cursor = connection.cursor()

        # The ML routing decision is authoritative. A stale HITL row must never
        # block a case whose master source_module is AI Automation.
        cursor.execute(
            """DELETE h FROM hitl_cases h
               INNER JOIN cases c ON c.case_id=h.case_id
               WHERE c.source_module='AI Automation'"""
        )

        cursor.execute(
            """SELECT w.*
               FROM workforce_cases w
               INNER JOIN cases c ON c.case_id=w.case_id
               LEFT JOIN smart_maintenance_tickets s ON s.case_id=w.case_id
               WHERE COALESCE(w.work_stage,'')='Pending Assignment'
                 AND COALESCE(w.assigned_technician_id,'')=''
                 AND (
                      c.source_module='AI Automation'
                      OR COALESCE(s.human_approval_required,'false')='false'
                 )
               ORDER BY COALESCE(w.case_created_at,'9999-12-31'), w.case_id
               FOR UPDATE"""
        )
        cases = cursor.fetchall()
        for case in cases:
            technician_id = assign_pending_workforce_case(cursor, case)
            if technician_id:
                assigned.append({"case_id": str(case["case_id"]), "technician_id": technician_id})
            else:
                unavailable.append(str(case["case_id"]))
        connection.commit()
        return {
            "success": True,
            "pending_found": len(cases),
            "assigned_count": len(assigned),
            "assigned": assigned,
            "still_pending": unavailable,
        }
    except Exception:
        connection.rollback()
        raise
    finally:
        connection.close()


@app.on_event("startup")
def assign_pending_on_startup() -> None:
    # Starting FastAPI is enough to dispatch existing AI Automation cases.
    # A temporary database-startup delay should not prevent the API itself.
    try:
        run_auto_assignment()
    except Exception as error:
        print(f"SmartOps startup auto-assignment skipped: {error}")

    # Also capture any cases that already existed before this upgraded version
    # was started. Later complaints refresh the same SQL automatically.
    try:
        result = export_database_snapshot(
            mysql_connection_options(),
            case_id="fastapi-startup",
        )
        if result.get("success"):
            print(f"SmartOps SQL snapshot ready: {result.get('version')}")
    except Exception as error:
        print(f"SmartOps startup SQL snapshot skipped: {error}")


@app.post("/api/v1/auto-assign-pending")
def auto_assign_pending():
    try:
        return run_auto_assignment()
    except Exception as error:
        raise HTTPException(500, str(error)) from error


def save_pipeline_payload(payload: dict[str, Any]) -> dict[str, Any]:
    data = extract(payload)
    if not data["case_id"]:
        raise HTTPException(422, "case_id is required")

    connection = db()
    try:
        cursor = connection.cursor()
        technician_id = upsert_case(cursor, data)
        connection.commit()

        # Keep the packaged SQL snapshot current. The complaint remains saved
        # even when the local SQL file cannot be refreshed.
        try:
            snapshot_result = export_database_snapshot(
                mysql_connection_options(),
                case_id=data["case_id"],
            )
        except Exception as snapshot_error:
            snapshot_result = {
                "success": False,
                "error": str(snapshot_error),
            }
            print(f"SmartOps SQL snapshot update failed: {snapshot_error}")

        return {
            "success": True,
            "case_id": data["case_id"],
            "route": "HITL" if data["human"] else "AI Automation",
            "hitl_reason": data["reason"] if data["human"] else None,
            "assigned_technician_id": technician_id,
            "work_stage": None if data["human"] else (
                "Assigned - Not Started" if technician_id else "Pending Assignment"
            ),
            "sql_snapshot_updated": bool(snapshot_result.get("success")),
            "sql_snapshot": snapshot_result,
        }
    except HTTPException:
        connection.rollback()
        raise
    except Exception as error:
        connection.rollback()
        raise HTTPException(500, str(error)) from error
    finally:
        connection.close()


@app.post("/api/v1/cases/process")
def process(payload: PipelineEnvelope):
    return save_pipeline_payload(payload.model_dump())


@app.post("/api/v1/snapshot/refresh")
def refresh_sql_snapshot():
    """Manually refresh the packaged SQL after non-complaint status changes."""
    try:
        result = export_database_snapshot(
            mysql_connection_options(),
            case_id="manual-refresh",
        )
        return {"success": bool(result.get("success")), "snapshot": result}
    except Exception as error:
        raise HTTPException(500, str(error)) from error


@app.post("/api/v1/ml/process-complaint")
async def process_complaint_with_ml(payload: MLComplaintRequest):
    """Send one complaint to the ML Team API, then save and route its result."""
    url = ml_api_url()
    if not url:
        raise HTTPException(500, "ML_API_URL is not configured")

    timeout_seconds = float(os.getenv("ML_API_TIMEOUT_SECONDS", "120"))
    api_key = os.getenv("ML_API_KEY", "").strip()
    headers = {"Content-Type": "application/json"}
    if api_key:
        headers["Authorization"] = f"Bearer {api_key}"

    try:
        async with httpx.AsyncClient(timeout=timeout_seconds) as client:
            response = await client.post(
                url,
                json=build_ml_request(payload),
                headers=headers,
            )
    except httpx.ConnectError as error:
        raise HTTPException(
            502,
            f"Cannot connect to ML API at {url}. Confirm the ML service is running on port 8000.",
        ) from error
    except httpx.TimeoutException as error:
        raise HTTPException(504, f"ML API timed out after {timeout_seconds:g} seconds") from error
    except httpx.HTTPError as error:
        raise HTTPException(502, f"ML API request failed: {error}") from error

    if response.status_code >= 400:
        detail = response.text[:1000]
        raise HTTPException(
            502,
            f"ML API returned HTTP {response.status_code}: {detail}",
        )

    try:
        ml_result = response.json()
    except ValueError as error:
        raise HTTPException(502, "ML API did not return valid JSON") from error

    if not isinstance(ml_result, dict):
        raise HTTPException(502, "ML API response must be a JSON object")

    # Preserve the original SmartOps identifiers when the ML response omits them.
    integrated_payload = dict(ml_result)
    integrated_payload.setdefault("case_id", payload.case_id)
    integrated_payload.setdefault("room_id", payload.room_id)
    integrated_payload.setdefault("complaint_text", payload.complaint_text)

    database_result = save_pipeline_payload(integrated_payload)
    return {
        **database_result,
        "ml_connected": True,
        "ml_http_status": response.status_code,
        "database_saved": True,
        "ml_api_url": url,
    }


@app.post("/api/v1/import-jsonl")
def import_jsonl():
    path = Path(os.getenv("JSONL_PATH", "../data/whole_pipeline_results.jsonl")).resolve()
    if not path.exists():
        raise HTTPException(404, f"JSONL not found: {path}")

    results = []
    for line in path.read_text(encoding="utf-8").splitlines():
        if not line.strip():
            continue
        try:
            payload = PipelineEnvelope.model_validate(json.loads(line))
            results.append(process(payload))
        except Exception as error:
            results.append({"success": False, "error": str(error)})

    return {
        "processed": len(results),
        "success": sum(1 for item in results if item.get("success")),
        "results": results,
    }
