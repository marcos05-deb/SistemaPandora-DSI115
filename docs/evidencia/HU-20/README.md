# Synthetic report export evidence

Generated on 2026-10-29 by the real application renderers in isolated WSL Docker QA. No patient data is used. The fixture has monthly consultation counts **6** and **7**, with general total **13**.

| File | Check | SHA-256 |
| --- | --- | --- |
| `synthetic-monthly.pdf` | Actual PDF text extracted by Poppler; rendered PNG visually inspected for headings, totals, rows and clipping. | `08f79adab6f3e38b33d2df7a53064c323cf4884cdda821b3560cc5664ae9c975` |
| `synthetic-monthly.xlsx` | Actual XLSX parsed with OpenSpout; total and both monthly rows matched. | `bb560ece5f0dca09bdefb395e96320ec84e0b7e089716105bcdc84e13af589ad` |
| `synthetic-monthly.png` | Rendering of the PDF's single page, retained as visual evidence. | Verify against the PDF if regenerated. |

Generator: `tests/Acceptance/ReporteExportPdfTest.php`, explicitly enabled with `PANDORA_WRITE_QA_EVIDENCE=1`. Result: **1 passed, 6 assertions**. The report DTO is synthetic; the HU-20 feature test separately compares screen data and endpoint XLSX rows. This sample does not replace limited-user endpoint tests or human acceptance.

See `../SPRINT_3_REPAIR_VERIFICATION.md` for exact QA conditions and remaining global failures. The generation date in the header is runtime metadata, so regenerating can change file bytes and hashes.
