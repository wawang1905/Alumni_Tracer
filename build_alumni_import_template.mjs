import fs from "node:fs/promises";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const outputDir = "public/templates";
const outputPath = `${outputDir}/alumni_import_template.xlsx`;

const workbook = Workbook.create();

const instructions = workbook.worksheets.add("Instructions");
instructions.showGridLines = false;
instructions.getRange("A1:F1").merge();
instructions.getRange("A1").values = [["Alumni Import Template"]];
instructions.getRange("A1").format = {
  fill: "#1E4A76",
  font: { bold: true, color: "#FFFFFF", size: 16 },
  horizontalAlignment: "center",
  verticalAlignment: "center"
};
instructions.getRange("A3:B9").values = [
  ["Step", "What to do"],
  ["1", "Open a batch first in the staff alumni page."],
  ["2", "Download this template and fill one row per alumni."],
  ["3", "Keep the header names exactly as provided."],
  ["4", "Graduation Year in every row must match the selected batch year."],
  ["5", "Do not leave any required field blank."],
  ["6", "Upload the completed .xlsx file from the selected batch."]
];
instructions.getRange("A3:B9").format.wrapText = true;
instructions.getRange("A3:B3").format = {
  fill: "#EAF2FB",
  font: { bold: true, color: "#16354D" }
};
instructions.getRange("A1:B9").format.autofitColumns();
instructions.getRange("A1:B9").format.autofitRows();

const sheet = workbook.worksheets.add("Alumni Import");
sheet.getRange("A1:F4").values = [
  ["FirstName", "LastName", "Email", "Graduation Year", "College", "Department"],
  ["Juan", "Dela Cruz", "juan.delacruz@example.com", "2024", "SEAIT", "BS Agriculture"],
  ["Maria", "Santos", "maria.santos@example.com", "2024", "SEAIT", "BS Fisheries"],
  ["", "", "", "", "", ""]
];
sheet.getRange("A1:F1").format = {
  fill: "#EE8B1D",
  font: { bold: true, color: "#FFFFFF" },
  horizontalAlignment: "center",
  verticalAlignment: "center"
};
sheet.getRange("A2:F3").format.wrapText = true;
sheet.getRange("A1:F20").format.autofitColumns();
sheet.getRange("A1:F20").format.autofitRows();
sheet.freezePanes.freezeRows(1);
sheet.tables.add("A1:F20", true, "AlumniImportTable");

await workbook.inspect({
  kind: "table",
  range: "Alumni Import!A1:F4",
  include: "values",
  tableMaxRows: 4,
  tableMaxCols: 6
});

await fs.mkdir(outputDir, { recursive: true });
const output = await SpreadsheetFile.exportXlsx(workbook);
await output.save(outputPath);
