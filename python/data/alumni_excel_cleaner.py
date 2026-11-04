#!/usr/bin/env python3
"""
Cleaner utilities for alumni Excel uploads.

Goal:
- Clean/deduplicate duplicate columns on the uploaded Excel.
- Clean/normalize records before inserting into the database.
"""

from __future__ import annotations

from typing import Any, Dict, List, Optional, Tuple

import numpy as np

from data.excel_import_processor import ExcelImportProcessor


def clean_uploaded_excel(
    file_path: str,
    duplicate_strategy: str = "first",
    processor: Optional[ExcelImportProcessor] = None,
) -> Dict[str, Any]:
    """
    Clean an uploaded Excel file and return the same result structure
    as `ExcelImportProcessor.process_excel_file()`.
    """
    if processor is None:
        processor = ExcelImportProcessor()
    return processor.process_excel_file(file_path, duplicate_strategy)


def _clean_str(value: Any) -> str:
    """Trim strings and normalize NaN/None to empty string."""
    if value is None:
        return ""
    if isinstance(value, float) and np.isnan(value):
        return ""
    s = str(value).strip()
    return "" if s.lower() == "nan" else s


def _normalize_status(value: Any) -> str:
    """Normalize status values to {active,pending,inactive}."""
    s = _clean_str(value).lower()
    if s in {"active", "act", "a"}:
        return "active"
    if s in {"pending", "pend", "p"}:
        return "pending"
    if s in {"inactive", "inact", "i"}:
        return "inactive"
    # Default matches existing ExcelImportProcessor behavior
    return "active"


def clean_alumni_records_for_db(
    alumni_records: List[Dict[str, Any]],
) -> Tuple[List[Dict[str, Any]], Dict[str, Any]]:
    """
    Clean/normalize records before database insertion.

    - Drop rows with missing required values (email/firstName/lastName).
    - Normalize email to lowercase.
    - Normalize status values.
    - Deduplicate by email (keep first).
    """
    original_count = len(alumni_records)

    seen_emails = set()
    cleaned: List[Dict[str, Any]] = []
    dropped_empty_required = 0
    dropped_duplicate_rows = 0

    for rec in alumni_records:
        first = _clean_str(rec.get("firstName", ""))
        last = _clean_str(rec.get("lastName", ""))
        email = _clean_str(rec.get("email", "")).lower()

        # Required values for DB insert (table has NOT NULL on first/last and UNIQUE on email)
        if not email or not first or not last:
            dropped_empty_required += 1
            continue

        if email in seen_emails:
            dropped_duplicate_rows += 1
            continue

        seen_emails.add(email)

        cleaned.append(
            {
                "firstName": first,
                "lastName": last,
                "email": email,
                "graduationYear": _clean_str(rec.get("graduationYear", "")),
                "status": _normalize_status(rec.get("status", "active")),
                "college": _clean_str(rec.get("college", "")),
                "course": _clean_str(rec.get("course", "")),
                "phone": _clean_str(rec.get("phone", "")),
                "address": _clean_str(rec.get("address", "")),
                "city": _clean_str(rec.get("city", "")),
                "country": _clean_str(rec.get("country", "")),
                "linkedin": _clean_str(rec.get("linkedin", "")),
                "employment_status": _clean_str(rec.get("employment_status", "")),
                "company": _clean_str(rec.get("company", "")),
                "job_title": _clean_str(rec.get("job_title", "")),
            }
        )

    stats = {
        "original_records": original_count,
        "cleaned_records": len(cleaned),
        "dropped_empty_required": dropped_empty_required,
        "dropped_duplicate_rows_by_email": dropped_duplicate_rows,
    }
    return cleaned, stats

