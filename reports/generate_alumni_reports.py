import csv
import subprocess
from pathlib import Path


PROJECT_ROOT = Path(__file__).resolve().parents[1]
REPORTS_DIR = Path(__file__).resolve().parent
MYSQL_EXE = Path(r"C:\xampp\mysql\bin\mysql.exe")
DATABASE_NAME = "alumni_tracer"
MYSQL_USER = "root"


def run_query(sql: str) -> list[list[str]]:
    command = [
        str(MYSQL_EXE),
        "-u",
        MYSQL_USER,
        "-D",
        DATABASE_NAME,
        "-B",
        "-N",
        "-e",
        sql,
    ]
    completed = subprocess.run(
        command,
        cwd=PROJECT_ROOT,
        capture_output=True,
        text=True,
        check=True,
    )
    lines = [line for line in completed.stdout.splitlines() if line.strip() != ""]
    return [line.split("\t") for line in lines]


def write_csv(filename: str, header: list[str], rows: list[list[str]]) -> Path:
    path = REPORTS_DIR / filename
    with path.open("w", newline="", encoding="utf-8") as file:
        writer = csv.writer(file)
        writer.writerow(header)
        writer.writerows(rows)
    return path


def clean_label(value: str) -> str:
    normalized = (value or "").strip()
    return normalized if normalized else "Unspecified"


def main() -> None:
    REPORTS_DIR.mkdir(parents=True, exist_ok=True)

    total_rows = run_query(
        """
        SELECT COUNT(*)
        FROM alumni
        """
    )
    total_alumni = int(total_rows[0][0]) if total_rows else 0

    by_college_raw = run_query(
        """
        SELECT COALESCE(NULLIF(TRIM(College), ''), 'Unspecified') AS college,
               COUNT(*) AS total_alumni
        FROM alumni
        GROUP BY COALESCE(NULLIF(TRIM(College), ''), 'Unspecified')
        ORDER BY total_alumni DESC, college ASC
        """
    )

    by_course_raw = run_query(
        """
        SELECT COALESCE(NULLIF(TRIM(Course), ''), 'Unspecified') AS course,
               COUNT(*) AS total_alumni
        FROM alumni
        GROUP BY COALESCE(NULLIF(TRIM(Course), ''), 'Unspecified')
        ORDER BY total_alumni DESC, course ASC
        """
    )

    by_college_course_raw = run_query(
        """
        SELECT COALESCE(NULLIF(TRIM(College), ''), 'Unspecified') AS college,
               COALESCE(NULLIF(TRIM(Course), ''), 'Unspecified') AS course,
               COUNT(*) AS total_alumni
        FROM alumni
        GROUP BY
            COALESCE(NULLIF(TRIM(College), ''), 'Unspecified'),
            COALESCE(NULLIF(TRIM(Course), ''), 'Unspecified')
        ORDER BY college ASC, total_alumni DESC, course ASC
        """
    )

    by_college = [[clean_label(college), count] for college, count in by_college_raw]
    by_course = [[clean_label(course), count] for course, count in by_course_raw]
    by_college_course = [
        [clean_label(college), clean_label(course), count]
        for college, course, count in by_college_course_raw
    ]

    summary_path = REPORTS_DIR / "alumni_summary_report.txt"
    with summary_path.open("w", encoding="utf-8") as file:
        file.write("ALUMNI REPORT\n")
        file.write("=============\n\n")
        file.write(f"Total alumni: {total_alumni}\n\n")

        file.write("Alumni per college\n")
        file.write("------------------\n")
        for college, count in by_college:
            file.write(f"- {college}: {count}\n")

        file.write("\nAlumni per course\n")
        file.write("-----------------\n")
        for course, count in by_course:
            file.write(f"- {course}: {count}\n")

    college_csv = write_csv("alumni_per_college.csv", ["College", "Total Alumni"], by_college)
    course_csv = write_csv("alumni_per_course.csv", ["Course", "Total Alumni"], by_course)
    college_course_csv = write_csv(
        "alumni_per_college_course.csv",
        ["College", "Course", "Total Alumni"],
        by_college_course,
    )

    print("Generated reports:")
    print(f"- {summary_path}")
    print(f"- {college_csv}")
    print(f"- {course_csv}")
    print(f"- {college_course_csv}")


if __name__ == "__main__":
    main()
