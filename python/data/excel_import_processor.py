#!/usr/bin/env python3
"""
Excel Import Processor for Alumni Management
Handles Excel file import with duplicate column detection and deduplication
"""

import pandas as pd
import numpy as np
from typing import Dict, List, Any, Tuple, Optional
import re
from datetime import datetime
import warnings
warnings.filterwarnings('ignore')

class ExcelImportProcessor:
    """Process Excel files for alumni import with duplicate column handling"""
    
    def __init__(self):
        self.required_columns = ['firstName', 'lastName', 'email']
        self.optional_columns = [
            'graduationYear', 'status', 'college', 'course', 'phone', 
            'address', 'city', 'country', 'linkedin', 'employment_status', 
            'company', 'job_title'
        ]
        self.column_mapping = {
            # Common variations of column names
            'first_name': 'firstName',
            'firstname': 'firstName',
            'first name': 'firstName',
            'last_name': 'lastName',
            'lastname': 'lastName',
            'last name': 'lastName',
            'email_address': 'email',
            'emailaddress': 'email',
            'graduation_year': 'graduationYear',
            'graduationyear': 'graduationYear',
            'graduation year': 'graduationYear',
            'batch': 'graduationYear',
            'year': 'graduationYear',
            'college_name': 'college',
            'collegename': 'college',
            'course_name': 'course',
            'coursename': 'course',
            'phone_number': 'phone',
            'phonenumber': 'phone',
            'mobile': 'phone',
            'contact': 'phone',
            'emp_status': 'employment_status',
            'employmentstatus': 'employment_status',
            'job_status': 'employment_status',
            'company_name': 'company',
            'companyname': 'company',
            'job_title': 'job_title',
            'jobtitle': 'job_title',
            'position': 'job_title',
            'designation': 'job_title'
        }
    
    def detect_duplicate_columns(self, df: pd.DataFrame) -> Dict[str, List[str]]:
        """Detect duplicate columns in the DataFrame"""
        duplicate_info = {}
        column_names = df.columns.tolist()
        
        # Group columns by their normalized names
        normalized_columns = {}
        for col in column_names:
            normalized_name = self.normalize_column_name(col)
            if normalized_name not in normalized_columns:
                normalized_columns[normalized_name] = []
            normalized_columns[normalized_name].append(col)
        
        # Find duplicates
        for normalized_name, columns in normalized_columns.items():
            if len(columns) > 1:
                duplicate_info[normalized_name] = columns
        
        return duplicate_info
    
    def normalize_column_name(self, column_name: str) -> str:
        """Normalize column name for comparison"""
        # Convert to lowercase and remove spaces/underscores
        normalized = re.sub(r'[_\s]+', '', str(column_name).lower().strip())
        
        # Apply column mapping if available
        if normalized in self.column_mapping:
            return self.column_mapping[normalized]
        
        return normalized
    
    def resolve_duplicate_columns(self, df: pd.DataFrame, duplicate_info: Dict[str, List[str]], 
                                strategy: str = 'first') -> Tuple[pd.DataFrame, Dict[str, Any]]:
        """Resolve duplicate columns using specified strategy.

        For `strategy='first'`: keep the first occurrence; drop subsequent columns
        only if their values are identical to the first column.
        """
        resolution_details: Dict[str, Any] = {}

        # Work index-based to handle truly duplicate headers (pandas drops by label
        # will remove *all* same-labeled columns, not just one occurrence).
        ncols = len(df.columns)
        normalized_by_index = [self.normalize_column_name(df.columns[i]) for i in range(ncols)]

        # This rename map is index-based: original column index -> new column label.
        # We apply it only to columns we keep.
        rename_by_index: Dict[int, str] = {}
        drop_indices: set[int] = set()

        for normalized_name in duplicate_info.keys():
            indices = [i for i in range(ncols) if normalized_by_index[i] == normalized_name]
            if len(indices) <= 1:
                continue

            resolution_details[normalized_name] = {
                'dropped_indices': [],
                # index -> new_name (kept because values differ)
                'renamed_by_index': {},
            }

            if strategy == 'first':
                canonical_idx = indices[0]
                for idx in indices[1:]:
                    if self.are_duplicate_columns_identical_indices(df, canonical_idx, idx):
                        drop_indices.add(idx)
                        resolution_details[normalized_name]['dropped_indices'].append(idx)
                    else:
                        # Rename the kept duplicate to ensure columns labels remain unique.
                        base = str(df.columns[idx])
                        candidate = f"{base}__DUP{idx}"
                        used_names = set(
                            rename_by_index.get(k, str(df.columns[k]))
                            for k in range(ncols)
                            if k not in drop_indices
                        )
                        k = 1
                        while candidate in used_names:
                            candidate = f"{base}__DUP{idx}_{k}"
                            k += 1
                        rename_by_index[idx] = candidate
                        resolution_details[normalized_name]['renamed_by_index'][idx] = candidate

            elif strategy == 'last':
                canonical_idx = indices[-1]
                for idx in indices[:-1]:
                    drop_indices.add(idx)
                    resolution_details[normalized_name]['dropped_indices'].append(idx)

            elif strategy == 'merge':
                # Merge all duplicates into the canonical (first) one, then drop the rest.
                canonical_idx = indices[0]
                merged_series = pd.Series(index=df.index, dtype=object)
                for idx in indices:
                    s = df.iloc[:, idx]
                    if s.dtype == object:
                        valid = s.notna() & s.astype(str).str.strip().ne('')
                    else:
                        valid = s.notna()
                    # Only fill rows we haven't already filled (preserve first non-empty).
                    set_mask = valid & merged_series.isna()
                    merged_series[set_mask] = s[set_mask]
                df.iloc[:, canonical_idx] = merged_series
                for idx in indices[1:]:
                    drop_indices.add(idx)
                    resolution_details[normalized_name]['dropped_indices'].append(idx)

            else:
                # Safety fallback
                pass

        keep_indices = [i for i in range(ncols) if i not in drop_indices]
        df_cleaned = df.iloc[:, keep_indices].copy()

        # Apply index-based renames (only for kept columns).
        new_columns = []
        for i in keep_indices:
            new_columns.append(rename_by_index.get(i, str(df.columns[i])))
        df_cleaned.columns = new_columns

        return df_cleaned, resolution_details

    def are_duplicate_columns_identical_indices(self, df: pd.DataFrame, idx_a: int, idx_b: int) -> bool:
        """Return True when two columns have identical values row-by-row.

        Treats NaN/empty strings as equivalent so common Excel "blank" patterns
        do not prevent deduping.
        """
        s1 = df.iloc[:, idx_a]
        s2 = df.iloc[:, idx_b]

        sentinel = '__ALUMNI_TRACER_NA__'

        def prep(s: pd.Series) -> np.ndarray:
            if s.dtype == object:
                mask = s.isna()
                v = s.astype(str).str.strip()
                v = v.mask(mask | (v == ''), sentinel)
                return v.to_numpy()

            mask = s.isna()
            v = s.astype('object').where(~mask, sentinel)
            return v.to_numpy()

        a = prep(s1)
        b = prep(s2)
        return np.array_equal(a, b)
    
    def merge_duplicate_columns(self, df: pd.DataFrame, duplicate_cols: List[str], target_name: str):
        """Merge duplicate columns by keeping first non-null value"""
        if len(duplicate_cols) < 2:
            return
        
        # Create a merged series
        merged_series = pd.Series(index=df.index, dtype=object)
        
        for idx in df.index:
            # Find first non-null value across duplicate columns
            for col in duplicate_cols:
                if pd.notna(df.loc[idx, col]) and str(df.loc[idx, col]).strip() != '':
                    merged_series[idx] = df.loc[idx, col]
                    break
        
        # Add merged column to DataFrame
        df[target_name] = merged_series
    
    def standardize_column_names(self, df: pd.DataFrame) -> pd.DataFrame:
        """Standardize column names according to mapping"""
        df_standardized = df.copy()
        
        # Create new column names
        new_columns = []
        for col in df.columns:
            normalized = self.normalize_column_name(col)
            if normalized in self.required_columns + self.optional_columns:
                new_columns.append(normalized)
            else:
                # Keep original if not in mapping
                new_columns.append(col)
        
        df_standardized.columns = new_columns
        
        return df_standardized
    
    def validate_required_columns(self, df: pd.DataFrame) -> Tuple[bool, List[str]]:
        """Validate that required columns are present"""
        missing_columns = []
        
        for required_col in self.required_columns:
            if required_col not in df.columns:
                missing_columns.append(required_col)
        
        return len(missing_columns) == 0, missing_columns
    
    def clean_data(self, df: pd.DataFrame) -> pd.DataFrame:
        """Clean and standardize data"""
        df_cleaned = df.copy()
        
        # Remove completely empty rows
        df_cleaned = df_cleaned.dropna(how='all')
        
        # Clean string columns
        for col in df_cleaned.columns:
            if df_cleaned[col].dtype == 'object':
                df_cleaned[col] = df_cleaned[col].astype(str).str.strip()
                # Replace empty strings with NaN
                df_cleaned[col] = df_cleaned[col].replace('', np.nan)
        
        # Standardize status values
        if 'status' in df_cleaned.columns:
            df_cleaned['status'] = df_cleaned['status'].str.lower()
            status_mapping = {
                'active': 'active', 'act': 'active', 'a': 'active',
                'pending': 'pending', 'pend': 'pending', 'p': 'pending',
                'inactive': 'inactive', 'inact': 'inactive', 'i': 'inactive'
            }
            df_cleaned['status'] = df_cleaned['status'].map(status_mapping).fillna('active')
        
        # Clean email format
        if 'email' in df_cleaned.columns:
            df_cleaned['email'] = df_cleaned['email'].str.lower().str.strip()
        
        # Clean graduation year
        if 'graduationYear' in df_cleaned.columns:
            df_cleaned['graduationYear'] = df_cleaned['graduationYear'].astype(str).str.extract(r'(\d{4})')
            df_cleaned['graduationYear'] = df_cleaned['graduationYear'].fillna('')
        
        return df_cleaned
    
    def process_excel_file(self, file_path: str, duplicate_strategy: str = 'first') -> Dict[str, Any]:
        """Process Excel file with duplicate column handling"""
        try:
            # Read Excel file
            df = pd.read_excel(file_path)
            
            if df.empty:
                return {
                    'success': False,
                    'error': 'Excel file is empty',
                    'data': None
                }
            
            original_columns = df.columns.tolist()
            
            # Step 1: Detect duplicate columns
            duplicate_info = self.detect_duplicate_columns(df)
            
            # Step 2: Resolve duplicate columns
            if duplicate_info:
                df, duplicate_columns_resolution = self.resolve_duplicate_columns(
                    df, duplicate_info, duplicate_strategy
                )
                resolved_columns = df.columns.tolist()
            else:
                resolved_columns = original_columns
                duplicate_columns_resolution = {}
            
            # Step 3: Standardize column names
            df = self.standardize_column_names(df)
            
            # Step 4: Validate required columns
            has_required, missing_cols = self.validate_required_columns(df)
            
            if not has_required:
                return {
                    'success': False,
                    'error': f'Missing required columns: {", ".join(missing_cols)}',
                    'data': None,
                    'duplicate_columns': duplicate_info,
                    'original_columns': original_columns,
                    'resolved_columns': resolved_columns
                }
            
            # Step 5: Clean data
            df = self.clean_data(df)
            
            # Step 6: Remove duplicate rows (based on email)
            if 'email' in df.columns:
                original_rows = len(df)
                df = df.drop_duplicates(subset=['email'], keep='first')
                duplicate_rows = original_rows - len(df)
            else:
                duplicate_rows = 0
            
            return {
                'success': True,
                'data': df,
                'duplicate_columns': duplicate_info,
                'duplicate_columns_resolution': duplicate_columns_resolution,
                'original_columns': original_columns,
                'resolved_columns': resolved_columns,
                'final_columns': df.columns.tolist(),
                'total_rows': len(df),
                'duplicate_rows_removed': duplicate_rows,
                'message': f'Successfully processed {len(df)} records'
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': f'Error processing Excel file: {str(e)}',
                'data': None
            }
    
    def generate_import_report(self, result: Dict[str, Any]) -> str:
        """Generate a detailed import report"""
        if not result['success']:
            return f"Import Failed: {result['error']}"
        
        report = []
        report.append("Excel Import Processing Report")
        report.append("=" * 40)
        report.append(f"Total Records: {result['total_rows']}")
        report.append(f"Duplicate Rows Removed: {result['duplicate_rows_removed']}")
        
        if result['duplicate_columns']:
            report.append("\nDuplicate Columns Found:")
            for normalized_name, columns in result['duplicate_columns'].items():
                resolution = (result.get('duplicate_columns_resolution') or {}).get(normalized_name, {})
                dropped_indices = resolution.get('dropped_indices', []) or []
                renamed_by_index = resolution.get('renamed_by_index', {}) or {}

                report.append(f"  - {normalized_name}: {', '.join(columns)}")
                if dropped_indices:
                    report.append(f"    → Dropped identical columns (by index): {', '.join(map(str, dropped_indices))}")
                if renamed_by_index:
                    # Show which kept duplicates were renamed (so column labels stay unique)
                    renamed_summary = ', '.join([f"{k}->{v}" for k, v in renamed_by_index.items()])
                    report.append(f"    → Renamed non-identical duplicates: {renamed_summary}")
        
        report.append(f"\nOriginal Columns: {len(result['original_columns'])}")
        report.append(f"Final Columns: {len(result['final_columns'])}")
        
        report.append("\nFinal Column Mapping:")
        for col in result['final_columns']:
            report.append(f"  - {col}")
        
        return "\n".join(report)
    
    def export_processed_data(self, df: pd.DataFrame, output_path: str):
        """Export processed data to CSV"""
        df.to_csv(output_path, index=False)
        return f"Processed data exported to {output_path}"


