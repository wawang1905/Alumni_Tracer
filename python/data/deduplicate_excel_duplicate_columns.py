#!/usr/bin/env python3
"""
Deduplicate duplicate Excel columns by identical values.

Rules:
- If a column header appears multiple times:
  - Keep the first occurrence.
  - For later occurrences, compare values row-by-row to the first.
    - If identical (treating NaN and empty strings as equivalent), drop it.
    - If not identical, rename it with a suffix so the output columns remain unique.
"""

from __future__ import annotations

import argparse
from pathlib import Path
from typing import List, Dict

import numpy as np
import pandas as pd


def columns_identical(df: pd.DataFrame, idx_a: int, idx_b: int) -> bool:
    """Compare two columns by positional index."""
    sentinel = "__ALUMNI_TRACER_NA__"

    s1 = df.iloc[:, idx_a]
    s2 = df.iloc[:, idx_b]

    def prep(s: pd.Series) -> np.ndarray:
        if s.dtype == object:
            mask = s.isna()
            v = s.astype(str).str.strip()
            v = v.mask(mask | (v == ""), sentinel)
            return v.to_numpy()

        mask = s.isna()
        v = s.astype("object").where(~mask, sentinel)
        return v.to_numpy()

    a = prep(s1)
    b = prep(s2)
    return np.array_equal(a, b)


def dedupe_duplicate_columns(df: pd.DataFrame) -> tuple[pd.DataFrame, Dict[str, object]]:
    """
    Return (clean_df, report) where report includes which columns were dropped/renamed.
    """
    original_columns: List[str] = list(df.columns)
    duplicates = []
    seen = set()
    for c in original_columns:
        if original_columns.count(c) > 1 and c not in seen:
            duplicates.append(c)
            seen.add(c)

    dropped: List[str] = []
    renamed: Dict[str, str] = {}

    df_out = df.copy()
    # We'll build a new column name list to avoid issues with duplicate labels.
    new_names = list(original_columns)

    for header in duplicates:
        idxs = [i for i, c in enumerate(original_columns) if c == header]
        canonical = idxs[0]
        for i in idxs[1:]:
            if columns_identical(df_out, canonical, i):
                dropped.append(f"{header}[{i}]")
                new_names[i] = None  # mark for dropping by later filter
            else:
                # Rename non-identical duplicates to keep output valid/usable.
                j = i
                candidate = f"{header}__DUP{j}"
                while candidate in new_names:
                    j += 1
                    candidate = f"{header}__DUP{j}"
                renamed[f"{header}[{i}]"] = candidate
                new_names[i] = candidate

    # Drop columns marked None
    keep_indices = [i for i, n in enumerate(new_names) if n is not None]
    df_out = df_out.iloc[:, keep_indices].copy()
    df_out.columns = [new_names[i] for i in keep_indices]

    report = {
        "duplicate_headers": duplicates,
        "dropped": dropped,
        "renamed": renamed,
        "original_column_count": len(original_columns),
        "final_column_count": len(df_out.columns),
    }
    return df_out, report


def main() -> None:
    parser = argparse.ArgumentParser(description="Deduplicate duplicate Excel columns by identical values.")
    parser.add_argument("input_excel", help="Path to input .xlsx/.xls file")
    parser.add_argument(
        "--output",
        default=None,
        help="Output file (.xlsx or .csv). Defaults to <input>_deduped.xlsx or .csv.",
    )
    parser.add_argument("--sheet", default=0, help="Sheet index or name (default: 0)")
    args = parser.parse_args()

    input_path = Path(args.input_excel)
    if not input_path.exists():
        raise SystemExit(f"Input file not found: {input_path}")

    output_path = Path(args.output) if args.output else None
    if output_path is None:
        # Keep extension style if possible.
        suffix = ".xlsx" if input_path.suffix.lower() in {".xlsx", ".xls"} else ".csv"
        output_path = input_path.with_name(input_path.stem + "_deduped" + suffix)

    df = pd.read_excel(str(input_path), sheet_name=args.sheet)
    df_clean, report = dedupe_duplicate_columns(df)

    if output_path.suffix.lower() in {".xlsx", ".xls"}:
        df_clean.to_excel(str(output_path), index=False)
    else:
        df_clean.to_csv(str(output_path), index=False)

    print("Done.")
    print(report)
    print(f"Saved: {output_path}")


if __name__ == "__main__":
    main()

