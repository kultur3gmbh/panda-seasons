#!/usr/bin/env python3
import json
import re
from pathlib import Path

root = Path(__file__).resolve().parent.parent
plugin = root / "panda-seasons"

backup = json.loads((root / "backups" / "casa-pepe-calendar-3299-v0.6.4.json").read_text(encoding="utf-8"))
calendar = backup.get("calendar", {})
doors = calendar.get("doors", [])
if calendar.get("id") != 3299 or len(doors) != 24:
    raise SystemExit("The pre-migration calendar snapshot is incomplete")
if sorted(int(door["settings"]["door_number"]) for door in doors) != list(range(1, 25)):
    raise SystemExit("The pre-migration snapshot does not contain doors 1 through 24")

for forbidden in ("subscriptions", "subscriber", "mail_log", "consent_events"):
    if forbidden in json.dumps(backup).lower():
        raise SystemExit(f"Private delivery data leaked into snapshot: {forbidden}")

for removed in ("admin.css", "admin-041.css", "frontend.css", "frontend-041.css"):
    if (plugin / "assets" / removed).exists():
        raise SystemExit(f"Legacy stylesheet still exists: {removed}")
for current in ("editor.css", "calendar.css"):
    if not (plugin / "assets" / current).exists():
        raise SystemExit(f"Consolidated stylesheet missing: {current}")

runtime = "\n".join(
    path.read_text(encoding="utf-8")
    for path in (plugin / "includes").glob("*.php")
    if path.name != "class-panda-seasons-migrator.php"
)
for legacy in (
    "_ps_calendar_id",
    "_ps_unlock_behavior",
    "_ps_availability_window",
    "_ps_quiz_question",
    "_ps_quiz_answers",
    "_ps_quiz_correct",
    "_ps_closed_opacity",
    "_ps_open_opacity",
):
    if re.search(r"['\"]" + re.escape(legacy) + r"['\"]", runtime):
        raise SystemExit(f"Legacy field is still used at runtime: {legacy}")

print("Panda Seasons consolidation checks passed.")