# Command-line interface
if __name__ == "__main__":
    import argparse
    import os
    
    parser = argparse.ArgumentParser(description='Process Excel file for alumni import')
    parser.add_argument('file_path', help='Path to Excel file')
    parser.add_argument('--output', help='Output CSV file path', default='processed_alumni.csv')
    parser.add_argument('--strategy', choices=['first', 'last', 'merge'], 
                       default='first', help='Strategy for handling duplicate columns')
    parser.add_argument('--report', help='Report file path', default='import_report.txt')
    
    args = parser.parse_args()
    
    if not os.path.exists(args.file_path):
        print(f"Error: File '{args.file_path}' not found")
        exit(1)
    
    processor = ExcelImportProcessor()
    
    print(f"Processing Excel file: {args.file_path}")
    print(f"Duplicate column strategy: {args.strategy}")
    print("-" * 50)
    
    # Process the file
    result = processor.process_excel_file(args.file_path, args.strategy)
    
    # Generate report
    report = processor.generate_import_report(result)
    print(report)
    
    # Save report
    with open(args.report, 'w') as f:
        f.write(report)
    
    if result['success']:
        # Export processed data
        export_message = processor.export_processed_data(result['data'], args.output)
        print(f"\n{export_message}")
        print(f"Report saved to: {args.report}")
    else:
        print(f"\nProcessing failed: {result['error']}")
        exit(1)
